<?php

namespace App\Livewire\Admin\Filters;

use App\Services\Payroll\SocialInsuranceResolver;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Narrows the employee list to the records a payroll checklist step asks for.
 *
 * The rules mirror the payroll readiness checklist, so the Getting started
 * links land on exactly the employees that still need attention.
 */
class EmployeeReadiness extends Filter
{
    public string $name = 'Needs attention';

    public const PAY_DETAILS = 'pay-details';

    public const GOVERNMENT_IDS = 'government-ids';

    public const TAX_STATUS = 'tax-status';

    public const UNASSIGNED = 'unassigned';

    public function apply(Request $request, Builder $query, mixed $value): Builder
    {
        return match ((string) $value) {
            self::PAY_DETAILS => $query->where(function (Builder $query): void {
                $query
                    ->where(function (Builder $query): void {
                        $query->where($this->missingAmount('basic_salary'))
                            ->where($this->missingAmount('daily_rate'));
                    })
                    ->orWhere($this->blank('payment_type'));
            }),
            self::GOVERNMENT_IDS => $query->where(function (Builder $query): void {
                $query->orWhere($this->blank(SocialInsuranceResolver::current()->identifierField()));
                $query->orWhere($this->blank('philhealth_number'));
                $query->orWhere($this->blank('hdmf_number'));
                $query->orWhere($this->blank('tin_number'));
            }),
            self::TAX_STATUS => $query->where($this->blank('tax_status')),
            self::UNASSIGNED => $query->whereNull('payroll_group_id'),
            default => $query,
        };
    }

    public function options(Request $request): array
    {
        return [
            self::PAY_DETAILS => 'Missing basic salary or daily rate',
            self::GOVERNMENT_IDS => 'Missing government IDs',
            self::TAX_STATUS => 'Missing tax status',
            self::UNASSIGNED => 'Not in a payroll group',
        ];
    }

    /**
     * A column PHP treats as empty (`filled()` is false).
     */
    private function blank(string $column): Closure
    {
        return fn (Builder $query) => $query->whereNull($column)->orWhere($column, '');
    }

    /**
     * A rate column that is unset or zero.
     */
    private function missingAmount(string $column): Closure
    {
        return fn (Builder $query) => $query->whereNull($column)->orWhere($column, '<=', 0);
    }
}
