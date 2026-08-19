<?php

namespace App\Http\Middleware;

use App\Models\ApiPartner;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Requires the authenticated partner token to carry the given scope
 * (Sanctum token ability). Fails closed when the scope is missing.
 */
final class EnsurePartnerScope
{
    public function handle(Request $request, Closure $next, string $scope): Response
    {
        $principal = $request->user();

        if (! $principal instanceof ApiPartner || ! $principal->tokenCan($scope)) {
            abort(403, 'Forbidden.');
        }

        return $next($request);
    }
}