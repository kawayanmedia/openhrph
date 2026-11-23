<?php

namespace App\Imports\Workbook;

use App\Imports\Workbook\Exceptions\ImportFormatException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

/**
 * Reads an uploaded workbook into plain arrays without calculating formulas.
 */
final class WorkbookReader
{
    public function __construct(private readonly int $maxRows = 5000) {}

    /**
     * @return array<int, SheetData>
     */
    public function read(string $path, string $extension): array
    {
        $extension = strtolower(ltrim($extension, '.'));

        if (! in_array($extension, ['xlsx', 'xls'], true)) {
            throw ImportFormatException::unsupportedExtension($extension);
        }

        if ($path === '' || ! is_file($path) || ! is_readable($path)) {
            throw ImportFormatException::unreadable();
        }

        try {
            $reader = IOFactory::createReader($extension === 'xlsx' ? 'Xlsx' : 'Xls');
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($path);
        } catch (Throwable) {
            throw ImportFormatException::corrupt();
        }

        try {
            $sheets = [];

            foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
                $sheets[] = $this->readSheet($sheet);
            }

            return $sheets;
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    private function readSheet(Worksheet $sheet): SheetData
    {
        $name = $sheet->getTitle();
        $highestRow = $sheet->getHighestDataRow();
        $highestColumn = $sheet->getHighestDataColumn();
        $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);

        $headers = $this->headerRow($sheet, $highestColumn);

        if ($highestRow <= 1) {
            return new SheetData($name, $headers, [], 0);
        }

        if ($highestRow - 1 > $this->maxRows) {
            return new SheetData($name, $headers, [], $highestRow - 1);
        }

        $grid = $sheet->toArray(null, false, false, false);

        $headers = array_values(array_slice($grid[0] ?? [], 0, $highestColumnIndex));
        $rows = [];

        foreach ($grid as $index => $cells) {
            if ($index === 0) {
                continue;
            }

            if (! $this->hasValues($cells)) {
                continue;
            }

            $rows[$index + 1] = $cells;
        }

        return new SheetData($name, $headers, $rows, count($rows));
    }

    /**
     * @return array<int, mixed>
     */
    private function headerRow(Worksheet $sheet, string $highestColumn): array
    {
        $range = 'A1:'.$highestColumn.'1';
        $row = $sheet->rangeToArray($range, null, false, false, false)[0] ?? [];

        return array_values($row);
    }

    /**
     * @param  array<int, mixed>  $cells
     */
    private function hasValues(array $cells): bool
    {
        foreach ($cells as $value) {
            if ($value === null) {
                continue;
            }

            if (is_string($value) && trim($value) === '') {
                continue;
            }

            return true;
        }

        return false;
    }
}
