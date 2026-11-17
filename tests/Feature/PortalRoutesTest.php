<?php

use App\Livewire\Portal\Login;
use Livewire\Livewire;

it('shows the portal login page to guests', function () {
    $this->get('/portal/login')->assertOk();
});

it('redirects guests away from the portal dashboard', function () {
    $this->get('/portal/dashboard')->assertRedirect(route('portal.login'));
});

it('redirects guests away from portal leaves', function () {
    $this->get('/portal/leaves')->assertRedirect(route('portal.login'));
});

it('mounts the portal login component', function () {
    Livewire::test(Login::class)
        ->assertOk()
        ->assertSee('Sign in to your account');
});
