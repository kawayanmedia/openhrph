<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Scopes\FilterScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PayrollRemittance extends Model
{
    use BelongsToTenant;
    use FilterScope;
    use HasFactory;
    use SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_FILED = 'filed';

    public const STATUS_PAID = 'paid';

    public const STATUS_VOID = 'void';

    protected $table = 'payroll_remittances';

    protected $fillable = [
        'company_id',
        'payroll_id',
        'agency',
        'period_from',
        'period_to',
        'due_date',
        'employee_amount',
        'employer_amount',
        'total_amount',
        'status',
        'reference_number',
        'filed_at',
        'paid_at',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'period_from' => 'date',
        'period_to' => 'date',
        'due_date' => 'date',
        'employee_amount' => 'integer',
        'employer_amount' => 'integer',
        'total_amount' => 'integer',
        'filed_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function payroll()
    {
        return $this->belongsTo(Payroll::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_FILED => 'Filed',
            self::STATUS_PAID => 'Paid',
            self::STATUS_VOID => 'Void',
            default => 'Pending',
        };
    }
}
