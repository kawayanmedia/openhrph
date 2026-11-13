<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Scopes\FilterScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmploymentType extends Model
{
    use BelongsToTenant;
    use FilterScope;
    use HasFactory;
    use SoftDeletes;

    const TYPE_CANDIDATE = 'Candidate';

    const TYPE_INTERN = 'Intern';

    const TYPE_TRAINEE = 'Trainee';

    const TYPE_CONSULTANT = 'Consultant';

    const TYPE_REGULAR_PERMANENT = 'Regular';

    const TYPE_PROBATIONARY = 'Probationary';

    const TYPE_ON_CALL = 'On Call';

    const TYPE_UNDER_AGENCY = 'Under Agency';

    // For government agencies
    const TYPE_CASUAL = 'Casual';

    const TYPE_CONTRACT_SERVICE = 'Contract of Service';

    const TYPE_JOB_ORDER = 'Job Order';

    const TYPE_ELECTED_OFFICIAL = 'Elected Official';

    const TYPE_TEMPORARY = 'Temporary';

    const TYPE_CONTRACTUAL_PLANTILLA = 'Contractual Plantilla';

    const TYPE_SEPARATED = 'Separated';

    protected $fillable = [];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {

        static::created(function (EmploymentType $model) {
            set_current_timestamp($model);
        });
    }

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }
}
