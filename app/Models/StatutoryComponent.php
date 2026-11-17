<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A statutory deduction for a country that is not handled by a built-in
 * driver. `company_id` null means the instalation/country default; a row with a
 * company id overrides that default for one company.
 */
class StatutoryComponent extends Model
{
    protected $guarded = [];

    protected $casts = [
        'employee_rate' => 'float',
        'employer_rate' => 'float',
        'is_active' => 'boolean',
        'mandatory' => 'boolean',
        'reduces_taxable_income' => 'boolean',
        'employer_tax_deductible' => 'boolean',
        'metadata' => 'array',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    /**
     * @return HasMany<StatutoryComponentBand, self>
     */
    public function bands(): HasMany
    {
        return $this->hasMany(StatutoryComponentBand::class)->orderBy('from_centavos');
    }

    /**
     * Amounts are stored in centavos but edited in major currency units.
     */
    public function employeeAmount(): Attribute
    {
        return Attribute::make(
            get: fn ($value, array $attributes) => isset($attributes['employee_amount_centavos']) ? $attributes['employee_amount_centavos'] / 100 : null,
            set: fn ($value) => ['employee_amount_centavos' => $value === null || $value === '' ? null : (int) round((float) $value * 100)],
        );
    }

    public function employerAmount(): Attribute
    {
        return Attribute::make(
            get: fn ($value, array $attributes) => isset($attributes['employer_amount_centavos']) ? $attributes['employer_amount_centavos'] / 100 : null,
            set: fn ($value) => ['employer_amount_centavos' => $value === null || $value === '' ? null : (int) round((float) $value * 100)],
        );
    }

    public function minBase(): Attribute
    {
        return Attribute::make(
            get: fn ($value, array $attributes) => isset($attributes['min_base_centavos']) ? $attributes['min_base_centavos'] / 100 : null,
            set: fn ($value) => ['min_base_centavos' => $value === null || $value === '' ? null : (int) round((float) $value * 100)],
        );
    }

    public function maxBase(): Attribute
    {
        return Attribute::make(
            get: fn ($value, array $attributes) => isset($attributes['max_base_centavos']) ? $attributes['max_base_centavos'] / 100 : null,
            set: fn ($value) => ['max_base_centavos' => $value === null || $value === '' ? null : (int) round((float) $value * 100)],
        );
    }

    public function maxEmployee(): Attribute
    {
        return Attribute::make(
            get: fn ($value, array $attributes) => isset($attributes['max_employee_centavos']) ? $attributes['max_employee_centavos'] / 100 : null,
            set: fn ($value) => ['max_employee_centavos' => $value === null || $value === '' ? null : (int) round((float) $value * 100)],
        );
    }

    public function maxEmployer(): Attribute
    {
        return Attribute::make(
            get: fn ($value, array $attributes) => isset($attributes['max_employer_centavos']) ? $attributes['max_employer_centavos'] / 100 : null,
            set: fn ($value) => ['max_employer_centavos' => $value === null || $value === '' ? null : (int) round((float) $value * 100)],
        );
    }

    public function employeeRatePercent(): Attribute
    {
        return Attribute::make(
            get: fn ($value, array $attributes) => isset($attributes['employee_rate']) ? (float) $attributes['employee_rate'] * 100 : null,
            set: fn ($value) => ['employee_rate' => $value === null || $value === '' ? null : (float) $value / 100],
        );
    }

    public function employerRatePercent(): Attribute
    {
        return Attribute::make(
            get: fn ($value, array $attributes) => isset($attributes['employer_rate']) ? (float) $attributes['employer_rate'] * 100 : null,
            set: fn ($value) => ['employer_rate' => $value === null || $value === '' ? null : (float) $value / 100],
        );
    }

    public function scopeEffectiveOn(Builder $query, mixed $date): Builder
    {
        return $query
            ->where(fn (Builder $query) => $query->whereNull('effective_from')->orWhere('effective_from', '<=', $date))
            ->where(fn (Builder $query) => $query->whereNull('effective_to')->orWhere('effective_to', '>=', $date));
    }

    public function scopeForSector(Builder $query, ?string $sector): Builder
    {
        $sector = strtolower(trim((string) $sector));

        return $query->where(function (Builder $query) use ($sector): void {
            $query->where('sector', 'any')->orWhere('sector', '');

            if ($sector !== '') {
                $query->orWhere('sector', $sector);
            }
        });
    }
}
