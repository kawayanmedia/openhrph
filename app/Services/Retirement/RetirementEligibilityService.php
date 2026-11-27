<?php

namespace App\Services\Retirement;

use App\Models\Employee;
use App\Models\RetirementPlan;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Age/service eligibility inputs for a retirement plan.
 *
 * The snapshot is advisory: HR must still review supporting records before
 * an application is approved.
 */
class RetirementEligibilityService
{
    /**
     * @return array<string, mixed>
     */
    public function evaluate(Employee $employee, ?RetirementPlan $plan, ?string $asOf = null): array
    {
        $asOf = $asOf !== null ? Carbon::parse($asOf)->startOfDay() : now()->startOfDay();
        $birthDate = $this->birthDate($employee);

        $age = $birthDate === null ? null : (int) floor($birthDate->diffInYears($asOf));
        $serviceYears = $employee->date_hired === null
            ? null
            : round($employee->date_hired->diffInDays($asOf) / 365.25, 2);

        $requiredAge = $plan?->eligibility_age;
        $requiredService = $plan?->minimum_service_years !== null ? (float) $plan->minimum_service_years : null;

        $reasons = [];

        if ($plan === null) {
            $reasons[] = 'No retirement plan is configured for this employee.';
        } else {
            if ($birthDate === null) {
                $reasons[] = 'Birth date is missing.';
            } elseif ($requiredAge !== null && $age < $requiredAge) {
                $reasons[] = "Minimum retirement age is {$requiredAge}.";
            }

            if ($serviceYears === null) {
                $reasons[] = 'Hire date is missing.';
            } elseif ($requiredService !== null && $serviceYears < $requiredService) {
                $reasons[] = "Minimum service is {$requiredService} years.";
            }

            if ($plan->effective_from !== null && $plan->effective_from->gt($asOf)) {
                $reasons[] = 'The retirement plan is not yet effective.';
            }

            if ($plan->effective_until !== null && $plan->effective_until->lt($asOf)) {
                $reasons[] = 'The retirement plan has expired.';
            }

            if (! $plan->is_active) {
                $reasons[] = 'The retirement plan is inactive.';
            }
        }

        return [
            'as_of' => $asOf->toDateString(),
            'plan_id' => $plan?->getKey(),
            'plan_name' => $plan?->name,
            'birth_date' => $birthDate?->toDateString(),
            'date_hired' => $employee->date_hired?->toDateString(),
            'age' => $age,
            'service_years' => $serviceYears,
            'required_age' => $requiredAge,
            'required_service_years' => $requiredService,
            'eligible' => $reasons === [],
            'reasons' => $reasons,
        ];
    }

    protected function birthDate(Employee $employee): ?Carbon
    {
        $value = $employee->getAttribute('birth_date');

        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }
}
