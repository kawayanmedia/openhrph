<?php

namespace App\Imports\Workbook;

/**
 * What a successful import wrote, per sheet.
 */
final readonly class ImportReport
{
    /**
     * @param  array<string, array{created: int, updated: int}>  $sheets
     */
    public function __construct(public array $sheets) {}

    public function created(): int
    {
        return array_sum(array_column($this->sheets, 'created'));
    }

    public function updated(): int
    {
        return array_sum(array_column($this->sheets, 'updated'));
    }

    public function total(): int
    {
        return $this->created() + $this->updated();
    }

    /**
     * @return array<string, array{created: int, updated: int}>
     */
    public function toArray(): array
    {
        return $this->sheets;
    }
}
