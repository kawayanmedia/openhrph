<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Scopes\FilterScope;
use Illuminate\Database\Eloquent\Model;

class ImmediateHead extends Model
{
    use BelongsToTenant;
    use FilterScope;

    protected $table = 'employees';

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::created(function (EmployeePlantilla $model) {
            set_current_timestamp($model);
        });
    }

    public function user()
    {
        return $this->hasOne(User::class, 'employee_id');
    }
}
