<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_fetch_their_profile(): void
    {
        $user = User::factory()->create();
        $user->profile()->create(['first_name' => 'Jane', 'last_name' => 'Doe']);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/user')
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.email', $user->email)
            ->assertJsonPath('user.profile.first_name', 'Jane')
            ->assertJsonPath('user.profile.last_name', 'Doe');
    }

    public function test_authenticated_user_profile_can_be_empty(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/user')
            ->assertOk()
            ->assertJsonPath('user.profile.first_name', null);
    }

    public function test_user_defaults_to_free_plan(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/user')
            ->assertOk()
            ->assertJsonPath('user.plan', 'free');
    }

    public function test_pro_user_is_reported_with_pro_plan(): void
    {
        $user = User::factory()->pro()->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/user')
            ->assertOk()
            ->assertJsonPath('user.plan', 'pro');
    }

    public function test_unauthenticated_user_cannot_fetch_profile(): void
    {
        $this->getJson('/api/v1/user')->assertStatus(401);
    }

    public function test_authenticated_user_can_update_their_display_name(): void
    {
        $user = User::factory()->create(['name' => 'Old Name']);

        Sanctum::actingAs($user);

        $this->putJson('/api/v1/user', ['name' => 'New Name'])
            ->assertOk()
            ->assertJsonPath('user.name', 'New Name');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'New Name']);
    }

    public function test_authenticated_user_can_update_their_profile_details(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->putJson('/api/v1/user', [
            'name' => 'Jane Q. Doe',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'date_of_birth' => '1990-05-14',
            'gender' => 'female',
        ])
            ->assertOk()
            ->assertJsonPath('user.name', 'Jane Q. Doe')
            ->assertJsonPath('user.profile.first_name', 'Jane')
            ->assertJsonPath('user.profile.last_name', 'Doe')
            ->assertJsonPath('user.profile.date_of_birth', '1990-05-14')
            ->assertJsonPath('user.profile.gender', 'female');

        $this->assertDatabaseHas('profiles', [
            'user_id' => $user->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'date_of_birth' => '1990-05-14 00:00:00',
            'gender' => 'female',
        ]);
    }

    public function test_profile_details_must_be_valid(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->putJson('/api/v1/user', [
            'name' => 'Jane',
            'date_of_birth' => 'not-a-date',
            'gender' => 'unknown',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['date_of_birth', 'gender']);
    }

    public function test_update_profile_requires_a_name(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->putJson('/api/v1/user', ['name' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_unauthenticated_user_cannot_update_profile(): void
    {
        $this->putJson('/api/v1/user', ['name' => 'New Name'])->assertStatus(401);
    }

    public function test_authenticated_user_can_upload_an_avatar(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/user/avatar', [
            'avatar' => UploadedFile::fake()->create('avatar.png', 20, 'image/png'),
        ])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);

        $this->assertDatabaseHas('profiles', ['user_id' => $user->id]);
        $this->assertNotNull($user->refresh()->profile->avatar_path);

        Storage::disk('public')->assertExists($user->profile->avatar_path);
    }

    public function test_avatar_must_be_a_valid_image(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/user/avatar', [
            'avatar' => UploadedFile::fake()->create('document.pdf', 10),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('avatar');
    }

    public function test_unauthenticated_user_cannot_upload_an_avatar(): void
    {
        $this->postJson('/api/v1/user/avatar', [
            'avatar' => UploadedFile::fake()->create('avatar.png', 20, 'image/png'),
        ])->assertStatus(401);
    }
}