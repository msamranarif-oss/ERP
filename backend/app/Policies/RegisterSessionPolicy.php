<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class RegisterSessionPolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return parent::viewAny($user) && ($user->can('view-pos-sessions') || $user->can('manage-cash-register'));
    }

    public function view(User $user, $model)
    {
        return parent::view($user, $model) && ($user->can('view-pos-sessions') || $user->can('manage-cash-register'));
    }

    public function create(User $user)
    {
        return parent::create($user) && ($user->can('manage-pos-sessions') || $user->can('manage-cash-register'));
    }

    public function update(User $user, $model)
    {
        return parent::update($user, $model) && ($user->can('manage-pos-sessions') || $user->can('manage-cash-register'));
    }

    public function delete(User $user, $model)
    {
        return parent::delete($user, $model) && ($user->can('manage-pos-sessions') || $user->can('manage-cash-register'));
    }
}
