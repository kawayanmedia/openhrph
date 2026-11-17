<?php

namespace App\Policies;

use App\Models\Compensation;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * The compensation catalogue is system-wide and read-only for companies. A
 * company may only maintain its own override rows.
 */
class CompensationPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Compensation $compensation): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Compensation $compensation): bool
    {
        return $this->ownsOverride($user, $compensation);
    }

    public function delete(User $user, Compensation $compensation): bool
    {
        return $this->ownsOverride($user, $compensation);
    }

    private function ownsOverride(User $user, Compensation $compensation): bool
    {
        $companyId = $compensation->getAttribute('company_id');

        return $companyId !== null && (int) $companyId === (int) $user->company_id;
    }
}
