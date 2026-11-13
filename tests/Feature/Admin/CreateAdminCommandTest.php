<?php

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('creates a company owner with a hashed password and membership', function () {
    $this->artisan('admin:create', [
        '--name' => 'Verify Admin',
        '--email' => 'verify-admin@example.com',
        '--password' => 'Secret123!',
        '--company-name' => 'Verify Company',
        '--no-interaction' => true,
    ])->assertSuccessful();

    $user = User::query()->where('email', 'verify-admin@example.com')->firstOrFail();
    $company = Company::query()->findOrFail($user->company_id);

    expect($user->owner)->toBe(1)
        ->and($user->name)->toBe('Verify Admin')
        ->and($user->first_name)->toBe('Verify')
        ->and($user->last_name)->toBe('Admin')
        ->and(Hash::check('Secret123!', $user->password))->toBeTrue()
        ->and($company->name)->toBe('Verify Company')
        ->and($company->user_id)->toBe($user->id);

    $this->assertDatabaseHas('company_user', [
        'company_id' => $company->id,
        'user_id' => $user->id,
        'is_owner' => 1,
    ]);
});

it('refuses to overwrite an existing email without force', function () {
    $this->artisan('admin:create', [
        '--name' => 'First Admin',
        '--email' => 'verify-admin@example.com',
        '--password' => 'Secret123!',
        '--company-name' => 'Verify Company',
        '--no-interaction' => true,
    ])->assertSuccessful();

    $this->artisan('admin:create', [
        '--name' => 'Second Admin',
        '--email' => 'verify-admin@example.com',
        '--password' => 'Another123!',
        '--no-interaction' => true,
    ])->assertFailed();

    expect(User::query()->where('email', 'verify-admin@example.com')->count())->toBe(1);
});

it('updates the existing user with force and keeps one membership', function () {
    $this->artisan('admin:create', [
        '--name' => 'First Admin',
        '--email' => 'verify-admin@example.com',
        '--password' => 'Secret123!',
        '--company-name' => 'Verify Company',
        '--no-interaction' => true,
    ])->assertSuccessful();

    $company = Company::query()->firstOrFail();

    $this->artisan('admin:create', [
        '--name' => 'Updated Admin',
        '--email' => 'verify-admin@example.com',
        '--password' => 'Another123!',
        '--company' => (string) $company->id,
        '--force' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();

    $user = User::query()->where('email', 'verify-admin@example.com')->sole();

    expect(User::query()->where('email', 'verify-admin@example.com')->count())->toBe(1)
        ->and($user->name)->toBe('Updated Admin')
        ->and(Hash::check('Another123!', $user->password))->toBeTrue();

    $this->assertDatabaseCount('company_user', 1);
});
