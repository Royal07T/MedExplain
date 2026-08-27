# Backend API Routes

## Route Structure

All routes are under `/api/v1/` prefix. Routes are organized by role-specific workspaces with proper middleware.

## Current Routes (Before Refactoring)

### Critical Issues

1. **Doctor routes outside auth:sanctum** — Lines 143-168 in `routes/api.php` are publicly accessible
2. **Missing imports** — Several controller classes referenced without `use` statements
3. **Inconsistent middleware** — Some routes lack proper role checks

## Refactored Route Structure

### Public Routes (No Auth)

```php
Route::prefix('v1')->group(function () {
    // OpenAPI spec
    Route::get('api-docs', [ApiDocsController::class, 'openapi']);

    // Email verification (signed URL)
    Route::get('email/verify/{id}/{hash}', [VerificationController::class, 'verify'])
        ->middleware('signed');

    // Auth routes
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register'])
            ->middleware('throttle:auth');
        Route::post('login', [AuthController::class, 'login'])
            ->middleware('throttle:auth');
        Route::post('forgot-password', [AuthController::class, 'forgotPassword'])
            ->middleware('throttle:auth');
        Route::post('reset-password', [AuthController::class, 'resetPassword'])
            ->middleware('throttle:auth');
    });

    // Partner OAuth (client credentials)
    Route::post('partner/oauth/token', [PartnerOAuthController::class, 'token'])
        ->middleware('throttle:partner-oauth');
});
```

### Authenticated Routes (auth:sanctum)

