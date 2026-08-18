<?php

namespace App\Policies;

use App\Models\User;
use App\Models\JournalEntry;
use Illuminate\Auth\Access\HandlesAuthorization;

class JournalEntryPolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return parent::viewAny($user) && $user->can('view-journal-entries');
    }

    public function view(User $user, $model)
    {
        return parent::view($user, $model) && $user->can('view-journal-entries');
    }

    public function create(User $user)
    {
        return parent::create($user) && $user->can('create-journal-entries');
    }

    public function update(User $user, $model)
    {
        return parent::update($user, $model) && $user->can('edit-journal-entries');
    }

    public function delete(User $user, $model)
    {
        return parent::delete($user, $model) && $user->can('void-journal-entries');
    }
}
