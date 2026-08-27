# Backend RBAC Implementation

## Overview

MedExplain uses spatie/laravel-permission for Role-Based Access Control. This document covers the complete RBAC implementation including roles, permissions, middleware, and policies.

## Installation

```bash
composer require spatie/laravel-permission
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
```

## Permission Schema

### Permissions Table Structure

The spatie package creates the following tables:
- `roles` — Role definitions
- `permissions` — Permission definitions
- `model_has_roles` — Links users to roles
- `model_has_permissions` — Links users to permissions
- `role_has_permissions` — Links roles to permissions

### Multi-Tenant Scoping

Add `organization_id` column to `roles` and `model_has_roles` tables for multi-tenant isolation:

```php
// In published migration
Schema::table('roles', function (Blueprint $table) {
    $table->foreignId('organization_id')->nullable()->constrained()->after('id');
});

Schema::table('model_has_roles', function (Blueprint $table) {
    $table->foreignId('organization_id')->nullable()->constrained()->after('role_id');
});
```

## Permission Definitions

### Patient Permissions

```php
$patientPermissions = [
    'own_profile.view',
    'own_profile.update',
    'own_records.view',
    'own_labs.view',
    'own_medications.view',
    'own_documents.view',
    'own_documents.upload',
    'own_appointments.view',
    'own_ai.query',
];
```

### Clinician Permissions

```php
$clinicianPermissions = [
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
    'prescriptions.create',
    'prescriptions.view',
    'documents.view',
    'documents.upload',
    'health_timeline.view',
    'ai.query',
];
```

### Nursing Staff Permissions

```php
$nursingPermissions = [
    'patients.view',
    'encounters.view',
    'vitals.create',
    'vitals.view',
    'nursing_notes.create',
    'nursing_notes.view',
    'medications.view',
    'medication_administration.create',
    'medication_administration.view',
    'care_plans.view',
    'care_plans.create',
    'alerts.view',
    'documents.view',
    'health_timeline.view',
];
```

### Admin Permissions

```php
$adminPermissions = [
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
];
```

### Super Admin Permissions

```php
$superAdminPermissions = [
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
    'audit.view',
];
```

## Role Assignment

### Role-to-Permission Mapping

| Role | Permissions Count | Key Permissions |
|------|------------------|-----------------|
| `patient` | 9 | own_profile.view, own_records.view, own_ai.query |
| `clinician` | 16 | patients.view, encounters.create, labs.order, ai.query |
| `nursing_staff` | 15 | vitals.create, medication_administration.create, care_plans.create |
| `admin` | 14 | staff.manage, departments.manage, billing.manage, audit.view |
| `super_admin` | 15 | organizations.manage, roles.manage, system_health.view |

### Seeder Implementation

```php
// database/seeders/PermissionSeeder.php
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

$roles = [
    'patient' => [...],
    'clinician' => [...],
    'nursing_staff' => [...],
    'admin' => [...],
    'super_admin' => [...],
];

foreach ($roles as $roleName => $permissions) {
    $role = Role::findOrCreate($roleName);
    foreach ($permissions as $permission) {
        $perm = Permission::findOrCreate($permission);
        $role->givePermissionTo($perm);
    }
}
```

## User Model Updates

### Adding HasRoles Trait

```php
// app/Models/User.php
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, MustVerifyEmailTrait, Notifiable, HasRoles;

    // ...

    public function isPatient(): bool
    {
        return $this->role === UserRole::Patient;
    }

    public function isClinician(): bool
    {
        return $this->role === UserRole::Clinician;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SuperAdmin;
    }

    public function isNursingStaff(): bool
    {
        return $this->role === UserRole::NursingStaff;
    }
}
```

## Middleware

### EnsureUserRole (Fixed)

```php
// app/Http/Middleware/EnsureUserRole.php
public function handle(Request $request, Closure $next, string ...$roles): Response
{
    $user = $request->user();

    if ($user === null || !$user->hasAnyRole($roles)) {
        abort(403, 'Forbidden.');
    }

    return $next($request);
}
```

### EnsurePermission (New)

```php
// app/Http/Middleware/EnsurePermission.php
public function handle(Request $request, Closure $next, string $permission): Response
{
    $user = $request->user();

    if ($user === null || !$user->hasPermissionTo($permission)) {
        abort(403, 'Forbidden.');
    }

    return $next($request);
}
```

### Registration

```php
// bootstrap/app.php
$middleware->alias([
    'role' => EnsureUserRole::class,
    'permission' => EnsurePermission::class,
]);
```