```php
Route::middleware('auth:sanctum')->group(function () {
    // User profile
    Route::get('user', [UserController::class, 'show']);
    Route::put('user', [UserController::class, 'update']);
    Route::post('user/avatar', [UserController::class, 'updateAvatar']);

    // Auth actions
    Route::post('auth/email/verification-notification', [AuthController::class, 'resendVerificationEmail']);
    Route::post('auth/logout', [AuthController::class, 'logout']);

    // Notifications (all authenticated users)
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead']);

    // Patient context (clinicians/nurses)
    Route::prefix('patient-context')->middleware('role:clinician,nursing_staff')->group(function () {
        Route::post('select', [PatientContextController::class, 'select']);
        Route::delete('/', [PatientContextController::class, 'clear']);
        Route::get('/', [PatientContextController::class, 'current']);
        Route::get('search', [PatientContextController::class, 'search']);
    });

    // ==================== PATIENT WORKSPACE ====================
    Route::prefix('patient')->middleware('role:patient')->group(function () {
        // Dashboard
        Route::get('dashboard', [PatientDashboardController::class, 'index']);

        // Appointments
        Route::get('appointments', [PatientAppointmentController::class, 'index']);

        // Records
        Route::get('records', [PatientRecordController::class, 'index']);

        // Labs
        Route::get('labs', [PatientLabController::class, 'index']);

        // Medications
        Route::get('medications', [PatientMedicationController::class, 'index']);

        // Documents
        Route::get('documents', [PatientDocumentController::class, 'index']);
        Route::post('documents', [PatientDocumentController::class, 'store'])
            ->middleware('throttle:documents');

        // Timeline
        Route::get('timeline', [PatientTimelineController::class, 'index']);

        // AI Query
        Route::post('ai/query', [PatientAIController::class, 'query'])
            ->middleware('throttle:health-query');
    });

    // ==================== CLINICIAN WORKSPACE ====================
    Route::prefix('clinician')->middleware('role:clinician')->group(function () {
        // Dashboard
        Route::get('dashboard', [ClinicianDashboardController::class, 'index']);

        // Patients
        Route::get('patients', [ClinicianPatientController::class, 'index']);
        Route::post('patients/grant', [ClinicianPatientController::class, 'grant']);
        Route::get('patients/{patient}/record', [ClinicianPatientController::class, 'record']);

        // Encounters
        Route::get('encounters', [ClinicianEncounterController::class, 'index']);
        Route::post('encounters', [ClinicianEncounterController::class, 'store']);
        Route::put('encounters/{encounter}', [ClinicianEncounterController::class, 'update']);

        // Triage
        Route::get('triage-queue', [ClinicianTriageController::class, 'queue']);

        // Lab orders
        Route::get('lab-orders', [ClinicianLabOrderController::class, 'index']);
        Route::post('lab-orders', [ClinicianLabOrderController::class, 'store']);
        Route::get('lab-orders/{id}', [ClinicianLabOrderController::class, 'show']);
        Route::post('lab-orders/{id}/status', [ClinicianLabOrderController::class, 'updateStatus']);

        // Prescriptions
        Route::get('prescriptions', [ClinicianPrescriptionController::class, 'index']);
        Route::post('prescriptions', [ClinicianPrescriptionController::class, 'store']);
        Route::get('prescriptions/{id}', [ClinicianPrescriptionController::class, 'show']);
        Route::post('prescriptions/{id}/status', [ClinicianPrescriptionController::class, 'updateStatus']);

        // Appointments
        Route::get('appointments', [ClinicianAppointmentController::class, 'index']);
        Route::post('appointments', [ClinicianAppointmentController::class, 'store']);

        // Documents
        Route::get('documents', [ClinicianDocumentController::class, 'index']);

        // AI Query (patient-context scoped)
        Route::post('ai/query', [ClinicianAIController::class, 'query'])
            ->middleware('throttle:health-query');
    });

    // ==================== NURSING WORKSPACE ====================
    Route::prefix('nursing')->middleware('role:nursing_staff')->group(function () {
        // Dashboard
        Route::get('dashboard', [NursingDashboardController::class, 'index']);

        // Patients
        Route::get('patients', [NursingPatientController::class, 'index']);

        // Vitals
        Route::get('vitals/{patientId}', [NursingVitalsController::class, 'history']);
        Route::post('vitals', [NursingVitalsController::class, 'store']);

        // Nursing notes
        Route::get('notes', [NursingNoteController::class, 'index']);
        Route::post('notes', [NursingNoteController::class, 'store']);

        // Medication administration
        Route::get('medication-administration', [NursingMedicationController::class, 'index']);
        Route::post('medication-administration', [NursingMedicationController::class, 'store']);

        // Care plans
        Route::get('care-plans', [NursingCarePlanController::class, 'index']);
        Route::post('care-plans', [NursingCarePlanController::class, 'store']);

        // Alerts
        Route::get('alerts', [NursingAlertController::class, 'index']);

        // Documents
        Route::get('documents', [NursingDocumentController::class, 'index']);
    });

    // ==================== ADMIN WORKSPACE ====================
    Route::prefix('admin')->middleware('role:admin,super_admin')->group(function () {
        // Dashboard
        Route::get('dashboard', [AdminDashboardController::class, 'index']);

        // Patients
        Route::get('patients', [AdminPatientController::class, 'index']);

        // Staff
        Route::get('staff', [AdminStaffController::class, 'index']);
        Route::post('staff/{userId}/departments/{departmentId}', [AdminStaffController::class, 'assign']);

        // Departments
        Route::get('departments', [AdminDepartmentController::class, 'index']);
        Route::post('departments', [AdminDepartmentController::class, 'store']);

        // Appointments
        Route::get('appointments', [AdminAppointmentController::class, 'index']);

        // Admissions
        Route::get('admissions', [AdminAdmissionController::class, 'index']);

        // Billing
        Route::get('billing', [AdminBillingController::class, 'index']);
        Route::post('billing', [AdminBillingController::class, 'store']);

        // Inventory
        Route::get('inventory', [AdminInventoryController::class, 'index']);

        // Reports
        Route::get('reports', [AdminReportController::class, 'index']);

        // Analytics
        Route::get('analytics', [AdminAnalyticsController::class, 'index']);

        // Audit logs
        Route::get('audit-logs', [AdminAuditController::class, 'index']);
    });

    // ==================== SUPER ADMIN WORKSPACE ====================
    Route::prefix('superadmin')->middleware('role:super_admin')->group(function () {
        // Dashboard
        Route::get('dashboard', [SuperAdminDashboardController::class, 'index']);

        // Organizations
        Route::get('organizations', [SuperAdminOrganizationController::class, 'index']);
        Route::post('organizations', [SuperAdminOrganizationController::class, 'store']);
        Route::get('organizations/{organization}', [SuperAdminOrganizationController::class, 'show']);

        // Users
        Route::get('users', [SuperAdminUserController::class, 'index']);
        Route::post('users', [SuperAdminUserController::class, 'store']);
        Route::get('users/{user}', [SuperAdminUserController::class, 'show']);
        Route::put('users/{user}', [SuperAdminUserController::class, 'update']);

        // Roles & Permissions
        Route::get('roles', [SuperAdminRoleController::class, 'index']);
        Route::put('roles/{role}/permissions', [SuperAdminRoleController::class, 'updatePermissions']);

        // System Configuration
        Route::get('system/config', [SuperAdminSystemController::class, 'config']);
        Route::put('system/config', [SuperAdminSystemController::class, 'updateConfig']);

        // AI Configuration
        Route::get('ai/config', [SuperAdminAIController::class, 'config']);
        Route::put('ai/config', [SuperAdminAIController::class, 'updateConfig']);

        // Usage
        Route::get('usage', [SuperAdminUsageController::class, 'index']);

        // Security
        Route::get('security', [SuperAdminSecurityController::class, 'index']);

        // System Health
        Route::get('system/health', [SuperAdminHealthController::class, 'index']);

        // Audit logs
        Route::get('audit-logs', [SuperAdminAuditController::class, 'index']);

        // Integrations
        Route::get('integrations', [SuperAdminIntegrationController::class, 'index']);
    });

    // ==================== SHARED ROUTES ====================
    // Documents (role-scoped)
    Route::get('documents', [DocumentController::class, 'index']);
    Route::get('documents/{document}', [DocumentController::class, 'show']);
    Route::get('documents/{document}/analysis', [DocumentController::class, 'analysis']);
    Route::delete('documents/{document}', [DocumentController::class, 'destroy']);

    // Labs (role-scoped)
    Route::get('labs/names', [LabController::class, 'names']);
    Route::get('labs/trends', [LabController::class, 'trends']);

    // Health (role-scoped)
    Route::get('health/timeline', [HealthController::class, 'timeline']);
    Route::get('health/record', [HealthController::class, 'record']);

    // Medications (role-scoped)
    Route::get('medications', [MedicationController::class, 'index']);

    // Partner consents
    Route::prefix('partner')->group(function () {
        Route::get('consents', [PartnerConsentController::class, 'index']);
        Route::post('consents/{partner}', [PartnerConsentController::class, 'grant']);
        Route::delete('consents/{partner}', [PartnerConsentController::class, 'revoke']);
    });
});

// Partner data access (separate auth)
Route::middleware(['auth:sanctum', 'partner', 'partner-scope:health_record:read', 'throttle:partner'])
    ->prefix('partner')
    ->group(function () {
        Route::get('patients', [PartnerDataController::class, 'patients']);
        Route::get('patients/{patient}/record', [PartnerDataController::class, 'record']);
    });
```

