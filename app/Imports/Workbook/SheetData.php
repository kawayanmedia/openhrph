<?php

namespace App\Imports\Workbook;

/**
 * One worksheet read from the uploaded workbook.
 */
final readonly class SheetData
{
    /**
     * @param  array<int, mixed>  $headers  Row 1 values keyed by zero-based column index.
     * @param  array<int, array<int, mixed>>  $rows  Cell values keyed by Excel row number then column index.
     * @param  int  $dataRowCount  Number of non-empty rows below the header.
     */
    public function __construct(
        public string $name,
        public array $headers,
        public array $rows,
        public int $dataRowCount,
    ) {}

    public function isEmpty(): bool
    {
        return $this->dataRowCount === 0;
    }
}
