<?php

namespace App\Imports\Workbook;

use App\Imports\Workbook\Exceptions\ImportValidationException;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use RuntimeException;

/**
 * Validates an uploaded workbook in full and only then writes it.
 *
 * Every sheet is checked before a single row is saved. If one cell is wrong the
 * whole workbook is rejected, so a failed import never leaves partial data.
 */
final class WorkbookImporter
{
    private readonly WorkbookReader $reader;

    /**
     * @var array<string, SheetData>
     */
    private array $data = [];

    /**
     * @var array<string, array<int, array{row: int, values: array<string, mixed>}>>
     */
    private array $rows = [];

    /**
     * @var array<string, array<int, true>>
     */
    private array $invalidRows = [];

    /**
     * @var array<string, array<string, string>>
     */
    private array $letters = [];

    /**
     * @var array<string, array<int, string>>
     */
    private array $mappedColumns = [];

    /**
     * @var array<int, ImportProblem>
     */
    private array $problems = [];

    private ?ReferenceIndex $references = null;

    public function __construct(
        private readonly WorkbookDefinition $workbook,
        ?WorkbookReader $reader = null,
    ) {
        $this->reader = $reader ?? new WorkbookReader((int) config('imports.max_rows', 5000));
    }

    /**
     * @throws ImportValidationException
     */
    public function import(string $path, string $extension): ImportReport
    {
        $this->analyse($path, $extension);

        if ($this->problems !== []) {
            throw new ImportValidationException($this->problems);
        }

        return DB::transaction(fn (): ImportReport => $this->persist());
    }

    /**
     * Validate the workbook and describe what an import would do, without
     * writing anything.
     */
    public function preview(string $path, string $extension, ?int $rowLimit = null): ImportPreview
    {
        $this->analyse($path, $extension);

        return $this->buildPreview($rowLimit ?? (int) config('imports.preview_rows', 5));
    }

    private function analyse(string $path, string $extension): void
    {
        $this->reset();

        if (app(TenantContext::class)->id() === null) {
            throw new RuntimeException('A company must be selected before data can be imported.');
        }

        $this->matchSheets($this->reader->read($path, $extension));

        foreach ($this->workbook->sheets() as $definition) {
            $data = $this->data[$definition->key()] ?? null;

            if ($data === null || $data->isEmpty()) {
                continue;
            }

            $this->validateSheet($definition, $data);
        }

        $this->validateReferences();
        $this->sortProblems();
    }

    private function reset(): void
    {
        $this->data = [];
        $this->rows = [];
        $this->invalidRows = [];
        $this->letters = [];
        $this->mappedColumns = [];
        $this->problems = [];
        $this->references = null;
    }

    /**
     * @param  array<int, SheetData>  $sheets
     */
    private function matchSheets(array $sheets): void
    {
        $hasRows = false;

        foreach ($sheets as $sheet) {
            if ($this->isIgnoredSheet($sheet->name)) {
                continue;
            }

            $definition = $this->workbook->forSheetName($sheet->name);

            if ($definition === null) {
                if (! $sheet->isEmpty()) {
                    $this->problems[] = ImportProblem::sheet(
                        $sheet->name,
                        'is not part of the import template. Remove the sheet or download a fresh template.',
                    );
                }

                continue;
            }

            $this->data[$definition->key()] = $sheet;

            if (! $sheet->isEmpty()) {
                $hasRows = true;
            }
        }

        if (! $hasRows && $this->problems === []) {
            $this->problems[] = ImportProblem::workbook(
                'The workbook does not contain any data rows. Fill in at least one sheet and upload it again.',
            );
        }
    }

    private function validateSheet(SheetDefinition $definition, SheetData $data): void
    {
        $maxRows = (int) config('imports.max_rows', 5000);

        if ($data->dataRowCount > $maxRows) {
            $this->problems[] = ImportProblem::sheet(
                $definition->title(),
                sprintf(
                    'has %d data rows; the maximum per import is %d. Split the workbook and import it in batches.',
                    $data->dataRowCount,
                    $maxRows,
                ),
            );
        }

        $map = $this->mapColumns($definition, $data);

        if ($map === null) {
            return;
        }

        $this->letters[$definition->key()] = [];

        foreach ($map as $index => $column) {
            $this->letters[$definition->key()][$column->key] = Coordinate::stringFromColumnIndex($index + 1);
        }

        $this->mappedColumns[$definition->key()] = array_values(array_map(
            static fn (Column $column): string => $column->key,
            $map,
        ));

        $this->validateRows($definition, $data, $map);
        $this->validateUniqueKeys($definition);
    }

