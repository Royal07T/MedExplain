<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Patient;
use App\Models\User;
use App\Enums\UserRole;
use App\Enums\Plan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PatientSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $organizations = Organization::all();

        if ($organizations->isEmpty()) {
            $this->command->warn('No organizations found. Seed organizations first.');
            return;
        }

        // Create patients for each organization
        foreach ($organizations as $organization) {
            // Create 5-15 patients per organization
            $patientCount = random_int(5, 15);

            for ($i = 0; $i < $patientCount; $i++) {
                // Create a user first
                $user = User::create([
                    'name' => fake()->name(),
                    'email' => fake()->unique()->safeEmail(),
                    'password' => Hash::make('password123'),
                    'role' => UserRole::Patient->value,
                    'plan' => Plan::Free->value,
                    'organization_id' => $organization->id,
                    'email_verified_at' => now(),
                ]);

                // Create patient record linked to the user
                Patient::create([
                    'user_id' => $user->id,
                    'organization_id' => $organization->id,
                    'mrn' => 'MRN-' . Str::random(8),
                    'first_name' => fake()->firstName(),
                    'last_name' => fake()->lastName(),
                    'date_of_birth' => fake()->date('-15 years', 'now'),
                    'gender' => fake->randomElement(['Male', 'Female', 'Other']),
                    'blood_type' => fake->randomElement(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'Unknown']),
                    'phone' => fake->phoneNumber(),
                    'email' => $user->email,
                    'address' => fake->address(),
                    'next_of_kin_name' => fake->name(),
                    'next_of_kin_phone' => fake->phoneNumber(),
                    'emergency_contact_name' => fake->name(),
                    'emergency_contact_phone' => fake->phoneNumber(),
                    'allergies' => fake->sentence(2, true),
                    'immunizations' => fake->sentence(3, true),
                ]);
            }
        }

        $this->command->info("Patients seeded successfully.");
    }
}
