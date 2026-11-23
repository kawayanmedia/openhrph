<?php

namespace App\Imports\Workbook;

use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * One worksheet of the import workbook: its columns, its natural key and how a
 * validated row is written.
 */
abstract class SheetDefinition
{
    abstract public function key(): string;

    abstract public function title(): string;

    /**
     * @return array<int, Column>
     */
    abstract public function columns(): array;

    /**
     * The columns that identify a row. Existing rows with the same key are
     * updated instead of duplicated.
     *
     * @return array<int, string>
     */
    abstract public function keyColumns(): array;

    /**
     * @return class-string<Model>
     */
    abstract public function model(): string;

    /**
     * @param  array<string, mixed>  $values
     */
    abstract public function fill(Model $model, array $values, ReferenceIndex $references, bool $creating): void;

    /**
     * Alternative sheet names accepted in the workbook.
     *
     * @return array<int, string>
     */
    public function aliases(): array
    {
        return [];
    }

    /**
     * Extra columns that must be unique inside the sheet when they have a value.
     *
     * @return array<int, string>
     */
    public function secondaryUniqueColumns(): array
    {
        return [];
    }

    /**
     * Example rows used by the sample workbook, keyed by column key.
     *
     * @return array<int, array<string, mixed>>
     */
    public function sampleRows(): array
    {
        return [];
    }

    /**
     * The reference type this sheet feeds, when other sheets point at it.
     */
    public function referenceType(): ?string
    {
        return null;
    }

    public function referenceNameColumn(): ?string
    {
        return null;
    }

    public function referenceCodeColumn(): ?string
    {
        return null;
    }

    /**
     * Records already in the company that workbook rows may reference.
     *
     * @return array<int, array{name: string, code: string|null, id: int}>
     */
    public function existingReferences(): array
    {
        $type = $this->referenceType();
        $nameColumn = $this->referenceNameColumn();

        if ($type === null || $nameColumn === null) {
            return [];
        }

        $codeColumn = $this->referenceCodeColumn();
        $references = [];

        foreach ($this->model()::query()->get() as $model) {
            $name = $model->getAttribute($nameColumn);

            if ($name === null || trim((string) $name) === '') {
                continue;
            }

            $code = $codeColumn === null ? null : $model->getAttribute($codeColumn);

            $references[] = [
                'name' => (string) $name,
                'code' => $code === null ? null : (string) $code,
                'id' => (int) $model->getKey(),
            ];
        }

        return $references;
    }

    public function column(string $key): ?Column
    {
        foreach ($this->columns() as $column) {
            if ($column->key === $key) {
                return $column;
            }
        }

        return null;
    }

    public function columnForHeading(string $heading): ?Column
    {
        foreach ($this->columns() as $column) {
            if ($column->matches($heading)) {
                return $column;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    public function requiredHeadings(): array
    {
        return array_values(array_map(
            static fn (Column $column): string => $column->heading,
            array_filter($this->columns(), static fn (Column $column): bool => $column->required),
        ));
    }

    /**
     * The unique signature of a row inside the sheet.
     *
     * @param  array<string, mixed>  $values
     */
    public function naturalKey(array $values): ?string
    {
        $parts = [];

        foreach ($this->keyColumns() as $column) {
            $value = $values[$column] ?? null;

            if ($value === null) {
                return null;
            }

            $parts[] = ReferenceIndex::normalise((string) $value);
        }

        return $parts === [] ? null : implode('|', $parts);
    }

    /**
     * Find the record this row updates, if it already exists.
     *
     * @param  array<string, mixed>  $values
     */
    public function findExisting(array $values): ?Model
    {
        $query = $this->model()::query();

        foreach ($this->keyColumns() as $column) {
            $value = $values[$column] ?? null;

            if ($value === null) {
                return null;
            }

            $query->whereRaw('LOWER(TRIM('.$column.')) = ?', [ReferenceIndex::normalise((string) $value)]);
        }

        return $query->first();
    }

    /**
     * The model a new row is written to. Sheets that create catalogue overrides
     * can return an existing catalogue-backed model instead.
     *
     * @param  array<string, mixed>  $values
     */
    public function modelFor(array $values): Model
    {
        return $this->newModel();
    }

    /**
     * Cross-field checks that do not need other sheets.
     *
     * @param  array<string, mixed>  $values
     * @param  callable(string, string): void  $add
     */
    public function validateValues(array $values, int $row, callable $add): void
    {
        //
    }

    /**
     * Checks against records in the workbook or already in the company.
     *
     * @param  array<string, mixed>  $values
     * @param  callable(string, string): void  $add
     */
    public function validateReferences(array $values, int $row, ReferenceIndex $references, callable $add): void
    {
        //
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function afterSave(Model $model, array $values, bool $creating): void
    {
        //
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function registerReference(ReferenceIndex $references, array $values, Model $model): void
    {
        $type = $this->referenceType();
        $nameColumn = $this->referenceNameColumn();

        if ($type === null || $nameColumn === null) {
            return;
        }

        $name = $values[$nameColumn] ?? null;

        if ($name === null) {
            return;
        }

        $codeColumn = $this->referenceCodeColumn();
        $code = $codeColumn === null ? null : ($values[$codeColumn] ?? null);

        $references->registerCreated(
            $type,
            (string) $name,
            $code === null ? null : (string) $code,
            (int) $model->getKey(),
        );
    }

    protected function newModel(): Model
    {
        $class = $this->model();

        return new $class;
    }

    protected function tenantId(): int
    {
        $tenantId = app(TenantContext::class)->id();

        if ($tenantId === null) {
            throw new RuntimeException('A company must be selected before data can be imported.');
        }

        return $tenantId;
    }

    /**
     * Copy the non-blank values onto the model.
     *
     * @param  array<string, mixed>  $values
     * @param  array<string, string>  $mapping  Column key => model attribute.
     */
    protected function setValues(Model $model, array $values, array $mapping): void
    {
        foreach ($mapping as $column => $attribute) {
            if (! array_key_exists($column, $values) || $values[$column] === null) {
                continue;
            }

            $model->setAttribute($attribute, $values[$column]);
        }
    }
}
