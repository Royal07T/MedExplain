# MEDEXPLAIN OS — Domain & Module Architecture

## 1. Overview

This document defines the domain-driven module architecture for MEDEXPLAIN OS, a production-grade, secure, AI-native healthcare platform. The architecture is organized into functional domains following the separation of concerns principle, with clear boundaries between operations, clinical data, and intelligence layers.

All domains connect to the **Unified Health Record (Patient 360)** at the center, which provides a longitudinal patient view while respecting organizational, role-based, and permission-based access controls.

The platform supports multiple healthcare organizations (multi-tenancy), with strict data isolation between organizations. Every resource belongs to exactly one organization, and access is determined by the authenticated user's organization, role, and specific permissions.

---

## 2. Architecture Layer Model

```
                           ┌───────────────────────────────────────────────────────┐
                           │                    PRESENTATION                        │
                           │  Vue 3 SPA + Tailwind | Patient Portal | Clinician     │
                           │                         Dashboard                   Workspace   │
                           └───────────────────────────────────────────────────────┘
                                           │
                                           ▼
 ┌─────────────────────────────────────────────────────────────────────┐
 │                            API LAYER                                 │
 │  Laravel 13 REST API with Sanctum auth, RBAC, tenant isolation   │
 │  All APIs require authentication + authorization + input validation│
 │  Consistent HTTP semantics, pagination, correlation IDs, audit    │
 │  logging, output filtering, rate limiting, secure error handling   │
 └─────────────────────────────────────────────────────────────────────┘
                                           │
                                           ▼
 ┌─────────────────────────────────────────────────────────────────────┐
 │                            SERVICE LAYER                             │
 │  Domain services with clear responsibilities                       │
 │  • Application services (use cases)                               │
 │  • Business logic (validations, workflows)                       │
 │  • Integration services (Laravel→FastAPI, service-to-service)      │
 │  • AI services (orchestration, RAG, document processing)         │
 │  • Queue/job services (background processing)                    │
 └─────────────────────────────────────────────────────────────────────┘
                                           │
                                           ▼
 ┌─────────────────────────────────────────────────────────────────────┐
 │                            DOMAIN LAYER                              │
 │  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐    │
 │  │ OPERATIONS      │  │ CLINICAL        │  │ INTELLIGENCE    │    │
 │  │                │  │                │  │                │    │
 │  │ • Patients     │  │ • EMR/EHR       │  │ • AI Assistant  │    │
 │  │ • Registration │  │ • Encounters    │  │ • RAG           │    │
 │  │ • Triage       │  │ • Diagnoses     │  │ • Document      │    │
 │  │ • Scheduling   │  │ • Vitals        │  │   Intelligence  │    │
 │  │ • Admissions   │  │ • Labs          │  │ • Patient       │    │
 │  │ • Billing      │  │ • Medications   │  │   Timeline      │    │
 │  │ • Insurance    │  │ • Procedures    │  │ • Trend         │    │
 │  │ • Inventory    │  │ • Clinical Notes│  • Analysis       │    │
 │  │ • Pharmacy     │  │ • Documents     │ • Clinical      │    │
 │  │ • Staff        │  │ • Referrals     • Summaries      │    │
 │  └─────────────────┘  └─────────────────┘  └─────────────────┘    │
 └─────────────────────────────────────────────────────────────────────┘
                                           │
                                           ▼
 ┌─────────────────────────────────────────────────────────────────────┐
 │                            INFRASTRUCTURE                           │
 │  • PostgreSQL (primary RDBMS)                                      │
 │  • Private Object Storage (S3-compatible, not public)             │
 │  • Redis (caching, queue broker)                                   │
 │  • PostgreSQL Full-Text Search                                     │
 │  • Vector Store (tenant-aware RAG)                                 │
 │  • Messaging Queue (background jobs)                               │
 │  • WAF / Reverse Proxy (nginx)                                     │
 └─────────────────────────────────────────────────────────────────────┘
                                           │
                                           ▼
                              ┌─────────────────┐
                              │  DATABASE       │
                              │  (PostgreSQL)   │
                              └─────────────────┘
```

---

## 3. Domain Definitions

### 3.1 Operations Domain

**Responsibility:** Healthcare organization administration, patient flow, and administrative operations.

