<?php

namespace App\Jurisdictions\Generic;

use App\Jurisdictions\Contracts\StatutoryDriver;

/**
 * Reads `statutory_components` for countries without a dedicated driver.
 */
final class GenericStatutoryDriver implements StatutoryDriver
{
    public function code(): string
    {
        return 'generic';
    }

    public function contributions(array $context): array
    {
        return [
            'driver' => $this->code(),
            'components' => array_values((array) ($context['components'] ?? [])),
        ];
    }

    public function tax(array $context): array
    {
        $components = array_values(array_filter(
            (array) ($context['components'] ?? []),
            static fn (mixed $component): bool => is_array($component)
                && strtolower((string) ($component['category'] ?? '')) === 'tax',
        ));

        return ['driver' => $this->code(), 'components' => $components];
    }

    public function supports(string $runType): bool
    {
        return true;
    }
}
