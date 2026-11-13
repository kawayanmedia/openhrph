<?php

namespace App\Policies;

use App\Models\EmployeeBonus;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class EmployeeBonusPolicy
{
    use HandlesAuthorization;

    /**
     * Create a new policy instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Determine whether the user can update the post.
     *
     * @param  \App\Models\Post  $post
     * @return mixed
     */
    public function update(User $user, EmployeeBonus $bonus)
    {
        // Allow update it not yet disbursed
        return $bonus->status != EmployeeBonus::STATUS_DISBURSED;
    }

    public function create()
    {
        return true;
    }

    public function view()
    {
        return true;
    }

    public function delete(User $user, EmployeeBonus $bonus)
    {
        // Allow update it not yet disbursed
        return $bonus->status != EmployeeBonus::STATUS_DISBURSED;
    }
}
