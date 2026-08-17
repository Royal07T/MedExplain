<?php

namespace Tests\Feature\Documents;

use App\Models\MedicalDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DocumentUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_upload_a_document(): void
    {
        Storage::fake('documents');

        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->post('/api/v1/documents', [
            'file' => UploadedFile::fake()->create('hemoglobin-report.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json']);

        $response->assertStatus(201)
            ->assertJsonPath('original_filename', 'hemoglobin-report.pdf')
            ->assertJsonPath('status', 'uploaded')
            ->assertJsonPath('document_type', 'unknown')
            ->assertJsonMissingPath('storage_path');

        $this->assertDatabaseHas('medical_documents', [
            'user_id' => $user->id,
            'status' => 'uploaded',
            'document_type' => 'unknown',
        ]);

        $path = MedicalDocument::firstOrFail()->storage_path;
        Storage::disk('documents')->assertExists($path);

        $this->assertDatabaseHas('audit_logs', ['action' => 'document_uploaded']);
    }

    public function test_upload_requires_authentication(): void
    {
        $this->post('/api/v1/documents', [
            'file' => UploadedFile::fake()->create('report.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertStatus(401);
    }

    public function test_upload_rejects_invalid_mime_type(): void
    {
        Storage::fake('documents');

        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->post('/api/v1/documents', [
            'file' => UploadedFile::fake()->create('notes.txt', 100, 'text/plain'),
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');

        $this->assertDatabaseCount('medical_documents', 0);
        Storage::disk('documents')->assertDirectoryEmpty('u'.$user->id);
    }

    public function test_upload_rejects_oversized_file(): void
    {
        Storage::fake('documents');

        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->post('/api/v1/documents', [
            'file' => UploadedFile::fake()->create('big-report.pdf', 11 * 1024, 'application/pdf'),
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');
    }

    public function test_upload_requires_a_file(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->post('/api/v1/documents', [], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');
    }
}