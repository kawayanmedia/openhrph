<?php

namespace App\Jurisdictions\Philippines\Government\Legacy;

use App\Exceptions\Tenancy\TenantNotResolvedException;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use KawayanMedia\Persistence\Sql\Condition;

/**
 * Shared database plumbing for the legacy Kawayan Media stores.
 *
 * The packages were written against `kawayanmedia/persistence`, whose PDO
 * adapter opens its own connection and knows nothing about tenancy. These
 * stores use the application connection instead, so every read and write stays
 * inside the resolved tenant and inside the test transaction.
 */
abstract class LegacyStore
{
    /**
     * Column listings keyed by table, cached for the life of the process.
     *
     * @var array<string, list<string>>
     */
    private static array $columns = [];

    /**
     * The tenant every legacy row belongs to.
     *
     * @throws TenantNotResolvedException
     */
    protected function tenantId(): int
    {
        $tenantId = app(TenantContext::class)->id();

        if ($tenantId === null) {
            throw TenantNotResolvedException::forModel(static::class);
        }

        return $tenantId;
    }

    /**
     * Rows of one table, scoped to the tenant.
     *
     * @param  array<mixed>  $conditions
     * @param  array{orderBy?: array<string, string>, limit?: int|null, offset?: int|null}  $options
     * @return list<array<string, mixed>>
     */
    protected function selectRows(string $table, array $conditions = [], array $options = []): array
    {
        $query = $this->newQuery($table);
        $this->applyConditions($query, $conditions);
        $this->applyOptions($query, $options);

        return array_map(
            static fn (object $row): array => (array) $row,
            $query->get()->all(),
        );
    }

