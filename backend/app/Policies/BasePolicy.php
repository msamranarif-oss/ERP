<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Tenant;

class BasePolicy
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
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return bool
     */
    public function view(User $user, $model)
    {
        return $model->tenant_id === $user->tenant_id;
    }

    /**
     * Determine if the user can create models.
     *
     * @param  \App\Models\User  $user
     * @return bool
     */
    public function create(User $user)
    {
        return $user->tenant_id !== null;
    }

    /**
     * Determine if the user can update the model.
     *
     * @param  \App\Models\User  $user
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return bool
     */
    public function update(User $user, $model)
    {
        return $model->tenant_id === $user->tenant_id;
    }

    /**
     * Determine if the user can delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return bool
     */
    public function delete(User $user, $model)
    {
        return $model->tenant_id === $user->tenant_id;
    }

    /**
     * Determine if the user can restore the model.
     *
     * @param  \App\Models\User  $user
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return bool
     */
    public function restore(User $user, $model)
    {
        return $model->tenant_id === $user->tenant_id;
    }

    /**
     * Determine if the user can permanently delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return bool
     */
    public function forceDelete(User $user, $model)
    {
        return $model->tenant_id === $user->tenant_id;
    }
}