<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Sale;
use Illuminate\Auth\Access\HandlesAuthorization;

class SalePolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return parent::viewAny($user) && $user->can('view-sales');
    }

    public function view(User $user, $model)
    {
        return parent::view($user, $model) && $user->can('view-sales');
    }

    public function create(User $user)
    {
        return parent::create($user) && $user->can('create-sales');
    }

    public function update(User $user, $model)
    {
        return parent::update($user, $model) && $user->can('edit-sales');
    }

    public function delete(User $user, $model)
    {
        return parent::delete($user, $model) && $user->can('void-sales');
    }

    public function void(User $user, Sale $sale)
    {
        return $sale->tenant_id === $user->tenant_id && $user->can('void-sales');
    }
}
