<?php

namespace App\Policies;

use App\Models\Enums\AccountRole;
use App\Models\Enums\IncidentStatus;
use App\Models\Incident;
use App\Models\User;

class IncidentPolicy
{
    public function view(User $user, Incident $incident): bool
    {
        return $this->isAdministrator($user) || ($this->isResponsible($user)
            && ($incident->reporter_user_id === $user->id || $this->isAssigned($user, $incident)));
    }

    public function viewInternal(User $user, Incident $incident): bool
    {
        return $this->isAdministrator($user) || ($this->isResponsible($user) && $this->isAssigned($user, $incident));
    }

    public function assign(User $user, Incident $incident): bool
    {
        return $this->isAdministrator($user) && $incident->status !== IncidentStatus::Closed;
    }

    public function followUp(User $user, Incident $incident): bool
    {
        return $this->isResponsible($user) && ! in_array($incident->status, [IncidentStatus::Attended, IncidentStatus::Closed], true)
            && $this->isAssigned($user, $incident);
    }

    public function confirmReceipt(User $user, Incident $incident): bool
    {
        return $this->followUp($user, $incident)
            && $incident->assignments()->whereNull('ended_at')->where('assigned_to', $user->id)->whereNull('received_at')->exists();
    }

    public function close(User $user, Incident $incident): bool
    {
        return $this->isAdministrator($user) && $incident->status === IncidentStatus::Attended;
    }

    private function isAssigned(User $user, Incident $incident): bool
    {
        return $incident->assignments()->whereNull('ended_at')->where('assigned_to', $user->id)->exists();
    }

    private function isAdministrator(User $user): bool
    {
        return $user->is_active && $user->role === AccountRole::Administrator;
    }

    private function isResponsible(User $user): bool
    {
        return $user->is_active && $user->role === AccountRole::Responsible;
    }
}