    /**
     * @return array<int, Column>|null
     */
    private function mapColumns(SheetDefinition $definition, SheetData $data): ?array
    {
        $title = $definition->title();
        $before = count($this->problems);
        $map = [];
        $mapped = [];

        foreach ($data->headers as $index => $raw) {
            $label = is_scalar($raw) ? trim((string) $raw) : '';

            if ($label === '') {
                continue;
            }

            $column = $definition->columnForHeading($label);

            if ($column === null) {
                $this->problems[] = ImportProblem::cell(
                    $title,
                    1,
                    Coordinate::stringFromColumnIndex($index + 1),
                    $label,
                    'is not a column of this sheet. Remove it or check the spelling.',
                );

                continue;
            }

            if (isset($mapped[$column->key])) {
                $this->problems[] = ImportProblem::cell(
                    $title,
                    1,
                    Coordinate::stringFromColumnIndex($index + 1),
                    $column->heading,
                    sprintf(
                        'is used more than once. "%s" is already in column %s.',
                        $column->heading,
                        Coordinate::stringFromColumnIndex($mapped[$column->key] + 1),
                    ),
                );

                continue;
            }

            $map[$index] = $column;
            $mapped[$column->key] = $index;
        }

        foreach ($definition->columns() as $column) {
            if ($column->required && ! isset($mapped[$column->key])) {
                $this->problems[] = ImportProblem::cell(
                    $title,
                    1,
                    null,
                    $column->heading,
                    sprintf('Required column "%s" is missing from the header row.', $column->heading),
                );
            }
        }

        return count($this->problems) === $before ? $map : null;
    }

    /**
     * @param  array<int, Column>  $map
     */
    private function validateRows(SheetDefinition $definition, SheetData $data, array $map): void
    {
        $title = $definition->title();
        $sheetKey = $definition->key();

        foreach ($data->rows as $rowNumber => $cells) {
            $rowBefore = count($this->problems);

            $this->checkUnlabelledCells($definition, $data, $map, $rowNumber, $cells);

            $values = [];

            foreach ($map as $index => $column) {
                $raw = $cells[$index] ?? null;
                $letter = $this->letters[$sheetKey][$column->key];

                if (is_string($raw) && str_starts_with(ltrim($raw), '=')) {
                    $this->problems[] = ImportProblem::cell(
                        $title,
                        $rowNumber,
                        $letter,
                        $column->heading,
                        'contains a formula. Copy the calculated value and paste it as a number or text.',
                    );

                    continue;
                }

                [$value, $error] = $column->normalise($raw);

                if ($error !== null) {
                    $this->problems[] = ImportProblem::cell(
                        $title,
                        $rowNumber,
                        $letter,
                        $column->heading,
                        sprintf('%s %s', $column->heading, $error),
                    );

                    continue;
                }

                if ($value === null) {
                    if ($column->required) {
                        $this->problems[] = ImportProblem::cell(
                            $title,
                            $rowNumber,
                            $letter,
                            $column->heading,
                            sprintf('%s is required.', $column->heading),
                        );
                    }

                    continue;
                }

                $values[$column->key] = $value;
            }

            if (count($this->problems) === $rowBefore) {
                $this->validateRules($definition, $rowNumber, $values, $map);
            }

            $this->rows[$sheetKey][] = ['row' => $rowNumber, 'values' => $values];

            if (count($this->problems) > $rowBefore) {
                $this->invalidRows[$sheetKey][$rowNumber] = true;

                continue;
            }

            $definition->validateValues($values, $rowNumber, function (string $columnKey, string $message) use ($definition, $sheetKey, $rowNumber): void {
                $column = $definition->column($columnKey);
                $letter = $this->letters[$sheetKey][$columnKey] ?? null;

                $this->problems[] = ImportProblem::cell(
                    $definition->title(),
                    $rowNumber,
                    $letter,
                    $column?->heading,
                    $message,
                );
            });

            if (count($this->problems) > $rowBefore) {
                $this->invalidRows[$sheetKey][$rowNumber] = true;
            }
        }
    }

