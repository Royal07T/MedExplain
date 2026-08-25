<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PatientFactory extends Factory
{
    /**
     * The name of the factory's model.
     *
     * @var string
     */
    protected $model = Patient::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        $organizations = Organization::all();

        $orgId = $organizations->isNotEmpty() ? $organizations->random->id : 1;

        return [
            'organization_id' => $orgId,
            'mrn' => 'MRN-' . Str::random(8),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'date_of_birth' => fake()->date('-15 years', 'now'),
            'gender' => fake->randomElement(['Male', 'Female', 'Other']),
            'blood_type' => fake->randomElement(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'Unknown']),
            'phone' => fake->phoneNumber(),
            'email' => fake->safeEmail(),
            'address' => fake->address(),
            'next_of_kin_name' => fake->name(),
            'next_of_kin_phone' => fake->phoneNumber(),
            'emergency_contact_name' => fake->name(),
            'emergency_contact_phone' => fake->phoneNumber(),
            'allergies' => fake->sentence() . ', ' . fake->word(),
            'immunizations' => fake->sentence(),
        ];
    }
}
