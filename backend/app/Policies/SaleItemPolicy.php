<?php

namespace App\Policies;

use App\Models\User;
use App\Models\SaleItem;
use Illuminate\Auth\Access\HandlesAuthorization;

class SaleItemPolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return parent::viewAny($user);
    }

    public function view(User $user, $saleItem)
    {
        return parent::view($user, $saleItem);
    }

    public function create(User $user)
    {
        return parent::create($user) && $user->hasPermissionTo('manage-sales');
    }

    public function update(User $user, $saleItem)
    {
        return parent::update($user, $saleItem) && $user->hasPermissionTo('manage-sales');
    }

    public function delete(User $user, $saleItem)
    {
        return parent::delete($user, $saleItem) && $user->hasPermissionTo('manage-sales');
    }

    public function restore(User $user, $saleItem)
    {
        return parent::restore($user, $saleItem) && $user->hasPermissionTo('manage-sales');
    }

    public function forceDelete(User $user, $saleItem)
    {
        return parent::forceDelete($user, $saleItem) && $user->hasPermissionTo('manage-sales');
    }
}