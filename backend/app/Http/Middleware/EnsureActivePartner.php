<?php

namespace App\Http\Middleware;

use App\Models\ApiPartner;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Requires the authenticated token to belong to an active partner.
 * Fails closed: inactive or non-partner principals are rejected.
 */
final class EnsureActivePartner
{
    public function handle(Request $request, Closure $next): Response
    {
        $principal = $request->user();

        if (! $principal instanceof ApiPartner || ! $principal->is_active) {
            abort(403, 'Forbidden.');
        }

        return $next($request);
    }
}