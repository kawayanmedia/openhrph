<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Government\ServiceRecord;
use App\Scopes\FilterScope;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Employee extends Model implements HasMedia
{
    use BelongsToTenant;
    use FilterScope;
    use HasFactory;
    use InteractsWithMedia;

    const PAYMENT_TYPE_MONTHLY = 'monthly';

    const PAYMENT_TYPE_DAILY = 'daily';

    const PAYMENT_TYPE_MONTHLY_TEXT = 'Monthly Paid';

    const PAYMENT_TYPE_DAILY_TEXT = 'Daily Paid';

    const REGULAR_PERMANENT = 1;

    const CASUAL = 2;

    const CONTRACT_SERVICE = 3;

    const JOB_ORDER = 4;

    const ELECTED_OFFICIAL = 5;

    const TEMPORARY = 6;

    const CONTRACTUAL_PLANTILLA = 7;

    protected $fillable = [
        'company_id',
        'employee_number',
        'office_id',
        'department_id',
        'last_name',
        'first_name',
        'middle_name',
        'position',
        'biometric_id',
        'basic_salary',
        'daily_rate',
        'tin_number',
        'sss_number',
        'philhealth_number',
        'hdmf_number',
        'is_minimum_wage',
        'region',
        'industry',
    ];

    protected $appends = ['fullname', 'office_name'];

    protected $casts = [
        'date_hired' => 'date',
        'date_resigned' => 'date',
    ];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {

        static::created(function (Employee $model) {
            set_current_timestamp($model);
        });
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(130)
            ->height(130);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('main')->singleFile();
        $this->addMediaCollection('my_multi_collection');
    }

    public function user()
    {
        return $this->hasOne(User::class);
    }

    public function nosa()
    {
        return $this->hasOne(Nosa::class);
    }

    public function personalInformation()
    {
        return $this->hasOne(PersonalInformation::class);
    }

    public function familyBackground()
    {
        return $this->hasOne(FamilyBackground::class);
    }

    public function children()
    {
        return $this->hasMany(Children::class);
    }

    public function educations()
    {
        return $this->hasMany(Education::class);
    }

    public function eligibilities()
    {
        return $this->hasMany(Eligibility::class);
    }

    public function workExperiences()
    {
        return $this->hasMany(WorkExperience::class);
    }

    public function voluntaryWorks()
    {
        return $this->hasMany(VoluntaryWork::class);
    }

    public function pdsTrainings()
    {
        return $this->hasMany(Training::class);
    }

    public function otherInfos()
    {
        return $this->hasMany(OtherInfo::class);
    }

    public function questions()
    {
        return $this->hasMany(Question::class);
    }

    public function references()
    {
        return $this->hasMany(Reference::class);
    }

    public function serviceRecords()
    {
        return $this->hasMany(ServiceRecord::class);
    }

    public function immediateHead()
    {
        return $this->belongsTo(ImmediateHead::class, 'immediate_head_employee_id');
    }

    public function office()
    {
        return $this->belongsTo(Office::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function employmentType()
    {
        return $this->belongsTo(EmploymentType::class);
    }

    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    public function payrollGroup()
    {
        return $this->belongsTo(PayrollGroup::class);
    }

    public function schedule()
    {
        return $this->belongsTo(Schedule::class);
    }

    public function bonuses()
    {
        return $this->hasMany(EmployeeBonus::class);
    }

    public function commissions()
    {
        return $this->hasMany(EmployeeCommission::class);
    }

    public function allowances()
    {
        return $this->hasMany(EmployeeAllowance::class);
    }

    public function compensations()
    {
        return $this->hasMany(EmployeeCompensation::class);
    }

    public function deductions()
    {
        return $this->hasMany(EmployeeDeduction::class);
    }

    public function loans()
    {
        return $this->hasMany(EmployeeLoan::class);
    }

    public function activeLoans()
    {
        return $this->hasMany(EmployeeLoan::class)->where('is_active', true);
    }

    public function certificates()
    {
        return $this->hasMany(EmployeeCertificate::class);
    }

    public function trainings()
    {
        return $this->hasMany(EmployeeTraining::class);
    }

    public function timesheets()
    {
        return $this->hasMany(Timesheet::class);
    }

    public function scopeByMultipleIds($query, $employeeIds = null)
    {
        if ($employeeIds) {
            return $query->whereIn('id', $employeeIds);
        }
    }

    public function scopeById($query, $employeeId = null)
    {
        if ($employeeId) {
            return $query->where('id', $employeeId);
        }
    }

    public function scopeByOfficeId($query, $officeId = null)
    {
        if ($officeId) {
            return $query->where('office_id', $officeId);
        }
    }

    public function scopeByOfficeIds($query, $officeIds = null)
    {
        if (! empty($officeIds)) {
            return $query->whereIn('office_id', $officeIds);
        }
    }

    public function basicSalary(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => $value * 100
        );
    }

    public function dailyRate(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => $value * 100
        );
    }

    public function hourlyRate(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => $value * 100
        );
    }

    public function totalVacationLeave(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => $value * 100
        );
    }

    public function totalSickLeave(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => $value * 100
        );
    }

    public function totalPersonalLeave(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => $value * 100
        );
    }

    public function allowance(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => $value * 100
        );
    }

    public function incentive(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => $value * 100
        );
    }

    public function getDailyRateFormatAttribute()
    {
        if ($this->payment_type == static::PAYMENT_TYPE_MONTHLY) {
            return $this->daily_rate;
        }

        return $this->basic_salary;
    }

    public function statuses($status = null, $stringFirst = false)
    {
        $statuses = [
            static::REGULAR_PERMANENT => 'Permanent',
            static::CASUAL => 'Casual',
            static::CONTRACT_SERVICE => 'Contract of Service',
            static::JOB_ORDER => 'Job Order',
            static::ELECTED_OFFICIAL => 'Elected Official',
            static::TEMPORARY => 'Temporary',
            static::CONTRACTUAL_PLANTILLA => 'Contractual Plantilla',
        ];

        if ($stringFirst) {
            $statuses = [
                'Permanent' => static::REGULAR_PERMANENT,
                'Casual' => static::CASUAL,
                'Contract of Service' => static::CONTRACT_SERVICE,
                'Job Order' => static::JOB_ORDER,
                'Elected Official' => static::ELECTED_OFFICIAL,
                'Temporary' => static::TEMPORARY,
                'Contractual Plantilla' => static::REGULAR_PERMANENT,
            ];
        }

        if ($status == null) {
            return collect($statuses);
        }

        return $statuses[$status];
    }

    public function getEmploymentStatusFormatAttribute()
    {
        return $this->employmentType?->name;

        return $this->statuses($this->employment_status);
    }

    public function getImageAttribute()
    {
        return request()->root().'/avatar.png';
    }

    public function getPreviousNosaSalaryGradeAttribute()
    {
        return $this->nosa?->from_salary_grade.'/'.$this->nosa?->from_step;
    }

    public function getCurrentNosaSalaryGradeAttribute()
    {
        if ($this->nosa == null) {
            return $this->salary_grade.'/'.$this->step;
        }

        return $this->nosa?->to_salary_grade.'/'.$this->nosa?->to_step;
    }

    public function getNosaEffectiveDateAttribute()
    {
        return $this->nosa?->effective_date->format('M d, Y');
    }

    public function getNosiDisabledAttribute()
    {
        return ($this->nosa?->from_salary_grade == $this->nosa?->to_salary_grade) &&
            ($this->nosa?->from_step == $this->nosa?->to_step);
    }

    public function getFullnameAttribute()
    {
        return $this->last_name.', '.$this->first_name;

        return $this->first_name.' '.$this->last_name;
    }

    public function getOfficeNameAttribute()
    {
        return $this->office?->name;
    }

    public function getAllowTimesheetEditAttribute()
    {
        // If employee is not active then return
        if (in_array($this->employmentType?->name, ['Resigned', 'Suspended', 'Terminated'])) {
            return false;
        }

        return true;
    }

    public static function employeeList()
    {
        $employees = [];
        foreach (static::lazy() as $employee) {
            $employees[$employee->id] = $employee->fullname;
        }

        return $employees;
    }
}
