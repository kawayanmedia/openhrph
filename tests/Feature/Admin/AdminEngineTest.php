<?php

use App\Livewire\Admin\Actions\ExportCsv;
use App\Livewire\Admin\AdminDetail;
use App\Livewire\Admin\AdminForm;
use App\Livewire\Admin\AdminIndex;
use App\Livewire\Admin\Settings\Settings;
use App\Models\Employee;
use App\Models\EmployeeCertificate;
use App\Models\Office;
use App\Models\Setting;
use App\Models\TimesheetRequest;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\Fixtures\EmployeeOfficeSearchResource;
use Tests\Fixtures\ModelAwareOfficeAction;
use Tests\Fixtures\ReadOnlyOffice;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create([
        'company_id' => 1,
        'owner' => true,
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'password' => 'secret',
    ]);

    $this->actingAs($this->user);
});

it('creates a record through the admin form', function () {
    Livewire::test(AdminForm::class, ['resource' => 'office'])
        ->set('form.code', 'HQ')
        ->set('form.name', 'Head Office')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('offices', [
        'code' => 'HQ',
        'name' => 'Head Office',
        'company_id' => 1,
    ]);
});

it('validates required fields and keeps the form open', function () {
    Livewire::test(AdminForm::class, ['resource' => 'office'])
        ->set('form.name', '')
        ->call('save')
        ->assertHasErrors(['form.name' => 'required']);

    expect(Office::count())->toBe(0);
});

it('updates a record and preserves values for fields without rules', function () {
    $office = Office::factory()->create(['company_id' => 1, 'code' => 'OLD', 'name' => 'Old name']);

    Livewire::test(AdminForm::class, ['resource' => 'office', 'resourceId' => $office->id])
        ->set('form.name', 'New name')
        ->call('save')
        ->assertHasNoErrors();

    $office->refresh();

    expect($office->name)->toBe('New name')
        ->and($office->code)->toBe('OLD');
});

it('deletes a record with soft deletes', function () {
    $office = Office::factory()->create(['company_id' => 1, 'code' => 'DEL', 'name' => 'Delete me']);

    Livewire::test(AdminDetail::class, ['resource' => 'office', 'resourceId' => $office->id])
        ->call('confirmDelete')
        ->call('delete');

    expect(Office::find($office->id))->toBeNull();
    $this->assertSoftDeleted('offices', ['id' => $office->id]);
});

it('shows related employee columns in the index', function () {
    $employee = Employee::factory()->create([
        'company_id' => 1,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'employee_number' => 'E-555',
    ]);

    TimesheetRequest::factory()->create([
        'company_id' => 1,
        'employee_id' => $employee->id,
        'type' => 'attendance',
        'reason' => 'Biometric error',
        'status' => 'pending',
        'log_date' => now()->toDateString(),
    ]);

    Livewire::test(AdminIndex::class, ['resource' => 'attendance-request'])
        ->assertSee('Doe, Jane');
});

it('searches related columns with whereHas', function () {
    $office = Office::factory()->create(['company_id' => 1, 'code' => 'MKT', 'name' => 'Makati']);
    $other = Office::factory()->create(['company_id' => 1, 'code' => 'LAG', 'name' => 'Laguna']);

    Employee::factory()->create([
        'company_id' => 1,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'employee_number' => 'E-100',
        'office_id' => $office->id,
    ]);

    Employee::factory()->create([
        'company_id' => 1,
        'first_name' => 'John',
        'last_name' => 'Doe',
        'employee_number' => 'E-101',
        'office_id' => $other->id,
    ]);

    $component = new AdminIndex;
    $component->resource = new EmployeeOfficeSearchResource;
    $component->search = 'Makati';

    $query = Employee::query();

    $method = new ReflectionMethod($component, 'applySearch');
    $method->setAccessible(true);
    $method->invoke($component, $query);

    expect($query->count())->toBe(1);
});

it('ignores unsortable or non-column attributes', function () {
    Livewire::test(AdminIndex::class, ['resource' => 'office'])
        ->call('sortBy', 'company_id')
        ->assertSet('sortAttribute', '');

    Livewire::test(AdminIndex::class, ['resource' => 'office'])
        ->call('sortBy', 'name')
        ->assertSet('sortAttribute', 'name');
});

it('opens an action modal, validates fields and runs the action', function () {
    Queue::fake();

    $employee = Employee::factory()->create([
        'company_id' => 1,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'employee_number' => 'E-001',
    ]);

    $request = TimesheetRequest::factory()->create([
        'company_id' => 1,
        'employee_id' => $employee->id,
        'type' => 'attendance',
        'status' => 'pending',
        'log_date' => now()->toDateString(),
    ]);

    Livewire::test(AdminIndex::class, ['resource' => 'attendance-request'])
        ->call('openAction', 'ApproveAttendance', $request->id)
        ->assertSet('showActionModal', true)
        ->call('runAction')
        ->assertHasErrors(['actionFields.remarks' => 'required']);

    Livewire::test(AdminIndex::class, ['resource' => 'attendance-request'])
        ->call('openAction', 'ApproveAttendance', $request->id)
        ->set('actionFields.remarks', 'Approved')
        ->call('runAction')
        ->assertHasNoErrors()
        ->assertSet('showActionModal', false);

    expect($request->refresh()->status)->toBe(STATUS_APPROVED);
});

