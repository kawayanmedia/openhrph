<?php

namespace App\Jurisdictions\Contracts;

/**
 * Supplies a country's public holiday calendar.
 */
interface HolidayProvider
{
    /**
     * @return array<int, array{date: string, name: string, type: string, code: string}>
     */
    public function calendar(int $year): array;

    /**
     * @return array<string, string>
     */
    public function types(): array;

    public function import(int $companyId, int $year, bool $fresh = false): int;
}
