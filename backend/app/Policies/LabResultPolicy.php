<?php

namespace App\Policies;

use App\Models\LabResult;
use App\Models\User;

class LabResultPolicy
{
    public function view(User $user, LabResult $labResult): bool
    {
        // Patient owns the result
        if ($user->isPatient() && $user->id === $labResult->user_id) {
            return true;
        }

        // Clinician with access to patient
        if ($user->isClinician()) {
            return $user->clinicianPatients()
                ->where('patient_user_id', $labResult->user_id)
                ->exists();
        }

        // Nursing staff in same organization
        if ($user->isNursingStaff()) {
            return $user->organization_id === $labResult->organization_id;
        }

        // Admin/super_admin in same organization
        if ($user->isAdmin() || $user->isSuperAdmin()) {
            return $user->organization_id === $labResult->organization_id;
        }

        return false;
    }
}
