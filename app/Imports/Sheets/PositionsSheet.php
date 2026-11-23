<?php

namespace App\Imports\Sheets;

use App\Imports\Workbook\Column;
use App\Imports\Workbook\ColumnType;
use App\Imports\Workbook\ReferenceIndex;
use App\Imports\Workbook\SheetDefinition;
use App\Models\Position;
use Illuminate\Database\Eloquent\Model;

class PositionsSheet extends SheetDefinition
{
    public function key(): string
    {
        return 'positions';
    }

    public function title(): string
    {
        return 'Positions';
    }

    public function aliases(): array
    {
        return ['Position'];
    }

    /**
     * @return class-string<Model>
     */
    public function model(): string
    {
        return Position::class;
    }

    public function keyColumns(): array
    {
        return ['name'];
    }

    public function referenceType(): ?string
    {
        return 'position';
    }

    public function referenceNameColumn(): ?string
    {
        return 'name';
    }

    public function referenceCodeColumn(): ?string
    {
        return 'code';
    }

    public function referenceColumns(): array
    {
        return ['department' => 'department'];
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
                aliases: ['Position'],
                example: 'Accountant',
            ),
            new Column(
                key: 'code',
                heading: 'Code',
                type: ColumnType::Text,
                required: true,
                rules: ['max:10'],
                example: 'ACC',
            ),
            new Column(
                key: 'department',
                heading: 'Department',
                type: ColumnType::Text,
                rules: ['max:128'],
                example: 'Finance',
                help: 'Must match a department in the Departments sheet or an existing department.',
            ),
            new Column(
                key: 'basic_pay',
                heading: 'Basic Pay',
                type: ColumnType::Decimal,
                rules: ['numeric', 'min:0'],
                example: '45000',
            ),
            new Column(
                key: 'is_vacant',
                heading: 'Vacant',
                type: ColumnType::Boolean,
                options: ['Yes', 'No'],
                example: 'No',
            ),
        ];
    }

    public function sampleRows(): array
    {
        return [
            ['name' => 'Accountant', 'code' => 'ACC', 'department' => 'Finance', 'basic_pay' => '45000', 'is_vacant' => 'No'],
            ['name' => 'HR Officer', 'code' => 'HRO', 'department' => 'Human Resources', 'basic_pay' => '38000', 'is_vacant' => 'Yes'],
        ];
    }

    public function validateReferences(array $values, int $row, ReferenceIndex $references, callable $add): void
    {
        $department = $values['department'] ?? null;

        if ($department !== null && ! $references->exists('department', (string) $department)) {
            $add('department', sprintf(
                'Department "%s" was not found in the Departments sheet or in your company departments.',
                $department,
            ));
        }
    }

    public function fill(Model $model, array $values, ReferenceIndex $references, bool $creating): void
    {
        if ($creating) {
            $model->setAttribute('is_vacant', false);
        }

        $this->setValues($model, $values, [
            'name' => 'name',
            'code' => 'code',
            'basic_pay' => 'basic_pay',
            'is_vacant' => 'is_vacant',
        ]);

        $department = $values['department'] ?? null;
        $departmentId = $department === null ? null : $references->id('department', (string) $department);

        if ($departmentId !== null) {
            $model->setAttribute('department_id', $departmentId);
        }
    }
}
