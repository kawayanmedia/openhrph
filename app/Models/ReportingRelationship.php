<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportingRelationship extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const TYPE_SUPERVISOR = 'supervisor';

    public const TYPE_MANAGER = 'manager';

    public const TYPE_HEAD = 'head';

    protected $fillable = [
        'company_id',
        'employee_id',
        'supervisor_employee_id',
        'relationship_type',
        'effective_from',
        'effective_until',
        'notes',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_until' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'supervisor_employee_id');
    }
}
