<?php

namespace App\Policies;

use App\Models\Loan;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class LoanPolicy
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
    public function update(User $user, Loan $loan)
    {
        return true;
    }

    public function create()
    {
        return true;
    }

    public function view()
    {
        return true;
    }

    public function delete(User $user, Loan $loan)
    {
        return false;
    }
}
