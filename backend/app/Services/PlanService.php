<?php

namespace App\Services;

use App\Enums\Plan;
use App\Models\User;

/**
 * Manages the user's subscription plan.
 *
 * There is no real billing provider yet; upgrades and cancellations simulate
 * the effect of a successful subscription or cancellation. The transitions are
 * idempotent so repeated calls never fail.
 */
final class PlanService
{
    /**
     * A summary of the user's current plan for display.
     *
     * @return array{plan: string, label: string, is_pro: bool}
     */
    public function current(User $user): array
    {
        $plan = $user->plan ?? Plan::Free;

        return [
            'plan' => $plan->value,
            'label' => $plan->label(),
            'is_pro' => $plan === Plan::Pro,
        ];
    }

    /**
     * Upgrade the user to the paid (Pro) plan.
     */
    public function upgrade(User $user): User
    {
        $user->update(['plan' => Plan::Pro]);

        return $user->refresh();
    }

    /**
     * Cancel the subscription and return the user to the free plan.
     */
    public function cancel(User $user): User
    {
        $user->update(['plan' => Plan::Free]);

        return $user->refresh();
    }
}