**Sub-Modules:**
- **Patients:** MRN generation, demographics, contacts, next-of-kin, longitudinal record
- **Registration:** New patient onboarding, organization assignment, MRN creation
- **Triage:** Patient check-in, vital signs, chief complaint, acuity/priority, queue management
- **Scheduling:** Appointment creation, scheduling, rescheduling, cancellation, waiting queue
- **Admissions:** Hospital admission workflow, bed assignment, stay tracking, discharge
- **Billing:** Charges, invoices, payments, receipts, insurance claims, refunds, financial reporting
- **Insurance:** Coverage verification, claims processing, prior authorizations, EOB generation
- **Inventory:** Medication and supply stock levels, purchase orders, supplier management, batch tracking, low-stock alerts
- **Pharmacy:** Medication catalog, prescriptions, dispensing workflow, medication history, refills, inventory, expiration tracking
- **Staff:** User management, role assignment, department assignment, organization hierarchy

**Key Constraints:**
- Every resource has `organization_id`
- Patient search restricted to user's organization
- Billing authorization separate from clinical authorization (minimum necessary access)
- Inventory adjustments audited with full trail

### 3.2 Clinical Domain

**Responsibility:** Electronic medical records, clinical workflows, and patient care coordination.

**Sub-Modules:**
- **EMR/EHR:** Unified patient record, longitudinal timeline, patient 360 view, MRN-based unique identification
- **Encounters:** Patient encounters, triage information, vitals, chief complaint, symptoms, clinical observations
- **Diagnoses:** Problem list, active/recurring/resolved diagnoses, versioned history with corrections
- **Vitals:** Structured vital signs recording, trend tracking, comparison across encounters
- **Laboratory:** LIS workflow (order → specimen → processing → verification → result release), structured lab data, lab comparison/trend analysis
- **Imaging:** Imaging orders, report storage, comparison across studies, document attachments
- **Medications:** Medication catalog, prescribing, dispensing lifecycle (prescribed→approved→dispensed→active→discontinued), medication history, inventory, batch numbers, expiration tracking, low-stock alerts
- **Procedures:** Surgical procedures, interventions, performed by, timestamp, findings
- **Clinical Notes:** Versioned/append-only notes, author, timestamp, reason, correction history if modified
- **Documents:** Secure upload, private storage, malware scanning, authorization before access, audit access, short-lived download URLs, file type/signature validation
- **Referrals:** Referral creation, tracking, status, follow-up scheduling, external provider coordination

**Key Constraints:**
- All clinical data scoped by `organization_id` and `patient_id`
- Laboratory data stored as structured data (not PDFs as primary source of truth)
- Medication lifecycle tracked with status transitions
- Clinical notes append-only/versioned; corrections record original, who, when, why
- Documents never executed, stored outside web directory, private storage only
- Every action audited with actor, organization, action, resource, outcome

### 3.3 Intelligence Domain

**Responsibility:** AI-powered clinical intelligence, patient analytics, and decision support.

**Sub-Modules:**
- **AI Assistant:** Natural language patient questions, grounded in authorized data only, with citations
- **RAG (Retrieval-Augmented Generation):** Tenant-aware and permission-aware retrieval over patient documents and curated medical content
- **Document Intelligence:** Automated extraction, lab report parsing, medication identification, clinical event detection
- **Patient Timeline:** Longitudinal view of all patient events over time, organized chronologically
- **Trend Analysis:** Lab value trends, medication changes over time, vital signs progression
- **Lab Comparison:** Cross-report laboratory result comparison, reference range analysis, abnormality detection
- **Clinical Summaries:** Generated summaries of patient history, hospitalizations, procedures, medications
- **Record Search:** Authorization-scoped patient record search (MRN, name, phone, DOB)
- **Longitudinal Health Intelligence:** Aggregated insights across entire patient history
- **Analytics:** Aggregated, anonymized clinical and operational metrics (never patient-identifiable in raw form)

**Key Security Constraints (Critical):**
- **AI must NEVER bypass application permissions**: If Doctor A cannot access Patient X, AI must also be unable to retrieve Patient X
- **AI retrieval inherits user's:** organization, role, permissions, patient access, department restrictions where applicable
- **RAG system uses authorization-aware retrieval**: Never global vector database where documents from unrelated organizations become retrievable
- **Prompt injection defenses**: Separate SYSTEM INSTRUCTIONS from USER INPUT from RETRIEVED DATA from DOCUMENT CONTENT
- **Retrieved medical documents are DATA, not trusted instructions**: Never blindly follow instructions from PDFs, lab reports, uploaded documents, clinical notes, external data
- **AI responses must:** use only authorized data, identify relevant source records, provide traceability/citations, distinguish facts from interpretation, avoid inventing clinical information, avoid pretending certainty when missing, never modify clinical data without explicit workflow, never make autonomous clinical decisions, never override a clinician
- **Output categories**: `fact`, `reference_comparison`, `education`, `possible_context`, `question_for_professional`, always with medical disclaimer

