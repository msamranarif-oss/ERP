<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    /**
     * Determine if the user can view any models of the given type.
     *
     * @param  \App\Models\User  $user
     * @return bool
     */
    public function viewAny(User $user)
    {
        return $user->tenant_id !== null;
    }

    /**
     * Determine if the user can view the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Payment  $payment
     * @return bool
     */
    public function view(User $user, Payment $payment)
    {
        return $payment->tenant_id === $user->tenant_id;
    }

    /**
     * Determine if the user can create models.
     *
     * @param  \App\Models\User  $user
     * @return bool
     */
    public function create(User $user)
    {
        return $user->tenant_id !== null && $user->can('create-payments');
    }

    /**
     * Determine if the user can update the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Payment  $payment
     * @return bool
     */
    public function update(User $user, Payment $payment)
    {
        return $payment->tenant_id === $user->tenant_id && $user->can('edit-payments');
    }

    /**
     * Determine if the user can delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Payment  $payment
     * @return bool
     */
    public function delete(User $user, Payment $payment)
    {
        return $payment->tenant_id === $user->tenant_id && $user->can('delete-payments');
    }

    /**
     * Determine if the user can restore the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Payment  $payment
     * @return bool
     */
    public function restore(User $user, Payment $payment)
    {
        return $payment->tenant_id === $user->tenant_id;
    }

    /**
     * Determine if the user can permanently delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Payment  $payment
     * @return bool
     */
    public function forceDelete(User $user, Payment $payment)
    {
        return $payment->tenant_id === $user->tenant_id;
    }
}