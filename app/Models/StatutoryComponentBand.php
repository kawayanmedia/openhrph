<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StatutoryComponentBand extends Model
{
    protected $guarded = [];

    protected $casts = [
        'employee_rate' => 'float',
        'employer_rate' => 'float',
    ];

    public function fromAmount(): Attribute
    {
        return Attribute::make(
            get: fn ($value, array $attributes) => ($attributes['from_centavos'] ?? 0) / 100,
            set: fn ($value) => ['from_centavos' => $value === null || $value === '' ? 0 : (int) round((float) $value * 100)],
        );
    }

    public function toAmount(): Attribute
    {
        return Attribute::make(
            get: fn ($value, array $attributes) => isset($attributes['to_centavos']) ? $attributes['to_centavos'] / 100 : null,
            set: fn ($value) => ['to_centavos' => $value === null || $value === '' ? null : (int) round((float) $value * 100)],
        );
    }

    public function baseAmount(): Attribute
    {
        return Attribute::make(
            get: fn ($value, array $attributes) => ($attributes['base_centavos'] ?? 0) / 100,
            set: fn ($value) => ['base_centavos' => $value === null || $value === '' ? 0 : (int) round((float) $value * 100)],
        );
    }

    public function employeeAmount(): Attribute
    {
        return Attribute::make(
            get: fn ($value, array $attributes) => ($attributes['employee_amount_centavos'] ?? 0) / 100,
            set: fn ($value) => ['employee_amount_centavos' => $value === null || $value === '' ? 0 : (int) round((float) $value * 100)],
        );
    }

    public function employerAmount(): Attribute
    {
        return Attribute::make(
            get: fn ($value, array $attributes) => ($attributes['employer_amount_centavos'] ?? 0) / 100,
            set: fn ($value) => ['employer_amount_centavos' => $value === null || $value === '' ? 0 : (int) round((float) $value * 100)],
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

    /**
     * @return BelongsTo<StatutoryComponent, self>
     */
    public function component(): BelongsTo
    {
        return $this->belongsTo(StatutoryComponent::class, 'statutory_component_id');
    }
}
