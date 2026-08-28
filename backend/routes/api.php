<?php

use App\Http\Controllers\Api\V1\Admin\AdminDashboardController;
use App\Http\Controllers\Api\V1\Admin\AdminDepartmentController;
use App\Http\Controllers\Api\V1\Admin\AdminStaffController;
use App\Http\Controllers\Api\V1\Admin\AdminInventoryController;
use App\Http\Controllers\Api\V1\Admin\AdminBillingController;
use App\Http\Controllers\Api\V1\AllergyController;
use App\Http\Controllers\Api\V1\AppointmentController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\VerificationController;
use App\Http\Controllers\Api\V1\BillingController;
use App\Http\Controllers\Api\V1\BedManagementController;
use App\Http\Controllers\Api\V1\Clinician\ClinicianDashboardController;
use App\Http\Controllers\Api\V1\ClinicianController;
use App\Http\Controllers\Api\V1\ClinicalNoteTemplateController;
use App\Http\Controllers\Api\V1\ClinicalDocumentationController;
use App\Http\Controllers\Api\V1\ClinicalAIController;
use App\Http\Controllers\Api\V1\ClinicalDecisionSupportController;
use App\Http\Controllers\Api\V1\DocumentController;
use App\Http\Controllers\Api\V1\EmergencyDepartmentController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\HealthQueryController;
use App\Http\Controllers\Api\V1\ImagingOrderController;
use App\Http\Controllers\Api\V1\LabController;
use App\Http\Controllers\Api\V1\LabOrderController;
use App\Http\Controllers\Api\V1\LabTestCatalogController;
use App\Http\Controllers\Api\V1\MedicationController;
use App\Http\Controllers\Api\V1\MessageController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\NursingDocumentationController;
use App\Http\Controllers\Api\V1\Patient\PatientDashboardController;
use App\Http\Controllers\Api\V1\PatientContextController;
use App\Http\Controllers\Api\V1\PartnerConsentController;
use App\Http\Controllers\Api\V1\PartnerDataController;
use App\Http\Controllers\Api\V1\PartnerOAuthController;
use App\Http\Controllers\Api\V1\PharmacyController;
use App\Http\Controllers\Api\V1\PlanController;
use App\Http\Controllers\Api\V1\PrescriptionController;
use App\Http\Controllers\Api\V1\PrescriptionRefillController;
use App\Http\Controllers\Api\V1\ProblemListController;
use App\Http\Controllers\Api\V1\SuperAdmin\SuperAdminAIController;
use App\Http\Controllers\Api\V1\SuperAdmin\SuperAdminDashboardController;
use App\Http\Controllers\Api\V1\SuperAdmin\SuperAdminHealthController;
use App\Http\Controllers\Api\V1\SuperAdmin\SuperAdminOrganizationController;
use App\Http\Controllers\Api\V1\SuperAdmin\SuperAdminUserController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\VitalSignController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {

    // ─── Public ───────────────────────────────────────────
    Route::get('api-docs', [\App\Http\Controllers\Api\V1\ApiDocsController::class, 'openapi']);

    Route::get('email/verify/{id}/{hash}', [VerificationController::class, 'verify'])
        ->middleware('signed')
        ->name('verification.verify');

    Route::prefix('auth')->group(function (): void {
        Route::post('register', [AuthController::class, 'register'])->middleware('throttle:auth');
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth');
        Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:auth');
        Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:auth');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::post('email/verification-notification', [AuthController::class, 'resendVerificationEmail'])
                ->middleware('throttle:6,1');
            Route::post('logout', [AuthController::class, 'logout']);
        });
    });

    // Partner OAuth — public, credential-gated.
    Route::post('partner/oauth/token', [PartnerOAuthController::class, 'token'])
        ->middleware('throttle:partner-oauth');

    // ─── All authenticated routes ──────────────────────────
    Route::middleware('auth:sanctum')->group(function (): void {

        // User profile (all roles)
        Route::get('user', [UserController::class, 'show']);
        Route::put('user', [UserController::class, 'update']);
        Route::post('user/avatar', [UserController::class, 'updateAvatar']);

        // Notifications (all roles)
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead']);

        // Plan (all roles)
        Route::get('plan', [PlanController::class, 'show']);
        Route::post('plan/upgrade', [PlanController::class, 'upgrade']);
        Route::post('plan/cancel', [PlanController::class, 'cancel']);

        // Partner consents
        Route::prefix('partner')->group(function (): void {
            Route::get('consents', [PartnerConsentController::class, 'index']);
            Route::post('consents/{partner}', [PartnerConsentController::class, 'grant'])->middleware('throttle:api');
            Route::delete('consents/{partner}', [PartnerConsentController::class, 'revoke'])->middleware('throttle:api');
        });

        // ─── Patient context (clinician + nursing) ─────────
        Route::prefix('patient-context')->middleware('role:clinician,nursing_staff')->group(function (): void {
            Route::post('select', [PatientContextController::class, 'select']);
            Route::delete('/', [PatientContextController::class, 'clear']);
            Route::get('/', [PatientContextController::class, 'current']);
            Route::get('search', [PatientContextController::class, 'search']);
        });

        // ─── PATIENT WORKSPACE ─────────────────────────────
        Route::prefix('patient')->middleware('role:patient')->group(function (): void {
            Route::get('dashboard', [PatientDashboardController::class, 'index']);
            
            // Appointments
            Route::get('appointments', [AppointmentController::class, 'patientAppointments'])->middleware('has.permission:own_appointments.view');
            Route::post('appointments', [AppointmentController::class, 'patientBookAppointment'])->middleware('throttle:api', 'has.permission:appointments.create');
            Route::get('appointments/{id}', [AppointmentController::class, 'show'])->middleware('has.permission:own_appointments.view');
            Route::delete('appointments/{id}', [AppointmentController::class, 'patientCancelAppointment'])->middleware('throttle:api', 'has.permission:appointments.cancel');

            // Prescription Refills
            Route::get('prescription-refills', [PrescriptionRefillController::class, 'index'])->middleware('has.permission:own_medications.view');
            Route::post('prescription-refills', [PrescriptionRefillController::class, 'store'])->middleware('throttle:api', 'has.permission:prescriptions.create');
            Route::get('prescription-refills/{id}', [PrescriptionRefillController::class, 'show'])->middleware('has.permission:own_medications.view');

            // Messaging
            Route::get('messages/conversations', [MessageController::class, 'conversations']);
            Route::get('messages/{userId}', [MessageController::class, 'index']);
            Route::post('messages', [MessageController::class, 'store'])->middleware('throttle:api');
            Route::post('messages/{id}/read', [MessageController::class, 'markAsRead']);

            // Billing
            Route::get('billing', [BillingController::class, 'patientInvoices'])->middleware('has.permission:billing.view');
            Route::post('billing/{id}/pay', [BillingController::class, 'patientPay'])->middleware('throttle:api', 'has.permission:billing.manage');

            // Clinical Note Templates
            Route::get('clinical-note-templates', [ClinicalNoteTemplateController::class, 'index'])->middleware('has.permission:clinical_notes.view');
            Route::post('clinical-note-templates', [ClinicalNoteTemplateController::class, 'store'])->middleware('throttle:api', 'has.permission:clinical_notes.create');
            Route::get('clinical-note-templates/{id}', [ClinicalNoteTemplateController::class, 'show'])->middleware('has.permission:clinical_notes.view');
            Route::put('clinical-note-templates/{id}', [ClinicalNoteTemplateController::class, 'update'])->middleware('throttle:api', 'has.permission:clinical_notes.update');
            Route::delete('clinical-note-templates/{id}', [ClinicalNoteTemplateController::class, 'destroy'])->middleware('has.permission:clinical_notes.delete');

            // Problem Lists
            Route::get('patients/{patientId}/problems', [ProblemListController::class, 'index'])->middleware('has.permission:patients.view');
            Route::post('problems', [ProblemListController::class, 'store'])->middleware('throttle:api', 'has.permission:patients.update');
            Route::put('problems/{id}', [ProblemListController::class, 'update'])->middleware('throttle:api', 'has.permission:patients.update');
            Route::delete('problems/{id}', [ProblemListController::class, 'destroy'])->middleware('has.permission:patients.update');

            // Allergies
            Route::get('patients/{patientId}/allergies', [AllergyController::class, 'index'])->middleware('has.permission:patients.view');
            Route::post('allergies', [AllergyController::class, 'store'])->middleware('throttle:api', 'has.permission:patients.update');
            Route::put('allergies/{id}', [AllergyController::class, 'update'])->middleware('throttle:api', 'has.permission:patients.update');
            Route::delete('allergies/{id}', [AllergyController::class, 'destroy'])->middleware('has.permission:patients.update');

            // Vital Signs
            Route::get('patients/{patientId}/vital-signs', [VitalSignController::class, 'index'])->middleware('has.permission:patients.view');
            Route::post('vital-signs', [VitalSignController::class, 'store'])->middleware('throttle:api', 'has.permission:patients.update');
            Route::put('vital-signs/{id}', [VitalSignController::class, 'update'])->middleware('throttle:api', 'has.permission:patients.update');
            Route::delete('vital-signs/{id}', [VitalSignController::class, 'destroy'])->middleware('has.permission:patients.update');

            // Prescriptions
            Route::get('patients/{patientId}/prescriptions', [PrescriptionController::class, 'index'])->middleware('has.permission:patients.view');
            Route::post('prescriptions', [PrescriptionController::class, 'store'])->middleware('throttle:api', 'has.permission:prescriptions.create');
            Route::put('prescriptions/{id}/status', [PrescriptionController::class, 'updateStatus'])->middleware('throttle:api', 'has.permission:prescriptions.update');
            Route::get('prescriptions/{id}', [PrescriptionController::class, 'show'])->middleware('has.permission:patients.view');

            // Pharmacy - Drug Inventory
            Route::get('pharmacy/inventory', [PharmacyController::class, 'inventoryIndex'])->middleware('has.permission:pharmacy.view');
            Route::post('pharmacy/inventory', [PharmacyController::class, 'inventoryStore'])->middleware('throttle:api', 'has.permission:pharmacy.manage');
            Route::put('pharmacy/inventory/{id}', [PharmacyController::class, 'inventoryUpdate'])->middleware('throttle:api', 'has.permission:pharmacy.manage');

            // Pharmacy - Formulary
            Route::get('pharmacy/formulary', [PharmacyController::class, 'formularyIndex'])->middleware('has.permission:pharmacy.view');
            Route::post('pharmacy/formulary', [PharmacyController::class, 'formularyStore'])->middleware('throttle:api', 'has.permission:pharmacy.manage');
            Route::put('pharmacy/formulary/{id}', [PharmacyController::class, 'formularyUpdate'])->middleware('throttle:api', 'has.permission:pharmacy.manage');

            // Laboratory - Test Catalog
            Route::get('lab/test-catalog', [LabTestCatalogController::class, 'index'])->middleware('has.permission:lab.view');
            Route::post('lab/test-catalog', [LabTestCatalogController::class, 'store'])->middleware('throttle:api', 'has.permission:lab.manage');
            Route::put('lab/test-catalog/{id}', [LabTestCatalogController::class, 'update'])->middleware('throttle:api', 'has.permission:lab.manage');
            Route::delete('lab/test-catalog/{id}', [LabTestCatalogController::class, 'destroy'])->middleware('has.permission:lab.manage');
        });

        // ─── CLINICIAN WORKSPACE ───────────────────────────
        Route::prefix('clinician')->middleware('role:clinician')->group(function (): void {
            Route::get('dashboard', [ClinicianDashboardController::class, 'index']);

            Route::get('patients', [ClinicianController::class, 'patients']);
            Route::post('patients', [ClinicianController::class, 'grantAccess'])->middleware('throttle:api');
            Route::get('patients/{patient}/record', [ClinicianController::class, 'record'])->middleware('throttle:api');

            Route::post('lab-orders', [LabOrderController::class, 'store'])->middleware('throttle:api');
            Route::get('lab-orders', [LabOrderController::class, 'index']);
            Route::get('lab-orders/{id}', [LabOrderController::class, 'show']);
            Route::post('lab-orders/{id}/status', [LabOrderController::class, 'updateStatus']);

            // Imaging / Radiology Orders
            Route::post('imaging/orders', [ImagingOrderController::class, 'store'])->middleware('throttle:api');
            Route::get('imaging/patients/{patientId}/orders', [ImagingOrderController::class, 'index']);
            Route::get('imaging/orders/{id}', [ImagingOrderController::class, 'show']);
            Route::post('imaging/orders/{id}/status', [ImagingOrderController::class, 'updateStatus']);
            Route::post('imaging/orders/{id}/result', [ImagingOrderController::class, 'recordResult']);
            Route::post('imaging/orders/{id}/cancel', [ImagingOrderController::class, 'cancel']);
            Route::post('imaging/orders/{id}/report', [ImagingOrderController::class, 'report']);

            // Appointments
            Route::get('patients/{patientId}/appointments', [AppointmentController::class, 'index'])->middleware('has.permission:appointments.view');
            Route::post('appointments', [AppointmentController::class, 'store'])->middleware('throttle:api', 'has.permission:appointments.create');
            Route::get('appointments/{id}', [AppointmentController::class, 'show'])->middleware('has.permission:appointments.view');
            Route::put('appointments/{id}/status', [AppointmentController::class, 'updateStatus'])->middleware('throttle:api', 'has.permission:appointments.update');
            Route::post('appointments/{id}/check-in', [AppointmentController::class, 'checkIn'])->middleware('throttle:api', 'has.permission:appointments.update');

            // Prescription Refills
            Route::get('prescription-refills', [PrescriptionRefillController::class, 'index'])->middleware('has.permission:medications.view');
            Route::put('prescription-refills/{id}', [PrescriptionRefillController::class, 'update'])->middleware('throttle:api', 'has.permission:prescriptions.update');

            // Messaging
            Route::get('messages/conversations', [MessageController::class, 'conversations']);
            Route::get('messages/{userId}', [MessageController::class, 'index']);
            Route::post('messages', [MessageController::class, 'store'])->middleware('throttle:api');
            Route::post('messages/{id}/read', [MessageController::class, 'markAsRead']);

            // Clinical Documentation
            Route::prefix('clinical')->group(function (): void {
                // Problem List
                Route::get('patients/{patientId}/problems', [ClinicalDocumentationController::class, 'getProblemList']);
                Route::post('problems', [ClinicalDocumentationController::class, 'storeProblem'])->middleware('throttle:api');
                Route::put('problems/{id}', [ClinicalDocumentationController::class, 'updateProblem'])->middleware('throttle:api');
                Route::delete('problems/{id}', [ClinicalDocumentationController::class, 'deleteProblem']);

                // Allergies
                Route::get('patients/{patientId}/allergies', [ClinicalDocumentationController::class, 'getAllergies']);
                Route::post('allergies', [ClinicalDocumentationController::class, 'storeAllergy'])->middleware('throttle:api');
                Route::put('allergies/{id}', [ClinicalDocumentationController::class, 'updateAllergy'])->middleware('throttle:api');
                Route::delete('allergies/{id}', [ClinicalDocumentationController::class, 'deleteAllergy']);

                // Vital Signs
                Route::get('patients/{patientId}/vital-signs', [ClinicalDocumentationController::class, 'getVitalSigns']);
                Route::get('patients/{patientId}/vital-signs/trends', [ClinicalDocumentationController::class, 'getVitalSignTrends']);
                Route::post('vital-signs', [ClinicalDocumentationController::class, 'storeVitalSign'])->middleware('throttle:api');

                // Clinical Notes
                Route::get('patients/{patientId}/clinical-notes', [ClinicalDocumentationController::class, 'getClinicalNotes']);
                Route::post('clinical-notes', [ClinicalDocumentationController::class, 'storeClinicalNote'])->middleware('throttle:api');
                Route::put('clinical-notes/{id}', [ClinicalDocumentationController::class, 'updateClinicalNote'])->middleware('throttle:api');
                Route::post('clinical-notes/{id}/cosign', [ClinicalDocumentationController::class, 'cosignClinicalNote'])->middleware('throttle:api');
                Route::delete('clinical-notes/{id}', [ClinicalDocumentationController::class, 'deleteClinicalNote']);

                // Clinical Note Templates
                Route::get('templates', [ClinicalDocumentationController::class, 'getTemplates']);
                Route::post('templates', [ClinicalDocumentationController::class, 'storeTemplate'])->middleware('throttle:api');
                Route::put('templates/{id}', [ClinicalDocumentationController::class, 'updateTemplate'])->middleware('throttle:api');
                Route::delete('templates/{id}', [ClinicalDocumentationController::class, 'deleteTemplate']);

                // Clinical Decision Support
                Route::prefix('cds')->group(function (): void {
                    Route::post('check-drug-allergy', [ClinicalDecisionSupportController::class, 'checkDrugAllergy'])->middleware('throttle:api');
                    Route::post('check-drug-interactions', [ClinicalDecisionSupportController::class, 'checkDrugInteractions'])->middleware('throttle:api');
                    Route::post('check-dose-adjustments', [ClinicalDecisionSupportController::class, 'checkDoseAdjustments'])->middleware('throttle:api');
                    Route::post('check-vital-signs', [ClinicalDecisionSupportController::class, 'checkVitalSigns'])->middleware('throttle:api');
                    Route::get('guidelines/{patientId}', [ClinicalDecisionSupportController::class, 'getGuidelineReminders']);
                    Route::get('preventive/{patientId}', [ClinicalDecisionSupportController::class, 'getPreventiveCareReminders']);
                    Route::post('comprehensive', [ClinicalDecisionSupportController::class, 'comprehensiveCheck'])->middleware('throttle:api');
                });
            });

            // AI Clinical Tools (NLP + Predictive)
            Route::prefix('ai')->group(function (): void {
                // 5.1 NLP
                Route::post('nlp/summarize', [ClinicalAIController::class, 'summarizeNote'])->middleware('throttle:api');
                Route::post('nlp/concepts', [ClinicalAIController::class, 'extractConcepts'])->middleware('throttle:api');
                Route::post('nlp/sentiment', [ClinicalAIController::class, 'analyzeSentiment'])->middleware('throttle:api');

                // 5.2 Predictive
                Route::post('predictive/readmission', [ClinicalAIController::class, 'predictReadmission'])->middleware('throttle:api');
                Route::post('predictive/length-of-stay', [ClinicalAIController::class, 'predictLengthOfStay'])->middleware('throttle:api');
                Route::post('predictive/deterioration', [ClinicalAIController::class, 'predictDeterioration'])->middleware('throttle:api');
            });
        });

        // ─── NURSING WORKSPACE ─────────────────────────────
        Route::prefix('nursing')->middleware('role:nursing_staff')->group(function (): void {
            Route::get('dashboard', [\App\Http\Controllers\Api\V1\Nursing\NursingDashboardController::class, 'index']);

            // Clinical Documentation
            Route::prefix('clinical')->group(function (): void {
                // Vital Signs
                Route::get('patients/{patientId}/vital-signs', [ClinicalDocumentationController::class, 'getVitalSigns']);
                Route::get('patients/{patientId}/vital-signs/trends', [ClinicalDocumentationController::class, 'getVitalSignTrends']);
                Route::post('vital-signs', [ClinicalDocumentationController::class, 'storeVitalSign'])->middleware('throttle:api');

                // Clinical Notes
                Route::get('patients/{patientId}/clinical-notes', [ClinicalDocumentationController::class, 'getClinicalNotes']);
                Route::post('clinical-notes', [ClinicalDocumentationController::class, 'storeClinicalNote'])->middleware('throttle:api');
                Route::put('clinical-notes/{id}', [ClinicalDocumentationController::class, 'updateClinicalNote'])->middleware('throttle:api');
            });

            // Bed Management
            Route::get('wards', [BedManagementController::class, 'wardsIndex']);
            Route::post('wards', [BedManagementController::class, 'wardStore'])->middleware('throttle:api');
            Route::get('wards/{wardId}/beds', [BedManagementController::class, 'bedsIndex']);
            Route::post('wards/{wardId}/beds', [BedManagementController::class, 'bedStore'])->middleware('throttle:api');
            Route::post('beds/{id}/assign', [BedManagementController::class, 'assignBed'])->middleware('throttle:api');
            Route::post('beds/{id}/discharge', [BedManagementController::class, 'dischargeBed'])->middleware('throttle:api');
            Route::post('beds/{id}/cleaning', [BedManagementController::class, 'updateCleaning'])->middleware('throttle:api');
            Route::get('utilization', [BedManagementController::class, 'utilization']);

            // Emergency Department
            Route::post('ed/visits', [EmergencyDepartmentController::class, 'checkIn'])->middleware('throttle:api');
            Route::post('ed/visits/{id}/triage', [EmergencyDepartmentController::class, 'triage'])->middleware('throttle:api');
            Route::post('ed/visits/{id}/assign', [EmergencyDepartmentController::class, 'assignClinician'])->middleware('throttle:api');
            Route::post('ed/visits/{id}/queue', [EmergencyDepartmentController::class, 'updateQueue'])->middleware('throttle:api');
            Route::post('ed/visits/{id}/disposition', [EmergencyDepartmentController::class, 'disposition'])->middleware('throttle:api');
            Route::get('ed/track-board', [EmergencyDepartmentController::class, 'trackBoard']);
            Route::post('ed/ambulance', [EmergencyDepartmentController::class, 'dispatchAmbulance'])->middleware('throttle:api');
            Route::post('ed/ambulance/{id}/status', [EmergencyDepartmentController::class, 'updateAmbulance'])->middleware('throttle:api');
            Route::get('ed/dashboard', [EmergencyDepartmentController::class, 'dashboard']);

            // Nursing Documentation
            Route::get('care-plans', [NursingDocumentationController::class, 'carePlansIndex']);
            Route::post('care-plans', [NursingDocumentationController::class, 'carePlanStore'])->middleware('throttle:api');
            Route::put('care-plans/{id}', [NursingDocumentationController::class, 'carePlanUpdate'])->middleware('throttle:api');
            Route::post('care-plans/{id}/status', [NursingDocumentationController::class, 'carePlanStatus'])->middleware('throttle:api');

            Route::get('mar', [NursingDocumentationController::class, 'medicationAdminIndex']);
            Route::post('mar', [NursingDocumentationController::class, 'medicationAdminStore'])->middleware('throttle:api');
            Route::post('mar/{id}/status', [NursingDocumentationController::class, 'medicationAdminStatus'])->middleware('throttle:api');

            Route::get('assessment-templates', [NursingDocumentationController::class, 'assessmentTemplates']);
            Route::get('assessments', [NursingDocumentationController::class, 'assessmentsIndex']);
            Route::post('assessments', [NursingDocumentationController::class, 'assessmentStore'])->middleware('throttle:api');
            Route::get('fall-risk', [NursingDocumentationController::class, 'fallRiskSummary']);

            Route::get('handoffs', [NursingDocumentationController::class, 'handoffsIndex']);
            Route::post('handoffs', [NursingDocumentationController::class, 'handoffStore'])->middleware('throttle:api');
        });

        // ─── ADMIN WORKSPACE ───────────────────────────────
        Route::prefix('admin')->middleware('role:admin,super_admin')->group(function (): void {
            Route::get('dashboard', [AdminDashboardController::class, 'index']);

            Route::get('departments', [AdminDepartmentController::class, 'index']);
            Route::post('departments', [AdminDepartmentController::class, 'store'])->middleware('throttle:api');
            Route::get('departments/{department}', [AdminDepartmentController::class, 'show']);
            Route::put('departments/{department}', [AdminDepartmentController::class, 'update']);
            Route::delete('departments/{department}', [AdminDepartmentController::class, 'destroy']);

            Route::get('staff', [AdminStaffController::class, 'index']);
            Route::post('staff/{userId}/departments/{departmentId}', [AdminStaffController::class, 'assign'])->middleware('throttle:api');
            Route::delete('staff/{userId}/departments/{departmentId}', [AdminStaffController::class, 'remove'])->middleware('throttle:api');

            Route::get('inventory', [AdminInventoryController::class, 'index']);
            Route::post('inventory', [AdminInventoryController::class, 'store'])->middleware('throttle:api');
            Route::get('inventory/{item}', [AdminInventoryController::class, 'show']);
            Route::put('inventory/{item}', [AdminInventoryController::class, 'update']);
            Route::delete('inventory/{item}', [AdminInventoryController::class, 'destroy']);

            Route::get('billing', [AdminBillingController::class, 'index']);
            Route::post('billing', [AdminBillingController::class, 'store'])->middleware('throttle:api');
            Route::get('billing/{invoice}', [AdminBillingController::class, 'show']);
            Route::post('billing/{invoice}/status', [AdminBillingController::class, 'updateStatus'])->middleware('throttle:api');
        });

        // ─── SUPER ADMIN WORKSPACE ─────────────────────────
        Route::prefix('superadmin')->middleware('role:super_admin')->group(function (): void {
            Route::get('dashboard', [SuperAdminDashboardController::class, 'index']);

            Route::get('organizations', [SuperAdminOrganizationController::class, 'index']);
            Route::post('organizations', [SuperAdminOrganizationController::class, 'store'])->middleware('throttle:api');
            Route::get('organizations/{organization}', [SuperAdminOrganizationController::class, 'show']);
            Route::put('organizations/{organization}', [SuperAdminOrganizationController::class, 'update']);

            Route::get('users', [SuperAdminUserController::class, 'index']);
            Route::get('users/{user}', [SuperAdminUserController::class, 'show']);
            Route::put('users/{user}', [SuperAdminUserController::class, 'update']);

            Route::get('ai/config', [SuperAdminAIController::class, 'config']);
            Route::get('ai/usage', [SuperAdminAIController::class, 'usage']);

            Route::get('system/health', [SuperAdminHealthController::class, 'index']);
        });

        // ─── Shared authenticated routes (role-scoped) ─────
        Route::middleware('throttle:api')->group(function (): void {
            // Documents
            Route::get('documents', [DocumentController::class, 'index']);
            Route::get('documents/{document}', [DocumentController::class, 'show']);
            Route::get('documents/{document}/analysis', [DocumentController::class, 'analysis']);
            Route::delete('documents/{document}', [DocumentController::class, 'destroy']);
            Route::post('documents', [DocumentController::class, 'store'])->middleware('throttle:documents');

            // Labs
            Route::get('labs/names', [LabController::class, 'names']);
            Route::get('labs/trends', [LabController::class, 'trends']);

            // Health
            Route::get('health/timeline', [HealthController::class, 'timeline']);
            Route::get('health/record', [HealthController::class, 'record']);

            // Medications
            Route::get('medications', [MedicationController::class, 'index']);

            // AI Query
            Route::post('health/query', [HealthQueryController::class, 'store'])
                ->middleware('throttle:health-query');
        });

        // ─── Doctor workspace (legacy — moved inside auth) ──
        Route::prefix('doctor')->group(function (): void {
            Route::get('triage-queue', [\App\Http\Controllers\Api\V1\DoctorWorkspaceController::class, 'triageQueue'])
                ->middleware('role:clinician');
            Route::get('assigned-patients', [\App\Http\Controllers\Api\V1\DoctorWorkspaceController::class, 'assignedPatients'])
                ->middleware('role:clinician');
        });
    });

    // ─── Partner data access (separate auth) ───────────────
    Route::middleware(['auth:sanctum', 'partner', 'partner-scope:health_record:read', 'throttle:partner'])
        ->prefix('partner')
        ->group(function (): void {
            Route::get('patients', [PartnerDataController::class, 'patients']);
            Route::get('patients/{patient}/record', [PartnerDataController::class, 'record']);
        });
});
