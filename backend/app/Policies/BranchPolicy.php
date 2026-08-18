<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Branch;
use Illuminate\Auth\Access\HandlesAuthorization;

class BranchPolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return parent::viewAny($user) && $user->can('view-branches');
    }

    public function view(User $user, $model)
    {
        return parent::view($user, $model) && $user->can('view-branches');
    }

    public function create(User $user)
    {
        return parent::create($user) && $user->can('create-branches');
    }

    public function update(User $user, $model)
    {
        return parent::update($user, $model) && $user->can('edit-branches');
    }

    public function delete(User $user, $model)
    {
        return parent::delete($user, $model) && $user->can('delete-branches');
    }
}
