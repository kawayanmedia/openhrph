<?php

namespace App\Jurisdictions\Support;

use App\Jurisdictions\Contracts\Jurisdiction;
use App\Jurisdictions\Generic\GenericJurisdiction;
use App\Support\Installation\Installation;

/**
 * Resolves the active country plugin.
 *
 * Resolution order:
 *  1. the installation country written by `openhr:install`;
 *  2. the configured jurisdiction default.
 *
 * An unknown code always resolves to the generic jurisdiction for that code.
 * It never falls back to the Philippine driver.
 */
final class JurisdictionManager
{
    /**
     * @var array<string, Jurisdiction>
     */
    private array $generic = [];

    public function __construct(private readonly JurisdictionRegistry $registry) {}

    public function current(): Jurisdiction
    {
        return $this->for(Installation::countryCode());
    }

    public function for(?string $code = null): Jurisdiction
    {
        $code = strtoupper(trim((string) ($code ?? Installation::countryCode())));

        if ($code === '') {
            $code = strtoupper((string) config('jurisdictions.default', 'PH'));
        }

        if ($jurisdiction = $this->registry->get($code)) {
            return $jurisdiction;
        }

        return $this->generic[$code] ??= GenericJurisdiction::fromConfig($code);
    }

    public function register(Jurisdiction $jurisdiction): void
    {
        $this->registry->register($jurisdiction);
    }

    /**
     * @return array<string, Jurisdiction>
     */
    public function all(): array
    {
        return $this->registry->all();
    }

    public function reset(): void
    {
        $this->generic = [];
    }
}
