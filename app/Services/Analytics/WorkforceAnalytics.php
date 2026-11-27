<?php

namespace App\Services\Analytics;

use App\Models\AttendanceCorrection;
use App\Models\ComplianceRule;
use App\Models\Employee;
use App\Models\EmployeeCertification;
use App\Models\ExternalClaimTracking;
use App\Models\PerformanceReview;
use App\Models\RetirementCase;
use App\Models\SeparationCase;
use App\Models\TrainingEnrollment;
use Illuminate\Support\Carbon;

/**
 * Read-only workforce metrics. Queries are tenant-scoped by the models.
 */
class WorkforceAnalytics
{
    /**
     * @return array<string, mixed>
     */
    public function summary(?Carbon $asOf = null): array
    {
        $asOf = $asOf?->copy() ?? now();
        $yearStart = $asOf->copy()->startOfYear()->toDateString();

        $activeHeadcount = Employee::query()
            ->whereNotIn('lifecycle_status', [Employee::LIFECYCLE_SEPARATED, Employee::LIFECYCLE_ARCHIVED])
            ->count();

        $newHires = Employee::query()
            ->whereDate('date_hired', '>=', $asOf->copy()->subDays(90)->toDateString())
            ->count();

        $separations = SeparationCase::query()
            ->where(function ($query) use ($yearStart, $asOf): void {
                $query->whereDate('effective_date', '>=', $yearStart)
                    ->orWhere(function ($query) use ($yearStart, $asOf): void {
                        $query->whereNull('effective_date')
                            ->whereDate('proposed_effective_date', '>=', $yearStart)
                            ->whereDate('proposed_effective_date', '<=', $asOf->toDateString());
                    });
            })
            ->count();

        $pendingRetirements = RetirementCase::query()
            ->whereIn('status', [RetirementCase::STATUS_APPLIED, RetirementCase::STATUS_APPROVED])
            ->count();

        $retirementForecast = RetirementCase::query()
            ->whereNotIn('status', [RetirementCase::STATUS_COMPLETED, RetirementCase::STATUS_CANCELLED])
            ->whereBetween('proposed_retirement_date', [
                $asOf->toDateString(),
                $asOf->copy()->addYear()->toDateString(),
            ])
            ->count();

        $enrollments = TrainingEnrollment::query();
        $trainingTotal = (clone $enrollments)->count();
        $trainingCompleted = (clone $enrollments)
            ->where('status', TrainingEnrollment::STATUS_COMPLETED)
            ->count();

        $reviews = PerformanceReview::query();
        $reviewTotal = (clone $reviews)->count();
        $reviewsFinalized = (clone $reviews)
            ->whereIn('status', [PerformanceReview::STATUS_FINALIZED, PerformanceReview::STATUS_ACKNOWLEDGED])
            ->count();

        return [
            'as_of' => $asOf->toDateString(),
            'active_headcount' => $activeHeadcount,
            'new_hires_90_days' => $newHires,
            'separations_ytd' => $separations,
            'pending_retirements' => $pendingRetirements,
            'retirement_forecast_12_months' => $retirementForecast,
            'training_completion_rate' => $this->rate($trainingCompleted, $trainingTotal),
            'performance_completion_rate' => $this->rate($reviewsFinalized, $reviewTotal),
            'pending_attendance_corrections' => AttendanceCorrection::query()
                ->where('status', AttendanceCorrection::STATUS_SUBMITTED)
                ->count(),
            'open_external_claims' => ExternalClaimTracking::query()
                ->whereIn('status', [
                    ExternalClaimTracking::STATUS_SUBMITTED,
                    ExternalClaimTracking::STATUS_IN_PROCESS,
                    ExternalClaimTracking::STATUS_APPROVED,
                ])
                ->count(),
            'expiring_certifications_30_days' => EmployeeCertification::query()
                ->where('status', EmployeeCertification::STATUS_ACTIVE)
                ->whereNotNull('expires_at')
                ->whereBetween('expires_at', [
                    $asOf->toDateString(),
                    $asOf->copy()->addDays(30)->toDateString(),
                ])
                ->count(),
            'approved_compliance_rules' => ComplianceRule::query()
                ->where('status', ComplianceRule::STATUS_APPROVED)
                ->count(),
            'compliance_rules_expiring_90_days' => ComplianceRule::query()
                ->where('status', ComplianceRule::STATUS_APPROVED)
                ->whereNotNull('effective_until')
                ->whereBetween('effective_until', [
                    $asOf->toDateString(),
                    $asOf->copy()->addDays(90)->toDateString(),
                ])
                ->count(),
        ];
    }

    protected function rate(int $part, int $total): float
    {
        return $total === 0 ? 0.0 : round(($part / $total) * 100, 1);
    }
}
