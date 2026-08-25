<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AddCorrelationId
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $correlationId = $request->header('X-Correlation-ID') ?? Str::uuid()->toString();

        // Add correlation ID to request headers for downstream services
        $request->headers->set('X-Correlation-ID', $correlationId);

        // Add correlation ID to response
        $response = $next($request);
        $response->headers->set('X-Correlation-ID', $correlationId);

        // Log correlation ID setup (without sensitive data)
        \Log::debug('Correlation ID set: ' . $correlationId);

        return $response;
    }
}
