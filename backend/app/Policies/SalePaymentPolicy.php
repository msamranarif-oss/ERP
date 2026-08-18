<?php

namespace App\Policies;

use App\Models\User;
use App\Models\SalePayment;
use Illuminate\Auth\Access\HandlesAuthorization;

class SalePaymentPolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return parent::viewAny($user);
    }

    public function view(User $user, $salePayment)
    {
        return parent::view($user, $salePayment);
    }

    public function create(User $user)
    {
        return parent::create($user) && $user->hasPermissionTo('manage-payments');
    }

    public function update(User $user, $salePayment)
    {
        return parent::update($user, $salePayment) && $user->hasPermissionTo('manage-payments');
    }

    public function delete(User $user, $salePayment)
    {
        return parent::delete($user, $salePayment) && $user->hasPermissionTo('manage-payments');
    }

    public function restore(User $user, $salePayment)
    {
        return parent::restore($user, $salePayment) && $user->hasPermissionTo('manage-payments');
    }

    public function forceDelete(User $user, $salePayment)
    {
        return parent::forceDelete($user, $salePayment) && $user->hasPermissionTo('manage-payments');
    }
}