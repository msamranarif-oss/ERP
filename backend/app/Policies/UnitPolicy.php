<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Unit;
use Illuminate\Auth\Access\HandlesAuthorization;

class UnitPolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return parent::viewAny($user) && $user->can('view-units');
    }

    public function view(User $user, $model)
    {
        return parent::view($user, $model) && $user->can('view-units');
    }

    public function create(User $user)
    {
        return parent::create($user) && $user->can('create-units');
    }

    public function update(User $user, $model)
    {
        return parent::update($user, $model) && $user->can('edit-units');
    }

    public function delete(User $user, $model)
    {
        return parent::delete($user, $model) && $user->can('delete-units');
    }
}
