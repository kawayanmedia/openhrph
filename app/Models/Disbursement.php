<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Scopes\FilterScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Disbursement extends Model
{
    use BelongsToTenant;
    use FilterScope;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'account_number',
    ];

    protected $table = 'companies_banks';

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::created(function (Disbursement $model) {
            set_current_timestamp($model);
        });
    }

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }
}
