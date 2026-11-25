<?php

namespace App\Console\Commands;

use App\Support\Installation\Installation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Re-pins every company to the installation sector.
 *
 * The installer already does this; the command exists so an operator can repair
 * a database that was edited outside the installer.
 */
class SectorRepair extends Command
{
    protected $signature = 'openhr:sector:repair
        {--apply : Write the changes; without this option the command only reports}';

    protected $description = 'Mark every company as the installation sector';

    public function handle(): int
    {
        $sector = Installation::sectorEnum();

        if ($sector === null) {
            $this->warn('No installation sector is configured. Run `php artisan openhr:install` first.');

            return self::SUCCESS;
        }

        if (! Schema::hasTable('companies')) {
            $this->error('The companies table does not exist.');

            return self::FAILURE;
        }

        $query = DB::table('companies')->where(function ($query) use ($sector): void {
            $query->whereNull('type')
                ->orWhereRaw('lower(type) <> ?', [strtolower($sector->value)]);
        });

        $count = $query->count();

        if (! $this->option('apply')) {
            $this->info("Would mark {$count} companies as {$sector->value}. Re-run with --apply to write the change.");

            return self::SUCCESS;
        }

        $query->update(['type' => $sector->value, 'updated_at' => now()]);
        Installation::forget();

        $this->info("Marked {$count} companies as {$sector->value}.");

        return self::SUCCESS;
    }
}
