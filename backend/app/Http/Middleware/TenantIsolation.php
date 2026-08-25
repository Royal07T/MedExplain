<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class TenantIsolation
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $organizationId = null;

        // Determine organization from authenticated user
        if ($request->user() && $request->user()->organization_id) {
            $organizationId = $request->user()->organization_id;
        }

        // Share organization context with the application
        // via a hidden header or request attribute for downstream use
        $request->merge([
            'organization_id' => $organizationId,
        ]);

        // Log tenant context for audit purposes
        if ($organizationId) {
            \Log::debug('Tenant isolation: organization_id=' . $organizationId . ' for user=' . $request->user()?->id ?? 'anonymous');
        }

        return $next($request);
    }
}
