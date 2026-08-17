<?php

return [
    /*
    |--------------------------------------------------------------------------
    | FastAPI service
    |--------------------------------------------------------------------------
    |
    | Service-to-service credentials for the MedExplain AI service. The key is
    | compared with a constant-time comparison on the FastAPI side. Never log
    | or expose these values.
    |
    */

    'base_url' => env('FASTAPI_BASE_URL', 'http://127.0.0.1:8001'),
    'api_key' => env('FASTAPI_API_KEY', 'dev-secret-change-me'),
    'timeout' => (int) env('FASTAPI_TIMEOUT', 30),
];