**Data Flow (AI Query):**
```
User Query → Authentication → Organization/Role/Permission Check → 
Tenant-Scoped Retrieval (RAG/Vector DB) → 
LLM with Grounding (only user's authorized data) → 
Response with Citations → Disclaimer → User
```

---

## 4. Module Interconnections

### 4.1 Core Data Flow

```
Patient Registration
    ↓
Organization Assignment → Patient has organization_id
    ↓
Encounter Creation → Linked to patient + organization + clinician
    ↓
Vitals/Lab/Orders → All linked to encounter + patient + organization
    ↓
Medications → Prescribed → Dispensed → Active → Discontinued (all with organization_id)
    ↓
Documents Uploaded → Private storage → organization_id + uploader_id tracked
    ↓
AI Query → Auth + Org Check → Tenant-Scoped Retrieval → LLM → Authorized Response
    ↓
Billing → Charges → Invoices → Payments → All linked to patient + organization + encounter
    ↓
Inventory → Stock movements → All items have organization_id + location tracking
```

### 4.2 Authorization Flow

```
User Authenticates (Sanctum token)
    ↓
Determine organization from user.session.organization_id (never client-supplied)
    ↓
Check role permissions (RBAC: super/admin/org/admin/doctor/nurse/etc.)
    ↓
Check resource-level authorization (does user have permission on this specific resource?)
    ↓
Check department scoping if applicable (e.g., ER doctor vs. psychiatry doctor)
    ↓
Grant or deny access with audit log entry
    ↓
Return data (filtered by user's authorized scope only)
```

### 4.3 AI Authorization Flow

```
User Asks AI Question
    ↓
Authentication + Authorization check (same as clinical API)
    ↓
Extract user's: organization_id, role, permissions, patient_access list
    ↓
RAG retrieval: filter by organization_id + patient_access
    ↓
Only retrieve documents where: organization matches + patient is assigned to user's care
    ↓
LLM receives: only authorized document chunks + question + system instructions
    ↓
Generate response grounded only in retrieved data
    ↓
Add citations to source records (with organization/patient context)
    ↓
Add medical disclaimer
    ↓
Return response to user
```

---

## 5. Key Design Principles

### 5.1 Tenant Isolation
- **Every resource** has `organization_id` foreign key
- **Organization context** determined from authenticated user session
- **Never trust** client-supplied organization_id
- **Database constraints** enforce organization relationship
- **Query layer** automatically scopes by organization_id
- **RAG/vector search** explicitly filtered by organization
- **Cross-tenant tests** automated and required for every release

### 5.2 Least Privilege
- Users have only the permissions they need for their role
- **Having a role does NOT** grant broad access to all resources
- Resource-level authorization required for sensitive operations
- Department scoping where appropriate (e.g., ER vs. ICU)
- Just-in-time access for sensitive operations with approval workflow
- Automatic permission expiration

### 5.3 Auditability
- **Every** sensitive operation logged with: actor, organization, action, resource type, resource ID, timestamp, outcome, correlation ID
- Audit logs append-only, stored separately, integrity-protected (hash chaining)
- No sensitive clinical content in logs (PII redacted)
- Regular integrity verification of audit logs
- Quarterly access reviews

### 5.4 Fail-Safe Defaults
- Default deny: everything must be explicitly allowed
- Authentication required for all API endpoints (except public auth/health endpoints)
- Authorization enforced server-side; frontend checks UI-only
- Sensitive operations require additional authorization beyond role
- Default deny on all new resources until permission explicitly granted
- Audit logs capture all access attempts (success and failure)

### 5.5 Data Minimization
- Collect only what is needed, retain only as long as necessary
- PII redaction in logs and error responses
- Export controls: minimum necessary data
- Document storage: private object storage, never public buckets
- AI retrieval: only fetch data user is authorized to access
- Aggregated/anonymized analytics only (no patient-identifiable raw data)

### 5.6 Separation of Concerns
- Clinical authorization separate from financial authorization
- Billing employees do NOT automatically have access to full clinical records
- AI intelligence layer operates under application permissions
- Identity/access management separate from clinical data layer
- Document management separate from clinical notes (different storage, different permissions)

### 5.7 Defense in Depth
- Multiple layers of security (auth → authorization → input validation → output filtering → rate limiting → audit logging)
- No single point of failure for security
- If one control fails, others still protect
- Defense-in-depth at every layer: network, application, database, AI

### 5.8 Secure Defaults
- HTTPS everywhere, HSTS enabled
- Passwords hashed with bcrypt (never plaintext)
- Tokens stored in HttpOnly/Secure cookies when possible
- Rate limiting on all endpoints
- Input validation on all APIs
- Output filtering: never return sensitive fields unless required
- Error handling: safe user-facing errors, detailed internal logs protected from exposure
- No secrets in source code; all from environment/secret manager

