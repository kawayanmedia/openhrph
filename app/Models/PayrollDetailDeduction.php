<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One country-neutral deduction line produced by a payroll run.
 */
class PayrollDetailDeduction extends Model
{
    protected $guarded = [];

    protected $casts = [
        'reduces_taxable_income' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * @return BelongsTo<PayrollDetail, self>
     */
    public function payrollDetail(): BelongsTo
    {
        return $this->belongsTo(PayrollDetail::class);
    }

    /**
     * @return BelongsTo<Employee, self>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
