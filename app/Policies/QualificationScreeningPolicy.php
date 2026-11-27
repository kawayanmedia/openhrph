<?php

namespace App\Policies;

use App\Jurisdictions\Philippines\Government\Models\QualificationScreening;
use App\Models\User;
use App\Policies\Concerns\TenantScopedAuthorization;

class QualificationScreeningPolicy
{
    use TenantScopedAuthorization;

    public function update(User $user, QualificationScreening $screening): bool
    {
        return false;
    }

    public function delete(User $user, QualificationScreening $screening): bool
    {
        return false;
    }
}