## Controller Organization

### New Controller Directories

```
app/Http/Controllers/Api/V1/
├── Auth/
│   ├── AuthController.php
│   └── VerificationController.php
├── Patient/                    ← NEW
│   ├── PatientDashboardController.php
│   ├── PatientAppointmentController.php
│   ├── PatientRecordController.php
│   ├── PatientLabController.php
│   ├── PatientMedicationController.php
│   ├── PatientDocumentController.php
│   ├── PatientTimelineController.php
│   └── PatientAIController.php
├── Clinician/                  ← NEW
│   ├── ClinicianDashboardController.php
│   ├── ClinicianPatientController.php
│   ├── ClinicianEncounterController.php
│   ├── ClinicianTriageController.php
│   ├── ClinicianLabOrderController.php
│   ├── ClinicianPrescriptionController.php
│   ├── ClinicianAppointmentController.php
│   ├── ClinicianDocumentController.php
│   └── ClinicianAIController.php
├── Nursing/                    ← NEW
│   ├── NursingDashboardController.php
│   ├── NursingPatientController.php
│   ├── NursingVitalsController.php
│   ├── NursingNoteController.php
│   ├── NursingMedicationController.php
│   ├── NursingCarePlanController.php
│   └── NursingAlertController.php
├── Admin/                      ← NEW
│   ├── AdminDashboardController.php
│   ├── AdminPatientController.php
│   ├── AdminStaffController.php
│   ├── AdminDepartmentController.php
│   ├── AdminAppointmentController.php
│   ├── AdminAdmissionController.php
│   ├── AdminBillingController.php
│   ├── AdminInventoryController.php
│   ├── AdminReportController.php
│   ├── AdminAnalyticsController.php
│   └── AdminAuditController.php
├── SuperAdmin/                 ← NEW
│   ├── SuperAdminDashboardController.php
│   ├── SuperAdminOrganizationController.php
│   ├── SuperAdminUserController.php
│   ├── SuperAdminRoleController.php
│   ├── SuperAdminSystemController.php
│   ├── SuperAdminAIController.php
│   ├── SuperAdminUsageController.php
│   ├── SuperAdminSecurityController.php
│   ├── SuperAdminHealthController.php
│   ├── SuperAdminAuditController.php
│   └── SuperAdminIntegrationController.php
├── PatientContextController.php  ← NEW
├── DocumentController.php
├── HealthController.php
├── HealthQueryController.php
├── LabController.php
├── MedicationController.php
├── NotificationController.php
├── UserController.php
└── ... (existing controllers)
```

