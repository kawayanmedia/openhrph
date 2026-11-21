<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * A country identifier for an employee (TIN, SSS, GSIS, NIS, …).
 */
class EmployeeIdentifier extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'company_id',
        'employee_id',
        'country_code',
        'type',
        'number',
        'issued_at',
        'expires_at',
        'metadata',
    ];

    protected $casts = [
        'issued_at' => 'date',
        'expires_at' => 'date',
        'metadata' => 'array',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
