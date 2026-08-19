<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_their_notifications(): void
    {
        $user = User::factory()->create();

        Notification::create([
            'user_id' => $user->id,
            'title' => 'Welcome to Pro',
            'body' => 'You now have full access.',
            'type' => 'plan',
            'data' => null,
            'read_at' => null,
            'created_at' => now()->subMinutes(2),
        ]);
        Notification::create([
            'user_id' => $user->id,
            'title' => 'Old notification',
            'type' => 'system',
            'data' => null,
            'read_at' => now(),
            'created_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', 'Old notification')
            ->assertJsonPath('data.1.title', 'Welcome to Pro')
            ->assertJsonPath('data.1.type', 'plan')
            ->assertJsonPath('data.1.read_at', null);
    }

    public function test_unread_count_endpoint(): void
    {
        $user = User::factory()->create();

        Notification::create(['user_id' => $user->id, 'title' => 'One', 'type' => 'system']);
        Notification::create(['user_id' => $user->id, 'title' => 'Two', 'type' => 'system']);
        Notification::create([
            'user_id' => $user->id,
            'title' => 'Read',
            'type' => 'system',
            'read_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('unread_count', 2);
    }

    public function test_user_can_mark_a_notification_as_read(): void
    {
        $user = User::factory()->create();
        $notification = Notification::create([
            'user_id' => $user->id,
            'title' => 'Analysis ready',
            'type' => 'analysis',
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJsonPath('id', $notification->id)
            ->assertJsonPath('read_at', fn ($value) => $value !== null);

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'read_at' => now(),
        ]);
    }

    public function test_user_cannot_mark_another_users_notification_as_read(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $notification = Notification::create([
            'user_id' => $owner->id,
            'title' => 'Private',
            'type' => 'system',
        ]);

        Sanctum::actingAs($other);

        $this->postJson("/api/v1/notifications/{$notification->id}/read")->assertStatus(403);
    }

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        $user = User::factory()->create();

        Notification::create(['user_id' => $user->id, 'title' => 'One', 'type' => 'system']);
        Notification::create(['user_id' => $user->id, 'title' => 'Two', 'type' => 'system']);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this->assertSame(0, Notification::where('user_id', $user->id)->whereNull('read_at')->count());
    }

    public function test_document_upload_creates_a_notification(): void
    {
        Queue::fake();
        Storage::fake('documents');

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->post('/api/v1/documents', [
            'file' => UploadedFile::fake()->create('hemoglobin-report.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])
            ->assertStatus(201);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'type' => 'document',
            'title' => 'Document uploaded',
        ]);
    }

    public function test_plan_upgrade_creates_a_notification(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/plan/upgrade')->assertOk();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'type' => 'plan',
            'title' => 'Welcome to Pro',
        ]);
    }

    public function test_notification_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/notifications')->assertUnauthorized();
        $this->getJson('/api/v1/notifications/unread-count')->assertUnauthorized();
        $this->postJson('/api/v1/notifications/read-all')->assertUnauthorized();
        $this->postJson('/api/v1/notifications/1/read')->assertUnauthorized();
    }
}