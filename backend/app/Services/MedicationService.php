<?php

namespace App\Services;

use App\Models\Medication;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Medication history and the patient context used to ground assistant replies.
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

    /**
     * Recent medications for assistant grounding.
     *
     * @return Collection<int, Medication>
     */
    public function recentForContext(User $user, int $limit = 50): Collection
    {
        return Medication::query()
            ->where('user_id', $user->id)
            ->latest('created_at')
            ->limit($limit)
            ->get();
    }
}