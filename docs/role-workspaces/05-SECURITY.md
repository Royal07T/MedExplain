# Security Implementation

## Overview

Healthcare data is highly sensitive. MedExplain implements multiple layers of security to protect patient data and ensure proper authorization. This document covers the security architecture, implementation, and best practices.

## Security Principles

1. **Backend Authorization is Mandatory** — Frontend checks are supplementary only
2. **Fail Closed** — Deny by default, grant access explicitly
3. **Least Privilege** — Users get minimum permissions needed
4. **Defense in Depth** — Multiple security layers
5. **Audit Everything** — Log all sensitive operations

## Authorization Layers

### Layer 1: Authentication (Sanctum)

All protected routes require a valid Sanctum bearer token.

```php
Route::middleware('auth:sanctum')->group(function () {
    // All protected routes
});
```

**Frontend:**
- Token stored in `localStorage` (key: `medexplain_token`)
- Axios interceptor adds `Authorization: Bearer {token}` to every request
- 401 response clears token and redirects to login

### Layer 2: Role Middleware

Route-level role checks using `EnsureUserRole` middleware.

```php
Route::middleware('role:clinician')->group(function () {
    // Clinician-only routes
});
```

**Implementation:**
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

### Layer 3: Permission Middleware

Granular permission checks using `EnsurePermission` middleware.

```php
Route::middleware('permission:billing.manage')->group(function () {
    // Billing management routes
});
```

**Implementation:**
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

### Layer 4: Policy Authorization

Resource-level authorization using Laravel Policies.

```php
// In controller
public function show(Patient $patient)
{
    $this->authorize('view', $patient);
    // ...
}
```

**Example Policy:**
```php
// app/Policies/PatientPolicy.php
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
```

### Layer 5: Tenant Isolation

Organization-scoped data isolation via `TenantIsolation` middleware.

```php
// app/Http/Middleware/TenantIsolation.php
public function handle(Request $request, Closure $next): Response
{
    $user = $request->user();

    if ($user && $user->organization_id) {
        $request->attributes->set('organization_id', $user->organization_id);
    }

    return $next($request);
}
```

**Usage in Controllers:**
```php
$organizationId = $request->attribute('organization_id');

$patients = Patient::where('organization_id', $organizationId)->get();
```

### Layer 6: Patient Context Validation

For clinician/nurse access to patient data, validate patient context.

```php
// In controller
public function show(Request $request, Patient $patient)
{
    $user = $request->user();

    // Validate patient access
    if ($user->hasRole('clinician')) {
        $hasAccess = $user->clinicianPatients()
            ->where('patient_user_id', $patient->user_id)
            ->exists();

        if (!$hasAccess) {
            abort(403, 'No access to this patient.');
        }
    }

    // ... proceed
}
```

### Layer 7: Frontend Route Guards

Client-side route guards for UX (not security).

```typescript
// router/index.ts
router.beforeEach(async (to) => {
    const auth = useAuthStore()

    // Auth check
    if (to.meta.requiresAuth && !auth.isAuthenticated) {
        return { name: 'login' }
    }

    // Role check
    if (to.meta.role && auth.user) {
        const allowedRoles = to.meta.role as string[]
        if (!allowedRoles.includes(auth.user.role)) {
            return { name: 'error.403' }
        }
    }
})
```

## Security Implementation by Role

### Patient

| Resource | Authorization | Implementation |
|----------|--------------|----------------|
| Own profile | User ID match | `user.id === patient.user_id` |
| Own records | User ID match | `user.id === record.user_id` |
| Own labs | User ID match | `user.id === lab.user_id` |
| Own medications | User ID match | `user.id === medication.user_id` |
| Own documents | User ID match | `user.id === document.user_id` |
| AI queries | User ID scope | `HealthQueryService` scopes to user |

### Clinician

| Resource | Authorization | Implementation |
|----------|--------------|----------------|
| Patient list | Access grants | `clinician_patient_access` pivot |
| Patient record | Access grants | `clinicianPatients()->exists()` |
| Encounters | Ownership + org | `encounter.clinician_id === user.id` |
| Lab orders | Ownership + org | `labOrder.clinician_id === user.id` |
| Prescriptions | Ownership + org | `prescription.clinician_id === user.id` |
| Appointments | Ownership + org | `appointment.clinician_id === user.id` |
| AI queries | Patient context | Uses selected patient's data |

### Nursing Staff

| Resource | Authorization | Implementation |
|----------|--------------|----------------|
| Assigned patients | Org scope | `user.organization_id === patient.organization_id` |
| Vitals | Patient access | `patient.organization_id === user.organization_id` |
| Nursing notes | Patient access | `patient.organization_id === user.organization_id` |
| Medication admin | Patient access | `patient.organization_id === user.organization_id` |
| Care plans | Patient access | `patient.organization_id === user.organization_id` |

### Admin

