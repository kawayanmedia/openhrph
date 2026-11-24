<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Company management is platform-wide, not tenant-scoped.
 *
 * Platform administrators manage every company. Company owners manage the
 * companies they own, and any owner may create another company under their
 * own account.
 */
class CompanyPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->isPlatformAdmin() || $user->companyMemberships()->isNotEmpty();
    }

    public function view(User $user, Company $company): bool
    {
        return $user->isPlatformAdmin() || $user->belongsToCompany((int) $company->getKey());
    }

    public function create(User $user): bool
    {
        return $user->isPlatformAdmin() || $user->ownsAnyCompany();
    }

    public function update(User $user, Company $company): bool
    {
        return $user->isPlatformAdmin() || $user->ownsCompany((int) $company->getKey());
    }

    public function delete(User $user, Company $company): bool
    {
        return $user->isPlatformAdmin();
    }
}
