<?php

namespace App\Imports\Sheets;

use App\Imports\Workbook\Column;
use App\Imports\Workbook\ColumnType;
use App\Imports\Workbook\ReferenceIndex;
use App\Imports\Workbook\SheetDefinition;
use App\Models\Office;
use Illuminate\Database\Eloquent\Model;

class OfficesSheet extends SheetDefinition
{
    public function key(): string
    {
        return 'offices';
    }

    public function title(): string
    {
        return 'Offices';
    }

    public function aliases(): array
    {
        return ['Office'];
    }

    /**
     * @return class-string<Model>
     */
    public function model(): string
    {
        return Office::class;
    }

    public function keyColumns(): array
    {
        return ['name'];
    }

    public function referenceType(): ?string
    {
        return 'office';
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
                aliases: ['Office', 'Office Branch'],
                example: 'Head Office',
                help: 'Unique office name used by employees.',
            ),
            new Column(
                key: 'code',
                heading: 'Code',
                type: ColumnType::Text,
                rules: ['max:10'],
                example: 'HO',
                help: 'Optional short code.',
            ),
            new Column(
                key: 'account_number',
                heading: 'Account Number',
                type: ColumnType::Text,
                rules: ['max:16'],
                example: '0012-3456-78',
            ),
        ];
    }

    public function sampleRows(): array
    {
        return [
            ['name' => 'Head Office', 'code' => 'HO', 'account_number' => '0012-3456-78'],
            ['name' => 'Makati Branch', 'code' => 'MKT', 'account_number' => '0012-3456-79'],
        ];
    }

    public function fill(Model $model, array $values, ReferenceIndex $references, bool $creating): void
    {
        $this->setValues($model, $values, [
            'name' => 'name',
            'code' => 'code',
            'account_number' => 'account_number',
        ]);
    }
}
