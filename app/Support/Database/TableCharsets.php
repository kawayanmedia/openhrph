<?php

namespace App\Support\Database;

use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Character sets and collations for the tables on the current connection.
 *
 * Databases imported from the retired SQL dumps still use latin1 or utf8mb3, so
 * names outside the Basic Multilingual Plane cannot be stored and string
 * comparisons mix collations with the utf8mb4 tables Laravel creates.
 */
class TableCharsets
{
    /**
     * Whether the connection supports per-table character sets.
     */
    public function supportsUtf8mb4(): bool
    {
        return in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
    }

    /**
     * The character set Laravel creates tables with.
     */
    public function charset(): string
    {
        return (string) (DB::connection()->getConfig('charset') ?: 'utf8mb4');
    }

    /**
     * The collation Laravel creates tables with.
     */
    public function collation(): string
    {
        return (string) (DB::connection()->getConfig('collation') ?: 'utf8mb4_unicode_ci');
    }

    /**
     * Tables whose default collation or any character column differs.
     *
     * @return array<string, string> Keyed by table name, with the collations found.
     */
    public function nonConformingTables(): array
    {
        if (! $this->supportsUtf8mb4()) {
            return [];
        }

        $collation = $this->collation();

        $tables = collect(DB::select(
            'select TABLE_NAME as name, TABLE_COLLATION as collation
             from information_schema.TABLES
             where TABLE_SCHEMA = database()
               and TABLE_TYPE = ?
               and (TABLE_COLLATION is null or TABLE_COLLATION <> ?)
             order by TABLE_NAME',
            ['BASE TABLE', $collation],
        ))->mapWithKeys(fn (stdClass $row): array => [(string) $row->name => (string) $row->collation]);

        // A table can keep its default collation while individual character
        // columns were created with a different one.
        $columns = DB::select(
            'select TABLE_NAME as name
             from information_schema.COLUMNS
             where TABLE_SCHEMA = database()
               and COLLATION_NAME is not null
               and COLLATION_NAME <> ?
             group by TABLE_NAME',
            [$collation],
        );

        foreach ($columns as $column) {
            $tables[$column->name] ??= 'mixed columns';
        }

        return $tables->sortKeys()->all();
    }

    /**
     * Convert every non-conforming table, optionally limited to the given tables.
     *
     * @param  array<int, string>  $only
     * @return array<string, string> The converted tables and the collations they had.
     */
    public function convertAll(array $only = []): array
    {
        $pending = $this->nonConformingTables();

        if ($only !== []) {
            $pending = array_intersect_key($pending, array_flip($only));
        }

        foreach (array_keys($pending) as $table) {
            $this->convert($table);
        }

        return $pending;
    }

    /**
     * Rebuild a table and its character columns with the configured charset.
     */
    public function convert(string $table): void
    {
        DB::statement(sprintf(
            'alter table %s convert to character set %s collate %s',
            DB::connection()->getQueryGrammar()->wrapTable($table),
            $this->charset(),
            $this->collation(),
        ));
    }
}
