<?php

namespace Tests\Feature;

use App\Enums\AuditEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_free_user_plan_is_reported(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/plan')
            ->assertOk()
            ->assertJsonPath('data.plan', 'free')
            ->assertJsonPath('data.label', 'Free')
            ->assertJsonPath('data.is_pro', false);
    }

    public function test_upgrade_moves_user_to_pro_and_audits(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/plan/upgrade')
            ->assertOk()
            ->assertJsonPath('user.plan', 'pro');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'plan' => 'pro']);
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditEvent::PlanUpgraded->value,
            'user_id' => $user->id,
        ]);
    }

    public function test_upgrade_is_idempotent(): void
    {
        $user = User::factory()->pro()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/plan/upgrade')
            ->assertOk()
            ->assertJsonPath('user.plan', 'pro');
    }

    public function test_cancel_returns_user_to_free_and_audits(): void
    {
        $user = User::factory()->pro()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/plan/cancel')
            ->assertOk()
            ->assertJsonPath('user.plan', 'free');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'plan' => 'free']);
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditEvent::PlanCancelled->value,
            'user_id' => $user->id,
        ]);
    }

    public function test_plan_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/plan')->assertUnauthorized();
        $this->postJson('/api/v1/plan/upgrade')->assertUnauthorized();
        $this->postJson('/api/v1/plan/cancel')->assertUnauthorized();
    }
}