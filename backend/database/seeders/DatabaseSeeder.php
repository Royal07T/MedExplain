<?php

namespace Database\Seeders;

use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create organization
        $organizationId = DB::table('organizations')->insertGetId([
            'name' => 'MedExplain Hospital',
            'slug' => 'medexplain-hospital',
            'address' => '123 Medical Center Dr',
            'phone' => '555-0123',
            'email' => 'info@medexplain.com',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create users with different roles
        $users = [
            [
                'name' => 'Dr. John Smith',
                'email' => 'clinician@example.com',
                'role' => UserRole::Clinician,
                'organization_id' => $organizationId,
            ],
            [
                'name' => 'Jane Patient',
                'email' => 'patient@example.com',
                'role' => UserRole::Patient,
                'organization_id' => $organizationId,
            ],
            [
                'name' => 'Admin User',
                'email' => 'admin@example.com',
                'role' => UserRole::Admin,
                'organization_id' => $organizationId,
            ],
            [
                'name' => 'Nurse Sarah',
                'email' => 'nurse@example.com',
                'role' => UserRole::NursingStaff,
                'organization_id' => $organizationId,
            ],
        ];

        foreach ($users as $userData) {
            $user = User::factory()->create([
                'name' => $userData['name'],
                'email' => $userData['email'],
                'role' => $userData['role'],
                'organization_id' => $userData['organization_id'],
            ]);

            // Assign Spatie role
            $user->assignRole($userData['role']->value);
        }

        // Create clinician-patient access relationship
        $clinician = User::where('email', 'clinician@example.com')->first();
        $patient = User::where('email', 'patient@example.com')->first();

        if ($clinician && $patient) {
            DB::table('clinician_patient_access')->insert([
                'clinician_user_id' => $clinician->id,
                'patient_user_id' => $patient->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