    /**
     * @param  array<int, Column>  $map
     * @param  array<int, mixed>  $cells
     */
    private function checkUnlabelledCells(
        SheetDefinition $definition,
        SheetData $data,
        array $map,
        int $rowNumber,
        array $cells,
    ): void {
        foreach ($cells as $index => $value) {
            if (isset($map[$index]) || $this->isBlank($value)) {
                continue;
            }

            $label = $data->headers[$index] ?? null;

            if (is_scalar($label) && trim((string) $label) !== '') {
                continue;
            }

            $letter = Coordinate::stringFromColumnIndex($index + 1);

            $this->problems[] = ImportProblem::cell(
                $definition->title(),
                $rowNumber,
                $letter,
                null,
                sprintf('contains a value but column %s has no heading.', $letter),
            );
        }
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  array<int, Column>  $map
     */
    private function validateRules(SheetDefinition $definition, int $rowNumber, array $values, array $map): void
    {
        $rules = [];
        $attributes = [];
        $messages = [];

        foreach ($map as $column) {
            if (! array_key_exists($column->key, $values) || $column->rules === []) {
                continue;
            }

            $rules[$column->key] = $column->rules;
            $attributes[$column->key] = $column->heading;

            foreach ($column->messages as $rule => $message) {
                $messages[$column->key.'.'.$rule] = $message;
            }
        }

        if ($rules === []) {
            return;
        }

        try {
            Validator::make($values, $rules, $messages, $attributes)->validate();
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $key => $list) {
                $column = $definition->column((string) $key);

                if ($column === null) {
                    continue;
                }

                foreach ($list as $message) {
                    $this->problems[] = ImportProblem::cell(
                        $definition->title(),
                        $rowNumber,
                        $this->letters[$definition->key()][$column->key] ?? null,
                        $column->heading,
                        $message,
                    );
                }
            }
        }
    }

    private function validateUniqueKeys(SheetDefinition $definition): void
    {
        $sheetKey = $definition->key();
        $title = $definition->title();
        $keyColumns = $definition->keyColumns();
        $headings = array_map(
            fn (string $key): string => $definition->column($key)?->heading ?? $key,
            $keyColumns,
        );

        $seen = [];

        foreach ($this->rows[$sheetKey] ?? [] as $row) {
            if ($this->isInvalid($sheetKey, $row['row'])) {
                continue;
            }

            $key = $definition->naturalKey($row['values']);

            if ($key === null) {
                continue;
            }

            if (isset($seen[$key])) {
                $columnKey = $keyColumns[0];
                $column = $definition->column($columnKey);

                $this->problems[] = ImportProblem::cell(
                    $title,
                    $row['row'],
                    $this->letters[$sheetKey][$columnKey] ?? null,
                    $column?->heading,
                    sprintf(
                        'Duplicates row %d. %s must be unique in the sheet.',
                        $seen[$key],
                        implode(' + ', $headings),
                    ),
                );

                $this->invalidRows[$sheetKey][$row['row']] = true;

                continue;
            }

            $seen[$key] = $row['row'];
        }

        foreach ($definition->secondaryUniqueColumns() as $columnKey) {
            $column = $definition->column($columnKey);

            if ($column === null) {
                continue;
            }

            $values = [];

            foreach ($this->rows[$sheetKey] ?? [] as $row) {
                if ($this->isInvalid($sheetKey, $row['row'])) {
                    continue;
                }

                $value = $row['values'][$columnKey] ?? null;

                if ($value === null || trim((string) $value) === '') {
                    continue;
                }

                $signature = ReferenceIndex::normalise((string) $value);

                if (isset($values[$signature])) {
                    $this->problems[] = ImportProblem::cell(
                        $title,
                        $row['row'],
                        $this->letters[$sheetKey][$columnKey] ?? null,
                        $column->heading,
                        sprintf(
                            'Duplicates row %d. %s must be unique in the sheet.',
                            $values[$signature],
                            $column->heading,
                        ),
                    );

                    $this->invalidRows[$sheetKey][$row['row']] = true;

                    continue;
                }

                $values[$signature] = $row['row'];
            }
        }
    }

