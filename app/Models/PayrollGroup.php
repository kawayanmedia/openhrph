<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Scopes\FilterScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PayrollGroup extends Model
{
    use BelongsToTenant;
    use FilterScope;
    use HasFactory;
    use SoftDeletes;

    const FREQUENCY_MONTHLY = 'monthly';

    const FREQUENCY_SEMI_MONTHLY = 'semi-monthly';

    const FREQUENCY_WEEKLY = 'weekly';

    const FREQUENCY_DAILY = 'daily';

    const PAY_SCHEDULE_FIRSTPAY = 'first_pay';

    const PAY_SCHEDULE_LASTPAY = 'last_pay';

    const PAY_SCHEDULE_EVERYPAY = 'every_pay';

    const NUMBER_WORKING_DAYS_MONFRI = 261;

    const NUMBER_WORKING_DAYS_MONSAT = 313;

    const NON_WORKING_DATE_BEFORE = 'pay_date_before';

    const NON_WORKING_DATE_AFTER = 'pay_date_after';

    const NON_WORKING_DATE_DATE = 'pay_date';

    const PAY_SCHEDULE_FIRSTPAY_TEXT = 'First Pay';

    const PAY_SCHEDULE_LASTPAY_TEXT = 'Last Pay';

    const PAY_SCHEDULE_EVERYPAY_TEXT = 'Every Pay';

    protected $table = 'payroll_groups';

    protected $fillable = [
        'company_id',
        'name',
    ];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::created(function (PayrollGroup $model) {
            set_current_timestamp($model);
        });
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function payrolls()
    {
        return $this->hasMany(Payroll::class);
    }

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }

    public function payrollDates()
    {
        return $this->hasMany(PayrollDate::class);
    }

    public function getSssIsEveryPayAttribute()
    {
        return $this->sss_schedule === PayrollGroup::PAY_SCHEDULE_EVERYPAY;
    }

    public function getSssIsFirstPayAttribute()
    {
        return $this->sss_schedule === PayrollGroup::PAY_SCHEDULE_FIRSTPAY;
    }

    public function getSssIsLastPayAttribute()
    {
        return $this->sss_schedule === PayrollGroup::PAY_SCHEDULE_LASTPAY;
    }

    public function getSssScheduleAttribute($value)
    {
        if ($this->frequency == static::FREQUENCY_SEMI_MONTHLY) {
            // Get the value from settings
            $schedule = get_setting('sss_schedule');

            if ($schedule != null and $schedule != '') {
                return $schedule;
            } else {
                return static::PAY_SCHEDULE_EVERYPAY;
            }
        }

        return static::PAY_SCHEDULE_EVERYPAY;
    }

    public function getPhilHealthScheduleAttribute($value)
    {
        if ($this->frequency == static::FREQUENCY_SEMI_MONTHLY) {
            // Get the value from settings
            $schedule = get_setting('phil_health_schedule');

            if ($schedule != null and $schedule != '') {
                return $schedule;
            } else {
                return static::PAY_SCHEDULE_EVERYPAY;
            }
        }

        return static::PAY_SCHEDULE_EVERYPAY;
    }

    public function getHdmfScheduleAttribute($value)
    {
        if ($this->frequency == static::FREQUENCY_SEMI_MONTHLY) {
            // Get the value from settings
            $schedule = get_setting('hdmf_schedule');

            if ($schedule != null and $schedule != '') {
                return $schedule;
            } else {
                return static::PAY_SCHEDULE_EVERYPAY;
            }
        }

        return static::PAY_SCHEDULE_EVERYPAY;
    }

    public function updateData($model, $request)
    {
        // If monthly then lets make second_cut_off and second_pay_date null
        if ($request->frequency == static::FREQUENCY_MONTHLY) {
            $model->second_cut_off = null;
            $model->second_pay_date = null;
            $model->save();
        }
    }
}
