# Database Schema

## Overview

MedExplain uses MySQL 8.4 with 37 tables. This document covers the complete database schema including the spatie/laravel-permission tables for RBAC.

## Existing Tables (Current Schema)

### Core Auth Tables

#### users
```sql
CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    email_verified_at TIMESTAMP NULL,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(255) DEFAULT 'patient',
    plan VARCHAR(255) DEFAULT 'free',
    organization_id BIGINT UNSIGNED NULL,
    remember_token VARCHAR(100) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_users_organization (organization_id),
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE SET NULL
);
```

#### profiles
```sql
CREATE TABLE profiles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED UNIQUE NOT NULL,
    first_name VARCHAR(255) NULL,
    last_name VARCHAR(255) NULL,
    date_of_birth DATE NULL,
    gender VARCHAR(255) NULL,
    avatar_path VARCHAR(255) NULL,
    organization_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE SET NULL
);
```

#### personal_access_tokens (Sanctum)
```sql
CREATE TABLE personal_access_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tokenable_type VARCHAR(255) NOT NULL,
    tokenable_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    token VARCHAR(64) UNIQUE NOT NULL,
    abilities TEXT NULL,
    last_used_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_tokenable (tokenable_type, tokenable_id)
);
```

### Organization Tables

#### organizations
```sql
CREATE TABLE organizations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    address TEXT NULL,
    phone VARCHAR(255) NULL,
    email VARCHAR(255) NULL,
    website VARCHAR(255) NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

#### departments
```sql
CREATE TABLE departments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(255) UNIQUE NOT NULL,
    description TEXT NULL,
    capacity INT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
);
```

#### user_department (pivot)
```sql
CREATE TABLE user_department (
    user_id BIGINT UNSIGNED NOT NULL,
    department_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (user_id, department_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
);
```

### Clinical Tables

#### patients
```sql
CREATE TABLE patients (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NOT NULL,
    mrn VARCHAR(255) UNIQUE NOT NULL,
    first_name VARCHAR(255) NOT NULL,
    last_name VARCHAR(255) NOT NULL,
    date_of_birth DATE NULL,
    gender VARCHAR(255) NULL,
    blood_type VARCHAR(10) NULL,
    phone VARCHAR(255) NULL,
    email VARCHAR(255) NULL,
    address TEXT NULL,
    next_of_kin_name VARCHAR(255) NULL,
    next_of_kin_phone VARCHAR(255) NULL,
    next_of_kin_relationship VARCHAR(255) NULL,
    emergency_contact_name VARCHAR(255) NULL,
    emergency_contact_phone VARCHAR(255) NULL,
    emergency_contact_relationship VARCHAR(255) NULL,
    allergies TEXT NULL,
    immunizations JSON NULL,
    user_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_patients_organization (organization_id),
    INDEX idx_patients_mrn (mrn),
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);
```

#### encounters
```sql
CREATE TABLE encounters (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id BIGINT UNSIGNED NOT NULL,
    organization_id BIGINT UNSIGNED NOT NULL,
    clinician_id BIGINT UNSIGNED NULL,
    triage_id BIGINT UNSIGNED NULL,
    chief_complaint TEXT NULL,
    symptoms JSON NULL,
    clinical_observations TEXT NULL,
    acuity_level ENUM('triage1','triage2','triage3','triage4','triage5') NULL,
    queue_status ENUM('waiting','in_progress','completed','cancelled') DEFAULT 'waiting',
    check_in_time TIMESTAMP NULL,
    check_out_time TIMESTAMP NULL,
    vitals_summary JSON NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_encounters_patient (patient_id),
    INDEX idx_encounters_organization (organization_id),
    INDEX idx_encounters_clinician (clinician_id),
    INDEX idx_encounters_queue (queue_status),
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (clinician_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (triage_id) REFERENCES users(id) ON DELETE SET NULL
);
```

#### appointments
```sql
CREATE TABLE appointments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id BIGINT UNSIGNED NOT NULL,
    organization_id BIGINT UNSIGNED NOT NULL,
    clinician_id BIGINT UNSIGNED NULL,
    status ENUM('scheduled','confirmed','checked_in','in_progress','completed','cancelled','no_show') DEFAULT 'scheduled',
    acuity_level ENUM('triage1','triage2','triage3','triage4','triage5') NULL,
    chief_complaint TEXT NULL,
    symptoms JSON NULL,
    check_in_time TIMESTAMP NULL,
    check_out_time TIMESTAMP NULL,
    duration_minutes INT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_appointments_patient (patient_id),
    INDEX idx_appointments_organization (organization_id),
    INDEX idx_appointments_clinician (clinician_id),
    INDEX idx_appointments_status (status),
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (clinician_id) REFERENCES users(id) ON DELETE SET NULL
);
```

### Medical Document Tables

#### medical_documents
```sql
CREATE TABLE medical_documents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    organization_id BIGINT UNSIGNED NULL,
    original_filename VARCHAR(255) NOT NULL,
    storage_path VARCHAR(255) NOT NULL,
    mime_type VARCHAR(255) NOT NULL,
    file_size BIGINT UNSIGNED NOT NULL,
    document_type ENUM('lab_report','prescription','imaging','discharge_summary','other') DEFAULT 'other',
    status ENUM('uploaded','processing','processed','failed') DEFAULT 'uploaded',
    error_message TEXT NULL,
    processed_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_documents_user (user_id),
    INDEX idx_documents_organization (organization_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE SET NULL
);
```

#### document_extractions
```sql
CREATE TABLE document_extractions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    medical_document_id BIGINT UNSIGNED UNIQUE NOT NULL,
    organization_id BIGINT UNSIGNED NULL,
    extraction_method ENUM('text','ocr','none') DEFAULT 'none',
    raw_text LONGTEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (medical_document_id) REFERENCES medical_documents(id) ON DELETE CASCADE,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE SET NULL
);
```

### Lab Tables

#### lab_results
```sql
CREATE TABLE lab_results (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    document_extraction_id BIGINT UNSIGNED NULL,
    organization_id BIGINT UNSIGNED NULL,
    user_id BIGINT UNSIGNED NULL,
    name VARCHAR(255) NOT NULL,
    normalized_name VARCHAR(255) NOT NULL,
    value VARCHAR(255) NOT NULL,
    unit VARCHAR(255) NULL,
    loinc VARCHAR(255) NULL,
    reference_range VARCHAR(255) NULL,
    status ENUM('normal','high','low','critical_high','critical_low','positive','negative','unknown') DEFAULT 'unknown',
    collected_at TIMESTAMP NULL,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_lab_results_user (user_id),
    INDEX idx_lab_results_organization (organization_id),
    INDEX idx_lab_results_normalized (normalized_name),
    FOREIGN KEY (document_extraction_id) REFERENCES document_extractions(id) ON DELETE SET NULL,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);
