<?php

namespace App\Policies;

use App\Models\PaymentReminder;
use App\Models\User;

class PaymentReminderPolicy
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
     * @param  \App\Models\PaymentReminder  $paymentReminder
     * @return bool
     */
    public function view(User $user, PaymentReminder $paymentReminder)
    {
        return $paymentReminder->tenant_id === $user->tenant_id;
    }

    /**
     * Determine if the user can create models.
     *
     * @param  \App\Models\User  $user
     * @return bool
     */
    public function create(User $user)
    {
        return $user->tenant_id !== null && $user->can('create-payment-reminders');
    }

    /**
     * Determine if the user can update the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\PaymentReminder  $paymentReminder
     * @return bool
     */
    public function update(User $user, PaymentReminder $paymentReminder)
    {
        return $paymentReminder->tenant_id === $user->tenant_id && $user->can('edit-payment-reminders');
    }

    /**
     * Determine if the user can delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\PaymentReminder  $paymentReminder
     * @return bool
     */
    public function delete(User $user, PaymentReminder $paymentReminder)
    {
        return $paymentReminder->tenant_id === $user->tenant_id && $user->can('delete-payment-reminders');
    }

    /**
     * Determine if the user can restore the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\PaymentReminder  $paymentReminder
     * @return bool
     */
    public function restore(User $user, PaymentReminder $paymentReminder)
    {
        return $paymentReminder->tenant_id === $user->tenant_id;
    }

    /**
     * Determine if the user can permanently delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\PaymentReminder  $paymentReminder
     * @return bool
     */
    public function forceDelete(User $user, PaymentReminder $paymentReminder)
    {
        return $paymentReminder->tenant_id === $user->tenant_id;
    }
}