<?php

namespace App\Jurisdictions\Support;

use App\Jurisdictions\Contracts\Jurisdiction;

/**
 * In-memory registry of country plugins.
 */
final class JurisdictionRegistry
{
    /**
     * @var array<string, Jurisdiction>
     */
    private array $jurisdictions = [];

    public function register(Jurisdiction $jurisdiction): void
    {
        $this->jurisdictions[strtoupper($jurisdiction->code())] = $jurisdiction;
    }

    public function get(string $code): ?Jurisdiction
    {
        return $this->jurisdictions[strtoupper(trim($code))] ?? null;
    }

    public function has(string $code): bool
    {
        return $this->get($code) !== null;
    }

    /**
     * @return array<string, Jurisdiction>
     */
    public function all(): array
    {
        return $this->jurisdictions;
    }

    /**
     * @return array<int, string>
     */
    public function codes(): array
    {
        return array_keys($this->jurisdictions);
    }

    public function flush(): void
    {
        $this->jurisdictions = [];
    }
}
