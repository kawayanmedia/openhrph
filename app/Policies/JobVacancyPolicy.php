<?php

namespace App\Policies;

use App\Models\JobVacancy;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class JobVacancyPolicy
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
        return can_see_filament('job_vacancies', $user);
    }

    /**
     * Determine whether the user can update the post.
     *
     * @param  \App\Models\Post  $post
     * @return mixed
     */
    public function update(User $user, JobVacancy $request)
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

    public function delete(User $user, JobVacancy $Request)
    {
        return true;
    }
}
