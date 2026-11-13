<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Scopes\FilterScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Office extends Model
{
    use BelongsToTenant;
    use FilterScope;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = ['company_id', 'name', 'code', 'account_number', 'region', 'industry'];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {

        static::created(function (Office $model) {
            set_current_timestamp($model);
        });
    }

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }

    public static function officesList()
    {
        return Office::orderBy('name')->pluck('name', 'id')->toArray();
    }
}
