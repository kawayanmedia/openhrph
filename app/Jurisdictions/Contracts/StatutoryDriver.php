<?php

namespace App\Jurisdictions\Contracts;

/**
 * Computes a country's statutory contributions and withholding tax.
 *
 * A table-driven country can reuse the generic driver. A country with a
 * dedicated formula implements this contract and registers it on the
 * jurisdiction.
 */
interface StatutoryDriver
{
    public function code(): string;

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function contributions(array $context): array;

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function tax(array $context): array;

    public function supports(string $runType): bool;
}
