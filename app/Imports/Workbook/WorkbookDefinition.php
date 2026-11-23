<?php

namespace App\Imports\Workbook;

use Illuminate\Database\Eloquent\Model;

/**
 * The sheets a workbook may contain, in insert order.
 */
abstract class WorkbookDefinition
{
    /**
     * @return array<int, SheetDefinition>
     */
    abstract public function sheets(): array;

    /**
     * @return array<string, SheetDefinition> keyed by sheet key
     */
    public function keyed(): array
    {
        $sheets = [];

        foreach ($this->sheets() as $sheet) {
            $sheets[$sheet->key()] = $sheet;
        }

        return $sheets;
    }

    public function find(string $key): ?SheetDefinition
    {
        return $this->keyed()[$key] ?? null;
    }

    /**
     * Sheets the template includes that are documentation, not data.
     *
     * @return array<int, string>
     */
    public function ignoredSheetNames(): array
    {
        return ['Instructions'];
    }

    /**
     * Reference types that sheets point at but that are not imported by a sheet
     * (for example an employment type maintained in the admin screens).
     *
     * @return array<string, array{model: class-string<Model>, name: string, code: string|null}>
     */
    public function externalReferences(): array
    {
        return [];
    }

    /**
     * Match a worksheet title to a definition, including its aliases.
     */
    public function forSheetName(string $name): ?SheetDefinition
    {
        $signature = Column::signature($name);

        foreach ($this->sheets() as $sheet) {
            if ($signature === Column::signature($sheet->title())) {
                return $sheet;
            }

            foreach ($sheet->aliases() as $alias) {
                if ($signature === Column::signature($alias)) {
                    return $sheet;
                }
            }
        }

        return null;
    }
}