    private function validateReferences(): void
    {
        $references = new ReferenceIndex;

        foreach ($this->workbook->sheets() as $definition) {
            if ($definition->referenceType() === null) {
                continue;
            }

            foreach ($definition->existingReferences() as $reference) {
                $references->seed(
                    $definition->referenceType(),
                    $reference['name'],
                    $reference['code'],
                    $reference['id'],
                );
            }
        }

        foreach ($this->workbook->externalReferences() as $type => $reference) {
            foreach ($reference['model']::query()->get() as $model) {
                $name = $model->getAttribute($reference['name']);

                if ($name === null || trim((string) $name) === '') {
                    continue;
                }

                $code = $reference['code'] === null ? null : $model->getAttribute($reference['code']);

                $references->seed(
                    $type,
                    (string) $name,
                    $code === null ? null : (string) $code,
                    (int) $model->getKey(),
                );
            }
        }

        foreach ($this->workbook->sheets() as $definition) {
            if ($definition->referenceType() === null) {
                continue;
            }

            $nameColumn = $definition->referenceNameColumn();
            $codeColumn = $definition->referenceCodeColumn();

            if ($nameColumn === null) {
                continue;
            }

            foreach ($this->validRows($definition) as $row) {
                $name = $row['values'][$nameColumn] ?? null;

                if ($name === null) {
                    continue;
                }

                $code = $codeColumn === null ? null : ($row['values'][$codeColumn] ?? null);

                $references->markPending(
                    $definition->referenceType(),
                    (string) $name,
                    $code === null ? null : (string) $code,
                    $definition->title().' row '.$row['row'],
                );
            }
        }

        foreach ($this->workbook->sheets() as $definition) {
            foreach ($this->validRows($definition) as $row) {
                $rowNumber = $row['row'];
                $before = count($this->problems);

                $definition->validateReferences($row['values'], $rowNumber, $references, function (string $columnKey, string $message) use ($definition, $rowNumber): void {
                    $column = $definition->column($columnKey);

                    $this->problems[] = ImportProblem::cell(
                        $definition->title(),
                        $rowNumber,
                        $this->letters[$definition->key()][$columnKey] ?? null,
                        $column?->heading,
                        $message,
                    );
                });

                if (count($this->problems) > $before) {
                    $this->invalidRows[$definition->key()][$rowNumber] = true;
                }
            }
        }

        $this->references = $references;
    }

