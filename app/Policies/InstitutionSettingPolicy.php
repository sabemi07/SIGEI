<?php

namespace App\Policies;

use App\Models\Enums\AccountRole;
use App\Models\User;

class InstitutionSettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->role === AccountRole::Administrator;
    }

    public function update(User $user): bool
    {
        return $this->viewAny($user);
    }
}
