<?php

use App\Models\Company;
use App\Models\OrganizationUnit;
use App\Models\User;
use App\Services\Organization\OrganizationUnitService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->user = User::factory()->forCompany($this->company)->create();

    $this->actingAs($this->user);

    $this->service = app(OrganizationUnitService::class);

    $this->makeUnit = fn (string $name, string $type, ?OrganizationUnit $parent = null): OrganizationUnit => $this->service->create([
        'company_id' => $this->company->id,
        'parent_id' => $parent?->id,
        'name' => $name,
        'unit_type' => $type,
    ]);
});

it('materialises the path and depth when a child unit is created', function () {
    $makeUnit = $this->makeUnit;

    $office = $makeUnit('Office of the Director', OrganizationUnit::TYPE_OFFICE);
    $division = $makeUnit('Administrative Division', OrganizationUnit::TYPE_DIVISION, $office);
    $section = $makeUnit('Records Section', OrganizationUnit::TYPE_SECTION, $division);

    expect($office->path)->toBe((string) $office->id)
        ->and($office->depth)->toBe(0)
        ->and($division->path)->toBe($office->id.'/'.$division->id)
        ->and($division->depth)->toBe(1)
        ->and($section->path)->toBe($office->id.'/'.$division->id.'/'.$section->id)
        ->and($section->depth)->toBe(2);
});

it('rewrites the paths and depths of the whole branch when the parent changes', function () {
    $makeUnit = $this->makeUnit;

    $headOffice = $makeUnit('Head Office', OrganizationUnit::TYPE_OFFICE);
    $division = $makeUnit('Administrative Division', OrganizationUnit::TYPE_DIVISION, $headOffice);
    $section = $makeUnit('Records Section', OrganizationUnit::TYPE_SECTION, $division);

    $regionalOffice = $makeUnit('Regional Office', OrganizationUnit::TYPE_OFFICE);
    $regionalDivision = $makeUnit('Regional Division', OrganizationUnit::TYPE_DIVISION, $regionalOffice);

    $moved = $this->service->move($division, $regionalDivision);

    $expectedDivisionPath = $regionalOffice->id.'/'.$regionalDivision->id.'/'.$division->id;

    expect($moved->path)->toBe($expectedDivisionPath)
        ->and($moved->depth)->toBe(2);

    $section->refresh();

    expect($section->path)->toBe($expectedDivisionPath.'/'.$section->id)
        ->and($section->depth)->toBe(3);

    $detached = $this->service->move($moved, null);

    expect($detached->path)->toBe((string) $division->id)
        ->and($detached->depth)->toBe(0);

    $section->refresh();

    expect($section->path)->toBe($division->id.'/'.$section->id)
        ->and($section->depth)->toBe(1);
});

it('rejects moving a unit under one of its own descendants', function () {
    $makeUnit = $this->makeUnit;

    $headOffice = $makeUnit('Head Office', OrganizationUnit::TYPE_OFFICE);
    $division = $makeUnit('Administrative Division', OrganizationUnit::TYPE_DIVISION, $headOffice);
    $section = $makeUnit('Records Section', OrganizationUnit::TYPE_SECTION, $division);

    expect(fn () => $this->service->move($headOffice, $section))
        ->toThrow(ValidationException::class);

    expect($headOffice->refresh()->path)->toBe((string) $headOffice->id)
        ->and($headOffice->depth)->toBe(0)
        ->and($division->refresh()->depth)->toBe(1)
        ->and($section->refresh()->depth)->toBe(2);
});

it('refuses to delete a unit that still has children', function () {
    $makeUnit = $this->makeUnit;

    $headOffice = $makeUnit('Head Office', OrganizationUnit::TYPE_OFFICE);
    $makeUnit('Administrative Division', OrganizationUnit::TYPE_DIVISION, $headOffice);

    expect(fn () => $this->service->delete($headOffice))
        ->toThrow(ValidationException::class);

    expect(OrganizationUnit::query()->whereKey($headOffice->id)->exists())->toBeTrue();
});

it('scopes organization units to the active tenant', function () {
    $makeUnit = $this->makeUnit;

    $mine = $makeUnit('Head Office', OrganizationUnit::TYPE_OFFICE);

    $otherCompany = Company::factory()->create();

    $theirs = app(TenantContext::class)->run($otherCompany->id, fn (): OrganizationUnit => OrganizationUnit::create([
        'company_id' => $otherCompany->id,
        'name' => 'Other Company Office',
        'unit_type' => OrganizationUnit::TYPE_OFFICE,
    ]));

    expect(OrganizationUnit::query()->whereKey($mine->id)->exists())->toBeTrue()
        ->and(OrganizationUnit::query()->whereKey($theirs->id)->exists())->toBeFalse()
        ->and(OrganizationUnit::query()->count())->toBe(1)
        ->and(OrganizationUnit::withoutTenant()->whereKey($theirs->id)->exists())->toBeTrue()
        ->and($theirs->path)->toBe((string) $theirs->id);
});
