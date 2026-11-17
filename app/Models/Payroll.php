<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Scopes\FilterScope;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payroll extends Model
{
    use BelongsToTenant;
    use FilterScope;
    use HasFactory;
    use SoftDeletes;

    const STATUS_OPEN = 'open';

    const STATUS_CLOSED = 'closed';

    const STATUS_RUNNING = 'running';

    const STATUS_PROCESSED = 'processed';

    const STATUS_APPROVED = 'approved';

    const STATUS_CLOSED_STATUS = 'closed';

    const TYPE_REGULAR = 'regular';

    const TYPE_THIRTEENTH_MONTH = 'thirteenth_month';

    const TYPE_FINAL_PAY = 'final_pay';

    const TYPE_ANNUAL_ADJUSTMENT = 'annual_adjustment';

    const PAY_SCHEDULE_FIRSTPAY = 'first_pay';

    const PAY_SCHEDULE_LASTPAY = 'last_pay';

    protected $table = 'payrolls';

    protected $fillable = [];

    protected $casts = [
        'payroll_date' => 'date',
        'date_from' => 'date',
        'date_to' => 'date',
        'processed_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'override_at' => 'datetime',
        'approved_at' => 'datetime',
        'reopened_at' => 'datetime',
        'meta' => 'array',
        'warnings' => 'array',
    ];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::created(function (Payroll $model) {
            set_current_timestamp($model);
        });
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function payrollGroup()
    {
        return $this->belongsTo(PayrollGroup::class);
    }

    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function overrideBy()
    {
        return $this->belongsTo(User::class, 'override_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function reopenedBy()
    {
        return $this->belongsTo(User::class, 'reopened_by');
    }

    public function payrollDetails()
    {
        return $this->hasMany(PayrollDetail::class);
    }

    public function grosssAmount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => $value * 100
        );
    }

    public function netAmount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => $value * 100
        );
    }

    public function getIsRunningAttribute(): bool
    {
        return $this->status == static::STATUS_RUNNING;
    }

    public function getIsOpenAttribute(): bool
    {
        return $this->status == static::STATUS_OPEN;
    }

    public function getIsProcessedAttribute(): bool
    {
        return $this->status == static::STATUS_PROCESSED;
    }

    public function getIsClosedAttribute(): bool
    {
        return $this->status == static::STATUS_CLOSED;
    }

    public function getCoveredDatesAttribute()
    {
        return $this->date_from->format('m/d/Y').' - '.$this->date_to->format('m/d/Y');
    }

    public static function semiMonthlyPeriods(PayrollGroup $payrollGroup)
    {
        // Generate dates from 3 months before and
        // 3 months after the current system date
        $date = now();
        $dateTo = now();

        $period = CarbonPeriod::create($date->subMonths(3), '1 month', $dateTo->addMonths(3));

        $months = [];

        foreach ($period as $dt) {
            $months[] = $dt->format('Y-m');
        }

        // Once we get the months then we get the date of
        // payroll on every month

        $periods = [];

        foreach ($months as $month) {
            // Check for non working day
            $periods[$month.'-'.$payrollGroup->first_pay_date.'-1'] = Carbon::createFromFormat('Y-m-d', $month.'-'.$payrollGroup->first_pay_date)->format('M d, Y');
            $periods[$month.'-'.$payrollGroup->second_pay_date.'-2'] = Carbon::createFromFormat('Y-m-d', $month.'-'.$payrollGroup->second_pay_date)->format('M d, Y');
        }

        $payrolls = Payroll::query()
            ->where('payroll_group_id', $payrollGroup->id)
            ->whereBetween('payroll_date', [
                now()->subMonths(3)->format('Y-m-d'),
                now()->addMonths(3)->format('Y-m-d'),
            ])
            ->pluck('payroll_date');

        // Check if the date is in the payroll already
        // then we remove it from array
        foreach ($payrolls->toArray() as $payroll) {
            unset($periods[$payroll->format('Y-m-d')]);
        }

        return $periods;
    }

    public static function monthlyPeriods(PayrollGroup $payrollGroup)
    {
        // Generate dates from 3 months before and
        // 3 months after the current system date
        $date = now();
        $dateTo = now();

        $period = CarbonPeriod::create($date->subMonths(3), '1 month', $dateTo->addMonths(3));

        $months = [];

        foreach ($period as $dt) {
            $months[] = $dt->format('Y-m');
        }

        // Once we get the months then we get the date of
        // payroll on every month

        $periods = [];

        foreach ($months as $month) {
            $periods[$month.'-'.$payrollGroup->first_pay_date] = Carbon::createFromFormat('Y-m-d', $month.'-'.$payrollGroup->first_pay_date)->format('M d, Y');
        }

        $payrolls = Payroll::query()
            ->where('payroll_group_id', $payrollGroup->id)
            ->whereBetween('payroll_date', [
                now()->subMonths(3)->format('Y-m-d'),
                now()->addMonths(3)->format('Y-m-d'),
            ])
            ->pluck('payroll_date');

        // Check if the date is in the payroll already
        // then we remove it from array
        foreach ($payrolls->toArray() as $payroll) {
            unset($periods[$payroll->format('Y-m-d')]);
        }

        return $periods;
    }
}
