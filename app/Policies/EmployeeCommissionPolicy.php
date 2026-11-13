<?php

namespace App\Policies;

use App\Models\EmployeeCommission;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class EmployeeCommissionPolicy
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
    public function update(User $user, EmployeeCommission $commission)
    {
        // Allow update it not yet disbursed
        return ! $commission->is_disbursed;
    }

    public function create()
    {
        return true;
    }

    public function view()
    {
        return true;
    }

    public function delete(User $user, EmployeeCommission $commission)
    {
        // Allow update it not yet disbursed
        return ! $commission->is_disbursed;
    }
}
