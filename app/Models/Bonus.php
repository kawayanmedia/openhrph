<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Scopes\FilterScope;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bonus extends Model
{
    use BelongsToTenant;
    use FilterScope;
    use SoftDeletes;

    const AMOUNT_BASIS_FIXED = 'Fixed';

    const AMOUNT_BASIS_SALARY_BASED = 'Salary Based';

    const FREQUENCY_ONE_TIME = 'One-Time';

    const FREQUENCY_PERIODIC = 'Periodic';

    const BONUS_13TH_MONTH = '13th Month Pay';

    protected $table = 'bonuses';

    protected $fillable = [];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {

        static::created(function (Bonus $model) {
            set_current_timestamp($model);
        });
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function employeeBonuses()
    {
        return $this->belongsTo(EmployeeBonus::class);
    }

    public function maximumNonTax(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => $value * 100
        );
    }
}
