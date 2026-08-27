<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class PatientContextService
{
    private const CACHE_PREFIX = 'patient_context:';
    private const CACHE_TTL = 3600; // 1 hour

    /**
     * Check if a user can access a specific patient.
     */
    public function canAccessPatient(User $user, int $patientId): bool
    {
        $patient = Patient::find($patientId);

        if (! $patient) {
            return false;
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

    /**
     * Set patient context for a user.
     */
    public function setContext(User $user, int $patientId): array
    {
        $patient = Patient::with('user')->find($patientId);

        $context = [
            'patient_id' => $patient->id,
            'patient_user_id' => $patient->user_id,
            'mrn' => $patient->mrn,
            'full_name' => trim($patient->first_name . ' ' . $patient->last_name),
            'date_of_birth' => $patient->date_of_birth?->toDateString(),
            'gender' => $patient->gender,
            'phone' => $patient->phone,
            'email' => $patient->email,
        ];

        $cacheKey = self::CACHE_PREFIX . $user->id;
        Cache::put($cacheKey, $context, self::CACHE_TTL);

        return $context;
    }

    /**
     * Get current patient context for a user.
     */
    public function getContext(User $user): ?array
    {
        $cacheKey = self::CACHE_PREFIX . $user->id;

        return Cache::get($cacheKey);
    }

    /**
     * Clear patient context for a user.
     */
    public function clearContext(User $user): void
    {
        $cacheKey = self::CACHE_PREFIX . $user->id;
        Cache::forget($cacheKey);
    }

    /**
     * Search patients for context selection.
     */
    public function searchPatients(User $user, string $query): array
    {
        return Patient::where('organization_id', $user->organization_id)
            ->where(function ($q) use ($query) {
                $q->where('first_name', 'like', "%{$query}%")
                    ->orWhere('last_name', 'like', "%{$query}%")
                    ->orWhere('mrn', 'like', "%{$query}%")
                    ->orWhereHas('user', function ($q) use ($query) {
                        $q->where('email', 'like', "%{$query}%");
                    });
            })
            ->limit(20)
            ->get()
            ->map(fn ($patient) => [
                'id' => $patient->id,
                'user_id' => $patient->user_id,
                'mrn' => $patient->mrn,
                'full_name' => trim($patient->first_name . ' ' . $patient->last_name),
                'date_of_birth' => $patient->date_of_birth?->toDateString(),
            ])
            ->toArray();
    }

    /**
     * Get the patient user ID from context (for AI queries).
     */
    public function getPatientUserId(User $user): ?int
    {
        $context = $this->getContext($user);

        return $context['patient_user_id'] ?? null;
    }
}
