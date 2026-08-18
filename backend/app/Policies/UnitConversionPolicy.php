<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UnitConversion;
use Illuminate\Auth\Access\HandlesAuthorization;

class UnitConversionPolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return parent::viewAny($user);
    }

    public function view(User $user, $unitConversion)
    {
        return parent::view($user, $unitConversion);
    }

    public function create(User $user)
    {
        return parent::create($user) && $user->hasPermissionTo('manage-units');
    }

    public function update(User $user, $unitConversion)
    {
        return parent::update($user, $unitConversion) && $user->hasPermissionTo('manage-units');
    }

    public function delete(User $user, $unitConversion)
    {
        return parent::delete($user, $unitConversion) && $user->hasPermissionTo('manage-units');
    }

    public function restore(User $user, $unitConversion)
    {
        return parent::restore($user, $unitConversion) && $user->hasPermissionTo('manage-units');
    }

    public function forceDelete(User $user, $unitConversion)
    {
        return parent::forceDelete($user, $unitConversion) && $user->hasPermissionTo('manage-units');
    }
}