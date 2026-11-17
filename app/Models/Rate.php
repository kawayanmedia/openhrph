<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Scopes\FilterScope;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Rate extends Model
{
    use BelongsToTenant;
    use FilterScope;
    use SoftDeletes;

    const PAID_LEAVE = 'pl';

    const UNPAID_LEAVE = 'upl';

    const NO_LOG = 'no_log';

    protected $table = 'rates';

    protected $fillable = [];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::created(function (Rate $model) {
            set_current_timestamp($model);
        });
    }

    public function regular(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => $value * 100
        );
    }

    public function ot(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => $value * 100
        );
    }

    public function nt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => $value * 100
        );
    }

    public function ntOt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 1000,
            set: fn ($value) => $value * 1000
        );
    }
}
