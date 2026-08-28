<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Define all permissions grouped by domain
        $permissions = [
            // Patient - own data
            'own_profile.view',
            'own_profile.update',
            'own_records.view',
            'own_labs.view',
            'own_medications.view',
            'own_documents.view',
            'own_documents.upload',
            'own_appointments.view',
            'own_ai.query',

            // Patients - clinical access
            'patients.view',
            'patients.create',
            'patients.update',

            // Encounters
            'encounters.view',
            'encounters.create',
            'encounters.update',

            // Labs
            'labs.view',
            'labs.create',
            'labs.order',

            // Medications
            'medications.view',
            'medications.create',

            // Prescriptions
            'prescriptions.view',
            'prescriptions.create',
            'prescriptions.update',

            // Documents
            'documents.view',
            'documents.upload',

            // Health timeline
            'health_timeline.view',

            // AI
            'ai.query',

            // Vitals
            'vitals.view',
            'vitals.create',

            // Nursing notes
            'nursing_notes.view',
            'nursing_notes.create',

            // Medication administration
            'medication_administration.view',
            'medication_administration.create',

            // Care plans
            'care_plans.view',
            'care_plans.create',

            // Alerts
            'alerts.view',

            // Staff
            'staff.view',
            'staff.manage',

            // Departments
            'departments.view',
            'departments.manage',

            // Appointments (admin)
            'appointments.manage',
            'appointments.view',
            'appointments.create',
            'appointments.update',
            'appointments.cancel',

            // Admissions
            'admissions.manage',

            // Billing
            'billing.view',
            'billing.manage',

            // Inventory
            'inventory.view',
            'inventory.manage',

            // Reports & Analytics
            'reports.view',
            'analytics.view',

            // Audit
            'audit.view',

            // Organizations (super admin)
            'organizations.view',
            'organizations.manage',

            // Users (super admin)
            'users.view',
            'users.manage',

            // Roles & Permissions (super admin)
            'roles.manage',
            'permissions.manage',

            // System (super admin)
            'system_configuration.manage',
            'ai_configuration.manage',
            'llm_providers.manage',
            'usage.view',
            'security.manage',
            'system_health.view',
            'integrations.manage',

            // Integrations — outbound webhooks
            'webhooks.view',
            'webhooks.manage',
        ];

        // Create all permissions
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        // Patient role
        $patient = Role::findOrCreate('patient', 'web');
        $patient->syncPermissions([
            'own_profile.view',
            'own_profile.update',
            'own_records.view',
            'own_labs.view',
            'own_medications.view',
            'own_documents.view',
            'own_documents.upload',
            'own_appointments.view',
            'appointments.create',
            'appointments.cancel',
            'own_ai.query',
        ]);

        // Clinician role
        $clinician = Role::findOrCreate('clinician', 'web');
        $clinician->syncPermissions([
            'own_profile.view',
            'own_profile.update',
            'patients.view',
            'patients.create',
            'patients.update',
            'encounters.view',
            'encounters.create',
            'encounters.update',
            'labs.view',
            'labs.create',
            'labs.order',
            'medications.view',
            'medications.create',
            'prescriptions.view',
            'prescriptions.create',
            'prescriptions.update',
            'documents.view',
            'documents.upload',
            'health_timeline.view',
            'ai.query',
            'appointments.view',
            'appointments.create',
            'appointments.update',
            'appointments.cancel',
        ]);

        // Nursing staff role
        $nursing = Role::findOrCreate('nursing_staff', 'web');
        $nursing->syncPermissions([
            'own_profile.view',
            'own_profile.update',
            'patients.view',
            'encounters.view',
            'vitals.view',
            'vitals.create',
            'nursing_notes.view',
            'nursing_notes.create',
            'medications.view',
            'medication_administration.view',
            'medication_administration.create',
            'care_plans.view',
            'care_plans.create',
            'alerts.view',
            'documents.view',
            'health_timeline.view',
        ]);

        // Admin role
        $admin = Role::findOrCreate('admin', 'web');
        $admin->syncPermissions([
            'own_profile.view',
            'own_profile.update',
            'patients.view',
            'staff.view',
            'staff.manage',
            'departments.view',
            'departments.manage',
            'appointments.manage',
            'admissions.manage',
            'billing.view',
            'billing.manage',
            'inventory.view',
            'inventory.manage',
            'reports.view',
            'analytics.view',
            'audit.view',
            'webhooks.view',
            'webhooks.manage',
        ]);

        // Super Admin role
        $superAdmin = Role::findOrCreate('super_admin', 'web');
        $superAdmin->syncPermissions([
            'own_profile.view',
            'own_profile.update',
            'organizations.view',
            'organizations.manage',
            'users.view',
            'users.manage',
            'roles.manage',
            'permissions.manage',
            'system_configuration.manage',
            'ai_configuration.manage',
            'llm_providers.manage',
            'usage.view',
            'security.manage',
            'system_health.view',
            'integrations.manage',
            'webhooks.view',
            'webhooks.manage',
            'audit.view',
            'reports.view',
            'analytics.view',
        ]);
    }
}
