<?php

namespace App\Services\Learning;

use App\Models\Employee;
use App\Models\EmployeeCertification;
use App\Models\LearningCourse;
use App\Models\TrainingEnrollment;
use App\Models\TrainingSession;
use App\Models\User;
use App\Services\Hr\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Training catalogue, sessions and employee certifications.
 */
class LearningService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createCourse(array $attributes, User $actor): LearningCourse
    {
        return DB::transaction(function () use ($attributes, $actor): LearningCourse {
            $course = LearningCourse::create($attributes);

            $this->audit->record('learning.course_created', $course, after: $course->getAttributes(), metadata: [
                'actor_id' => $actor->getKey(),
            ]);

            return $course->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function scheduleSession(LearningCourse $course, array $attributes, User $actor): TrainingSession
    {
        return DB::transaction(function () use ($course, $attributes, $actor): TrainingSession {
            $session = TrainingSession::create([
                ...$attributes,
                'company_id' => $course->company_id,
                'training_course_id' => $course->getKey(),
                'status' => $attributes['status'] ?? TrainingSession::STATUS_SCHEDULED,
            ]);

            $this->audit->record('learning.session_scheduled', $session, metadata: ['actor_id' => $actor->getKey()]);

            return $session->refresh();
        });
    }

    public function enroll(TrainingSession $session, Employee $employee, User $actor): TrainingEnrollment
    {
        return DB::transaction(function () use ($session, $employee, $actor): TrainingEnrollment {
            $session = TrainingSession::withoutTenant()->whereKey($session->getKey())->lockForUpdate()->firstOrFail();

            if ($session->status !== TrainingSession::STATUS_SCHEDULED) {
                throw ValidationException::withMessages([
                    'training_session_id' => 'Only a scheduled session accepts enrollments.',
                ]);
            }

            $existing = TrainingEnrollment::withoutTenant()
                ->where('training_session_id', $session->getKey())
                ->where('employee_id', $employee->getKey())
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            if ($session->capacity !== null) {
                $count = TrainingEnrollment::withoutTenant()
                    ->where('training_session_id', $session->getKey())
                    ->whereNotIn('status', [TrainingEnrollment::STATUS_WITHDRAWN])
                    ->count();

                if ($count >= $session->capacity) {
                    throw ValidationException::withMessages([
                        'training_session_id' => 'This training session is full.',
                    ]);
                }
            }

            $enrollment = TrainingEnrollment::firstOrCreate(
                [
                    'training_session_id' => $session->getKey(),
                    'employee_id' => $employee->getKey(),
                ],
                [
                    'company_id' => $session->company_id,
                    'status' => TrainingEnrollment::STATUS_ENROLLED,
                ],
            );

            $this->audit->record('learning.enrolled', $enrollment, metadata: ['actor_id' => $actor->getKey()]);

            return $enrollment->refresh();
        });
    }

    public function recordCompletion(
        TrainingEnrollment $enrollment,
        string $status,
        ?float $score,
        ?string $evaluation,
        User $actor,
    ): TrainingEnrollment {
        return DB::transaction(function () use ($enrollment, $status, $score, $evaluation, $actor): TrainingEnrollment {
            if (! in_array($status, [
                TrainingEnrollment::STATUS_ATTENDED,
                TrainingEnrollment::STATUS_COMPLETED,
                TrainingEnrollment::STATUS_FAILED,
                TrainingEnrollment::STATUS_WITHDRAWN,
            ], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Unsupported enrollment status.',
                ]);
            }

            $enrollment->forceFill([
                'status' => $status,
                'score' => $score,
                'evaluation' => $evaluation,
                'completion_date' => now()->toDateString(),
            ])->save();

            $this->audit->record('learning.completion_recorded', $enrollment, after: [
                'status' => $status,
            ], metadata: ['actor_id' => $actor->getKey()]);

            return $enrollment->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function issueCertification(Employee $employee, array $attributes, User $actor): EmployeeCertification
    {
        return DB::transaction(function () use ($employee, $attributes, $actor): EmployeeCertification {
            $certification = EmployeeCertification::create([
                ...$attributes,
                'company_id' => $employee->company_id,
                'employee_id' => $employee->getKey(),
                'status' => $attributes['status'] ?? EmployeeCertification::STATUS_ACTIVE,
            ]);

            $this->audit->record('learning.certification_issued', $certification, metadata: [
                'actor_id' => $actor->getKey(),
            ]);

            return $certification->refresh();
        });
    }

    /**
     * @return Collection<int, EmployeeCertification>
     */
    public function expiringCertifications(int $days = 30)
    {
        return EmployeeCertification::query()
            ->with('employee')
            ->where('status', EmployeeCertification::STATUS_ACTIVE)
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now()->toDateString(), now()->addDays($days)->toDateString()])
            ->orderBy('expires_at')
            ->get();
    }
}
