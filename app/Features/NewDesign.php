<?php

namespace App\Features;

use App\Models\User;

class NewDesign
{
    /**
     * Resolve the feature's initial value.
     */
    public function resolve(User $user): mixed
    {
        return match (true) {
            $user->newUser() => true,
            $user->oldUser() => false,
            default => false,
        };
    }
}
