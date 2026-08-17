<?php

namespace Tests\Feature\Documents;

use App\Models\MedicalDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DocumentShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_document(): void
    {
        $user = User::factory()->create();
        $document = MedicalDocument::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/documents/'.$document->id)
            ->assertOk()
            ->assertJsonPath('id', $document->id)
            ->assertJsonPath('original_filename', $document->original_filename)
            ->assertJsonMissingPath('storage_path');

        $this->assertDatabaseHas('audit_logs', ['action' => 'document_viewed']);
    }

    public function test_other_user_cannot_view_document(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $document = MedicalDocument::factory()->for($owner)->create();

        Sanctum::actingAs($other);

        $this->getJson('/api/v1/documents/'.$document->id)
            ->assertStatus(403);
    }

    public function test_show_requires_authentication(): void
    {
        $document = MedicalDocument::factory()->create();

        $this->getJson('/api/v1/documents/'.$document->id)->assertStatus(401);
    }
}