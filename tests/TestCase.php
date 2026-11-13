<?php

namespace Tests;

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(TenantContext::class)->forget();
    }

    /**
     * Authenticate a user and initialise the tenant context for the test.
     */
    public function actingAs($user, $guard = null)
    {
        $result = parent::actingAs($user, $guard);

        if ($user instanceof User && $user->company_id !== null) {
            app(TenantContext::class)->set((int) $user->company_id);
        }

        return $result;
    }
}
