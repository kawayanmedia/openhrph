<?php

namespace App\Console\Commands;

use App\Support\Database\TableCharsets;
use App\Support\Database\TableEngines;
use Illuminate\Console\Command;

/**
 * Normalises the storage engines and character sets of legacy tables.
 *
 * Writes to MyISAM tables are rejected inside a transaction when MySQL enforces
 * GTID consistency (Laravel Cloud), and MyISAM ignores rollbacks. latin1 and
 * utf8mb3 tables cannot store every UTF-8 character and mix collations with the
 * utf8mb4 tables Laravel creates. Run the check after importing a legacy dump.
 */
class EnsureSchema extends Command
{
    protected $signature = 'db:ensure-schema
        {--check : Report non-conforming tables without converting them}';

    protected $description = 'Convert legacy tables to InnoDB and utf8mb4 so transactions and UTF-8 stay safe';

    public function handle(TableEngines $engines, TableCharsets $charsets): int
    {
        $engineResult = $this->ensureEngines($engines);

        $this->newLine();

        $charsetResult = $this->ensureCharsets($charsets);

        if ($engineResult === self::FAILURE || $charsetResult === self::FAILURE) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function ensureEngines(TableEngines $engines): int
    {
        if (! $engines->supportsInnoDb()) {
            $this->line('Storage engines: skipped, this connection has no selectable engines.');

            return self::SUCCESS;
        }

        $tables = $engines->nonTransactionalTables();

        if ($tables === []) {
            $this->info('Storage engines: every table uses InnoDB.');

            return self::SUCCESS;
        }

        $this->table(['Table', 'Engine'], $this->rows($tables));

        if ($this->option('check')) {
            $this->error(count($tables).' table(s) are not InnoDB. Writes to them fail inside a transaction when GTID consistency is enforced.');

            return self::FAILURE;
        }

        foreach (array_keys($tables) as $table) {
            $engines->convert($table);
        }

        $this->info(count($tables).' table(s) converted to InnoDB.');

        return self::SUCCESS;
    }

    private function ensureCharsets(TableCharsets $charsets): int
    {
        if (! $charsets->supportsUtf8mb4()) {
            $this->line('Character sets: skipped, this connection has no per-table character sets.');

            return self::SUCCESS;
        }

        $tables = $charsets->nonConformingTables();

        if ($tables === []) {
            $this->info('Character sets: every table uses '.$charsets->collation().'.');

            return self::SUCCESS;
        }

        $this->table(['Table', 'Collation'], $this->rows($tables));

        if ($this->option('check')) {
            $this->error(count($tables).' table(s) do not use '.$charsets->collation().'.');

            return self::FAILURE;
        }

        foreach (array_keys($tables) as $table) {
            $charsets->convert($table);
        }

        $this->info(count($tables).' table(s) converted to '.$charsets->charset().'.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, string>  $tables
     * @return array<int, array<int, string>>
     */
    private function rows(array $tables): array
    {
        return collect($tables)
            ->map(fn (string $value, string $table): array => [$table, $value])
            ->values()
            ->all();
    }
}
