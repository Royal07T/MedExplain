<?php

namespace App\Services;

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Records security-sensitive actions for audit purposes.
 *
 * Safety: metadata and log messages must NEVER contain medical document
 * contents, test values, or other protected health information. Only store
 * identifiers, action names, and non-sensitive technical context.
 */
final class AuditService
{
    /**
     * Record an audit event.
     *
     * @param  array<string, mixed>  $metadata  non-sensitive context only
     */
    public function record(
        AuditEvent $event,
        ?User $user = null,
        ?Model $auditable = null,
        array $metadata = [],
    ): void {
        AuditLog::create([
            'user_id' => $user?->id,
            'action' => $event->value,
            'auditable_type' => $auditable ? $auditable->getMorphClass() : null,
            'auditable_id' => $auditable?->getKey(),
            'ip_address' => request()->ip(),
            'user_agent' => Str::limit(request()->userAgent() ?? '', 255, ''),
            'metadata' => $metadata !== [] ? $metadata : null,
        ]);
    }

    /**
     * Log a technical audit failure without exposing sensitive data.
     */
    public function logFailure(string $message, \Throwable $exception): void
    {
        Log::error("audit.{$message}", [
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
        ]);
    }
}