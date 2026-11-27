<?php

namespace KawayanMedia\Attendance\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use KawayanMedia\Attendance\Devices\DeviceDescriptor;
use KawayanMedia\Attendance\Devices\DriverManager;
use KawayanMedia\Attendance\Devices\PullWindow;
use KawayanMedia\Attendance\Devices\PunchRecord;
use Throwable;

/**
 * Runs inside the customer's network and relays LAN devices to the cloud app.
 *
 * The device list (host, credentials, cursor) comes from the application over
 * an authenticated API, so the box in the office never needs a database or a
 * copy of the device secrets. Punches are posted to the existing ingestion API,
 * which applies the usual de-duplication and lock rules.
 */
final class AgentCommand extends Command
{
    protected $signature = 'attendance:agent
        {--server= : Base URL of the OpenHR PH application}
        {--key= : Company API key (X-Company-Key)}
        {--device=* : Only relay these device ids}
        {--interval= : Seconds between passes when not using --once}
        {--state= : Path to the cursor state file}
        {--once : Run a single pass and exit}';

    protected $description = 'Relay time logs from LAN biometric devices to the application';

    public function handle(DriverManager $drivers): int
    {
        $server = rtrim((string) ($this->option('server') ?: config('attendance-devices.agent.server')), '/');
        $key = (string) ($this->option('key') ?: config('attendance-devices.agent.company_key'));

        if ($server === '' || $key === '') {
            $this->error('Set --server and --key (or ATTENDANCE_AGENT_SERVER and ATTENDANCE_AGENT_COMPANY_KEY).');

            return Command::FAILURE;
        }

        do {
            $this->pass($drivers, $server, $key);

            if ($this->option('once')) {
                break;
            }

            sleep(max(5, (int) ($this->option('interval') ?: config('attendance-devices.agent.interval', 60))));
        } while (true);

        return Command::SUCCESS;
    }

    private function pass(DriverManager $drivers, string $server, string $key): void
    {
        $devices = $this->fetchDevices($server, $key);

        if ($devices === null) {
            return;
        }

        $state = $this->readState();
        $only = array_filter(array_map('intval', (array) $this->option('device')));
        $this->info(count($devices).' device(s) assigned to this agent.');

        foreach ($devices as $row) {
            if (! is_array($row)) {
                continue;
            }

            $device = DeviceDescriptor::fromArray($row);

            if ($only !== [] && ! in_array($device->id, $only, true)) {
                continue;
            }

            if ($device->host === null || $device->host === '') {
                $this->warn("{$device->name}: no host configured, skipping.");

                continue;
            }

            $cursor = $state[(string) $device->id] ?? $device->cursor;
            $device = $device->withCursor($cursor);

            $this->relay($drivers, $server, $key, $device, $state);
        }

        $this->writeState($state);
    }

    /**
     * @param  array<string, string>  $state
     */
    private function relay(DriverManager $drivers, string $server, string $key, DeviceDescriptor $device, array &$state): void
    {
        $driver = $drivers->driverFor($device->protocol);

        if (! $driver->canPull()) {
            $this->line("{$device->name}: {$device->protocol} pushes to the server directly.");

            return;
        }

        try {
            $result = $driver->pull($device, PullWindow::forDevice($device, (array) config('attendance-devices.pull', [])));
        } catch (Throwable $exception) {
            $this->error("{$device->name}: {$exception->getMessage()}");

            return;
        }

        if ($result->records === []) {
            $this->line("{$device->name}: no new punches.");

            return;
        }

        $batchSize = max(1, (int) config('attendance-devices.agent.batch_size', 500));
        $pushed = 0;
        $accepted = 0;

        foreach (array_chunk($result->records, $batchSize) as $batch) {
            $logs = array_map(fn ($record): array => $record->toLogArray(), $batch);

            $response = Http::timeout((int) config('attendance-devices.agent.timeout', 30))
                ->withHeaders(['X-Company-Key' => $key])
                ->acceptJson()
                ->post($server.'/api/attendance/logs', [
                    'device' => $device->name,
                    'logs' => $logs,
                ]);

            if (! $response->successful()) {
                $this->error("{$device->name}: the server answered HTTP {$response->status()}; the cursor was not advanced.");

                return;
            }

            $pushed += count($batch);
            $accepted += (int) ($response->json('data.accepted') ?? 0);

            $state[(string) $device->id] = $this->newestCursor($batch) ?? $state[(string) $device->id] ?? null;
        }

        $state[(string) $device->id] = $result->cursor ?? $state[(string) $device->id] ?? null;

        $this->info("{$device->name}: pushed {$pushed} punch(es), {$accepted} accepted.");

        foreach ($result->warnings as $warning) {
            $this->warn($warning);
        }
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    private function fetchDevices(string $server, string $key): ?array
    {
        try {
            $response = Http::timeout((int) config('attendance-devices.agent.timeout', 30))
                ->withHeaders(['X-Company-Key' => $key])
                ->acceptJson()
                ->get($server.'/api/attendance/devices');
        } catch (Throwable $exception) {
            $this->error('Could not reach the application: '.$exception->getMessage());

            return null;
        }

        if (! $response->successful()) {
            $this->error("The application answered HTTP {$response->status()} when listing devices.");

            return null;
        }

        $devices = $response->json('data.devices', []);

        return is_array($devices) ? array_values($devices) : [];
    }

    /**
     * @param  array<int, PunchRecord>  $batch
     */
    private function newestCursor(array $batch): ?string
    {
        $latest = null;

        foreach ($batch as $record) {
            $moment = $record->moment();

            if ($latest === null || $moment->greaterThan($latest)) {
                $latest = $moment;
            }
        }

        return $latest?->utc()->toIso8601String();
    }

    /**
     * @return array<string, string|null>
     */
    private function readState(): array
    {
        $path = $this->statePath();

        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, string|null>  $state
     */
    private function writeState(array $state): void
    {
        $path = $this->statePath();
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        file_put_contents($path, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function statePath(): string
    {
        $configured = $this->option('state') ?: config('attendance-devices.agent.state_path');

        return is_string($configured) && $configured !== ''
            ? $configured
            : storage_path('app/attendance-agent-state.json');
    }
}