| Resource | Authorization | Implementation |
|----------|--------------|----------------|
| Patients | Org scope | `user.organization_id === patient.organization_id` |
| Staff | Org scope | `user.organization_id === staff.organization_id` |
| Departments | Org scope | `user.organization_id === department.organization_id` |
| Billing | Org scope | `user.organization_id === invoice.organization_id` |
| Inventory | Org scope | `user.organization_id === item.organization_id` |
| Audit logs | Org scope | `user.organization_id === log.organization_id` |

### Super Admin

| Resource | Authorization | Implementation |
|----------|--------------|----------------|
| Organizations | Platform-wide | `hasRole('super_admin')` |
| Users | Platform-wide | `hasRole('super_admin')` |
| Roles | Platform-wide | `hasRole('super_admin')` |
| System config | Platform-wide | `hasRole('super_admin')` |
| Audit logs | Platform-wide | `hasRole('super_admin')` |

## Audit Logging

### What to Audit

| Event | Priority | Details |
|-------|----------|---------|
| Patient record access | HIGH | Who accessed what patient data |
| Clinical record modifications | HIGH | Changes to encounters, prescriptions |
| Lab result access | HIGH | Who viewed lab results |
| Document access | MEDIUM | Who viewed uploaded documents |
| AI queries | MEDIUM | What questions were asked |
| Login/logout | LOW | Authentication events |
| Role changes | HIGH | Who changed user roles |
| Permission changes | HIGH | Who modified permissions |

### Audit Implementation

```php
// Using AuditService
$this->auditService->record(
    AuditEvent::PatientRecordAccessed,
    $user,
    [
        'patient_id' => $patient->id,
        'action' => 'view',
        'resource' => 'patient_record',
    ]
);
```

### Audit Log Schema

```php
Schema::create('audit_logs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('organization_id')->nullable();
    $table->string('actor_type');
    $table->foreignId('actor_id')->nullable();
    $table->foreignId('user_id')->nullable();
    $table->string('action');
    $table->morphs('auditable');
    $table->ipAddress('ip_address');
    $table->string('user_agent')->nullable();
    $table->json('metadata')->nullable();
    $table->timestamps();
});
```

## Rate Limiting

### Configuration

```php
// app/Providers/AppServiceProvider.php
RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
});

RateLimiter::for('auth', function (Request $request) {
    return Limit::perMinute(5)->by($request->input('email') . '|' . $request->ip());
});

RateLimiter::for('health-query', function (Request $request) {
    return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
});
```

### Usage

```php
Route::post('auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:auth');

Route::post('health/query', [HealthQueryController::class, 'store'])
    ->middleware('throttle:health-query');
```

## Security Checklist

### Authentication
- [ ] Sanctum tokens for all API access
- [ ] Token expiration configured appropriately
- [ ] 401 handling clears token and redirects
- [ ] Password reset uses secure tokens
- [ ] Email verification required

### Authorization
- [ ] Role middleware on all role-specific routes
- [ ] Permission middleware on sensitive operations
- [ ] Policies for all major resources
- [ ] Tenant isolation enforced globally
- [ ] Patient context validated for clinical access

### Data Protection
- [ ] No clinical data in logs
- [ ] No sensitive data in URLs
- [ ] HTTPS enforced
- [ ] CORS properly configured
- [ ] Input validation on all endpoints

### Audit
- [ ] All patient record access logged
- [ ] All clinical modifications logged
- [ ] All role/permission changes logged
- [ ] Audit logs immutable
- [ ] Audit logs retained appropriately

### Frontend
- [ ] Route guards for role-based access
- [ ] No sensitive data in localStorage (except token)
- [ ] XSS protection (Vue escapes by default)
- [ ] CSRF protection (Sanctum handles)
- [ ] No console.log of sensitive data

## Common Security Anti-Patterns to Avoid

### Don't

1. **Use frontend checks as security** — `v-if` hides UI, doesn't secure data
2. **Trust user input** — Always validate and sanitize
3. **Log sensitive data** — Never log passwords, tokens, or PHI
4. **Use GET for mutations** — POST/PUT/DELETE for changes
5. **Skip authorization checks** — Every endpoint needs authorization
6. **Hardcode secrets** — Use environment variables
7. **Disable HTTPS** — Always use HTTPS in production
8. **Ignore rate limiting** — Protect against abuse

### Do

1. **Validate on backend** — Every request, every time
2. **Use prepared statements** — Laravel's Eloquent does this
3. **Encrypt sensitive data** — At rest and in transit
4. **Implement least privilege** — Minimum permissions needed
5. **Log security events** — For incident response
6. **Regular security audits** — Review code and logs
7. **Keep dependencies updated** — Patch vulnerabilities
8. **Test security controls** — Penetration testing

## Incident Response

### If a Security Issue is Found

1. **Assess severity** — Is patient data at risk?
2. **Contain** — Block affected users/endpoints
3. **Investigate** — Review audit logs
4. **Remediate** — Fix the vulnerability
5. **Notify** — Report to affected parties if required
6. **Document** — Record the incident and response
7. **Prevent** — Add controls to prevent recurrence

### Security Contacts

- **Security Team**: security@medexplain.com
- **Incident Response**: incident@medexplain.com
- **Compliance**: compliance@medexplain.com
