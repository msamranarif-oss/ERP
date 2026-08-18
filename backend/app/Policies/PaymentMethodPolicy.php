<?php

namespace App\Policies;

use App\Models\User;
use App\Models\PaymentMethod;
use Illuminate\Auth\Access\HandlesAuthorization;

class PaymentMethodPolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return parent::viewAny($user) && $user->can('view-payment-methods');
    }

    public function view(User $user, $model)
    {
        return parent::view($user, $model) && $user->can('view-payment-methods');
    }

    public function create(User $user)
    {
        return parent::create($user) && $user->can('manage-payment-methods');
    }

    public function update(User $user, $model)
    {
        return parent::update($user, $model) && $user->can('manage-payment-methods');
    }

    public function delete(User $user, $model)
    {
        return parent::delete($user, $model) && $user->can('manage-payment-methods');
    }
}
