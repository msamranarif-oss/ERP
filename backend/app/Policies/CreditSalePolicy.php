<?php

namespace App\Policies;

use App\Models\User;
use App\Models\CreditSale;
use Illuminate\Auth\Access\HandlesAuthorization;

class CreditSalePolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return parent::viewAny($user) && $user->can('view-credit-sales');
    }

    public function view(User $user, $model)
    {
        return parent::view($user, $model) && $user->can('view-credit-sales');
    }

    public function create(User $user)
    {
        return parent::create($user) && $user->can('create-credit-sales');
    }

    public function update(User $user, $model)
    {
        return parent::update($user, $model) && $user->can('edit-credit-sales');
    }

    public function delete(User $user, $model)
    {
        return parent::delete($user, $model) && $user->can('delete-credit-sales');
    }
}
