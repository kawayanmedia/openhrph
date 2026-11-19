<?php

use App\Livewire\Demo\Register;
use App\Livewire\Demo\VerifyEmailNotice;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\User;
use App\Services\Demo\DemoTenantProvisioner;
use App\Support\Tenancy\TenantContext;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    RateLimiter::clear('demo-register:127.0.0.1');

    config(['demo.enabled' => true]);
});

it('hides demo registration and verification routes when demo mode is off', function () {
    config(['demo.enabled' => false]);

    $this->get('/register')->assertNotFound();

    $user = User::factory()->create(['owner' => true]);

    $this->actingAs($user)
        ->get('/email/verify')
        ->assertNotFound();
});

it('renders the demo registration page when demo mode is on', function () {
    $this->get('/register')
        ->assertOk()
        ->assertSee('Create a demo account')
        ->assertSee('Company name');
});

it('sends a signed-in unverified visitor back to the verification notice', function () {
    $user = User::factory()->create([
        'owner' => true,
        'email_verified_at' => null,
    ]);

    $this->actingAs($user)
        ->get('/register')
        ->assertRedirect(route('verification.notice'));
});

it('creates an isolated demo company and sends the verification email', function () {
    Notification::fake();

    Livewire::test(Register::class)
        ->set('name', 'Demo Owner')
        ->set('companyName', 'Demo Workspace')
        ->set('email', 'demo@example.com')
        ->set('password', 'secret-password')
        ->set('password_confirmation', 'secret-password')
        ->call('register')
        ->assertHasNoErrors()
        ->assertRedirect(route('verification.notice'));

    $user = User::query()->where('email', 'demo@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->owner)->toBe(1)
        ->and($user->company_id)->not->toBeNull()
        ->and($user->hasVerifiedEmail())->toBeFalse();

    expect(Auth::guard('web')->id())->toBe($user->getKey());

    $company = Company::query()->findOrFail($user->company_id);

    expect($company->name)->toBe('Demo Workspace')
        ->and($company->setup_done)->toBe(1);

    app(TenantContext::class)->run($company, function (): void {
        expect(Employee::query()->count())->toBe(3)
            ->and(Payroll::query()->count())->toBe(1);
    });

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('rejects a second demo account with the same email', function () {
    User::factory()->create(['email' => 'demo@example.com']);

    Livewire::test(Register::class)
        ->set('name', 'Second Demo')
        ->set('companyName', 'Another Workspace')
        ->set('email', 'demo@example.com')
        ->set('password', 'secret-password')
        ->set('password_confirmation', 'secret-password')
        ->call('register')
        ->assertHasErrors(['email']);

    expect(User::query()->where('email', 'demo@example.com')->count())->toBe(1);
});

it('keeps unverified demo users out of the admin workspace', function () {
    $user = User::factory()->create([
        'owner' => true,
        'email_verified_at' => null,
    ]);

    $this->actingAs($user)
        ->get('/app/dashboard')
        ->assertRedirect(route('verification.notice'));

    $this->actingAs($user)
        ->get(route('verification.notice'))
        ->assertOk()
        ->assertSee('Verify your email');
});

it('verifies a demo account through the signed link', function () {
    $user = User::factory()->create([
        'owner' => true,
        'email_verified_at' => null,
    ]);

    $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $user->getKey(),
        'hash' => sha1($user->email),
    ]);

    $this->actingAs($user)
        ->get($url)
        ->assertRedirect(route('admin.dashboard'));

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('opens the demo workspace once the account is verified', function () {
    $user = app(DemoTenantProvisioner::class)->provision([
        'name' => 'Demo Owner',
        'company_name' => 'Demo Workspace',
        'email' => 'workspace@example.com',
        'password' => 'secret-password',
    ]);

    $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $user->getKey(),
        'hash' => sha1($user->email),
    ]);

    $this->actingAs($user)
        ->get($url)
        ->assertRedirect(route('admin.dashboard'));

    $this->actingAs($user->fresh())
        ->get('/app/dashboard')
        ->assertOk()
        ->assertSee('Dashboard');
});

it('rejects a tampered verification link', function () {
    $user = User::factory()->create([
        'owner' => true,
        'email_verified_at' => null,
    ]);

    $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $user->getKey(),
        'hash' => sha1('someone-else@example.com'),
    ]);

    $this->actingAs($user)
        ->get($url)
        ->assertForbidden();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('resends the verification email from the notice page', function () {
    Notification::fake();

    $user = User::factory()->create([
        'owner' => true,
        'email_verified_at' => null,
    ]);

    RateLimiter::clear('demo-verification:'.$user->getKey());

    $this->actingAs($user);

    Livewire::test(VerifyEmailNotice::class)
        ->call('resend')
        ->assertHasNoErrors()
        ->assertSee('A new verification link has been sent');

    Notification::assertSentTo($user, VerifyEmail::class);
});
