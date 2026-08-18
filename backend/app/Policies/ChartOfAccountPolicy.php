<?php

namespace App\Policies;

use App\Models\User;
use App\Models\ChartOfAccount;
use Illuminate\Auth\Access\HandlesAuthorization;

class ChartOfAccountPolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return parent::viewAny($user) && $user->can('view-chart-of-accounts');
    }

    public function view(User $user, $model)
    {
        return parent::view($user, $model) && $user->can('view-chart-of-accounts');
    }

    public function create(User $user)
    {
        return parent::create($user) && $user->can('manage-chart-of-accounts');
    }

    public function update(User $user, $model)
    {
        return parent::update($user, $model) && $user->can('manage-chart-of-accounts');
    }

    public function delete(User $user, $model)
    {
        return parent::delete($user, $model) && $user->can('manage-chart-of-accounts');
    }
}
