<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Serves the OpenAPI specification for the HealthTech API platform.
 *
 * The document describes the partner-facing surface: OAuth client-credentials
 * token flow, consent-scoped health record access, scopes, and rate limits.
 */
final class ApiDocsController extends Controller
{
    public function openapi(): JsonResponse
    {
        $document = json_decode(
            (string) file_get_contents(resource_path('api/openapi.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        return response()->json($document);
    }
}