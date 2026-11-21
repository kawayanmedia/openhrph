<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

uses(RefreshDatabase::class);

it('renders the password reset request page', function () {
    $this->get(route('admin.password.request'))
        ->assertOk()
        ->assertSee('Reset your password');
});

it('sends a reset link for a company user', function () {
    $user = User::factory()->create([
        'company_id' => 1,
        'email' => 'reset-me@example.com',
        'password' => 'secret-password',
    ]);

    $this->post(route('admin.password.email'), ['email' => $user->email])
        ->assertSessionHas('status');

    $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
});

it('resets the password with a valid token', function () {
    $user = User::factory()->create([
        'company_id' => 1,
        'email' => 'reset-me@example.com',
        'password' => 'secret-password',
    ]);

    $token = Password::createToken($user);

    $this->post(route('admin.password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'new-secret-password',
        'password_confirmation' => 'new-secret-password',
    ])->assertRedirect(route('admin.login'));

    expect(Hash::check('new-secret-password', $user->refresh()->password))->toBeTrue();
});
