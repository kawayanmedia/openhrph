<?php

namespace App\Policies;

use App\Models\Bonus;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class BonusPolicy
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
    public function update(User $user, Bonus $bonus)
    {
        // Dont allow to update if 13th Month Pay
        return $bonus->name != Bonus::BONUS_13TH_MONTH;
    }

    public function create()
    {
        return true;
    }

    public function view()
    {
        return true;
    }

    public function delete(User $user, Bonus $bonus)
    {
        // Dont allow to delete if 13th Month Pay
        return $bonus->name != Bonus::BONUS_13TH_MONTH;
    }
}
