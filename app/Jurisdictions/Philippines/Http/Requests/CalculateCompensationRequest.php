<?php

namespace App\Jurisdictions\Philippines\Http\Requests;

use App\Jurisdictions\Philippines\Statutory\Bir\TaxPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The compensation lines shared by the withholding tax and take-home pay
 * calculators: one payroll period of regular and supplementary compensation.
 */
abstract class CalculateCompensationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $value = $this->input('period');

        if (! is_string($value)) {
            return;
        }

        $period = TaxPeriod::fromString($value);

        if ($period !== null) {
            $this->merge(['period' => $period->value]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'period' => ['required', Rule::enum(TaxPeriod::class)],
            'basic_salary' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'representation_allowance' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'transportation_allowance' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'cost_of_living_allowance' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'fixed_housing_allowance' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'other_taxable_regular' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'commission' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'profit_sharing' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'fees' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'thirteenth_month' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'hazard_pay' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'overtime_pay' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'other_taxable_supplementary' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
        ];
    }
}
