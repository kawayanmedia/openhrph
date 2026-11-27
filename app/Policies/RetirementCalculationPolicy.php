<?php

namespace App\Policies;

use App\Models\RetirementCalculation;
use App\Models\User;
use App\Policies\Concerns\TenantScopedAuthorization;

class RetirementCalculationPolicy
{
    use TenantScopedAuthorization;

    public function update(User $user, RetirementCalculation $calculation): bool
    {
        return $this->sameTenant($user, $calculation) && ! $calculation->isApproved();
    }

    public function delete(User $user, RetirementCalculation $calculation): bool
    {
        return $this->sameTenant($user, $calculation) && ! $calculation->isApproved();
    }
}