    private function buildPreview(int $rowLimit): ImportPreview
    {
        $rowLimit = max(1, $rowLimit);
        $sheets = [];
        $emptySheets = [];
        $totals = ['created' => 0, 'updated' => 0, 'total' => 0];

        foreach ($this->workbook->sheets() as $definition) {
            $data = $this->data[$definition->key()] ?? null;

            if ($data === null) {
                continue;
            }

            if ($data->isEmpty()) {
                $emptySheets[] = $definition->title();

                continue;
            }

            $existingKeys = $definition->existingNaturalKeys();
            $validRows = $this->validRows($definition);
            $created = 0;
            $updated = 0;

            foreach ($validRows as $row) {
                $key = $definition->naturalKey($row['values']);

                if ($key !== null && isset($existingKeys[$key])) {
                    $updated++;
                } else {
                    $created++;
                }
            }

            $previewRows = [];

            foreach (array_slice($validRows, 0, $rowLimit) as $row) {
                $key = $definition->naturalKey($row['values']);
                $isUpdate = $key !== null && isset($existingKeys[$key]);
                $values = [];
                $references = [];

                foreach ($this->mappedColumns[$definition->key()] ?? [] as $columnKey) {
                    $column = $definition->column($columnKey);

                    if ($column === null) {
                        continue;
                    }

                    $value = $row['values'][$columnKey] ?? null;
                    $values[$column->heading] = $this->displayValue($value);

                    $referenceType = $definition->referenceColumns()[$columnKey] ?? null;

                    if ($referenceType === null || $value === null) {
                        continue;
                    }

                    $details = $this->references?->details($referenceType, (string) $value);

                    if ($details === null) {
                        continue;
                    }

                    $references[$column->heading] = $details['status'] === 'new'
                        ? 'new · '.$details['location']
                        : 'existing';
                }

                $previewRows[] = [
                    'row' => $row['row'],
                    'action' => $isUpdate ? 'update' : 'create',
                    'values' => $values,
                    'references' => $references,
                    'warnings' => $definition->warnings(
                        $row['values'],
                        $this->references ?? new ReferenceIndex,
                        ! $isUpdate,
                    ),
                ];
            }

            $visibleColumns = [];

            foreach ($this->mappedColumns[$definition->key()] ?? [] as $columnKey) {
                $column = $definition->column($columnKey);

                if ($column === null) {
                    continue;
                }

                foreach ($validRows as $validRow) {
                    if ($this->displayValue($validRow['values'][$columnKey] ?? null) !== '') {
                        $visibleColumns[] = $column->heading;

                        break;
                    }
                }
            }

            $sheets[] = [
                'key' => $definition->key(),
                'title' => $definition->title(),
                'created' => $created,
                'updated' => $updated,
                'total' => $created + $updated,
                'columns' => $visibleColumns,
                'rows' => $previewRows,
            ];

            $totals['created'] += $created;
            $totals['updated'] += $updated;
        }

        $totals['total'] = $totals['created'] + $totals['updated'];

        return new ImportPreview($this->problems, $sheets, $totals, $emptySheets);
    }

    private function displayValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_float($value)) {
            if (floor($value) === $value) {
                return (string) (int) $value;
            }

            return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');
        }

        return (string) $value;
    }

    private function persist(): ImportReport
    {
        $references = $this->references ?? new ReferenceIndex;
        $report = [];

        foreach ($this->workbook->sheets() as $definition) {
            $rows = $this->rows[$definition->key()] ?? [];

            if ($rows === []) {
                continue;
            }

            $counts = ['created' => 0, 'updated' => 0];

            foreach ($rows as $row) {
                $model = $definition->findExisting($row['values']) ?? $definition->modelFor($row['values']);
                $creating = ! $model->exists;

                $definition->fill($model, $row['values'], $references, $creating);
                $model->save();

                $definition->afterSave($model, $row['values'], $creating);
                $definition->registerReference($references, $row['values'], $model);

                $counts[$creating ? 'created' : 'updated']++;
            }

            $report[$definition->title()] = $counts;
        }

        return new ImportReport($report);
    }

    /**
     * @return array<int, array{row: int, values: array<string, mixed>}>
     */
    private function validRows(SheetDefinition $definition): array
    {
        return array_values(array_filter(
            $this->rows[$definition->key()] ?? [],
            fn (array $row): bool => ! $this->isInvalid($definition->key(), $row['row']),
        ));
    }

    private function isInvalid(string $sheetKey, int $rowNumber): bool
    {
        return isset($this->invalidRows[$sheetKey][$rowNumber]);
    }

    private function isIgnoredSheet(string $name): bool
    {
        $signature = Column::signature($name);

        foreach ($this->workbook->ignoredSheetNames() as $ignored) {
            if ($signature === Column::signature($ignored)) {
                return true;
            }
        }

        return false;
    }

    private function isBlank(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }

    private function sortProblems(): void
    {
        $order = [];

        foreach ($this->workbook->sheets() as $index => $definition) {
            $order[$definition->title()] = $index;
        }

        usort($this->problems, function (ImportProblem $a, ImportProblem $b) use ($order): int {
            $aSheet = $a->sheet === null ? -1 : ($order[$a->sheet] ?? 999);
            $bSheet = $b->sheet === null ? -1 : ($order[$b->sheet] ?? 999);

            return $aSheet <=> $bSheet
                ?: ($a->row ?? 0) <=> ($b->row ?? 0)
                ?: strcmp((string) $a->column, (string) $b->column);
        });
    }
}
