<?php

namespace Tests\Feature\Health;

use App\Models\Medication;
use App\Models\MedicalDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MedicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_users_medications_newest_first(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $document = MedicalDocument::factory()->for($user)->create(['status' => 'processed']);

        Medication::create([
            'user_id' => $user->id,
            'medical_document_id' => $document->id,
            'name' => 'Metformin',
            'strength' => '500 mg',
            'dosage_form' => 'tablet',
            'dose' => '500',
            'frequency' => 'twice daily',
            'route' => 'oral',
            'sort_order' => 0,
        ]);
        Medication::create([
            'user_id' => $user->id,
            'medical_document_id' => $document->id,
            'name' => 'Lisinopril',
            'strength' => '10 mg',
            'dosage_form' => 'tablet',
            'route' => 'oral',
            'sort_order' => 1,
        ]);

        $response = $this->getJson('/api/v1/medications')->assertOk();

        $response->assertJsonCount(2, 'data');
        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Metformin'));
        $this->assertTrue($names->contains('Lisinopril'));
        $metformin = collect($response->json('data'))->first(fn (array $m) => $m['name'] === 'Metformin');
        $this->assertSame('500 mg', $metformin['strength']);
        $this->assertSame('twice daily', $metformin['frequency']);
        $this->assertSame($document->id, $metformin['medical_document_id']);
    }

    public function test_excludes_other_users_medications(): void
    {
        $owner = User::factory()->create();
        $document = MedicalDocument::factory()->for($owner)->create(['status' => 'processed']);
        Medication::create([
            'user_id' => $owner->id,
            'medical_document_id' => $document->id,
            'name' => 'Metformin',
            'sort_order' => 0,
        ]);

        $other = User::factory()->create();
        Sanctum::actingAs($other);

        $this->getJson('/api/v1/medications')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/v1/medications')->assertUnauthorized();
    }
}