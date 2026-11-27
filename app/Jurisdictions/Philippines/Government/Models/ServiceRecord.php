<?php

namespace App\Jurisdictions\Philippines\Government\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use KawayanMedia\Government\Models\ServiceRecord as BaseServiceRecord;

/**
 * CSC service record entry, now tenant-scoped and linked to the appointment
 * and plantilla item that produced it.
 */
class ServiceRecord extends BaseServiceRecord
{
    use BelongsToTenant;

    public const STATUS_PERMANENT = 'permanent';

    protected $fillable = [
        'company_id',
        'employee_id',
        'date_from',
        'date_to',
        'designation',
        'status',
        'salary',
        'office_entity',
        'branch',
        'remarks',
        'lwop',
        'separation_date',
        'separation_cause',
        'plantilla_item_id',
        'appointment_id',
    ];

    protected $casts = [
        'separation_date' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function plantillaItem(): BelongsTo
    {
        return $this->belongsTo(PlantillaItem::class, 'plantilla_item_id');
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(AppointmentRecord::class, 'appointment_id');
    }
}
