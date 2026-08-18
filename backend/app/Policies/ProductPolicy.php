<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Product;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProductPolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return parent::viewAny($user) && $user->can('view-products');
    }

    public function view(User $user, $model)
    {
        return parent::view($user, $model) && $user->can('view-products');
    }

    public function create(User $user)
    {
        return parent::create($user) && $user->can('create-products');
    }

    public function update(User $user, $model)
    {
        return parent::update($user, $model) && $user->can('edit-products');
    }

    public function delete(User $user, $model)
    {
        return parent::delete($user, $model) && $user->can('delete-products');
    }
}
