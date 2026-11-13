<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Scopes\FilterScope;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Compensation extends Model
{
    use BelongsToTenant;
    use FilterScope;
    use SoftDeletes;

    // https://www.bir.gov.ph/tax_calculator/wt_calculator.html

    // Taxable
    const TAX_CLASS_BASIC_SALARY = 'BASICSALARY';

    const TAX_CLASS_REPRESENTATION = 'REPRESENTATION';

    const TAX_CLASS_TRANSPORTATION = 'TRANSPORTATION';

    const TAX_CLASS_COLA = 'COLA';

    const TAX_CLASS_FIXEDHOUSING = 'FIXEDHOUSING';

    const TAX_CLASS_OTHER1 = 'OTHER1';

    const TAX_CLASS_OTHER2 = 'OTHER2';

    const TAX_CLASS_COMMISSION = 'COMMISSION';

    const TAX_CLASS_PROFITSHARING = 'PROFITSHARING';

    const TAX_CLASS_FEES = 'FEES';

    const TAX_CLASS_HAZARDPAY = 'HAZARDPAY';

    const TAX_CLASS_OTHSUPP1 = 'OTHSUPP1';

    const TAX_CLASS_OTHSUPP2 = 'OTHSUPP2';

    const TAX_CLASS_NTAXCOMPENSATION = 'NTAXCOMPENSATION';

    const TAX_CLASS_13THMONTH = '13THMONTH'; // max of 90k per year. excess should fall on taxable income

    const TAX_CLASS_FRINGEBENEFIT = 'FRINGEBENEFIT';

    const TAX_CLASS_DEMINIMISBENEFITS = 'DEMINIMISBENEFITS';

    // Not taxable provided that minimum wage earner
    const TAX_CLASS_HOLIDAYPAY = 'HOLIDAYPAY';

    const TAX_CLASS_OVERTIMEPAY = 'OVERTIMEPAY';

    const TAX_CLASS_NIGHT_DIFFERENTIAL = 'NIGHT_SHIFT_DIFFERENTIAL';

    protected $fillable = [
        'code',
        'name',
        'tax_class',
        'group',
    ];

    protected $table = 'compensations';

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {

        static::created(function (Compensation $model) {
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
            get: fn ($value) => $value / 100,
            set: fn ($value) => $value * 100
        );
    }
}
