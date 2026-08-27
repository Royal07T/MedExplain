<?php

namespace App\Policies;

use App\Models\Encounter;
use App\Models\User;

class EncounterPolicy
{
    public function view(User $user, Encounter $encounter): bool
    {
        if ($user->isClinician()) {
            return $encounter->clinician_id === $user->id
                && $user->organization_id === $encounter->organization_id;
        }

        if ($user->isNursingStaff()) {
            return $user->organization_id === $encounter->organization_id;
        }

        if ($user->isAdmin() || $user->isSuperAdmin()) {
            return $user->organization_id === $encounter->organization_id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('encounters.create');
    }

    public function update(User $user, Encounter $encounter): bool
    {
        if (!$user->hasPermissionTo('encounters.update')) {
            return false;
        }

        return $user->organization_id === $encounter->organization_id;
    }
}
