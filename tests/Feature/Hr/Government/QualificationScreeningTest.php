<?php

use App\Jurisdictions\Philippines\Government\Models\QualificationScreening;
use App\Jurisdictions\Philippines\Government\Models\QualificationStandard;
use App\Jurisdictions\Philippines\Government\Services\QualificationScreeningService;
use App\Models\AuditEvent;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $attributes
 */
function qualificationTestStandard(Company $company, Position $position, array $attributes = []): QualificationStandard
{
    return QualificationStandard::create(array_merge([
        'company_id' => $company->id,
        'position_id' => $position->id,
        'position_title' => $position->name,
        'version' => 1,
        'training_hours' => 8,
        'experience_years' => 1,
        'requires_eligibility' => true,
        'requires_license' => false,
        'status' => QualificationStandard::STATUS_APPROVED,
        'effective_from' => '2026-01-01',
    ], $attributes));
}

beforeEach(function (): void {
    $this->company = Company::factory()->create();
    $this->user = User::factory()->forCompany($this->company)->create();
    $this->actingAs($this->user);

    $this->service = app(QualificationScreeningService::class);
    $this->employee = Employee::factory()->create(['company_id' => $this->company->id]);

    $this->position = Position::forceCreate([
        'company_id' => $this->company->id,
        'code' => 'AO1',
        'name' => 'Administrative Officer I',
    ]);
});

it('qualifies an employee whose verified evidence meets the approved standard', function (): void {
    $standard = qualificationTestStandard($this->company, $this->position);

    $screening = $this->service->screen($this->employee, $standard, [
        'education_met' => true,
        'training_hours' => 16,
        'experience_years' => 2,
        'eligibility_provided' => true,
        'license_provided' => false,
    ], $this->user);

    expect($screening->result)->toBe(QualificationScreening::RESULT_QUALIFIED)
        ->and($screening->deficiencies)->toBe([])
        ->and((int) $screening->qualification_standard_id)->toBe($standard->id)
        ->and($screening->standard_version)->toBe(1)
        ->and((int) $screening->screened_by)->toBe($this->user->id)
        ->and((int) $screening->employee_id)->toBe($this->employee->id);
});

it('disqualifies an employee and lists every unmet requirement', function (): void {
    $standard = qualificationTestStandard($this->company, $this->position);

    $screening = $this->service->screen($this->employee, $standard, [
        'education_met' => true,
        'training_hours' => 4,
        'experience_years' => 0,
        'eligibility_provided' => false,
    ]);

    expect($screening->result)->toBe(QualificationScreening::RESULT_DISQUALIFIED)
        ->and($screening->deficiencies)->toHaveCount(3)
        ->and($screening->deficiencies)->toContain('Training requirement not met (4 of 8 hours).')
        ->and($screening->deficiencies)->toContain('Experience requirement not met (0 of 1.00 years).')
        ->and($screening->deficiencies)->toContain('Required eligibility has not been evidenced.');
});

it('rejects screening against a draft standard without writing a row', function (): void {
    $standard = qualificationTestStandard($this->company, $this->position, [
        'status' => QualificationStandard::STATUS_DRAFT,
    ]);

    expect(fn () => $this->service->screen($this->employee, $standard, ['education_met' => true]))
        ->toThrow(ValidationException::class);

    expect(QualificationScreening::query()->count())->toBe(0);
});

it('selects the latest approved standard effective on the given date', function (): void {
    qualificationTestStandard($this->company, $this->position, [
        'version' => 1,
        'effective_from' => '2026-01-01',
    ]);

    $latest = qualificationTestStandard($this->company, $this->position, [
        'version' => 2,
        'effective_from' => '2026-06-01',
    ]);

    qualificationTestStandard($this->company, $this->position, [
        'version' => 3,
        'effective_from' => '2026-01-01',
        'effective_until' => '2026-03-01',
    ]);

    qualificationTestStandard($this->company, $this->position, [
        'version' => 4,
        'status' => QualificationStandard::STATUS_RETIRED,
        'effective_from' => '2026-01-01',
    ]);

    $asOf = Carbon::parse('2026-09-01');
    $applicable = $this->service->applicableStandard($this->position, $asOf);

    expect($applicable)->not->toBeNull()
        ->and($applicable->id)->toBe($latest->id)
        ->and($applicable->version)->toBe(2);

    expect($this->service->applicableStandard($this->position, Carbon::parse('2026-02-01'))->version)->toBe(3);
});

it('writes an audit event when a screening is recorded', function (): void {
    $standard = qualificationTestStandard($this->company, $this->position);

    $screening = $this->service->screen($this->employee, $standard, [
        'education_met' => true,
        'training_hours' => 16,
        'experience_years' => 2,
        'eligibility_provided' => true,
    ], $this->user);

    $event = AuditEvent::query()->where('action', 'government.qualification_screened')->first();

    expect($event)->not->toBeNull()
        ->and($event->subject_type)->toBe($screening->getMorphClass())
        ->and((int) $event->subject_id)->toBe($screening->id)
        ->and((int) $event->actor_id)->toBe($this->user->id)
        ->and((int) $event->company_id)->toBe($this->company->id);
});
