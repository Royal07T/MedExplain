<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures the authenticated user has one of the required application roles.
 *
 * Accepts one or more role names. The user must have at least one of them.
 * Always fails closed with a 403 if the user is missing or has no matching role.
 */
final class EnsureUserRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->hasAnyRole($roles)) {
            abort(403, 'Forbidden.');
        }

        return $next($request);
    }
}