<?php

namespace App\Imports\Sheets;

use App\Imports\Workbook\Column;
use App\Imports\Workbook\ColumnType;
use App\Imports\Workbook\ReferenceIndex;
use App\Imports\Workbook\SheetDefinition;
use App\Models\Department;
use Illuminate\Database\Eloquent\Model;

class DepartmentsSheet extends SheetDefinition
{
    public function key(): string
    {
        return 'departments';
    }

    public function title(): string
    {
        return 'Departments';
    }

    public function aliases(): array
    {
        return ['Department'];
    }

    /**
     * @return class-string<Model>
     */
    public function model(): string
    {
        return Department::class;
    }

    public function keyColumns(): array
    {
        return ['name'];
    }

    public function referenceType(): ?string
    {
        return 'department';
    }

    public function referenceNameColumn(): ?string
    {
        return 'name';
    }

    public function referenceCodeColumn(): ?string
    {
        return 'code';
    }

    public function columns(): array
    {
        return [
            new Column(
                key: 'name',
                heading: 'Name',
                type: ColumnType::Text,
                required: true,
                rules: ['max:128'],
                aliases: ['Department'],
                example: 'Finance',
                help: 'Unique department name used by employees and positions.',
            ),
            new Column(
                key: 'code',
                heading: 'Code',
                type: ColumnType::Text,
                rules: ['max:10'],
                example: 'FIN',
            ),
            new Column(
                key: 'address',
                heading: 'Address',
                type: ColumnType::Text,
                rules: ['max:64'],
                example: '2nd Floor, Head Office',
            ),
        ];
    }

    public function sampleRows(): array
    {
        return [
            ['name' => 'Finance', 'code' => 'FIN', 'address' => '2nd Floor, Head Office'],
            ['name' => 'Human Resources', 'code' => 'HR', 'address' => '1st Floor, Head Office'],
        ];
    }

    public function fill(Model $model, array $values, ReferenceIndex $references, bool $creating): void
    {
        $this->setValues($model, $values, [
            'name' => 'name',
            'code' => 'code',
            'address' => 'address',
        ]);
    }
}
