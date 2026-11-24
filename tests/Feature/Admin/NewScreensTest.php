<?php

use App\Livewire\Admin\AdminForm;
use App\Livewire\Admin\AdminIndex;
use App\Livewire\Admin\Reports\EmployeeMasterFile;
use App\Livewire\Admin\Reports\Remittances;
use App\Models\BankFileTemplate;
use App\Models\Company;
use App\Models\CompanyBank;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::factory()->create(['name' => 'Acme', 'type' => 'Private']);
    app(TenantContext::class)->set($this->company->id);

    $this->user = User::factory()->forCompany($this->company)->create(['owner' => true]);
    $this->actingAs($this->user);
});

it('renders the new admin resource screens', function () {
    foreach (['company', 'company-bank', 'bank-file-template'] as $resource) {
        Livewire::test(AdminIndex::class, ['resource' => $resource])->assertOk();
        Livewire::test(AdminForm::class, ['resource' => $resource])->assertOk();
    }
});

it('creates a payroll account and a bank file template', function () {
    Livewire::test(AdminForm::class, ['resource' => 'company-bank'])
        ->set('form.name', 'BDO Unibank')
        ->set('form.code', 'BDO')
        ->set('form.account_number', '000000000001')
        ->call('save');

    expect(CompanyBank::query()->where('name', 'BDO Unibank')->exists())->toBeTrue();

    $columns = json_encode([
        ['source' => 'employee_number', 'label' => 'Employee No'],
        ['source' => 'account_number', 'label' => 'Account Number'],
        ['source' => 'amount', 'label' => 'Amount'],
    ]);

    Livewire::test(AdminForm::class, ['resource' => 'bank-file-template'])
        ->set('form.name', 'LandBank payroll')
        ->set('form.delimiter', ',')
        ->set('form.columns', $columns)
        ->set('form.date_format', 'Ymd')
        ->call('save');

    $template = BankFileTemplate::query()->where('name', 'LandBank payroll')->first();

    expect($template)->not->toBeNull()
        ->and($template->columns)->toBeArray()->toHaveCount(3)
        ->and($template->columns[0]['source'])->toBe('employee_number');
});

it('renders the report centre screens', function () {
    Livewire::test(Remittances::class)->assertOk();
    Livewire::test(EmployeeMasterFile::class)->assertOk();
});
