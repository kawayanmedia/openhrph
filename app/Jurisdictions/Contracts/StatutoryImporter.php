<?php

namespace App\Jurisdictions\Contracts;

/**
 * Imports a country's effective-dated statutory data.
 */
interface StatutoryImporter
{
    public function code(): string;

    public function supports(string $countryCode): bool;

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function import(string $countryCode, array $options = []): array;
}
