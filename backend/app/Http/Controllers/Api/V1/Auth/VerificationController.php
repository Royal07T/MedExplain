<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\AuditEvent;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class VerificationController extends Controller
{
    public function __construct(private readonly AuditService $auditService) {}

    /**
     * Verify an email address from a signed verification link.
     */
    public function verify(Request $request, string $id, string $hash): JsonResponse
    {
        $user = User::findOrFail($id);

        if (! hash_equals((string) $request->route('hash'), sha1($user->getEmailForVerification()))) {
            abort(403, 'Invalid verification link.');
        }

        if ($user->markEmailAsVerified()) {
            $this->auditService->record(AuditEvent::EmailVerified, $user);
        }

        return response()->json(['message' => 'Email address verified.']);
    }
}