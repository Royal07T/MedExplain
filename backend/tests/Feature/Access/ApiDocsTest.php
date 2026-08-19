<?php

namespace Tests\Feature\Access;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiDocsTest extends TestCase
{
    use RefreshDatabase;

    public function test_serves_openapi_document(): void
    {
        $this->getJson('/api/v1/api-docs')
            ->assertOk()
            ->assertJsonPath('openapi', '3.0.3')
            ->assertJsonPath('info.title', 'MedExplain HealthTech API')
            ->assertJsonPath('paths./partner/oauth/token.post.summary', 'Obtain a bearer token (client credentials)')
            ->assertJsonPath('paths./partner/patients/{id}/record.get.description', 'Requires an active `health_record:read` consent from the patient. Access is audited.')
            ->assertJsonStructure([
                'components' => ['securitySchemes' => ['bearerAuth']],
            ]);
    }
}