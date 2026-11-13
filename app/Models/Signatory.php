<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

// use Glorand\Model\Settings\Traits\HasSettingsField;

class Signatory extends Model
{
    use BelongsToTenant;

    // use HasSettingsField;

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::created(function (Signatory $model) {
            set_current_timestamp($model);
        });
    }

    public function offices()
    {
        return $this->hasMany(Office::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
