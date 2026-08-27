<?php

namespace App\Policies;

use App\Models\Medication;
use App\Models\User;

class MedicationPolicy
{
    public function view(User $user, Medication $medication): bool
    {
        // Patient owns the medication
        if ($user->isPatient() && $user->id === $medication->user_id) {
            return true;
        }

        // Clinician with access to patient
        if ($user->isClinician()) {
            return $user->clinicianPatients()
                ->where('patient_user_id', $medication->user_id)
                ->exists();
        }

        // Nursing staff in same organization
        if ($user->isNursingStaff()) {
            return $user->organization_id === $medication->organization_id;
        }

        return false;
    }
}
