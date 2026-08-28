<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class Phase61WebhookSmokeTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organization = Organization::create([
            'name' => 'Webhook Clinic',
            'slug' => 'webhook-clinic',
            'is_active' => true,
        ]);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['role' => 'admin', 'organization_id' => $this->organization->id]);
        $user->refresh();

        return $user;
    }

    private function clinician(): User
    {
        $user = User::factory()->create(['role' => 'clinician', 'organization_id' => $this->organization->id]);
        $user->refresh();

        return $user;
    }

    private function makeSubscription(array $events = ['patient.created']): WebhookSubscription
    {
        return WebhookSubscription::create([
            'organization_id' => $this->organization->id,
            'url' => 'https://example.com/hook',
            'secret' => WebhookSubscription::generateSecret(),
            'events' => $events,
            'is_active' => true,
        ]);
    }

    // ─── CRUD ─────────────────────────────────────────────

    public function test_admin_lists_empty_webhooks(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/v1/webhooks')
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_admin_creates_webhook_subscription(): void
    {
        $resp = $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/v1/webhooks', [
                'url' => 'https://example.com/hook',
                'events' => ['patient.created', 'lab.result'],
                'description' => 'Notify CRM',
            ])
            ->assertCreated();

        $this->assertSame('https://example.com/hook', $resp->json('data.subscription.url'));
        $this->assertSame(['patient.created', 'lab.result'], $resp->json('data.subscription.events'));
        $this->assertNotEmpty($resp->json('data.secret'));
        $this->assertDatabaseHas('webhook_subscriptions', [
            'organization_id' => $this->organization->id,
            'url' => 'https://example.com/hook',
        ]);
    }

    public function test_create_rejects_unsupported_event(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/v1/webhooks', [
                'url' => 'https://example.com/hook',
                'events' => ['not.a.real.event'],
            ])
            ->assertStatus(422);
    }

    public function test_admin_views_and_updates_subscription(): void
    {
        $subscription = $this->makeSubscription();

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson("/api/v1/webhooks/{$subscription->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $subscription->id);

        $this->actingAs($this->admin(), 'sanctum')
            ->putJson("/api/v1/webhooks/{$subscription->id}", [
                'url' => 'https://example.com/v2/hook',
                'events' => ['medication.administered'],
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.url', 'https://example.com/v2/hook')
            ->assertJsonPath('data.events', ['medication.administered'])
            ->assertJsonPath('data.is_active', false);
    }

    public function test_admin_deletes_subscription(): void
    {
        $subscription = $this->makeSubscription();

        $this->actingAs($this->admin(), 'sanctum')
            ->deleteJson("/api/v1/webhooks/{$subscription->id}")
            ->assertOk();

        $this->assertDatabaseMissing('webhook_subscriptions', ['id' => $subscription->id]);
    }

    public function test_admin_cannot_access_another_orgs_subscription(): void
    {
        $otherOrg = Organization::create(['name' => 'Other', 'slug' => 'other-org', 'is_active' => true]);
        $other = WebhookSubscription::create([
            'organization_id' => $otherOrg->id,
            'url' => 'https://example.com/hook',
            'secret' => 'sec',
            'events' => ['patient.created'],
            'is_active' => true,
        ]);

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson("/api/v1/webhooks/{$other->id}")
            ->assertNotFound();
    }

    public function test_non_admin_role_is_denied(): void
    {
        $this->actingAs($this->clinician(), 'sanctum')
            ->getJson('/api/v1/webhooks')
            ->assertForbidden();
    }

    // ─── Delivery ─────────────────────────────────────────

    public function test_deliver_sends_signed_payload_and_records_delivery(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        $subscription = $this->makeSubscription(['care.gap.found']);

        $resp = $this->actingAs($this->admin(), 'sanctum')
            ->postJson("/api/v1/webhooks/{$subscription->id}/deliver", [
                'event' => 'care.gap.found',
                'data' => ['patient_id' => 42],
            ])
            ->assertOk();

        $this->assertSame('delivered', $resp->json('data.status'));
        $this->assertSame(200, $resp->json('data.http_status'));

        $this->assertDatabaseHas('webhook_deliveries', [
            'webhook_subscription_id' => $subscription->id,
            'event' => 'care.gap.found',
            'status' => 'delivered',
            'http_status' => 200,
        ]);

        Http::assertSent(function ($request) use ($subscription): bool {
            $contents = $request->data();
            $expected = hash_hmac('sha256', (string) json_encode($contents, JSON_UNESCAPED_SLASHES), $subscription->secret);
            $signature = $request->header('X-Webhook-Signature');
            $sent = is_array($signature) ? ($signature[0] ?? '') : (string) $signature;

            return $request->url() === 'https://example.com/hook'
                && $request->hasHeader('X-Webhook-Signature')
                && hash_equals($expected, $sent);
        });
    }

    public function test_deliver_records_failure_on_non_2xx(): void
    {
        Http::fake(['*' => Http::response('boom', 500)]);

        $subscription = $this->makeSubscription(['patient.created']);

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson("/api/v1/webhooks/{$subscription->id}/deliver", [
                'event' => 'patient.created',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('data.http_status', 500);

        $this->assertDatabaseHas('webhook_deliveries', [
            'webhook_subscription_id' => $subscription->id,
            'status' => 'failed',
            'http_status' => 500,
        ]);
    }

    public function test_deliveries_are_listed_for_a_subscription(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        $subscription = $this->makeSubscription();

        WebhookDelivery::create([
            'webhook_subscription_id' => $subscription->id,
            'event' => 'patient.created',
            'payload' => ['event' => 'patient.created'],
            'status' => 'delivered',
            'http_status' => 200,
        ]);

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson("/api/v1/webhooks/{$subscription->id}/deliveries")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
