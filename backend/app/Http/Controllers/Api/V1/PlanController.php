<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AuditEvent;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Services\AuditService;
use App\Services\NotificationService;
use App\Services\PlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Plan / subscription management.
 *
 * Upgrades and cancellations are user-scoped, idempotent, and audited. No
 * billing provider is integrated yet — these simulate the subscription change.
 */
final class PlanController extends Controller
{
    public function __construct(
        private readonly PlanService $planService,
        private readonly AuditService $auditService,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * The authenticated user's current plan.
     */
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->planService->current($request->user()),
        ]);
    }

    /**
     * Upgrade the authenticated user to the paid plan. Audited.
     */
    public function upgrade(Request $request): JsonResponse
    {
        $user = $this->planService->upgrade($request->user());

        $this->auditService->record(AuditEvent::PlanUpgraded, $user);

        $this->notifications->notify(
            $user,
            'Welcome to Pro',
            'You now have full access to all MedExplain features.',
            'plan',
        );

        return response()->json([
            'user' => new UserResource($user->load('profile')),
        ]);
    }

    /**
     * Cancel the authenticated user's subscription. Audited.
     */
    public function cancel(Request $request): JsonResponse
    {
        $user = $this->planService->cancel($request->user());

        $this->auditService->record(AuditEvent::PlanCancelled, $user);

        $this->notifications->notify(
            $user,
            'Subscription cancelled',
            'You are back on the Free plan.',
            'plan',
        );

        return response()->json([
            'user' => new UserResource($user->load('profile')),
        ]);
    }
}