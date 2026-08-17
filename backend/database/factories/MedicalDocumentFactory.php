<?php

namespace Database\Factories;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\MedicalDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MedicalDocument>
 */
class MedicalDocumentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = MedicalDocument::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'original_filename' => 'lab-report-'.fake()->randomNumber(4).'.pdf',
            'storage_path' => 'u'.fake()->numberBetween(1, 99999).'/'.Str::uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => fake()->numberBetween(1000, 5000000),
            'document_type' => DocumentType::Unknown,
            'status' => DocumentStatus::Uploaded,
        ];
    }
}