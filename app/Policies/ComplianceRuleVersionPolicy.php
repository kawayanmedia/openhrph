<?php

namespace App\Policies;

use App\Models\ComplianceRuleVersion;
use App\Models\User;
use App\Policies\Concerns\TenantScopedAuthorization;

class ComplianceRuleVersionPolicy
{
    use TenantScopedAuthorization;

    public function update(User $user, ComplianceRuleVersion $version): bool
    {
        return $version->status !== ComplianceRuleVersion::STATUS_APPROVED;
    }

    public function delete(User $user, ComplianceRuleVersion $version): bool
    {
        return $version->status !== ComplianceRuleVersion::STATUS_APPROVED;
    }
}
