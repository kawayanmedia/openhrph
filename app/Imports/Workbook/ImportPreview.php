<?php

namespace App\Imports\Workbook;

/**
 * What a workbook will do, without writing anything.
 */
final readonly class ImportPreview
{
    /**
     * @param  array<int, ImportProblem>  $problems
     * @param  array<int, array{
     *     key: string,
     *     title: string,
     *     created: int,
     *     updated: int,
     *     total: int,
     *     columns: array<int, string>,
     *     rows: array<int, array{row: int, action: string, values: array<string, string>, references: array<string, string>, warnings: array<int, string>}>
     * }>  $sheets
     * @param  array{created: int, updated: int, total: int}  $totals
     * @param  array<int, string>  $emptySheets
     */
    public function __construct(
        public array $problems,
        public array $sheets,
        public array $totals,
        public array $emptySheets,
    ) {}

    public function isClean(): bool
    {
        return $this->problems === [];
    }

    /**
     * @return array{
     *     clean: bool,
     *     totals: array{created: int, updated: int, total: int},
     *     empty_sheets: array<int, string>,
     *     sheets: array<int, array<string, mixed>>,
     *     problems: array<int, array<string, mixed>>
     * }
     */
    public function toArray(): array
    {
        return [
            'clean' => $this->isClean(),
            'totals' => $this->totals,
            'empty_sheets' => $this->emptySheets,
            'sheets' => $this->sheets,
            'problems' => array_map(
                static fn (ImportProblem $problem): array => $problem->toArray(),
                $this->problems,
            ),
        ];
    }
}
