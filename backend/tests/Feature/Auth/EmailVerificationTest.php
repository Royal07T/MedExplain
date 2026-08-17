<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_sends_email_verification_notification(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertStatus(201);

        $user = User::where('email', 'jane@example.com')->firstOrFail();

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_authenticated_user_can_resend_verification_email(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/auth/email/verification-notification')
            ->assertOk()
            ->assertJson(['message' => 'A new verification link has been sent to your email address.']);

        Notification::assertSentTo($user, VerifyEmail::class);

        $this->assertDatabaseHas('audit_logs', ['action' => 'resend_verification']);
    }

    public function test_verified_user_cannot_resend_verification_email(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/auth/email/verification-notification')
            ->assertOk()
            ->assertJson(['message' => 'Your email address is already verified.']);

        Notification::assertNothingSent();
    }

    public function test_resending_verification_email_requires_authentication(): void
    {
        $this->postJson('/api/v1/auth/email/verification-notification')->assertStatus(401);
    }

    public function test_user_can_verify_email_via_signed_link(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)],
        );

        $this->get($url)
            ->assertOk()
            ->assertJson(['message' => 'Email address verified.']);

        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'email_verified']);
    }

    public function test_email_verification_rejects_invalid_hash(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1('someone-else@example.com')],
        );

        $this->get($url)->assertStatus(403);

        $this->assertNull($user->fresh()->email_verified_at);
    }
}