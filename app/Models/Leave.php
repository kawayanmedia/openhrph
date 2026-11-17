<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Scopes\FilterScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Leave extends Model
{
    use BelongsToTenant;
    use FilterScope;
    use SoftDeletes;

    protected $fillable = [];

    protected $table = 'leave_types';

    protected $casts = ['approved_date' => 'date'];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::created(function (Leave $model) {
            set_current_timestamp($model);
        });
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
