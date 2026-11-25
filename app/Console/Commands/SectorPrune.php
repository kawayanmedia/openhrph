<?php

namespace App\Console\Commands;

use App\Enums\Sector;
use App\Support\Installation\Installation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Deactivates catalogue rows that belong to the other sector.
 *
 * Never deletes payroll history or employee records; reference records such as
 * plantilla items are reported by `openhr:sector:audit` for manual review.
 */
class SectorPrune extends Command
{
    protected $signature = 'openhr:sector:prune
        {--apply : Write the changes; without this option the command only reports}
        {--json : Write machine-readable JSON}';

    protected $description = 'Deactivate government or private catalogue rows that do not match the installation sector';

    public function handle(): int
    {
        $sector = Installation::sectorEnum();

        if ($sector === null) {
            $this->warn('No installation sector is configured. Run `php artisan openhr:install` first.');

            return self::SUCCESS;
        }

        $foreign = $sector === Sector::Government ? Sector::Private : Sector::Government;
        $apply = (bool) $this->option('apply');
        $counts = [];

        foreach (['compensations', 'leave_types'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'sector')) {
                continue;
            }

            $query = DB::table($table)->whereRaw('lower(sector) = ?', [strtolower($foreign->value)]);

            $inactive = Schema::hasColumn($table, 'is_active')
                ? ['is_active' => false]
                : ['status' => 'Inactive'];

            $counts[$table] = $apply
                ? $query->update($inactive + ['updated_at' => now()])
                : $query->count();
        }

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'sector' => $sector->value,
                'applied' => $apply,
                'deactivated' => $counts,
            ], JSON_PRETTY_PRINT));
        } else {
            $this->info(($apply ? 'Deactivated' : 'Would deactivate')." {$foreign->label()} catalogue rows for a {$sector->label()} installation.");

            foreach ($counts as $table => $count) {
                $this->line("  {$table}: {$count}");
            }

            if (! $apply) {
                $this->line('Re-run with --apply to write the changes.');
            }
        }

        return self::SUCCESS;
    }
}