## Policies

### PatientPolicy

```php
// app/Policies/PatientPolicy.php
class PatientPolicy
{
    public function view(User $user, Patient $patient): bool
    {
        // Patient can view own record
        if ($user->id === $patient->user_id) {
            return true;
        }

        // Clinician with access grant
        if ($user->hasRole('clinician')) {
            return $user->clinicianPatients()
                ->where('patient_user_id', $patient->user_id)
                ->exists();
        }

        // Admin/super_admin in same organization
        if ($user->hasAnyRole(['admin', 'super_admin'])) {
            return $user->organization_id === $patient->organization_id;
        }

        return false;
    }
}
```

### EncounterPolicy

```php
class EncounterPolicy
{
    public function view(User $user, Encounter $encounter): bool
    {
        if ($user->hasRole('clinician')) {
            return $encounter->clinician_id === $user->id
                && $user->organization_id === $encounter->organization_id;
        }

        if ($user->hasRole('nursing_staff')) {
            return $user->organization_id === $encounter->organization_id;
        }

        return false;
    }
}
```

### LabResultPolicy

```php
class LabResultPolicy
{
    public function view(User $user, LabResult $labResult): bool
    {
        // Patient owns the result
        if ($user->id === $labResult->user_id) {
            return true;
        }

        // Clinician with access to patient
        if ($user->hasRole('clinician')) {
            return $user->clinicianPatients()
                ->where('patient_user_id', $labResult->user_id)
                ->exists();
        }

        // Nursing staff in same organization
        if ($user->hasRole('nursing_staff')) {
            return $user->organization_id === $labResult->organization_id;
        }

        return false;
    }
}
```

### Additional Policies

Create similar policies for:
- `MedicationPolicy` — patient ownership + clinician access
- `PrescriptionPolicy` — clinician ownership
- `AppointmentPolicy` — patient/clinician ownership
- `InvoicePolicy` — admin role + organization scope
- `InventoryItemPolicy` — admin role + organization scope

## Usage in Controllers

### Using Middleware

```php
// Route-level role check
Route::middleware('role:clinician,nursing_staff')->group(function () {
    Route::get('/patients', [PatientController::class, 'index']);
});

// Route-level permission check
Route::middleware('permission:billing.manage')->group(function () {
    Route::post('/billing', [BillingController::class, 'store']);
});
```

### Using Policies

```php
// In controller
public function show(Patient $patient)
{
    $this->authorize('view', $patient);
    // ...
}
```

### Inline Permission Checks

```php
// In controller or service
if (!$request->user()->hasPermissionTo('encounters.create')) {
    abort(403, 'Forbidden.');
}
```

## Cache Configuration

spatie/laravel-permission caches roles and permissions. Configure in `config/permission.php`:

```php
'cache_enabled' => true,
'cache_store' => 'redis',
'cache_prefix' => 'spatie.permission.cache',
```

Clear cache after role/permission changes:

```bash
php artisan cache:clear
php artisan permission:cache-reset
```

## Frontend Integration

### Permissions in User Response

Update `UserResource` to include permissions:

```php
// app/Http/Resources/UserResource.php
public function toArray(Request $request): array
{
    return [
        // ...
        'permissions' => $this->resource->getAllPermissions()->pluck('name'),
    ];
}
```

### Frontend Permission Checking

```typescript
// composables/usePermissions.ts
export function usePermissions() {
    const auth = useAuthStore()

    function hasPermission(permission: string): boolean {
        return auth.user?.permissions?.includes(permission) ?? false
    }

    function hasAnyPermission(permissions: string[]): boolean {
        return permissions.some(p => hasPermission(p))
    }

    function hasRole(role: string): boolean {
        return auth.user?.role === role
    }

    return { hasPermission, hasAnyPermission, hasRole }
}
```

## Testing RBAC

### Test Cases

1. **Role assignment** — Verify users can only have one role
2. **Permission inheritance** — Verify roles have correct permissions
3. **Middleware enforcement** — Verify unauthorized access returns 403
4. **Policy enforcement** — Verify resource ownership is checked
5. **Multi-tenant isolation** — Verify organization scoping works
6. **Cache invalidation** — Verify cache clears after role changes

### Test Commands

```bash
# Run RBAC tests
php artisan test --filter=RbacTest

# Test permission checks
php artisan tinker
>>> $user = User::find(1);
>>> $user->hasPermissionTo('patients.view');
>>> $user->hasRole('clinician');
```
