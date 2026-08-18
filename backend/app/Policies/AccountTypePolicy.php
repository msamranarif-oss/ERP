<?php

namespace App\Policies;

use App\Models\User;
use App\Models\AccountType;
use Illuminate\Auth\Access\HandlesAuthorization;

class AccountTypePolicy extends BasePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user)
    {
        return parent::viewAny($user);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, $accountType)
    {
        return parent::view($user, $accountType);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user)
    {
        return parent::create($user) && $user->hasPermissionTo('manage-account-types');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, $accountType)
    {
        return parent::update($user, $accountType) && $user->hasPermissionTo('manage-account-types');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, $accountType)
    {
        return parent::delete($user, $accountType) && $user->hasPermissionTo('manage-account-types');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, $accountType)
    {
        return parent::restore($user, $accountType) && $user->hasPermissionTo('manage-account-types');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, $accountType)
    {
        return parent::forceDelete($user, $accountType) && $user->hasPermissionTo('manage-account-types');
    }
}