<?php

namespace App\Policies;

use App\Models\User;
use App\Models\InstallmentPayment;
use Illuminate\Auth\Access\HandlesAuthorization;

class InstallmentPaymentPolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return parent::viewAny($user);
    }

    public function view(User $user, $installmentPayment)
    {
        return parent::view($user, $installmentPayment);
    }

    public function create(User $user)
    {
        return parent::create($user) && $user->hasPermissionTo('manage-payments');
    }

    public function update(User $user, $installmentPayment)
    {
        return parent::update($user, $installmentPayment) && $user->hasPermissionTo('manage-payments');
    }

    public function delete(User $user, $installmentPayment)
    {
        return parent::delete($user, $installmentPayment) && $user->hasPermissionTo('manage-payments');
    }

    public function restore(User $user, $installmentPayment)
    {
        return parent::restore($user, $installmentPayment) && $user->hasPermissionTo('manage-payments');
    }

    public function forceDelete(User $user, $installmentPayment)
    {
        return parent::forceDelete($user, $installmentPayment) && $user->hasPermissionTo('manage-payments');
    }
}