<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return parent::viewAny($user) && $user->can('view-users');
    }

    public function view(User $user, $model)
    {
        return parent::view($user, $model) && $user->can('view-users');
    }

    public function create(User $user)
    {
        return parent::create($user) && $user->can('create-users');
    }

    public function update(User $user, $model)
    {
        return parent::update($user, $model) && $user->can('edit-users');
    }

    public function delete(User $user, $model)
    {
        return parent::delete($user, $model) && $user->can('delete-users');
    }
}
