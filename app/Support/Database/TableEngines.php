<?php

namespace App\Support\Database;

use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Storage engines for the tables on the current connection.
 *
 * Databases imported from the retired SQL dumps still contain MyISAM tables.
 * MySQL rejects writes to non-transactional tables inside a transaction when
 * GTID consistency is enforced (Laravel Cloud), and those tables ignore
 * rollbacks, so every table must use InnoDB.
 */
class TableEngines
{
    public const ENGINE = 'InnoDB';

    /**
     * Whether the connection has selectable storage engines.
     */
    public function supportsInnoDb(): bool
    {
        return in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
    }

    /**
     * Tables that are not InnoDB, keyed by table name with their current engine.
     *
     * @return array<string, string>
     */
    public function nonTransactionalTables(): array
    {
        if (! $this->supportsInnoDb()) {
            return [];
        }

        $rows = DB::select(
            'select TABLE_NAME as name, ENGINE as engine
             from information_schema.TABLES
             where TABLE_SCHEMA = database()
               and TABLE_TYPE = ?
               and ENGINE is not null
               and ENGINE <> ?
             order by TABLE_NAME',
            ['BASE TABLE', self::ENGINE],
        );

        return collect($rows)
            ->mapWithKeys(fn (stdClass $row): array => [(string) $row->name => (string) $row->engine])
            ->all();
    }

    /**
     * Convert every non-InnoDB table, optionally limited to the given tables.
     *
     * @param  array<int, string>  $only
     * @return array<string, string> The converted tables and their previous engine.
     */
    public function convertAll(array $only = []): array
    {
        $pending = $this->nonTransactionalTables();

        if ($only !== []) {
            $pending = array_intersect_key($pending, array_flip($only));
        }

        foreach (array_keys($pending) as $table) {
            $this->convert($table);
        }

        return $pending;
    }

    /**
     * Rebuild a single table with the transactional engine.
     */
    public function convert(string $table): void
    {
        DB::statement(sprintf(
            'alter table %s engine = %s',
            DB::connection()->getQueryGrammar()->wrapTable($table),
            self::ENGINE,
        ));
    }
}
