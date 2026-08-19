<?php

namespace App\Services;

use App\Models\LabResult;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Manages clinician access to patient records.
 *
 * A clinician may only ever access patients they have been explicitly granted
 * access to. The granted relationship is the single source of truth for
 * authorization — there is no implicit access by role alone.
 */
final class ClinicianService
{
    /**
     * Patients the clinician is authorized to view, each with the date of
     * their most recent lab result.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function assignedPatients(User $clinician): Collection
    {
        $patients = $clinician->clinicianPatients()
            ->orderBy('name')
            ->get();

        $lastLabDates = LabResult::query()
            ->whereIn('user_id', $patients->pluck('id'))
            ->groupBy('user_id')
            ->selectRaw('user_id, MAX(collected_at) AS last_lab_date')
            ->pluck('last_lab_date', 'user_id');

        return $patients->map(function (User $patient) use ($lastLabDates): array {
            $lastLabDate = $lastLabDates[$patient->id] ?? null;

            return [
                'id' => $patient->id,
                'name' => $patient->name,
                'email' => $patient->email,
                'last_lab_date' => $lastLabDate !== null
                    ? Carbon::parse($lastLabDate)->toISOString()
                    : null,
            ];
        })->values();
    }

    /**
     * Whether the clinician may view this patient's record.
     */
    public function hasAccess(User $clinician, User $patient): bool
    {
        return $clinician->clinicianPatients()
            ->where('patient_user_id', $patient->id)
            ->exists();
    }

    /**
     * Grant a clinician access to a patient. Returns true when the access was
     * newly created, false when it already existed.
     */
    public function grantAccess(User $clinician, User $patient): bool
    {
        $result = $clinician->clinicianPatients()->syncWithoutDetaching([$patient->id]);

        return $result['attached'] !== [];
    }
}