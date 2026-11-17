<?php

namespace App\Console\Commands;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TenancyAudit extends Command
{
    protected $signature = 'tenancy:audit';

    protected $description = 'Report tenant tables with missing company ids and models without tenant scoping';

    public function handle(): int
    {
        $tables = collect(DB::select(
            "SELECT TABLE_NAME AS name FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'company_id' ORDER BY TABLE_NAME"
        ))->pluck('name');

        $rows = [];

        foreach ($tables->diff(config('tenancy.global_tables', [])) as $table) {
            $missing = DB::table($table)->whereNull('company_id')->count();

            if ($missing > 0) {
                $rows[] = [$table, $missing];
            }
        }

        if ($rows === []) {
            $this->info('Every row in tenant tables has a company_id.');
        } else {
            $this->table(['Table', 'Rows without company_id'], $rows);
        }

        $unscoped = collect(glob(app_path('Models/*.php')))
            ->map(fn (string $path) => 'App\\Models\\'.basename($path, '.php'))
            ->filter(fn (string $class) => class_exists($class) && is_subclass_of($class, Model::class))
            ->reject(fn (string $class) => in_array($class, config('tenancy.unscoped_models', []), true))
            ->reject(fn (string $class) => in_array(BelongsToTenant::class, class_uses_recursive($class), true))
            ->filter(function (string $class): bool {
                try {
                    return Schema::hasColumn((new $class)->getTable(), 'company_id');
                } catch (\Throwable) {
                    return false;
                }
            })
            ->values();

        if ($unscoped->isNotEmpty()) {
            $this->warn('Models with a company_id column but no BelongsToTenant trait:');
            $this->line($unscoped->implode(PHP_EOL));
        }

        return self::SUCCESS;
    }
}
