<?php

namespace App\Services\Desktop;

use App\Models\Company;
use App\Models\User;
use App\Sectors\Catalogues\SectorCatalogue;
use App\Services\Demo\DemoWorkspaceSeeder;
use App\Support\Installation\Installation;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;
use Throwable;

/**
 * Turns a freshly migrated desktop database into a usable workspace.
 *
 * A packaged desktop app has no terminal, so the same steps the deployment
 * scripts run (`openhr:install`, reference data, `admin:create`) are executed
 * from the setup wizard instead. The wizard finishes by filling the company
 * with the same sample records the hosted demo account starts from, so the
 * tables are populated on first launch.
 */
final class DesktopInstaller
{
    public function __construct(private readonly DemoWorkspaceSeeder $workspace) {}

    public function isInstalled(): bool
    {
        try {
            return User::query()->exists();
        } catch (Throwable) {
            // The database may not have been migrated yet; the wizard will
            // surface the real error when it tries to install.
            return false;
        }
    }

    /**
     * @param  array{sector: string, country: string, company_name: string, name: string, email: string, password: string}  $attributes
     * @return array{user: User, output: array<int, string>, warnings: array<int, string>}
     */
    public function install(array $attributes): array
    {
        if ($this->isInstalled()) {
            throw new RuntimeException('This installation already has an account. Sign in instead.');
        }

        $output = [];
        $warnings = [];

        $install = [
            '--sector' => $attributes['sector'],
            '--country' => $attributes['country'],
            '--no-interaction' => true,
        ];

        if (Installation::installed()) {
            $install['--force'] = true;
        }

        $this->run($output, 'openhr:install', $install);

        $this->run($output, 'db:seed', [
            '--class' => ReferenceDataSeeder::class,
            '--force' => true,
            '--no-interaction' => true,
        ]);

        $this->run($output, 'admin:create', [
            '--name' => $attributes['name'],
            '--email' => $attributes['email'],
            '--password' => $attributes['password'],
            '--company-name' => $attributes['company_name'],
            '--no-interaction' => true,
        ]);

        $user = User::query()->where('email', $attributes['email'])->first();

        if (! $user instanceof User || $user->company_id === null) {
            throw new RuntimeException('The administrator account was created but no company was attached to it.');
        }

        // The company now exists, so the catalogue rows that are scoped to a
        // company (employment types, deductions, loans) can be seeded.
        SectorCatalogue::seedForCompany((int) $user->company_id);

        $company = Company::query()->find($user->company_id);

        if ($company instanceof Company) {
            $this->workspace->seed($company);
        }

        if (strcasecmp($attributes['sector'], Installation::SECTOR_GOVERNMENT) !== 0) {
            $this->runOptional($warnings, 'payroll:wage-orders:import');
        }

        $this->runOptional($warnings, 'payroll:holidays:import');

        return [
            'user' => $user->refresh(),
            'output' => $output,
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  array<int, string>  $output
     * @param  array<string, mixed>  $parameters
     */
    private function run(array &$output, string $command, array $parameters = []): void
    {
        $exitCode = Artisan::call($command, $parameters);
        $result = trim(Artisan::output());

        if ($exitCode !== 0) {
            throw new RuntimeException($result !== '' ? $result : "The [{$command}] step failed.");
        }

        if ($result !== '') {
            $output[] = $result;
        }
    }

    /**
     * Reference imports must not block onboarding; report them as warnings.
     *
     * @param  array<int, string>  $warnings
     * @param  array<string, mixed>  $parameters
     */
    private function runOptional(array &$warnings, string $command, array $parameters = []): void
    {
        try {
            $exitCode = Artisan::call($command, $parameters);
            $result = trim(Artisan::output());

            if ($exitCode !== 0) {
                $warnings[] = $result !== '' ? $result : "The [{$command}] import did not complete.";
            }
        } catch (Throwable $exception) {
            report($exception);

            $warnings[] = "The [{$command}] import did not complete: {$exception->getMessage()}";
        }
    }
}
