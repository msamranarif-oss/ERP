<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Supplier;
use Illuminate\Auth\Access\HandlesAuthorization;

class SupplierPolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return parent::viewAny($user) && $user->can('view-suppliers');
    }

    public function view(User $user, $model)
    {
        return parent::view($user, $model) && $user->can('view-suppliers');
    }

    public function create(User $user)
    {
        return parent::create($user) && $user->can('create-suppliers');
    }

    public function update(User $user, $model)
    {
        return parent::update($user, $model) && $user->can('edit-suppliers');
    }

    public function delete(User $user, $model)
    {
        return parent::delete($user, $model) && $user->can('delete-suppliers');
    }
}
