<?php

namespace App\Policies;

use App\Models\User;
use App\Models\StockTransfer;
use Illuminate\Auth\Access\HandlesAuthorization;

class StockTransferPolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return parent::viewAny($user) && $user->can('view-stock-transfers');
    }

    public function view(User $user, $model)
    {
        return parent::view($user, $model) && $user->can('view-stock-transfers');
    }

    public function create(User $user)
    {
        return parent::create($user) && $user->can('create-stock-transfers');
    }

    public function update(User $user, $model)
    {
        return parent::update($user, $model) && $user->can('edit-stock-transfers');
    }

    public function delete(User $user, $model)
    {
        return parent::delete($user, $model) && $user->can('delete-stock-transfers');
    }
}
