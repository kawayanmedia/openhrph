<?php

namespace App\Imports\Workbook;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Builds the downloadable workbook from the sheet definitions, so the template
 * always matches what the importer expects.
 */
class SampleWorkbook
{
    public function __construct(private readonly WorkbookDefinition $workbook) {}

    public function build(bool $withExamples = true): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->removeSheetByIndex(0);

        $this->instructionsSheet($spreadsheet);

        foreach ($this->workbook->sheets() as $definition) {
            $this->dataSheet($spreadsheet, $definition, $withExamples);
        }

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    private function instructionsSheet(Spreadsheet $spreadsheet): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Instructions');

        $lines = [
            ['OpenHR PH — data import'],
            ['One workbook. Every sheet is validated before anything is saved.'],
            [''],
            ['How to use this workbook'],
            ['1. Fill in the sheets you need. Leave the others as they are.'],
            ['2. Keep the header row. Column order can change, but extra columns and sheets are rejected.'],
            ['3. Yes/No columns accept Yes, No, TRUE, FALSE, 1 and 0.'],
            ['4. Dates use YYYY-MM-DD. Times use HH:MM (24-hour).'],
            ['5. Amounts may include thousands separators or a currency symbol.'],
            ['6. A reference such as Office must be in this workbook or already exist for your company.'],
            ['7. If a single cell is invalid, nothing is imported and the page lists every problem.'],
            ['8. Rows are matched on the "Matched on" columns. Existing records are updated, new ones created.'],
            ['9. Blank cells leave an existing value unchanged. Blank rows are ignored.'],
            [''],
            ['Sheets in this workbook'],
        ];

        $row = 1;

        foreach ($lines as $line) {
            $sheet->setCellValueExplicit([1, $row], (string) $line[0], DataType::TYPE_STRING);
            $row++;
        }

        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A4')->getFont()->setBold(true)->setSize(12);

        $tableHeaderRow = $row;
        $this->writeRow($sheet, $tableHeaderRow, ['Sheet', 'Required columns', 'Matched on', 'Notes']);
        $this->styleTableHeader($sheet, $tableHeaderRow, 4);

        foreach ($this->workbook->sheets() as $definition) {
            $row++;
            $this->writeRow($sheet, $row, [
                $definition->title(),
                implode(', ', $definition->requiredHeadings()),
                $this->keyHeadings($definition),
                $definition->sampleRows() === []
                    ? 'Header only. Add your own rows.'
                    : 'Example rows are included — replace them with your data.',
            ]);
        }

        $row += 2;
        $sheet->setCellValueExplicit([1, $row], 'Column reference', DataType::TYPE_STRING);
        $sheet->getStyle([1, $row])->getFont()->setBold(true)->setSize(12);

        $row++;
        $this->writeRow($sheet, $row, ['Sheet', 'Column', 'Required', 'Format', 'Example', 'Notes']);
        $this->styleTableHeader($sheet, $row, 6);

        foreach ($this->workbook->sheets() as $definition) {
            foreach ($definition->columns() as $column) {
                $row++;
                $this->writeRow($sheet, $row, [
                    $definition->title(),
                    $column->heading,
                    $column->required ? 'Yes' : 'No',
                    $this->formatLabel($column),
                    $column->example,
                    $column->help,
                ]);
            }
        }

        $sheet->getColumnDimension('A')->setWidth(22);
        $sheet->getColumnDimension('B')->setWidth(38);
        $sheet->getColumnDimension('C')->setWidth(22);
        $sheet->getColumnDimension('D')->setWidth(24);
        $sheet->getColumnDimension('E')->setWidth(26);
        $sheet->getColumnDimension('F')->setWidth(60);
        $sheet->freezePane('A2');
    }

    private function dataSheet(Spreadsheet $spreadsheet, SheetDefinition $definition, bool $withExamples): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle($definition->title());

        $columns = $definition->columns();

        foreach ($columns as $index => $column) {
            $sheet->setCellValueExplicit(
                [$index + 1, 1],
                $column->heading.($column->required ? ' *' : ''),
                DataType::TYPE_STRING,
            );

            $sheet->getColumnDimensionByColumn($index + 1)
                ->setWidth(min(max(mb_strlen($column->heading) + 6, 14), 42));

            $this->addDropdown($sheet, $column, $index + 1);
        }

        $this->styleTableHeader($sheet, 1, count($columns));

        $rows = $withExamples ? $definition->sampleRows() : [];
        $rowNumber = 1;

        foreach ($rows as $row) {
            $rowNumber++;

            foreach ($columns as $index => $column) {
                $value = $row[$column->key] ?? '';

                $sheet->setCellValueExplicit(
                    [$index + 1, $rowNumber],
                    (string) $value,
                    DataType::TYPE_STRING,
                );
            }
        }

        $lastColumn = Coordinate::stringFromColumnIndex(count($columns));

        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:'.$lastColumn.'1');

        if ($rowNumber > 1) {
            $sheet->getStyle('A2:'.$lastColumn.$rowNumber)->getFont()->setItalic(true);
        }
    }

    private function addDropdown(Worksheet $sheet, Column $column, int $columnIndex): void
    {
        if ($column->type !== ColumnType::Boolean && $column->options === []) {
            return;
        }

        $options = $column->type === ColumnType::Boolean ? ['Yes', 'No'] : $column->options;

        $validation = new DataValidation;
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setAllowBlank(true);
        $validation->setShowDropDown(true);
        $validation->setShowErrorMessage(true);
        $validation->setErrorTitle('Invalid value');
        $validation->setError('Choose a value from the drop-down list.');
        $validation->setFormula1('"'.implode(',', $options).'"');

        for ($row = 2; $row <= 101; $row++) {
            $sheet->getCell([$columnIndex, $row])->setDataValidation(clone $validation);
        }
    }

    /**
     * @param  array<int, string>  $values
     */
    private function writeRow(Worksheet $sheet, int $row, array $values): void
    {
        foreach (array_values($values) as $index => $value) {
            $sheet->setCellValueExplicit([$index + 1, $row], (string) $value, DataType::TYPE_STRING);
        }
    }

    private function styleTableHeader(Worksheet $sheet, int $row, int $columnCount): void
    {
        $lastColumn = Coordinate::stringFromColumnIndex($columnCount);
        $range = 'A'.$row.':'.$lastColumn.$row;

        $sheet->getStyle($range)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0F766E'],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'bottom' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '0B5F58'],
                ],
            ],
        ]);

        $sheet->getRowDimension($row)->setRowHeight(20);
    }

    private function keyHeadings(SheetDefinition $definition): string
    {
        return implode(', ', array_map(
            static fn (string $key): string => $definition->column($key)?->heading ?? $key,
            $definition->keyColumns(),
        ));
    }

    private function formatLabel(Column $column): string
    {
        if ($column->type === ColumnType::Boolean) {
            return 'Yes / No';
        }

        if ($column->options !== []) {
            return implode(', ', $column->options);
        }

        return match ($column->type) {
            ColumnType::Integer => 'Whole number',
            ColumnType::Decimal => 'Number',
            ColumnType::Date => 'Date (YYYY-MM-DD)',
            ColumnType::Time => 'Time (HH:MM)',
            default => 'Text',
        };
    }
}
