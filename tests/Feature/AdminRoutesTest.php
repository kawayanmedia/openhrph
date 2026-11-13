<?php

use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

it('redirects guests away from the admin dashboard', function () {
    $this->get('/app/dashboard')->assertRedirect(route('admin.login'));
});

it('redirects guests away from an admin resource index', function () {
    $this->get('/app/resources/office')->assertRedirect(route('admin.login'));
});

it('redirects guests away from an admin resource create form', function () {
    $this->get('/app/resources/office/new')->assertRedirect(route('admin.login'));
});

it('redirects guests away from admin settings', function () {
    $this->get('/app/settings/general')->assertRedirect(route('admin.login'));
});

it('allows company owners through the viewAdmin gate', function () {
    $owner = User::factory()->make(['owner' => true]);

    expect(Gate::forUser($owner)->allows(config('admin.gate')))->toBeTrue();
});

it('allows users with a group permission through the viewAdmin gate', function () {
    $group = Group::factory()->make([
        'company_id' => 1,
        'name' => 'Admins',
        'permissions' => ['Offices' => true],
    ]);

    $user = User::factory()->make(['owner' => false, 'group_id' => 1]);
    $user->setRelation('group', $group);

    expect(Gate::forUser($user)->allows(config('admin.gate')))->toBeTrue();
});

it('denies users without an owner flag or group permission', function () {
    $user = User::factory()->make(['owner' => false, 'group_id' => null]);

    expect(Gate::forUser($user)->allows(config('admin.gate')))->toBeFalse();
});
