<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures the authenticated user has the required application role.
 *
 * The middleware is intentionally strict: a missing or mismatched role
 * always fails closed with a 403, never falling back to another role.
 */
final class EnsureUserRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->isClinician() || $user->role->value !== $role) {
            abort(403, 'Forbidden.');
        }

        return $next($request);
    }
}