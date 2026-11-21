<?php

namespace App\Sectors\Support;

use App\Enums\Sector;
use Illuminate\Support\Arr;

/**
 * A typed view over one entry in config/sectors.php.
 *
 * The profile is intentionally read-only: every rule is declarative, and the
 * effective-dated statutory tables remain the source of truth for rates.
 */
final class SectorProfile
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public readonly Sector $sector,
        private readonly array $attributes,
    ) {}

    /**
     * @param  array<string, mixed>|null  $attributes
     */
    public static function fromConfig(Sector $sector, ?array $attributes = null): self
    {
        $attributes ??= config('sectors.profiles.'.$sector->value, []);

        return new self($sector, is_array($attributes) ? $attributes : []);
    }

    public function label(): string
    {
        return (string) $this->get('label', $this->sector->label());
    }

    public function description(): string
    {
        return (string) $this->get('description', '');
    }

    public function companyType(): string
    {
        return (string) $this->get('company_type', $this->sector->companyType());
    }

    /**
     * @return array<int, string>
     */
    public function aliases(): array
    {
        return array_values(array_map('strval', (array) $this->get('aliases', [])));
    }

    /**
     * @return array<string, mixed>
     */
    public function statutory(): array
    {
        return $this->array('statutory');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function employmentTypes(): array
    {
        return $this->rows('employment_types');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function leaveTypes(): array
    {
        return $this->rows('leave.types');
    }

    /**
     * @return array<string, mixed>
     */
    public function leaveRules(): array
    {
        return $this->array('leave.rules');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function compensationElements(): array
    {
        return $this->rows('compensation.elements');
    }

    /**
     * @return array<string, mixed>
     */
    public function bonusScheme(): array
    {
        return $this->array('bonus');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function documents(): array
    {
        return $this->rows('documents');
    }

    /**
     * @return array<string, mixed>
     */
    public function reports(): array
    {
        return $this->array('reports');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->array('rules');
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return $this->array('defaults');
    }

    /**
     * @return array<string, bool>
     */
    public function features(): array
    {
        return array_map(
            static fn (mixed $value): bool => (bool) $value,
            $this->array('features'),
        );
    }

    public function supports(string $feature): bool
    {
        return (bool) $this->get('features.'.$feature, false);
    }

    public function showsMenu(string $key): bool
    {
        // `menu.show` documents the sector's own screens; `menu.hide` is the
        // exclusion list the navigation uses. Shared keys stay visible unless
        // they are explicitly hidden.
        $hide = array_map('strval', (array) $this->get('menu.hide', []));

        return ! in_array($key, $hide, true);
    }

    public function get(string $path, mixed $default = null): mixed
    {
        return Arr::get($this->attributes, $path, $default);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['sector' => $this->sector->value] + $this->attributes;
    }

    /**
     * @return array<string, mixed>
     */
    private function array(string $path): array
    {
        $value = $this->get($path, []);

        return is_array($value) ? $value : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rows(string $path): array
    {
        return array_values(array_filter(
            $this->array($path),
            static fn (mixed $row): bool => is_array($row),
        ));
    }
}