---

## 6. Module-to-Database Mapping

| Module | Key Tables | Organization Scoping | Important Constraints |
|---|---|---|---|
| Patients | `patients`, `profiles` | `patients.organization_id` | MRN unique per organization |
| Operations | `appointments`, `admissions`, `discharges` | All have `organization_id` | Patient-provider linking enforced |
| Clinical EMR | `encounters`, `diagnoses`, `vitals`, `lab_results`, `medications`, `clinical_notes`, `documents` | All have `organization_id` + `patient_id` | Structured lab data; versioned clinical notes |
| Intelligence | AI query flow, RAG retrieval | Scoped at retrieval layer | Tenant-aware, permission-aware |
| Billing | `charges`, `invoices`, `payments`, `receipts` | `organization_id` + `patient_id` | Separate from clinical auth |
| Inventory | `inventory_items`, `stock_movements`, `purchase_orders` | `organization_id` + `location_id` | Batch/expiration tracking |
| Staff | `users`, `organizations`, `departments` | `users.organization_id` | RBAC with resource-level auth |

---

## 7. Technology Stack per Domain

| Domain | Backend | AI/ML | Database | Storage | Notifications |
|---|---|---|---|---|---|
| Operations | Laravel 13 (PHP 8.3) | — | PostgreSQL | Private object storage (S3-compatible) | Laravel Notifications |
| Clinical | Laravel 13 (PHP 8.3) | FastAPI service (Python 3.12) | PostgreSQL | Private object storage | Laravel Notifications |
| Intelligence | Laravel 13 (PHP 8.3) | FastAI service (Python 3.12) | PostgreSQL + Vector store | Private object storage | Laravel Notifications |
| Frontend | Vue 3 + Vite + TS + Tailwind | — | — | — | Pinia, axios |

---

## 8. API Design Conventions

### 8.1 All APIs Must Have:
- Authentication (Sanctum bearer tokens, or service-key for internal)
- Authorization (RBAC + resource-level checks, enforced server-side)
- Input validation (Pydantic schemas for AI, FormRequest for Laravel)
- Output filtering (never return sensitive fields unless required)
- Rate limiting (per-endpoint configs, per-organization where applicable)
- Request size limits
- Secure error handling (never stack traces, SQL, secrets to client)
- Consistent HTTP semantics (REST principles)
- Correlation IDs (propagated across all service boundaries)
- Audit logging where appropriate

### 8.2 Pagination:
- All list endpoints support pagination
- Default page size: 20, max: 100
- Link headers for next/prev pages
- Enumeration attack prevention (no sequential ID guessing)

### 8.3 Versioning:
- API versioned at `/api/v1/`
- Deprecation strategy: 12-month notice, `Deprecation` header, `Sunset` header
- Backward compatible changes only in minor versions

### 8.4 Error Responses:
- `400`: Bad request (invalid input)
- `401`: Unauthenticated (no/expired token)
- `403`: Unauthorized (authenticated but not authorized)
- `422`: Validation errors
- `429`: Rate limit exceeded
- `500`: Internal error (user-facing: generic message; internal: detailed in protected logs)
- Never return stack traces or internal implementation details to clients

---

## 9. Security By Design Summary

| Principle | Implementation |
|---|---|
| Tenant Isolation | `organization_id` on every resource; never client-supplied; DB constraints; middleware scoping; RAG filtering |
| RBAC + Resource Auth | Roles + explicit permissions; having role ≠ broad access; resource-level checks; department scoping |
| Audit Logging | Every sensitive action logged with metadata; append-only; integrity-protected; no PII in logs |
| Input Validation | MIME + size on uploads; Pydantic schemas for AI; FormRequest for Laravel; never trust frontend |
| Output Filtering | Never return sensitive fields; paginate large sets; mask/PII-redact in non-sensitive contexts |
| Encryption in Transit | TLS everywhere, HSTS, HTTPS-only cookies |
| Encryption at Rest | Database encryption, object storage encryption, key management via environment/secret manager |
| Secure Defaults | Deny-by-default; auth required; authorization enforced; rate limiting; input validation |
| Fail-Safe | All errors fall back to safe state; no stack traces to client; audit captures outcome; defaults to deny |
| Data Minimization | Only collect/retain needed; PII redaction; aggregated analytics only; minimum necessary export |
| Separation of Duties | Clinical ≠ financial authorization; AI ≠ autonomous clinician; different roles for different operations |
| Defense in Depth | Multiple security layers; if one fails, others protect; depth at network, app, DB, AI layers |

---
*Document generated as part of MEDEXPLAIN OS architecture specification.*
*Last updated: 2026-08-24*