<?php

namespace Tests\Feature\Documents;

use App\Models\MedicalDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DocumentDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_delete_document_and_stored_file(): void
    {
        Queue::fake();
        Storage::fake('documents');

        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->post('/api/v1/documents', [
            'file' => UploadedFile::fake()->create('report.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertStatus(201);

        $document = MedicalDocument::firstOrFail();
        $path = $document->storage_path;

        $this->delete('/api/v1/documents/'.$document->id, [], ['Accept' => 'application/json'])
            ->assertStatus(204);

        $this->assertDatabaseMissing('medical_documents', ['id' => $document->id]);
        Storage::disk('documents')->assertMissing($path);
        $this->assertDatabaseHas('audit_logs', ['action' => 'document_deleted']);
    }

    public function test_other_user_cannot_delete_document(): void
    {
        Storage::fake('documents');

        $owner = User::factory()->create();
        $other = User::factory()->create();
        $document = MedicalDocument::factory()->for($owner)->create();

        Sanctum::actingAs($other);

        $this->delete('/api/v1/documents/'.$document->id, [], ['Accept' => 'application/json'])
            ->assertStatus(403);

        $this->assertDatabaseHas('medical_documents', ['id' => $document->id]);
    }

    public function test_delete_requires_authentication(): void
    {
        $document = MedicalDocument::factory()->create();

        $this->delete('/api/v1/documents/'.$document->id, [], ['Accept' => 'application/json'])
            ->assertStatus(401);
    }
}