<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures the authenticated user has the specified permission.
 *
 * Uses spatie/laravel-permission to check the user's direct and
 * role-based permissions. Always fails closed with a 403.
 */
final class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->hasPermissionTo($permission)) {
            abort(403, 'Forbidden.');
        }

        return $next($request);
    }
}
