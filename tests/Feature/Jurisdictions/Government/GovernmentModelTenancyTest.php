<?php

use App\Models\Company;
use App\Models\Office;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use KawayanMedia\Government\Exception\CompanyMismatchException;
use KawayanMedia\Government\Models\PlantillaItem;
use KawayanMedia\Government\Models\SalaryGrade;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->company = Company::factory()->create();
    app(TenantContext::class)->set($this->company);
});

it('assigns the active company to new government records', function (): void {
    $plantilla = PlantillaItem::factory()->create();

    expect((int) $plantilla->company_id)->toBe($this->company->id);

    $grade = SalaryGrade::query()->create([
        'name' => 'First Tranche',
        'salary_grade_type' => 'First Tranche',
        'sg' => 11,
        'year' => '2024',
    ]);

    expect((int) $grade->company_id)->toBe($this->company->id);
});

it('hides another company records from the government models', function (): void {
    PlantillaItem::factory()->create();

    $other = Company::factory()->create();

    app(TenantContext::class)->run($other, function (): void {
        expect(PlantillaItem::query()->count())->toBe(0);
    });

    expect(PlantillaItem::query()->count())->toBe(1);
});

it('fails closed when no company is resolved', function (): void {
    PlantillaItem::factory()->create();

    app(TenantContext::class)->forget();

    expect(PlantillaItem::query()->count())->toBe(0);
});

it('rejects a write for another company', function (): void {
    $office = Office::factory()->create(['company_id' => $this->company->id]);
    $other = Company::factory()->create();

    app(TenantContext::class)->run($other, function () use ($office): void {
        $plantilla = new PlantillaItem;
        $plantilla->company_id = $this->company->id;
        $plantilla->office_id = $office->id;
        $plantilla->position_title = 'Administrative Officer I';
        $plantilla->save();
    });
})->throws(CompanyMismatchException::class);
