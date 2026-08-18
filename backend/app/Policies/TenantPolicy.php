<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Auth\Access\HandlesAuthorization;

class TenantPolicy
{
    use HandlesAuthorization;

    public function view(User $user, Tenant $tenant)
    {
        return $user->tenant_id === $tenant->id;
    }

    public function update(User $user, Tenant $tenant)
    {
        return $user->tenant_id === $tenant->id && $user->hasRole(['admin', 'super-admin']);
    }

    public function manageSettings(User $user, Tenant $tenant)
    {
        return $user->tenant_id === $tenant->id && $user->hasRole(['admin', 'super-admin']);
    }
}
