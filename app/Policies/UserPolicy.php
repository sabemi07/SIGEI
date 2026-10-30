<?php

namespace App\Policies;

use App\Models\Enums\AccountRole;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->role === AccountRole::Administrator;
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, User $account): bool
    {
        return $this->viewAny($user);
    }
}