## Dashboard Endpoints

### Patient Dashboard

```php
GET /api/v1/patient/dashboard

Response:
{
    "upcoming_appointments": [...],
    "recent_labs": [...],
    "medications": [...],
    "recent_documents": [...],
    "health_summary": {
        "total_labs": 12,
        "active_medications": 3,
        "recent_encounters": 1
    }
}
```

### Clinician Dashboard

```php
GET /api/v1/clinician/dashboard

Response:
{
    "today_appointments": [...],
    "waiting_patients": [...],
    "recent_encounters": [...],
    "pending_labs": [...],
    "patients_requiring_attention": [...],
    "stats": {
        "patients_today": 8,
        "encounters_completed": 3,
        "pending_reviews": 5
    }
}
```

### Nursing Dashboard

```php
GET /api/v1/nursing/dashboard

Response:
{
    "assigned_patients": [...],
    "pending_vitals": [...],
    "medication_rounds": [...],
    "nursing_tasks": [...],
    "active_alerts": [...],
    "admissions_discharges": [...]
}
```

### Admin Dashboard

```php
GET /api/v1/admin/dashboard

Response:
{
    "patient_count": { "total": 1250, "new_today": 12 },
    "appointments": { "scheduled": 45, "completed": 23, "no_shows": 2 },
    "admissions": { "today": 8, "this_week": 42 },
    "staff": { "on_duty": 34, "available": 12 },
    "laboratory": { "ordered": 28, "completed": 19, "pending": 9 },
    "pharmacy": { "filled": 15, "pending": 7 },
    "billing": { "revenue": 45000, "outstanding": 12000 }
}
```

### SuperAdmin Dashboard

```php
GET /api/v1/superadmin/dashboard

Response:
{
    "platform_overview": {
        "organizations": 5,
        "total_users": 2500,
        "active_sessions": 120
    },
    "ai_usage": {
        "queries_today": 450,
        "cost_today": 12.50,
        "avg_latency": 1.2
    },
    "system_health": {
        "uptime": "99.9%",
        "response_time": "120ms",
        "error_rate": "0.1%"
    }
}
```

## Rate Limiting

| Endpoint | Rate | Key |
|----------|------|-----|
| Auth (login/register) | 5/min | email + IP |
| Documents | 10/min | user ID |
| Health query | 10/min | user ID |
| Partner OAuth | 10/min | client_id |
| Partner data | per-partner quota | partner ID |
| General API | 60/min | user ID or IP |

## Error Responses

### 403 Forbidden

```json
{
    "message": "Forbidden.",
    "error": "You do not have permission to access this resource."
}
```

### 401 Unauthorized

```json
{
    "message": "Unauthenticated."
}
```

### 422 Validation Error

```json
{
    "message": "Validation failed",
    "errors": {
        "field": ["Error message"]
    }
}
```
