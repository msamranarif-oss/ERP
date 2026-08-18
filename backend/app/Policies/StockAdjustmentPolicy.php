<?php

namespace App\Policies;

use App\Models\User;
use App\Models\StockAdjustment;
use Illuminate\Auth\Access\HandlesAuthorization;

class StockAdjustmentPolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return parent::viewAny($user) && $user->can('view-stock-adjustments');
    }

    public function view(User $user, $model)
    {
        return parent::view($user, $model) && $user->can('view-stock-adjustments');
    }

    public function create(User $user)
    {
        return parent::create($user) && $user->can('create-stock-adjustments');
    }

    public function update(User $user, $model)
    {
        return parent::update($user, $model) && $user->can('edit-stock-adjustments');
    }

    public function delete(User $user, $model)
    {
        return parent::delete($user, $model) && $user->can('delete-stock-adjustments');
    }
}
