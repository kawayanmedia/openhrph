<?php

namespace App\Jurisdictions\Philippines\Government\Legacy;

use KawayanMedia\Pds\Config\PdsTables;
use KawayanMedia\Pds\Contract\PdsStoreInterface;
use KawayanMedia\Pds\Support\PdsSection;

/**
 * PDS write side on top of the CS Form 212 tables.
 *
 * Section table names come from `PdsTables`, except the employee profile: the
 * package defaults to an `employee` table, this application uses `employees`
 * keyed by `id`. Two of the package's section tables were renamed by the legacy
 * application, so they are mapped to the names this application stores.
 */
final class EloquentPdsStore extends LegacyStore implements PdsStoreInterface
{
    /**
     * Package profile column => application column.
     *
     * @var array<string, string>
     */
    private const PROFILE_COLUMNS = [
        'permanent' => 'employment_type_id',
        'first_day_of_service' => 'date_hired',
    ];

    private readonly PdsTables $tables;

    public function __construct(?PdsTables $tables = null)
    {
        $this->tables = $tables ?? PdsTables::fromArray(
            [
                PdsSection::PROFILE => 'employees',
                PdsSection::VOLUNTEER => 'pds_voluntary_works',
                PdsSection::TRAINING => 'pds_trainings',
            ],
            [PdsSection::PROFILE => 'id'],
        );
    }

    public function find(string $section, string $employeeId): array
    {
        return array_map(
            fn (array $row): array => $this->fromRow($section, $row),
            $this->selectRows($this->table($section), [$this->key($section) => $employeeId]),
        );
    }

    public function first(string $section, string $employeeId): ?array
    {
        $row = $this->selectFirst($this->table($section), [$this->key($section) => $employeeId]);

        return $row === null ? null : $this->fromRow($section, $row);
    }

    public function save(string $section, string $employeeId, array $values): void
    {
        $key = $this->key($section);
        $values = $this->toRow($section, $values);

        if ($this->first($section, $employeeId) !== null) {
            $this->updateRows($this->table($section), $values, [$key => $employeeId]);

            return;
        }

        $this->insertRow($this->table($section), [$key => $employeeId] + $values);
    }

    public function sync(string $section, string $employeeId, array $values): void
    {
        if ($values === []) {
            return;
        }

        $this->updateRows(
            $this->table($section),
            $this->toRow($section, $values),
            [$this->key($section) => $employeeId],
        );
    }

    public function replace(string $section, string $employeeId, array $rows, array $scope = []): void
    {
        $table = $this->table($section);
        $key = $this->key($section);
        $employeeId = (string) $employeeId;

        $this->runTransaction(function () use ($table, $key, $employeeId, $rows, $scope): void {
            $this->deleteRows($table, [$key => $employeeId] + $scope, includeTrashed: true);

            foreach ($rows as $row) {
                $this->insertRow($table, [$key => $employeeId] + $scope + $row);
            }
        });
    }

    public function transaction(callable $operation): mixed
    {
        return $this->runTransaction($operation);
    }

    public function tables(): PdsTables
    {
        return $this->tables;
    }

    private function table(string $section): string
    {
        return $this->tables()->table($section);
    }

    private function key(string $section): string
    {
        return $this->tables()->key($section);
    }

    /**
     * Translates package column names into the application ones on write.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function toRow(string $section, array $values): array
    {
        if ($section !== PdsSection::PROFILE) {
            return $values;
        }

        $row = [];

        foreach ($values as $column => $value) {
            // The employee columns are typed (dates, integers); the editor
            // sends empty strings for blank fields and MySQL rejects those.
            $row[self::PROFILE_COLUMNS[$column] ?? $column] = $value === '' ? null : $value;
        }

        return $row;
    }

    /**
     * Translates the application profile columns back to the package ones.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function fromRow(string $section, array $row): array
    {
        if ($section !== PdsSection::PROFILE) {
            return $row;
        }

        foreach (self::PROFILE_COLUMNS as $packageColumn => $column) {
            if (array_key_exists($column, $row)) {
                $row[$packageColumn] = $row[$column];
            }
        }

        return $row;
    }
}
