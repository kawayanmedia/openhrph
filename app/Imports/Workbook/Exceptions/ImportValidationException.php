<?php

namespace App\Imports\Workbook\Exceptions;

use App\Imports\Workbook\ImportProblem;
use RuntimeException;

/**
 * Every problem found while validating a workbook. Thrown before any write, so
 * catching it always means "nothing was imported".
 */
final class ImportValidationException extends RuntimeException
{
    /**
     * @param  array<int, ImportProblem>  $problems
     */
    public function __construct(private readonly array $problems)
    {
        parent::__construct(sprintf(
            'The workbook has %d problem%s. Nothing was imported.',
            count($problems),
            count($problems) === 1 ? '' : 's',
        ));
    }

    /**
     * @return array<int, ImportProblem>
     */
    public function problems(): array
    {
        return $this->problems;
    }

    /**
     * @return array<int, array{sheet: string|null, row: int|null, column: string|null, heading: string|null, message: string, location: string}>
     */
    public function toArray(): array
    {
        return array_map(static fn (ImportProblem $problem): array => $problem->toArray(), $this->problems);
    }
}
