<?php

namespace App\Policies;

use App\Models\ScheduleAdjustmentRequest;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ScheduleAdjustmentRequestPolicy
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
        return can_see_filament('requests', $user);
    }

    /**
     * Determine whether the user can update the post.
     *
     * @param  \App\Models\Post  $post
     * @return mixed
     */
    public function update(User $user, ScheduleAdjustmentRequest $request)
    {
        return true;
    }

    public function create()
    {
        return false;
    }

    public function view()
    {
        return true;
    }

    public function delete(User $user, ScheduleAdjustmentRequest $Request)
    {
        return false;
    }
}
