<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Scopes\FilterScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shift extends Model
{
    use BelongsToTenant;
    use FilterScope;
    use SoftDeletes;

    protected $fillable = [];

    protected $casts = ['date' => 'date'];

    protected $table = 'schedules';

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::created(function (Shift $model) {
            set_current_timestamp($model);
        });
    }

    // public function company()
    // {
    //     return $this->belongsTo(Company::class);
    // }

    // public function office()
    // {
    //     return $this->belongsTo(Office::class);
    // }
}
