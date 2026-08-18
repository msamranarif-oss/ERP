<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Category;
use Illuminate\Auth\Access\HandlesAuthorization;

class CategoryPolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return parent::viewAny($user) && $user->can('view-categories');
    }

    public function view(User $user, $model)
    {
        return parent::view($user, $model) && $user->can('view-categories');
    }

    public function create(User $user)
    {
        return parent::create($user) && $user->can('create-categories');
    }

    public function update(User $user, $model)
    {
        return parent::update($user, $model) && $user->can('edit-categories');
    }

    public function delete(User $user, $model)
    {
        return parent::delete($user, $model) && $user->can('delete-categories');
    }
}
