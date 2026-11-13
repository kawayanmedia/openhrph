<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CompanyPolicy
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
     * @param  \App\Models\Company $company
     * @return mixed
     */
    public function update(User $user)
    {
        if ($user->email == 'manny.isles@gmail.com') {
            return true;
        }

        return false;
    }

    public function create()
    {
        return true;
    }

    public function viewAny(User $user)
    {
        if ($user->email == 'manny.isles@gmail.com') {
            return true;
        }

        return false;
    }

    public function view(User $user)
    {
        if ($user->email == 'manny.isles@gmail.com') {
            return true;
        }

        return false;
    }

    public function delete(User $user, Company $company)
    {
        if ($user->email == 'manny.isles@gmail.com') {
            return true;
        }

        return false;
    }
}
