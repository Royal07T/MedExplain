<?php

namespace App\Services;

use App\Enums\AuditEvent;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

final class AuthService
{
    public function __construct(private readonly AuditService $auditService) {}

    /**
     * Register a new user and return the user with an API token.
     *
     * @param  array<string, mixed>  $data
     * @return array{user: UserResource, token: string}
     */
    public function register(array $data): array
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        // Assign default patient role via spatie/laravel-permission
        $user->assignRole('patient');

        $user->profile()->create([
            'first_name' => $data['first_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
        ]);

        $user->sendEmailVerificationNotification();

        $this->auditService->record(AuditEvent::Register, $user);

        return [
            'user' => new UserResource($user->load('profile', 'roles')),
            'token' => $user->createToken('api')->plainTextToken,
        ];
    }

    /**
     * Authenticate an existing user and return the user with an API token.
     *
     * @param  array<string, mixed>  $data
     * @return array{user: UserResource, token: string}
     */
    public function login(array $data): array
    {
        if (! Auth::attempt(['email' => $data['email'], 'password' => $data['password']])) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        /** @var User $user */
        $user = Auth::user();

        $this->auditService->record(AuditEvent::Login, $user);

        return [
            'user' => new UserResource($user->load('profile', 'roles')),
            'token' => $user->createToken('api')->plainTextToken,
        ];
    }

    /**
     * Revoke the caller's current API token.
     */
    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();

        $this->auditService->record(AuditEvent::Logout, $user);
    }

    /**
     * Send a password reset link. Always returns the generic success status
     * to avoid account enumeration.
     */
    public function sendPasswordResetLink(string $email): string
    {
        $status = Password::broker()->sendResetLink(['email' => $email]);

        $this->auditService->record(AuditEvent::RequestPasswordReset);

        return $status;
    }

    /**
     * Reset a user's password via the password broker.
     *
     * @param  array<string, mixed>  $data
     */
    public function resetPassword(array $data): string
    {
        return Password::broker()->reset(
            $data,
            function (User $user, string $password): void {
                $user->forceFill(['password' => $password])->save();

                $user->tokens()->delete();

                $this->auditService->record(AuditEvent::ResetPassword, $user);

                event(new PasswordReset($user));
            }
        );
    }

    /**
     * Re-send the email verification notification.
     */
    public function resendVerificationEmail(User $user): void
    {
        $user->sendEmailVerificationNotification();

        $this->auditService->record(AuditEvent::ResendVerification, $user);
    }
}