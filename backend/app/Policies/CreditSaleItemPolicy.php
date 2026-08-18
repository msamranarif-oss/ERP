<?php

namespace App\Policies;

use App\Models\CreditSaleItem;
use App\Models\User;

class CreditSaleItemPolicy
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
     * @param  \App\Models\CreditSaleItem  $creditSaleItem
     * @return bool
     */
    public function view(User $user, CreditSaleItem $creditSaleItem)
    {
        return $creditSaleItem->tenant_id === $user->tenant_id;
    }

    /**
     * Determine if the user can create models.
     *
     * @param  \App\Models\User  $user
     * @return bool
     */
    public function create(User $user)
    {
        return $user->tenant_id !== null && $user->can('create-credit-sale-items');
    }

    /**
     * Determine if the user can update the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\CreditSaleItem  $creditSaleItem
     * @return bool
     */
    public function update(User $user, CreditSaleItem $creditSaleItem)
    {
        return $creditSaleItem->tenant_id === $user->tenant_id && $user->can('edit-credit-sale-items');
    }

    /**
     * Determine if the user can delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\CreditSaleItem  $creditSaleItem
     * @return bool
     */
    public function delete(User $user, CreditSaleItem $creditSaleItem)
    {
        return $creditSaleItem->tenant_id === $user->tenant_id && $user->can('delete-credit-sale-items');
    }

    /**
     * Determine if the user can restore the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\CreditSaleItem  $creditSaleItem
     * @return bool
     */
    public function restore(User $user, CreditSaleItem $creditSaleItem)
    {
        return $creditSaleItem->tenant_id === $user->tenant_id;
    }

    /**
     * Determine if the user can permanently delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\CreditSaleItem  $creditSaleItem
     * @return bool
     */
    public function forceDelete(User $user, CreditSaleItem $creditSaleItem)
    {
        return $creditSaleItem->tenant_id === $user->tenant_id;
    }
}