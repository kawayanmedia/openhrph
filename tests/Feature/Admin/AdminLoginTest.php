<?php

use App\Livewire\Admin\Auth\Login;
use App\Livewire\Admin\Support\Navigation;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    RateLimiter::clear('admin-login:admin@example.com|127.0.0.1');
});

function adminLoginUser(array $attributes = []): User
{
    return User::factory()->create(array_merge([
        'company_id' => 1,
        'name' => 'Admin User',
        'first_name' => 'Admin',
        'last_name' => 'User',
        'email' => 'admin@example.com',
        'password' => 'secret-password',
        'owner' => false,
    ], $attributes));
}

it('signs in a user whose group grants admin permissions', function () {
    $group = Group::factory()->create([
        'company_id' => 1,
        'name' => 'Administrators',
        'permissions' => ['Offices' => true],
    ]);

    adminLoginUser(['group_id' => $group->id]);

    Livewire::test(Login::class)
        ->set('email', 'admin@example.com')
        ->set('password', 'secret-password')
        ->call('authenticate')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.dashboard'));

    expect(auth()->guard('web')->check())->toBeTrue();
});

it('allows a company owner to sign in without a group', function () {
    adminLoginUser(['owner' => true]);

    Livewire::test(Login::class)
        ->set('email', 'admin@example.com')
        ->set('password', 'secret-password')
        ->call('authenticate')
        ->assertHasNoErrors();

    expect(auth()->guard('web')->user()?->email)->toBe('admin@example.com');
});

it('rejects valid credentials without admin access', function () {
    $group = Group::factory()->create([
        'company_id' => 1,
        'name' => 'Payroll only',
        'permissions' => [],
    ]);

    adminLoginUser(['group_id' => $group->id]);

    Livewire::test(Login::class)
        ->set('email', 'admin@example.com')
        ->set('password', 'secret-password')
        ->call('authenticate')
        ->assertHasErrors(['email']);

    expect(auth()->guard('web')->check())->toBeFalse();
});

it('rejects invalid credentials', function () {
    adminLoginUser();

    Livewire::test(Login::class)
        ->set('email', 'admin@example.com')
        ->set('password', 'wrong-password')
        ->call('authenticate')
        ->assertHasErrors(['email']);

    expect(auth()->guard('web')->check())->toBeFalse();
});

it('throttles repeated failed attempts', function () {
    adminLoginUser();

    for ($attempt = 0; $attempt < 5; $attempt++) {
        Livewire::test(Login::class)
            ->set('email', 'admin@example.com')
            ->set('password', 'wrong-password')
            ->call('authenticate');
    }

    $component = Livewire::test(Login::class)
        ->set('email', 'admin@example.com')
        ->set('password', 'wrong-password')
        ->call('authenticate')
        ->assertHasErrors(['email']);

    expect($component->errors()->first('email'))->toContain('Too many login attempts');
});

it('redirects guests from the admin to the admin login', function () {
    $this->get('/app/dashboard')->assertRedirect(route('admin.login'));
});

it('hides menu entries the group cannot access', function () {
    $group = Group::factory()->create([
        'company_id' => 1,
        'name' => 'Offices only',
        'permissions' => ['Offices' => true],
    ]);

    $this->actingAs(adminLoginUser(['group_id' => $group->id]));

    $labels = Navigation::sections()
        ->flatMap(fn (array $section) => collect($section['items'] ?? [])
            ->merge(collect($section['groups'] ?? [])->flatMap(fn (array $group) => $group['items'])))
        ->pluck('label');

    expect($labels)->not->toContain('General Settings')
        ->and($labels)->toContain('Office');
});
