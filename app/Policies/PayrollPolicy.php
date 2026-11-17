<?php

namespace App\Policies;

use App\Models\Payroll;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PayrollPolicy
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
    public function update(User $user, Payroll $payroll)
    {
        // Allow update if still open
        return ($payroll->is_open or $payroll->is_processed) && $user->company_id == $payroll->company_id;
    }

    public function delete(User $user, Payroll $payroll)
    {
        // Allow edit if still open
        return $payroll->is_open  && $user->company_id == $payroll->company_id;
    }

    public function create()
    {
        return true;
    }

    public function view(User $user, Payroll $payroll)
    {
        return $user->company_id == $payroll->company_id;
    }
}
