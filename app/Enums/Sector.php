<?php

namespace App\Enums;

/**
 * The employment sector an installation serves.
 *
 * The installer locks one sector for the whole installation. `Non-Profit` is a
 * legacy value on `companies.type`; it follows Private-sector labour rules, so
 * it normalizes to Private here rather than becoming a third policy surface.
 */
enum Sector: string
{
    case Government = 'Government';

    case Private = 'Private';

    public static function tryFromValue(?string $value): ?self
    {
        return match (strtolower(trim((string) $value))) {
            'government', 'gov', 'public', 'public sector', 'government-owned' => self::Government,
            'private', 'priv', 'non-profit', 'nonprofit', 'non profit', 'ngo' => self::Private,
            default => null,
        };
    }

    public static function fromValue(?string $value): self
    {
        return self::tryFromValue($value) ?? self::Private;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $sector): array => [$sector->value => $sector->label()])
            ->all();
    }

    public function label(): string
    {
        return match ($this) {
            self::Government => 'Government',
            self::Private => 'Private',
        };
    }

    public function companyType(): string
    {
        return match ($this) {
            self::Government => 'Government',
            self::Private => 'Private',
        };
    }

    public function isGovernment(): bool
    {
        return $this === self::Government;
    }

    public function isPrivate(): bool
    {
        return $this === self::Private;
    }
}
