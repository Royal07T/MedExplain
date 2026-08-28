<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WebhookDelivery;
use App\Models\WebhookSubscription;
use App\Services\WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Organization-scoped webhook subscription management and delivery.
 *
 * Administrative/integration feature. Each subscription belongs to the
 * caller's organization and is signed with its own secret. Only payload and
 * metadata are stored; the signing secret is returned once on creation.
 */
final class WebhookController extends Controller
{
    public function __construct(private readonly WebhookService $webhooks) {}

    public function index(Request $request): JsonResponse
    {
        $subscriptions = WebhookSubscription::query()
            ->where('organization_id', $this->organizationId($request))
            ->withCount('deliveries')
            ->orderByDesc('id')
            ->get();

        return response()->json(['success' => true, 'data' => $subscriptions]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateNew($request);

        $subscription = WebhookSubscription::create([
            'organization_id' => $this->organizationId($request),
            'url' => $validated['url'],
            'secret' => WebhookSubscription::generateSecret(),
            'events' => $validated['events'],
            'description' => $validated['description'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        $secret = $subscription->secret;
        $subscription->makeHidden('secret');

        return response()->json([
            'success' => true,
            'data' => [
                'subscription' => $subscription,
                'secret' => $secret,
            ],
        ], 201);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $subscription = $this->find($request, (int) $id);

        return response()->json(['success' => true, 'data' => $subscription]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $subscription = $this->find($request, (int) $id);

        $validated = $request->validate([
            'url' => ['sometimes', 'string', 'url'],
            'events' => ['sometimes', 'array', 'min:1'],
            'events.*' => ['string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (isset($validated['url'])) {
            $subscription->url = $validated['url'];
        }
        if (isset($validated['events'])) {
            $subscription->events = $validated['events'];
        }
        if (array_key_exists('description', $validated)) {
            $subscription->description = $validated['description'];
        }
        if (array_key_exists('is_active', $validated)) {
            $subscription->is_active = $validated['is_active'];
        }

        $subscription->save();

        return response()->json(['success' => true, 'data' => $subscription]);
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $subscription = $this->find($request, (int) $id);
        $subscription->delete();

        return response()->json(['success' => true, 'data' => null]);
    }

    public function deliveries(Request $request, $id): JsonResponse
    {
        $subscription = $this->find($request, (int) $id);

        $deliveries = WebhookDelivery::query()
            ->where('webhook_subscription_id', $subscription->id)
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return response()->json(['success' => true, 'data' => $deliveries]);
    }

    public function deliver(Request $request, $id): JsonResponse
    {
        $subscription = $this->find($request, (int) $id);

        $validated = $request->validate([
            'event' => ['required', 'string', 'max:100'],
            'data' => ['sometimes', 'array'],
        ]);

        $delivery = $this->webhooks->deliver(
            $subscription,
            $validated['event'],
            $validated['data'] ?? ['message' => 'Ping from MedExplain'],
        );

        return response()->json(['success' => true, 'data' => $delivery]);
    }

    private function find(Request $request, int $id): WebhookSubscription
    {
        return WebhookSubscription::query()
            ->where('organization_id', $this->organizationId($request))
            ->findOrFail($id);
    }

    private function organizationId(Request $request): int
    {
        return (int) $request->user()?->organization_id;
    }

    private function validateNew(Request $request): array
    {
        $validated = $request->validate([
            'url' => ['required', 'string', 'url'],
            'events' => ['required', 'array', 'min:1', 'max:50'],
            'events.*' => ['required', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $allowed = ['patient.created', 'lab.result', 'medication.administered', 'care.gap.found', 'appointment.scheduled'];
        foreach ($validated['events'] as $event) {
            if (!in_array($event, $allowed, true)) {
                throw ValidationException::withMessages([
                    'events' => "Unsupported event '{$event}'.",
                ]);
            }
        }

        return $validated;
    }
}
