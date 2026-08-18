<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Customer;
use Illuminate\Auth\Access\HandlesAuthorization;

class CustomerPolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return parent::viewAny($user) && $user->can('view-customers');
    }

    public function view(User $user, $model)
    {
        return parent::view($user, $model) && $user->can('view-customers');
    }

    public function create(User $user)
    {
        return parent::create($user) && $user->can('create-customers');
    }

    public function update(User $user, $model)
    {
        return parent::update($user, $model) && $user->can('edit-customers');
    }

    public function delete(User $user, $model)
    {
        return parent::delete($user, $model) && $user->can('delete-customers');
    }
}
