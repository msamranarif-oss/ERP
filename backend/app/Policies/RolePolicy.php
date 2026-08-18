<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class RolePolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return parent::viewAny($user) && $user->can('view-roles');
    }

    public function view(User $user, $model)
    {
        return parent::view($user, $model) && $user->can('view-roles');
    }

    public function create(User $user)
    {
        return parent::create($user) && $user->can('create-roles');
    }

    public function update(User $user, $model)
    {
        return parent::update($user, $model) && $user->can('edit-roles');
    }

    public function delete(User $user, $model)
    {
        return parent::delete($user, $model) && $user->can('delete-roles');
    }
}
