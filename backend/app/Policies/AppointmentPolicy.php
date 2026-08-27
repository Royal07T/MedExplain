<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;

class AppointmentPolicy
{
    public function view(User $user, Appointment $appointment): bool
    {
        // Patient can view own appointments
        if ($user->isPatient()) {
            return $user->id === $appointment->patient_id;
        }

        // Clinician can view their own appointments
        if ($user->isClinician()) {
            return $appointment->clinician_id === $user->id
                && $user->organization_id === $appointment->organization_id;
        }

        // Nursing staff in same organization
        if ($user->isNursingStaff()) {
            return $user->organization_id === $appointment->organization_id;
        }

        // Admin in same organization
        if ($user->isAdmin() || $user->isSuperAdmin()) {
            return $user->organization_id === $appointment->organization_id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['clinician', 'admin', 'super_admin']);
    }
}
