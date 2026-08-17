<?php

namespace Tests\Feature\Documents;

use App\Models\MedicalDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DocumentListTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_returns_only_own_documents(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $owned = MedicalDocument::factory()->count(2)->for($owner)->create();
        MedicalDocument::factory()->count(3)->for($other)->create();

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/v1/documents')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'original_filename', 'status', 'document_type']],
                'meta',
                'links',
            ]);

        $returnedIds = collect($response->json('data'))->pluck('id')->all();

        $this->assertCount(2, $returnedIds);
        $this->assertEqualsCanonicalizing(
            $owned->pluck('id')->all(),
            $returnedIds,
        );
    }

    public function test_list_returns_most_recent_first(): void
    {
        $user = User::factory()->create();

        $first = MedicalDocument::factory()->for($user)->create();
        $second = MedicalDocument::factory()->for($user)->create([
            'created_at' => now()->addHour(),
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/documents')
            ->assertOk()
            ->assertJsonPath('data.0.id', $second->id)
            ->assertJsonPath('data.1.id', $first->id);
    }

    public function test_list_requires_authentication(): void
    {
        $this->getJson('/api/v1/documents')->assertStatus(401);
    }
}