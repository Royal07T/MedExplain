<?php

namespace App\Services;

use App\Models\Medication;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Medication history for the authenticated user's health record.
 */
final class MedicationService
{
    /**
     * All medications recorded for the user, newest first.
     *
     * @return Collection<int, Medication>
     */
    public function forUser(User $user): Collection
    {
        return Medication::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->orderBy('sort_order')
            ->get();
    }
}
