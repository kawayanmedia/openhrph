<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Scopes\FilterScope;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Deduction extends Model
{
    use BelongsToTenant;
    use FilterScope;
    use SoftDeletes;

    protected $fillable = [];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {

        static::created(function (Deduction $model) {
            set_current_timestamp($model);
        });
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function amount(): Attribute
    {
        return Attribute::make(
            get: fn ($value, array $attributes) => ($attributes['amount_centavos'] ?? 0) / 100,
            set: fn ($value) => ['amount_centavos' => $value === null || $value === '' ? 0 : (int) round((float) $value * 100)],
        );
    }

    public function ratePercent(): Attribute
    {
        return Attribute::make(
            get: fn ($value, array $attributes) => isset($attributes['rate']) ? (float) $attributes['rate'] * 100 : null,
            set: fn ($value) => ['rate' => $value === null || $value === '' ? null : (float) $value / 100],
        );
    }
}
