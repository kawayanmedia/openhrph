<?php

namespace App\Jurisdictions\Philippines\Government\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrmpsbMember extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const ROLE_CHAIR = 'chair';

    public const ROLE_MEMBER = 'member';

    public const ROLE_SECRETARIAT = 'secretariat';

    protected $fillable = [
        'company_id',
        'committee_id',
        'employee_id',
        'user_id',
        'external_name',
        'role',
        'effective_from',
        'effective_until',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_until' => 'date',
    ];

    public function committee(): BelongsTo
    {
        return $this->belongsTo(HrmpsbCommittee::class, 'committee_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
