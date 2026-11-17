<?php

namespace App\Features;

use App\Models\User;
use Illuminate\Support\Lottery;

class Payroll
{
    /**
     * Resolve the feature's initial value.
     */
    public function resolve(User $user): mixed
    {
        // return match (true) {
        //     $user->isInternalTeamMember() => true,
        //     $user->isHighTrafficCustomer() => false,
        //     default => Lottery::odds(1 / 100),
        // };

        return false;
    }
}
