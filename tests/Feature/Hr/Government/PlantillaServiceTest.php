<?php

use App\Jurisdictions\Philippines\Government\Models\AppointmentRecord;
use App\Jurisdictions\Philippines\Government\Models\PlantillaItem;
use App\Jurisdictions\Philippines\Government\Models\PlantillaVersion;
use App\Jurisdictions\Philippines\Government\Services\PlantillaService;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Office;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $attributes
 * @return array<string, mixed>
 */
function plantillaTestVersionPayload(Company $company, array $attributes = []): array
{
    return array_merge([
        'company_id' => $company->id,
        'version' => '2026-01',
        'name' => 'CY 2026 Plantilla',
    ], $attributes);
}

/**
 * @param  array<string, mixed>  $attributes
 * @return array<string, mixed>
 */
function plantillaTestItemPayload(Office $office, array $attributes = []): array
{
    return array_merge([
        'office_id' => $office->id,
        'item_number' => 'ITM-0001',
        'position_title' => 'Administrative Officer I',
        'salary_grade' => 11,
        'step' => 1,
    ], $attributes);
}

beforeEach(function (): void {
    $this->company = Company::factory()->create();
    $this->user = User::factory()->forCompany($this->company)->create();
    $this->actingAs($this->user);

    $this->service = app(PlantillaService::class);

    $this->office = Office::create([
        'company_id' => $this->company->id,
        'name' => 'Office of the Mayor',
        'code' => 'MAYOR',
    ]);
});

it('creates a plantilla version in draft', function (): void {
    $version = $this->service->createVersion(plantillaTestVersionPayload($this->company));

    expect($version->status)->toBe(PlantillaVersion::STATUS_DRAFT)
        ->and((int) $version->company_id)->toBe($this->company->id)
        ->and($version->version)->toBe('2026-01');
});

it('rejects a duplicate plantilla version for the same company', function (): void {
    $this->service->createVersion(plantillaTestVersionPayload($this->company));

    expect(fn () => $this->service->createVersion(plantillaTestVersionPayload($this->company)))
        ->toThrow(ValidationException::class);
});

it('approves a draft version recording the approver and reference', function (): void {
    $version = $this->service->createVersion(plantillaTestVersionPayload($this->company));

    $approved = $this->service->approveVersion($version, $this->user, 'CSC-RES-2026-001');

    expect($approved->status)->toBe(PlantillaVersion::STATUS_APPROVED)
        ->and((int) $approved->approved_by)->toBe($this->user->id)
        ->and($approved->approval_reference)->toBe('CSC-RES-2026-001')
        ->and($approved->approved_at)->not->toBeNull();
});

it('rejects approving a version twice', function (): void {
    $version = $this->service->createVersion(plantillaTestVersionPayload($this->company));
    $this->service->approveVersion($version, $this->user);

    expect(fn () => $this->service->approveVersion($version->refresh(), $this->user))
        ->toThrow(ValidationException::class);
});

it('retires a version only after it has been approved', function (): void {
    $version = $this->service->createVersion(plantillaTestVersionPayload($this->company));

    expect(fn () => $this->service->retireVersion($version, $this->user))
        ->toThrow(ValidationException::class);

    $this->service->approveVersion($version, $this->user);

    $retired = $this->service->retireVersion($version, $this->user);

    expect($retired->status)->toBe(PlantillaVersion::STATUS_RETIRED);
});

it('adds items with unique item numbers to a draft version', function (): void {
    $version = $this->service->createVersion(plantillaTestVersionPayload($this->company));

    $item = $this->service->addItem($version, plantillaTestItemPayload($this->office));

    expect((int) $item->plantilla_version_id)->toBe($version->id)
        ->and($item->status)->toBe(PlantillaItem::STATUS_AUTHORIZED)
        ->and($item->is_vacant)->toBeTrue();

    expect(fn () => $this->service->addItem($version, plantillaTestItemPayload($this->office)))
        ->toThrow(ValidationException::class);

    expect(PlantillaItem::query()->count())->toBe(1);
});

it('rejects adding items to an approved version', function (): void {
    $version = $this->service->createVersion(plantillaTestVersionPayload($this->company));
    $this->service->approveVersion($version, $this->user);

    expect(fn () => $this->service->addItem($version->refresh(), plantillaTestItemPayload($this->office)))
        ->toThrow(ValidationException::class);

    expect(PlantillaItem::query()->count())->toBe(0);
});

it('rejects an item already occupied by another employee', function (): void {
    $version = $this->service->createVersion(plantillaTestVersionPayload($this->company));

    $occupant = Employee::factory()->create(['company_id' => $this->company->id]);
    $applicant = Employee::factory()->create(['company_id' => $this->company->id]);

    $item = $this->service->addItem($version, plantillaTestItemPayload($this->office, [
        'employee_id' => $occupant->id,
    ]));

    expect(fn () => $this->service->assertItemAvailable($item, $applicant->id))
        ->toThrow(ValidationException::class);

    expect(fn () => $this->service->assertItemAvailable($item, $occupant->id))
        ->not->toThrow(ValidationException::class);
});

it('rejects an item referenced by another active appointment', function (): void {
    $version = $this->service->createVersion(plantillaTestVersionPayload($this->company));

    $holder = Employee::factory()->create(['company_id' => $this->company->id]);
    $applicant = Employee::factory()->create(['company_id' => $this->company->id]);

    $item = $this->service->addItem($version, plantillaTestItemPayload($this->office));

    $appointment = AppointmentRecord::create([
        'company_id' => $this->company->id,
        'employee_id' => $holder->id,
        'plantilla_item_id' => $item->id,
        'plantilla_version_id' => $version->id,
        'effectivity_date' => now()->toDateString(),
        'status' => AppointmentRecord::STATUS_ISSUED,
    ]);

    expect(fn () => $this->service->assertItemAvailable($item, $applicant->id))
        ->toThrow(ValidationException::class);

    expect(fn () => $this->service->assertItemAvailable($item, $applicant->id, $appointment->id))
        ->not->toThrow(ValidationException::class);
});

it('ignores cancelled appointments when checking item availability', function (): void {
    $version = $this->service->createVersion(plantillaTestVersionPayload($this->company));

    $holder = Employee::factory()->create(['company_id' => $this->company->id]);
    $applicant = Employee::factory()->create(['company_id' => $this->company->id]);

    $item = $this->service->addItem($version, plantillaTestItemPayload($this->office));

    AppointmentRecord::create([
        'company_id' => $this->company->id,
        'employee_id' => $holder->id,
        'plantilla_item_id' => $item->id,
        'plantilla_version_id' => $version->id,
        'effectivity_date' => now()->toDateString(),
        'status' => AppointmentRecord::STATUS_CANCELLED,
    ]);

    expect(fn () => $this->service->assertItemAvailable($item, $applicant->id))
        ->not->toThrow(ValidationException::class);
});

it('isolates versions and items to the acting tenant', function (): void {
    $version = $this->service->createVersion(plantillaTestVersionPayload($this->company));
    $this->service->addItem($version, plantillaTestItemPayload($this->office));

    $otherCompany = Company::factory()->create();

    app(TenantContext::class)->run($otherCompany, function (): void {
        expect(PlantillaVersion::query()->count())->toBe(0)
            ->and(PlantillaItem::query()->count())->toBe(0);
    });

    expect(PlantillaVersion::query()->count())->toBe(1)
        ->and(PlantillaItem::query()->count())->toBe(1);
});
