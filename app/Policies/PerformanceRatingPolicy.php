<?php

namespace App\Policies;

use App\Models\PerformanceRating;
use App\Models\User;
use App\Policies\Concerns\TenantScopedAuthorization;

class PerformanceRatingPolicy
{
    use TenantScopedAuthorization;

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, PerformanceRating $model): bool
    {
        return false;
    }

    public function delete(User $user, PerformanceRating $model): bool
    {
        return false;
    }
}
