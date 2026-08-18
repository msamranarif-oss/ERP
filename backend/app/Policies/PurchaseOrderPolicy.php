<?php

namespace App\Policies;

use App\Models\User;
use App\Models\PurchaseOrder;
use Illuminate\Auth\Access\HandlesAuthorization;

class PurchaseOrderPolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return parent::viewAny($user) && $user->can('view-purchases');
    }

    public function view(User $user, $model)
    {
        return parent::view($user, $model) && $user->can('view-purchases');
    }

    public function create(User $user)
    {
        return parent::create($user) && $user->can('create-purchases');
    }

    public function update(User $user, $model)
    {
        return parent::update($user, $model) && $user->can('edit-purchases');
    }

    public function delete(User $user, $model)
    {
        return parent::delete($user, $model) && $user->can('delete-purchases');
    }
}