    /**
     * @param  array<mixed>  $conditions
     * @return array<string, mixed>|null
     */
    protected function selectFirst(string $table, array $conditions = []): ?array
    {
        return $this->selectRows($table, $conditions, ['limit' => 1])[0] ?? null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return int the new primary key
     */
    protected function insertRow(string $table, array $row): int
    {
        return (int) DB::table($table)->insertGetId($this->prepareRow($table, $row));
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  array<mixed>  $conditions
     * @return int affected rows
     */
    protected function updateRows(string $table, array $values, array $conditions): int
    {
        $this->guardConditions($table, $conditions);

        $values = $this->prepareRow($table, $values, inserting: false);

        if ($values === []) {
            return 0;
        }

        $query = $this->newQuery($table);
        $this->applyConditions($query, $conditions);

        return $query->update($values);
    }

    /**
     * @param  array<mixed>  $conditions
     * @return int affected rows
     */
    protected function deleteRows(string $table, array $conditions, bool $includeTrashed = false): int
    {
        $this->guardConditions($table, $conditions);

        $query = $includeTrashed
            ? DB::table($table)->where($table.'.company_id', $this->tenantId())
            : $this->newQuery($table);

        $this->applyConditions($query, $conditions);

        return $query->delete();
    }

    /**
     * Writes one legacy `logs` row into the tenant-scoped audit trail.
     *
     * @param  array<string, mixed>  $values  module, username, office_id, command, details, employee_id_affected
     * @return int the new audit row id
     */
    protected function insertAuditRow(array $values): int
    {
        $employeeId = $values['employee_id_affected'] ?? null;

        return $this->insertRow('hr_audit_events', [
            'employee_id' => is_numeric($employeeId) ? (int) $employeeId : null,
            'event' => mb_substr((string) ($values['command'] ?? 'LOG'), 0, 64),
            'reason' => (string) ($values['module'] ?? ''),
            'payload' => json_encode([
                'details' => $values['details'] ?? '',
                'office_id' => $values['office_id'] ?? null,
                'username' => $values['username'] ?? null,
                'employee_id_affected' => $employeeId,
            ]),
            'created_at' => now(),
        ]);
    }

    /**
     * @template T
     *
     * @param  callable(static): T  $operation
     * @return T
     */
    protected function runTransaction(callable $operation): mixed
    {
        return DB::transaction(fn (): mixed => $operation($this));
    }

    protected function newQuery(string $table): Builder
    {
        $query = DB::table($table)->where($table.'.company_id', $this->tenantId());

        if (in_array('deleted_at', $this->columnListing($table), true)) {
            $query->whereNull($table.'.deleted_at');
        }

        return $query;
    }

    /**
     * @return list<string>
     */
    protected function columnListing(string $table): array
    {
        return self::$columns[$table] ??= Schema::getColumnListing($table);
    }

    /**
     * Adds the tenant and timestamps, and drops keys the table does not have.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function prepareRow(string $table, array $row, bool $inserting = true): array
    {
        $columns = $this->columnListing($table);
        $now = now();

        if (in_array('company_id', $columns, true) && ($row['company_id'] ?? null) === null) {
            $row['company_id'] = $this->tenantId();
        }

        if ($inserting && in_array('created_at', $columns, true) && ! isset($row['created_at'])) {
            $row['created_at'] = $now;
        }

        if (in_array('updated_at', $columns, true) && ! isset($row['updated_at'])) {
            $row['updated_at'] = $now;
        }

        return array_intersect_key($row, array_flip($columns));
    }

    /**
     * @param  array<mixed>  $conditions
     */
    protected function guardConditions(string $table, array $conditions): void
    {
        if ($conditions === []) {
            throw new InvalidArgumentException(
                sprintf('A legacy store write to [%s] needs an explicit condition.', $table),
            );
        }
    }

    /**
     * @param  array<mixed>  $conditions
     */
    protected function applyConditions(Builder $query, array $conditions): void
    {
        foreach ($this->normaliseConditions($conditions) as $condition) {
            $this->applyCondition($query, $condition);
        }
    }

    /**
     * @param  array<mixed>  $conditions
     * @return list<Condition>
     */
    private function normaliseConditions(array $conditions): array
    {
        if ($conditions === []) {
            return [];
        }

        if (! array_is_list($conditions)) {
            return Condition::fromArray($conditions);
        }

        return array_map(function (mixed $condition): Condition {
            if ($condition instanceof Condition) {
                return $condition;
            }

            if (is_array($condition) && count($condition) >= 2) {
                $values = array_values($condition);

                return new Condition((string) $values[0], (string) $values[1], $values[2] ?? null);
            }

            throw new InvalidArgumentException('Unsupported legacy store condition.');
        }, $conditions);
    }

    private function applyCondition(Builder $query, Condition $condition): void
    {
        match ($condition->operator) {
            Condition::EQUALS => $query->where($condition->column, '=', $condition->value),
            Condition::NOT_EQUALS => $query->where($condition->column, '!=', $condition->value),
            Condition::GREATER_THAN => $query->where($condition->column, '>', $condition->value),
            Condition::GREATER_THAN_OR_EQUALS => $query->where($condition->column, '>=', $condition->value),
            Condition::LESS_THAN => $query->where($condition->column, '<', $condition->value),
            Condition::LESS_THAN_OR_EQUALS => $query->where($condition->column, '<=', $condition->value),
            Condition::IN => $query->whereIn($condition->column, (array) $condition->value),
            Condition::NOT_IN => $query->whereNotIn($condition->column, (array) $condition->value),
            Condition::IS_NULL => $query->whereNull($condition->column),
            Condition::IS_NOT_NULL => $query->whereNotNull($condition->column),
            Condition::LIKE => $query->where($condition->column, 'like', $condition->value),
            Condition::NOT_LIKE => $query->where($condition->column, 'not like', $condition->value),
            Condition::BETWEEN => $query->whereBetween($condition->column, [$condition->value, $condition->upper]),
            Condition::NOT_BETWEEN => $query->whereNotBetween($condition->column, [$condition->value, $condition->upper]),
            default => throw new InvalidArgumentException(
                sprintf('Unsupported condition operator "%s".', $condition->operator),
            ),
        };
    }

    /**
     * @param  array{orderBy?: array<string, string>, limit?: int|null, offset?: int|null}  $options
     */
    private function applyOptions(Builder $query, array $options): void
    {
        foreach ((array) ($options['orderBy'] ?? []) as $column => $direction) {
            $query->orderBy((string) $column, strtolower((string) $direction) === 'desc' ? 'desc' : 'asc');
        }

        if (($options['limit'] ?? null) !== null) {
            $query->limit((int) $options['limit']);
        }

        if (($options['offset'] ?? null) !== null) {
            $query->offset((int) $options['offset']);
        }
    }
}
