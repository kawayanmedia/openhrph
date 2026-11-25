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
 * keyed by `id`.
 */
final class EloquentPdsStore extends LegacyStore implements PdsStoreInterface
{
    private readonly PdsTables $tables;

    public function __construct(?PdsTables $tables = null)
    {
        $this->tables = $tables ?? PdsTables::fromArray(
            [PdsSection::PROFILE => 'employees'],
            [PdsSection::PROFILE => 'id'],
        );
    }

    public function find(string $section, string $employeeId): array
    {
        return $this->selectRows($this->table($section), [$this->key($section) => $employeeId]);
    }

    public function first(string $section, string $employeeId): ?array
    {
        return $this->selectFirst($this->table($section), [$this->key($section) => $employeeId]);
    }

    public function save(string $section, string $employeeId, array $values): void
    {
        $key = $this->key($section);

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

        $this->updateRows($this->table($section), $values, [$this->key($section) => $employeeId]);
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
}
