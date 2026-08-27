<?php

namespace App\Policies;

use App\Models\Patient;
use App\Models\User;

class PatientPolicy
{
    public function view(User $user, Patient $patient): bool
    {
        // Patient can view own record
        if ($user->isPatient() && $user->id === $patient->user_id) {
            return true;
        }

        // Clinician with access grant
        if ($user->isClinician()) {
            return $user->clinicianPatients()
                ->where('patient_user_id', $patient->user_id)
                ->exists();
        }

        // Nursing staff in same organization
        if ($user->isNursingStaff()) {
            return $user->organization_id === $patient->organization_id;
        }

        // Admin/super_admin in same organization
        if ($user->isAdmin() || $user->isSuperAdmin()) {
            return $user->organization_id === $patient->organization_id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('patients.create');
    }

    public function update(User $user, Patient $patient): bool
    {
        if ($user->hasPermissionTo('patients.update')) {
            return $user->organization_id === $patient->organization_id;
        }

        return false;
    }
}
