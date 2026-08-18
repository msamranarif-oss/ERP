<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Auth\Access\HandlesAuthorization;

class WarehousePolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return parent::viewAny($user) && $user->can('view-warehouses');
    }

    public function view(User $user, $model)
    {
        return parent::view($user, $model) && $user->can('view-warehouses');
    }

    public function create(User $user)
    {
        return parent::create($user) && $user->can('create-warehouses');
    }

    public function update(User $user, $model)
    {
        return parent::update($user, $model) && $user->can('edit-warehouses');
    }

    public function delete(User $user, $model)
    {
        return parent::delete($user, $model) && $user->can('delete-warehouses');
    }
}
