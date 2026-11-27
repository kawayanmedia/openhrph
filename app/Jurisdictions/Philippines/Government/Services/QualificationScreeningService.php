<?php

namespace App\Jurisdictions\Philippines\Government\Services;

use App\Jurisdictions\Philippines\Government\Models\QualificationScreening;
use App\Jurisdictions\Philippines\Government\Models\QualificationStandard;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use App\Services\Hr\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Qualification screening against a versioned standard.
 *
 * Evidence is supplied explicitly: the system never infers educational
 * equivalence or eligibility. HR records the evidence it has verified.
 */
class QualificationScreeningService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function applicableStandard(Position $position, ?Carbon $asOf = null): ?QualificationStandard
    {
        $asOf ??= now();

        return QualificationStandard::query()
            ->where('position_id', $position->getKey())
            ->where('status', QualificationStandard::STATUS_APPROVED)
            ->where(fn ($query) => $query->whereNull('effective_from')->orWhere('effective_from', '<=', $asOf->toDateString()))
            ->where(fn ($query) => $query->whereNull('effective_until')->orWhere('effective_until', '>=', $asOf->toDateString()))
            ->orderByDesc('version')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $evidence
     */
    public function screen(
        Employee $employee,
        QualificationStandard $standard,
        array $evidence,
        ?User $actor = null,
    ): QualificationScreening {
        if (! $standard->isApproved()) {
            throw ValidationException::withMessages([
                'qualification_standard_id' => 'Only an approved qualification standard can be used for screening.',
            ]);
        }

        $deficiencies = [];

        if (array_key_exists('education_met', $evidence) && ! (bool) $evidence['education_met']) {
            $deficiencies[] = 'Education requirement not met.';
        }

        if ($standard->training_hours !== null) {
            $hours = (float) ($evidence['training_hours'] ?? 0);

            if ($hours < (float) $standard->training_hours) {
                $deficiencies[] = "Training requirement not met ({$hours} of {$standard->training_hours} hours).";
            }
        }

        if ($standard->experience_years !== null) {
            $years = (float) ($evidence['experience_years'] ?? 0);

            if ($years < (float) $standard->experience_years) {
                $deficiencies[] = "Experience requirement not met ({$years} of {$standard->experience_years} years).";
            }
        }

        if ($standard->requires_eligibility && ! (bool) ($evidence['eligibility_provided'] ?? false)) {
            $deficiencies[] = 'Required eligibility has not been evidenced.';
        }

        if ($standard->requires_license && ! (bool) ($evidence['license_provided'] ?? false)) {
            $deficiencies[] = 'Required license has not been evidenced.';
        }

        $screening = QualificationScreening::create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->getKey(),
            'qualification_standard_id' => $standard->getKey(),
            'standard_version' => $standard->version,
            'evidence' => $evidence,
            'result' => $deficiencies === [] ? QualificationScreening::RESULT_QUALIFIED : QualificationScreening::RESULT_DISQUALIFIED,
            'deficiencies' => $deficiencies,
            'screened_by' => $actor?->getKey(),
            'screened_at' => now(),
        ]);

        $this->audit->record('government.qualification_screened', $screening, after: [
            'result' => $screening->result,
            'standard_id' => $standard->getKey(),
            'standard_version' => $standard->version,
        ]);

        return $screening->refresh();
    }
}
