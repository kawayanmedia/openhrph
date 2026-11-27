<?php

namespace App\Jurisdictions\Philippines\Government\Models\Concerns;

use App\Jurisdictions\Philippines\Government\Models\Children;
use App\Jurisdictions\Philippines\Government\Models\Education;
use App\Jurisdictions\Philippines\Government\Models\Eligibility;
use App\Jurisdictions\Philippines\Government\Models\FamilyBackground;
use App\Jurisdictions\Philippines\Government\Models\OtherInfo;
use App\Jurisdictions\Philippines\Government\Models\PersonalInformation;
use App\Jurisdictions\Philippines\Government\Models\Question;
use App\Jurisdictions\Philippines\Government\Models\Reference;
use App\Jurisdictions\Philippines\Government\Models\ServiceRecord;
use App\Jurisdictions\Philippines\Government\Models\Training;
use App\Jurisdictions\Philippines\Government\Models\VoluntaryWork;
use App\Jurisdictions\Philippines\Government\Models\WorkExperience;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use KawayanMedia\Government\Models\Nosa;

/**
 * CS Form 212 (Personal Data Sheet) and service-record relations.
 *
 * The PDS tables key on `employee_id`, so the relations stay on the employee
 * record. Composing them through a trait keeps public-sector records out of the
 * core Employee model body while preserving `$employee->personalInformation`
 * and friends for the government screens.
 */
trait HasPersonalDataSheet
{
    public function nosa(): HasOne
    {
        return $this->hasOne(Nosa::class);
    }

    public function personalInformation(): HasOne
    {
        return $this->hasOne(PersonalInformation::class);
    }

    public function familyBackground(): HasOne
    {
        return $this->hasOne(FamilyBackground::class);
    }

    public function children(): HasMany
    {
        return $this->hasMany(Children::class);
    }

    public function educations(): HasMany
    {
        return $this->hasMany(Education::class);
    }

    public function eligibilities(): HasMany
    {
        return $this->hasMany(Eligibility::class);
    }

    public function workExperiences(): HasMany
    {
        return $this->hasMany(WorkExperience::class);
    }

    public function voluntaryWorks(): HasMany
    {
        return $this->hasMany(VoluntaryWork::class);
    }

    public function pdsTrainings(): HasMany
    {
        return $this->hasMany(Training::class);
    }

    public function otherInfos(): HasMany
    {
        return $this->hasMany(OtherInfo::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    public function references(): HasMany
    {
        return $this->hasMany(Reference::class);
    }

    public function serviceRecords(): HasMany
    {
        return $this->hasMany(ServiceRecord::class);
    }

    public function getPreviousNosaSalaryGradeAttribute(): ?string
    {
        return $this->nosa?->from_salary_grade.'/'.$this->nosa?->from_step;
    }

    public function getCurrentNosaSalaryGradeAttribute(): ?string
    {
        if ($this->nosa === null) {
            return $this->salary_grade.'/'.$this->step;
        }

        return $this->nosa->to_salary_grade.'/'.$this->nosa->to_step;
    }

    public function getNosaEffectiveDateAttribute(): ?string
    {
        return $this->nosa?->effective_date?->format('M d, Y');
    }

    public function getNosiDisabledAttribute(): bool
    {
        return ($this->nosa?->from_salary_grade == $this->nosa?->to_salary_grade)
            && ($this->nosa?->from_step == $this->nosa?->to_step);
    }
}