it('denies access to another company\'s record', function () {
    $office = Office::factory()->create(['company_id' => 1, 'code' => 'X', 'name' => 'Other company']);

    $intruder = User::factory()->create([
        'company_id' => 2,
        'owner' => true,
        'name' => 'Intruder',
        'email' => 'intruder@example.com',
        'password' => bcrypt('secret'),
    ]);

    $this->actingAs($intruder);

    $this->get("/app/resources/office/{$office->id}")->assertNotFound();
});

it('changes the current user password', function () {
    Livewire::test(AdminForm::class, ['resource' => 'change-password', 'resourceId' => $this->user->id])
        ->set('form.old_password', 'secret')
        ->set('form.password', 'NewSecret!2345')
        ->set('form.password_confirmation', 'NewSecret!2345')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(url(config('admin.path').'/dashboard'));

    expect(Hash::check('NewSecret!2345', $this->user->fresh()->password))->toBeTrue();
});

it('stores an uploaded file and persists its path', function () {
    Storage::fake('public');

    $employee = Employee::factory()->create([
        'company_id' => 1,
        'first_name' => 'File',
        'last_name' => 'Upload',
        'employee_number' => 'E-002',
    ]);

    Livewire::test(AdminForm::class, ['resource' => 'employee-certificate'])
        ->set('form.employee', $employee->id)
        ->set('form.certicate_title', 'Safety Training')
        ->set('form.certicate_number', 'CERT-001')
        ->set('form.document', UploadedFile::fake()->create('certificate.pdf', 12, 'application/pdf'))
        ->call('save')
        ->assertHasNoErrors();

    $certificate = EmployeeCertificate::query()->latest('id')->firstOrFail();

    expect($certificate->document)->not->toBeNull();
    Storage::disk('public')->assertExists($certificate->document);
});

it('streams a csv export of the selected rows', function () {
    $office = Office::factory()->create(['company_id' => 1, 'code' => 'CSV', 'name' => 'CSV Office']);

    $response = ExportCsv::make()->handle(request(), [], collect([$office]));

    expect($response)->toBeInstanceOf(StreamedResponse::class);

    ob_start();
    $response->sendContent();
    $csv = ob_get_clean();

    expect($csv)->toContain('CSV Office');
});

it('saves admin settings', function () {
    Livewire::test(Settings::class, ['page' => 'general'])
        ->set('form.trade_name', 'Acme Corp')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('settings', [
        'key' => 'trade_name',
        'value' => 'Acme Corp',
        'company_id' => 1,
    ]);
});

it('uses human readable field names in validation messages', function () {
    $component = Livewire::test(AdminForm::class, ['resource' => 'office'])
        ->set('form.name', '')
        ->call('save')
        ->assertHasErrors(['form.name' => 'required']);

    expect($component->errors()->first('form.name'))->toBe('The Name field is required.');
});

it('binds the target record when running an action', function () {
    config(['admin.resources_namespace' => 'Tests\\Fixtures']);

    $office = Office::factory()->create(['company_id' => 1, 'code' => 'ACT', 'name' => 'Action office']);

    ModelAwareOfficeAction::$lastModelId = null;

    Livewire::test(AdminIndex::class, ['resource' => 'model-aware-office'])
        ->call('openAction', 'ModelAwareOfficeAction', $office->id)
        ->call('runAction')
        ->assertHasNoErrors();

    expect(ModelAwareOfficeAction::$lastModelId)->toBe($office->id);
});

it('honours resource authorization overrides before the policy', function () {
    config(['admin.resources_namespace' => 'Tests\\Fixtures']);

    $office = Office::factory()->create(['company_id' => 1, 'code' => 'RO', 'name' => 'Read only']);

    expect(ReadOnlyOffice::allows('create', ReadOnlyOffice::$model))->toBeFalse()
        ->and(ReadOnlyOffice::allows('view', $office))->toBeTrue()
        ->and(ReadOnlyOffice::allows('update', $office))->toBeFalse()
        ->and(ReadOnlyOffice::allows('delete', $office))->toBeFalse();

    expect(fn () => ReadOnlyOffice::authorize('update', $office))
        ->toThrow(AuthorizationException::class);
});

it('hides create and edit actions for read only resources', function () {
    config(['admin.resources_namespace' => 'Tests\\Fixtures']);

    Office::factory()->create(['company_id' => 1, 'code' => 'RO', 'name' => 'Read only']);

    $html = Livewire::test(AdminIndex::class, ['resource' => 'read-only-office'])->html();

    expect($html)->not->toContain('/edit')
        ->and($html)->not->toContain('/new');
});

it('only saves settings for the current company', function () {
    $other = User::factory()->create([
        'company_id' => 2,
        'owner' => true,
        'name' => 'Other Admin',
        'email' => 'other@example.com',
        'password' => bcrypt('secret'),
    ]);

    $ownSetting = Setting::factory()->create(['key' => 'trade_name', 'value' => 'Mine', 'company_id' => 1]);
    $otherSetting = app(TenantContext::class)->run(2, fn () => Setting::factory()->create(['key' => 'trade_name', 'value' => 'Theirs', 'company_id' => 2]));

    Livewire::test(Settings::class, ['page' => 'general'])
        ->set('form.trade_name', 'Updated')
        ->call('save')
        ->assertHasNoErrors();

    expect($ownSetting->refresh()->value)->toBe('Updated')
        ->and($otherSetting->refresh()->value)->toBe('Theirs')
        ->and($other->company_id)->toBe(2);
});