```

#### lab_orders
```sql
CREATE TABLE lab_orders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    organization_id BIGINT UNSIGNED NOT NULL,
    clinician_id BIGINT UNSIGNED NOT NULL,
    test_name VARCHAR(255) NOT NULL,
    test_code VARCHAR(255) NULL,
    status ENUM('pending','ordered','in_progress','completed','cancelled') DEFAULT 'pending',
    result_due_date DATE NULL,
    notes TEXT NULL,
    ordered_at TIMESTAMP NULL,
    result_received_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_lab_orders_user (user_id),
    INDEX idx_lab_orders_organization (organization_id),
    INDEX idx_lab_orders_clinician (clinician_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (clinician_id) REFERENCES users(id) ON DELETE CASCADE
);
```

### Medication Tables

#### medications
```sql
CREATE TABLE medications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    organization_id BIGINT UNSIGNED NULL,
    medical_document_id BIGINT UNSIGNED NULL,
    name VARCHAR(255) NOT NULL,
    strength VARCHAR(255) NULL,
    dosage_form VARCHAR(255) NULL,
    dose VARCHAR(255) NULL,
    frequency VARCHAR(255) NULL,
    route VARCHAR(255) NULL,
    prescriber VARCHAR(255) NULL,
    indications TEXT NULL,
    start_date DATE NULL,
    end_date DATE NULL,
    sort_order INT DEFAULT 0,
    status ENUM('active','discontinued','completed') DEFAULT 'active',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_medications_user (user_id),
    INDEX idx_medications_organization (organization_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE SET NULL,
    FOREIGN KEY (medical_document_id) REFERENCES medical_documents(id) ON DELETE SET NULL
);
```

#### prescriptions
```sql
CREATE TABLE prescriptions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    organization_id BIGINT UNSIGNED NOT NULL,
    clinician_id BIGINT UNSIGNED NOT NULL,
    medication_id BIGINT UNSIGNED NOT NULL,
    status ENUM('pending','active','dispensed','completed','cancelled') DEFAULT 'pending',
    notes TEXT NULL,
    expires_at TIMESTAMP NULL,
    dispensed_at TIMESTAMP NULL,
    ordered_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_prescriptions_user (user_id),
    INDEX idx_prescriptions_organization (organization_id),
    INDEX idx_prescriptions_clinician (clinician_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (clinician_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (medication_id) REFERENCES medications(id) ON DELETE CASCADE
);
```

### AI Analysis Tables

#### ai_analyses
```sql
CREATE TABLE ai_analyses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    medical_document_id BIGINT UNSIGNED NOT NULL,
    organization_id BIGINT UNSIGNED NULL,
    status ENUM('pending','processing','completed','failed') DEFAULT 'pending',
    summary TEXT NULL,
    disclaimer TEXT NULL,
    concerns JSON NULL,
    error_message TEXT NULL,
    processed_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (medical_document_id) REFERENCES medical_documents(id) ON DELETE CASCADE,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE SET NULL
);
```

#### analysis_items
```sql
CREATE TABLE analysis_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ai_analysis_id BIGINT UNSIGNED NOT NULL,
    organization_id BIGINT UNSIGNED NULL,
    test_name VARCHAR(255) NULL,
    explanation TEXT NOT NULL,
    category ENUM('fact','reference_comparison','education','possible_context','question_for_professional') NOT NULL,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (ai_analysis_id) REFERENCES ai_analyses(id) ON DELETE CASCADE,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE SET NULL
);
```

### Access Control Tables

#### clinician_patient_access (pivot)
```sql
CREATE TABLE clinician_patient_access (
    clinician_user_id BIGINT UNSIGNED NOT NULL,
    patient_user_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (clinician_user_id, patient_user_id),
    FOREIGN KEY (clinician_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (patient_user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

### Billing & Inventory Tables

#### invoices
```sql
CREATE TABLE invoices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id BIGINT UNSIGNED NOT NULL,
    organization_id BIGINT UNSIGNED NOT NULL,
    appointment_id BIGINT UNSIGNED NULL,
    invoice_number VARCHAR(255) UNIQUE NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    paid_amount DECIMAL(10,2) DEFAULT 0,
    status ENUM('pending','partial','paid','overdue','cancelled') DEFAULT 'pending',
    payment_method ENUM('cash','card','insurance','bank_transfer','other') NULL,
    insurance_claim_id VARCHAR(255) NULL,
    notes TEXT NULL,
    issued_at TIMESTAMP NULL,
    due_at TIMESTAMP NULL,
    paid_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_invoices_patient (patient_id),
    INDEX idx_invoices_organization (organization_id),
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL
);
```

#### inventory_items
```sql
CREATE TABLE inventory_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    sku VARCHAR(255) UNIQUE NOT NULL,
    item_type VARCHAR(255) NULL,
    status ENUM('in_stock','low_stock','out_of_stock','expired') DEFAULT 'in_stock',
    quantity_on_hand INT DEFAULT 0,
    min_stock_level INT NULL,
    max_stock_level INT NULL,
    batch_number VARCHAR(255) NULL,
    expiration_date DATE NULL,
    supplier VARCHAR(255) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_inventory_organization (organization_id),
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
);
```

### Audit & Notification Tables

#### audit_logs
```sql
CREATE TABLE audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NULL,
    actor_type VARCHAR(255) NULL,
    actor_id BIGINT UNSIGNED NULL,
    user_id BIGINT UNSIGNED NULL,
    action VARCHAR(255) NOT NULL,
    auditable_type VARCHAR(255) NULL,
    auditable_id BIGINT UNSIGNED NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    metadata JSON NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_audit_organization (organization_id),
    INDEX idx_audit_user (user_id),
    INDEX idx_audit_action (action),
    INDEX idx_audit_auditable (auditable_type, auditable_id),
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);
```

#### notifications
```sql
CREATE TABLE notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    body TEXT NULL,
    type VARCHAR(255) NULL,
    data JSON NULL,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_notifications_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

### Partner Integration Tables

#### api_partners
```sql
CREATE TABLE api_partners (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    client_id VARCHAR(255) UNIQUE NOT NULL,
    client_secret VARCHAR(255) NOT NULL,
    scopes JSON NULL,
    quota_per_minute INT DEFAULT 30,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

#### patient_consents
```sql
CREATE TABLE patient_consents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    partner_id BIGINT UNSIGNED NOT NULL,
    patient_user_id BIGINT UNSIGNED NOT NULL,
    scopes JSON NULL,
    granted_at TIMESTAMP NULL,
    revoked_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY unique_consent (partner_id, patient_user_id),
    FOREIGN KEY (partner_id) REFERENCES api_partners(id) ON DELETE CASCADE,
    FOREIGN KEY (patient_user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

## New Tables (spatie/laravel-permission)

### roles
```sql
CREATE TABLE roles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    guard_name VARCHAR(255) NOT NULL,
    organization_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY unique_role (name, guard_name, organization_id),
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
);
```

### permissions
```sql
CREATE TABLE permissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    guard_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY unique_permission (name, guard_name)
);
```

### model_has_roles
```sql
CREATE TABLE model_has_roles (
    role_id BIGINT UNSIGNED NOT NULL,
    model_type VARCHAR(255) NOT NULL,
    model_id BIGINT UNSIGNED UNSIGNED NOT NULL,
    organization_id BIGINT UNSIGNED NULL,
    PRIMARY KEY (role_id, model_id, model_type),
    INDEX idx_model (model_type, model_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
);
```

### model_has_permissions
```sql
CREATE TABLE model_has_permissions (
    permission_id BIGINT UNSIGNED NOT NULL,
    model_type VARCHAR(255) NOT NULL,
    model_id BIGINT UNSIGNED UNSIGNED NOT NULL,
    PRIMARY KEY (permission_id, model_id, model_type),
    INDEX idx_model (model_type, model_id),
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
);
```

### role_has_permissions
```sql
CREATE TABLE role_has_permissions (
    role_id BIGINT UNSIGNED NOT NULL,
    permission_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
);
```

## Entity Relationship Diagram

```
┌─────────────┐     ┌──────────────┐     ┌─────────────┐
│  organizations│────<│    users     │>────│   profiles  │
└─────────────┘     └──────────────┘     └─────────────┘
                           │
                           │ role
                           ▼
                    ┌──────────────┐
                    │    roles     │
                    │(spatie)      │
                    └──────────────┘
                           │
                           │ permissions
                           ▼
                    ┌──────────────┐
                    │  permissions │
                    │(spatie)      │
                    └──────────────┘

┌─────────────┐     ┌──────────────┐
│  patients   │────<│  encounters  │
└─────────────┘     └──────────────┘
       │                    │
       │                    │ clinician_id
       │                    ▼
       │             ┌──────────────┐
       │             │    users     │
       │             └──────────────┘
       │
       ├────<┌──────────────┐
       │     │  lab_results │
       │     └──────────────┘
       │
       ├────<┌──────────────┐
       │     │ medications  │
       │     └──────────────┘
       │
       └────<┌──────────────┐
             │ appointments │
             └──────────────┘

┌─────────────┐     ┌──────────────┐     ┌─────────────┐
│  documents  │────<│ extractions  │────<│ lab_results │
└─────────────┘     └──────────────┘     └─────────────┘
       │
       └────<┌──────────────┐
             │ ai_analyses  │
             └──────────────┘
                    │
                    └────<┌──────────────┐
                          │analysis_items│
                          └──────────────┘
```

## Indexes for Performance

### Critical Indexes

| Table | Index | Purpose |
|-------|-------|---------|
| `users` | `idx_users_organization` | Organization-scoped queries |
| `users` | `email` (unique) | Authentication lookups |
| `patients` | `idx_patients_organization` | Organization-scoped queries |
| `patients` | `mrn` (unique) | MRN lookups |
| `encounters` | `idx_encounters_patient` | Patient encounters |
| `encounters` | `idx_encounters_queue` | Triage queue queries |
| `lab_results` | `idx_lab_results_user` | User lab results |
| `lab_results` | `idx_lab_results_normalized` | Lab name lookups |
| `audit_logs` | `idx_audit_user` | User audit trail |
| `audit_logs` | `idx_audit_action` | Action-based queries |

### Recommended Additional Indexes

```sql
-- For dashboard queries
CREATE INDEX idx_appointments_clinician_status ON appointments(clinician_id, status);
CREATE INDEX idx_encounters_clinician_status ON encounters(clinician_id, queue_status);
CREATE INDEX idx_lab_orders_clinician_status ON lab_orders(clinician_id, status);

-- For patient context
CREATE INDEX idx_patients_user ON patients(user_id);
CREATE INDEX idx_clinician_patient_access_patient ON clinician_patient_access(patient_user_id);

-- For AI queries
CREATE INDEX idx_medications_user_status ON medications(user_id, status);
CREATE INDEX idx_lab_results_user_normalized ON lab_results(user_id, normalized_name);
```

## Migration Strategy

### Adding spatie/laravel-permission Tables

```bash
# Install package
composer require spatie/laravel-permission

# Publish migration
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider" --tag="permission-migrations"

# Add organization_id to roles table
# (Edit published migration before running)

# Run migrations
php artisan migrate

# Seed roles and permissions
php artisan db:seed --class=PermissionSeeder
```

### Data Migration for Existing Users

```php
// In seeder or migration
$users = User::all();

foreach ($users as $user) {
    $role = Role::findOrCreate($user->role);
    $user->assignRole($role);
}
```

## Backup Strategy

### What to Backup

1. **Database** — Full MySQL dump daily
2. **Files** — S3/object storage backup
3. **Configuration** — Environment files
4. **Code** — Git repository

### Backup Schedule

| Type | Frequency | Retention |
|------|-----------|-----------|
| Full database | Daily | 30 days |
| Incremental | Hourly | 7 days |
| File storage | Daily | 30 days |
| Code | On commit | Indefinite |

### Recovery Testing

- Test restore monthly
- Verify data integrity
- Document recovery procedures
- Train staff on recovery process
