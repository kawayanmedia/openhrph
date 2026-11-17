<?php

namespace App\Policies;

use App\Models\PayrollGroup;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PayrollGroupPolicy
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

    public function viewAny(User $user)
    {
        return true;
        // return $user->company_id == $office->company_id;
    }

    /**
     * Determine whether the user can update the post.
     *
     * @param  \App\Models\Post  $post
     * @return mixed
     */
    public function update(User $user, PayrollGroup $office)
    {
        return $user->company_id == $office->company_id;
    }

    public function create()
    {
        return true;
    }

    public function view(User $user, PayrollGroup $office)
    {
        return $user->company_id == $office->company_id;
    }

    public function delete(User $user, PayrollGroup $office)
    {
        return $user->company_id == $office->company_id;
    }
}
