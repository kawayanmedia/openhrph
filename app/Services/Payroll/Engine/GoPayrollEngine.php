<?php

namespace App\Services\Payroll\Engine;

use App\Models\Payroll;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Runs the Go payroll engine as a subprocess.
 *
 * The engine connects to MySQL itself, so the only thing crossing the process
 * boundary is the payroll id and the JSON summary.
 */
class GoPayrollEngine implements PayrollEngine
{
    public function __construct(
        protected ?string $binary = null,
        protected ?string $dsn = null,
        protected int $timeout = 600,
    ) {
        $this->binary = $binary ?: config('payroll.engine.binary');
        $this->dsn = $dsn ?: $this->resolveDsn();
    }

    public function preview(Payroll $payroll): EngineResult
    {
        return $this->run($payroll, dryRun: true, force: false);
    }

    public function process(Payroll $payroll, bool $force = false): EngineResult
    {
        return $this->run($payroll, dryRun: false, force: $force);
    }

    public function version(): ?string
    {
        if (! $this->isAvailable()) {
            return null;
        }

        try {
            $process = new Process([$this->binary, '--version']);
            $process->setTimeout(15);
            $process->run();

            if (! $process->isSuccessful()) {
                return null;
            }

            return trim($process->getOutput());
        } catch (Throwable) {
            return null;
        }
    }

    public function isAvailable(): bool
    {
        return is_string($this->binary) && $this->binary !== '' && is_executable($this->binary);
    }

    protected function run(Payroll $payroll, bool $dryRun, bool $force): EngineResult
    {
        if (! $this->isAvailable()) {
            throw new PayrollEngineException(
                'The payroll engine binary was not found at '.$this->binary.'. Run `make -C payroll-engine build`.'
            );
        }

        $command = [$this->binary, '--payroll-id='.$payroll->getKey(), '--json'];

        if ($this->dsn !== null && $this->dsn !== '') {
            $command[] = '--dsn='.$this->dsn;
        }

        if ($dryRun) {
            $command[] = '--dry-run';
        }

        if ($force) {
            $command[] = '--force';
        }

        $process = new Process($command, base_path(), $this->environment());
        $process->setTimeout($this->timeout);
        $process->run();

        if (! $process->isSuccessful()) {
            $error = trim($process->getErrorOutput()) ?: trim($process->getOutput());

            Log::error('Payroll engine failed', [
                'payroll_id' => $payroll->getKey(),
                'exit_code' => $process->getExitCode(),
                'error' => $error,
            ]);

            throw new PayrollEngineException(
                $error !== '' ? $error : 'The payroll engine exited with code '.$process->getExitCode()
            );
        }

        $payload = json_decode($process->getOutput(), true);

        if (! is_array($payload)) {
            throw new PayrollEngineException('The payroll engine returned an invalid response: '.$process->getOutput());
        }

        return EngineResult::fromArray($payload);
    }

    /**
     * @return array<string, string>
     */
    protected function environment(): array
    {
        return [
            'PATH' => (string) getenv('PATH'),
            'HOME' => (string) getenv('HOME'),
            'TZ' => (string) config('app.timezone', 'UTC'),
        ];
    }

    protected function resolveDsn(): ?string
    {
        if (filled(config('payroll.engine.dsn'))) {
            return (string) config('payroll.engine.dsn');
        }

        $connection = config('database.connections.'.config('database.default'));

        if (($connection['driver'] ?? null) !== 'mysql') {
            return null;
        }

        return sprintf(
            '%s:%s@tcp(%s:%s)/%s?parseTime=true&charset=utf8mb4&collation=utf8mb4_unicode_ci&loc=UTC',
            $connection['username'] ?? 'root',
            $connection['password'] ?? '',
            $connection['host'] ?? '127.0.0.1',
            $connection['port'] ?? '3306',
            $connection['database'] ?? '',
        );
    }
}
