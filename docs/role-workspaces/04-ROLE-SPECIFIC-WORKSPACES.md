# Role-Specific Workspaces

## Overview

Each role in MedExplain gets a purpose-built workspace with dedicated navigation, dashboard, workflows, and content. This document details each workspace's requirements and implementation.

## Workspace Architecture

### Common Pattern

Every workspace follows this structure:

```
workspaces/{role}/
├── {Role}Layout.vue          # Layout extending WorkspaceLayout
├── {Role}Dashboard.vue       # Role-specific dashboard
├── {Role}Navigation.vue      # Sidebar navigation config
└── ... (role-specific views)
```

### Layout Hierarchy

```
WorkspaceLayout.vue (shared base)
    ├── Sidebar.vue (shared sidebar component)
    ├── PatientSelector.vue (for clinician/nurse roles)
    └── <slot /> (page content)

{Role}Layout.vue (extends WorkspaceLayout)
    └── <router-view /> (role-specific content)
```

---

## ROLE 1: Patient

### Workspace Root: `/patient/*`

### Navigation

```
Dashboard          /patient/dashboard
My Health          /patient/health
Appointments       /patient/appointments
Medical Records    /patient/records
Lab Results        /patient/labs
Medications        /patient/medications
Documents          /patient/documents
Health Timeline    /patient/timeline
Ask MedExplain     /patient/ai
```

### PatientNavigation.vue

```typescript
const patientNav = [
    { label: 'Dashboard', route: 'patient.dashboard', icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' },
    { label: 'My Health', route: 'patient.health', icon: 'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z' },
    { label: 'Appointments', route: 'patient.appointments', icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z' },
    { label: 'Medical Records', route: 'patient.records', icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
    { label: 'Lab Results', route: 'patient.labs', icon: 'M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z' },
    { label: 'Medications', route: 'patient.medications', icon: 'M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z' },
    { label: 'Documents', route: 'patient.documents', icon: 'M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z' },
    { label: 'Health Timeline', route: 'patient.timeline', icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' },
    { label: 'Ask MedExplain', route: 'patient.ai', icon: 'M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z' },
]
```

### Dashboard Widgets

| Widget | Data Source | Description |
|--------|------------|-------------|
| Welcome Card | User profile | Greeting with name and health summary |
| Upcoming Appointments | `/patient/appointments` | Next 3-5 appointments |
| Recent Lab Results | `/patient/labs` | Latest labs with status indicators |
| Current Medications | `/patient/medications` | Active medications list |
| Recent Documents | `/patient/documents` | Latest uploaded documents |
| Health Timeline Mini | `/patient/timeline` | Last 5 health events |
| Health Trends Mini | `/patient/labs` | Trend chart for key labs |
| Ask MedExplain Quick | — | Quick access to AI assistant |

### Patient Dashboard Response

```typescript
interface PatientDashboardData {
    upcoming_appointments: Appointment[]
    recent_labs: LabResult[]
    medications: Medication[]
    recent_documents: Document[]
    health_summary: {
        total_labs: number
        active_medications: number
        recent_encounters: number
    }
}
```

### Key Workflows

1. **View Health Summary** — Dashboard → My Health → consolidated view
2. **Schedule Appointment** — Appointments → Request new
3. **View Lab Results** — Lab Results → Select test → View trend
4. **Upload Document** — Documents → Upload → AI analysis
5. **Ask AI Question** — Ask MedExplain → Type question → Get answer

### Access Restrictions

- Can ONLY access own records
- Cannot view other patients' data
- Cannot access clinical operations
- Cannot access admin functions

---

## ROLE 2: Clinician

### Workspace Root: `/clinician/*`

### Navigation

```
Dashboard              /clinician/dashboard
My Patients            /clinician/patients
Patient Workspace      /clinician/patients/:id
Encounters             /clinician/encounters
Triage Queue           /clinician/triage
Lab Orders             /clinician/lab-orders
Prescriptions          /clinician/prescriptions
Appointments           /clinician/appointments
Documents              /clinician/documents
Clinical Intelligence  /clinician/intelligence
```

### ClinicianNavigation.vue

```typescript
const clinicianNav = [
    { label: 'Dashboard', route: 'clinician.dashboard', icon: '...' },
    { label: 'My Patients', route: 'clinician.patients', icon: '...' },
    { label: 'Encounters', route: 'clinician.encounters', icon: '...' },
    { label: 'Triage Queue', route: 'clinician.triage', icon: '...' },
    { label: 'Lab Orders', route: 'clinician.labOrders', icon: '...' },
    { label: 'Prescriptions', route: 'clinician.prescriptions', icon: '...' },
    { label: 'Appointments', route: 'clinician.appointments', icon: '...' },
    { label: 'Documents', route: 'clinician.documents', icon: '...' },
    { label: 'Clinical Intelligence', route: 'clinician.intelligence', icon: '...' },
]
```

### Dashboard Widgets

| Widget | Data Source | Description |
|--------|------------|-------------|
| Today's Schedule | `/clinician/dashboard` | Appointments for today |
| Waiting Patients | `/clinician/dashboard` | Checked-in, not yet seen |
| Recent Encounters | `/clinician/dashboard` | Last 24h encounters |
| Pending Labs | `/clinician/dashboard` | Labs requiring review |
| Patients Requiring Attention | `/clinician/dashboard` | Abnormal results, overdue follow-ups |
| Clinical Intelligence | `/clinician/dashboard` | AI-powered insights |
| Quick Actions | — | Start encounter, Order labs, Write prescription |

### Clinician Dashboard Response

```typescript
interface ClinicianDashboardData {
    today_appointments: Appointment[]
    waiting_patients: Patient[]
    recent_encounters: Encounter[]
    pending_labs: LabOrder[]
    patients_requiring_attention: Patient[]
    stats: {
        patients_today: number
        encounters_completed: number
        pending_reviews: number
    }
}
```

### Patient Context

When a clinician selects a patient:

1. **PatientSelector** component allows searching and selecting
2. **PatientContextStore** manages the active patient
3. **All subsequent views** scope to the selected patient
4. **AI queries** use the patient's data context

### Key Workflows

1. **Start Patient Visit** — Dashboard → Waiting patient → Start encounter
2. **Order Lab Test** — Patient workspace → Order labs → Select test → Submit
3. **Write Prescription** — Patient workspace → Prescriptions → Create
4. **Review Lab Results** — Lab orders → Pending → Review → Add notes
5. **Clinical Intelligence** — Select patient → Ask AI → Get insights

### Access Restrictions

- Can view granted patients only
- Cannot access admin functions
- Cannot access other clinicians' patients
- Must have explicit patient access grant

---

## ROLE 3: Nursing Staff

### Workspace Root: `/nursing/*`

### Navigation

```
Dashboard              /nursing/dashboard
My Patients            /nursing/patients
Vitals                 /nursing/vitals
Nursing Tasks          /nursing/tasks
Medication Administration  /nursing/medications
Care Plans             /nursing/care-plans
Clinical Notes         /nursing/notes
Alerts                 /nursing/alerts
```

### NursingNavigation.vue

```typescript
const nursingNav = [
    { label: 'Dashboard', route: 'nursing.dashboard', icon: '...' },
    { label: 'My Patients', route: 'nursing.patients', icon: '...' },
    { label: 'Vitals', route: 'nursing.vitals', icon: '...' },
    { label: 'Nursing Tasks', route: 'nursing.tasks', icon: '...' },
    { label: 'Medication Administration', route: 'nursing.medications', icon: '...' },
    { label: 'Care Plans', route: 'nursing.carePlans', icon: '...' },
    { label: 'Clinical Notes', route: 'nursing.notes', icon: '...' },
    { label: 'Alerts', route: 'nursing.alerts', icon: '...' },
]
```

### Dashboard Widgets

| Widget | Data Source | Description |
|--------|------------|-------------|
| Assigned Patients | `/nursing/dashboard` | Patients under care |
| Pending Vitals | `/nursing/dashboard` | Patients needing vitals |
| Medication Rounds | `/nursing/dashboard` | Upcoming administrations |
| Nursing Tasks | `/nursing/dashboard` | Care tasks due |
| Active Alerts | `/nursing/dashboard` | Critical alerts |
| Admissions/Discharges | `/nursing/dashboard` | Relevant movements |

### Nursing Dashboard Response

```typescript
interface NursingDashboardData {
    assigned_patients: Patient[]
    pending_vitals: Patient[]
    medication_rounds: MedicationAdministration[]
    nursing_tasks: NursingTask[]
    active_alerts: Alert[]
    admissions_discharges: Admission[]
}
```

### Key Workflows

1. **Record Vitals** — Dashboard → Pending vitals → Select patient → Enter vitals
2. **Administer Medication** — Medication rounds → Select patient → Record administration
3. **Create Nursing Note** — Notes → Create → Select patient → Write note
4. **View Care Plans** — Care plans → Select patient → View/update
5. **Respond to Alert** — Alerts → Select alert → Take action

### Access Restrictions

- Can view assigned patients only
- Cannot order labs or prescribe medications
- Cannot access admin functions
- Must have proper nursing credentials

---

## ROLE 4: Admin

### Workspace Root: `/admin/*`

### Navigation

```
Dashboard              /admin/dashboard
Patients               /admin/patients
Staff                  /admin/staff
Departments            /admin/departments
Appointments           /admin/appointments
Admissions             /admin/admissions
Billing                /admin/billing
Inventory              /admin/inventory
Reports                /admin/reports
Analytics              /admin/analytics
Audit Logs             /admin/audit-logs
```

### AdminNavigation.vue

```typescript
const adminNav = [
    { label: 'Dashboard', route: 'admin.dashboard', icon: '...' },
    { label: 'Patients', route: 'admin.patients', icon: '...' },
    { label: 'Staff', route: 'admin.staff', icon: '...' },
    { label: 'Departments', route: 'admin.departments', icon: '...' },
    { label: 'Appointments', route: 'admin.appointments', icon: '...' },
    { label: 'Admissions', route: 'admin.admissions', icon: '...' },
    { label: 'Billing', route: 'admin.billing', icon: '...' },
    { label: 'Inventory', route: 'admin.inventory', icon: '...' },
    { label: 'Reports', route: 'admin.reports', icon: '...' },
    { label: 'Analytics', route: 'admin.analytics', icon: '...' },
    { label: 'Audit Logs', route: 'admin.auditLogs', icon: '...' },
]
```

### Dashboard Widgets

| Widget | Data Source | Description |
|--------|------------|-------------|
| Patient Count | `/admin/dashboard` | Total, new today, active |
| Appointments | `/admin/dashboard` | Scheduled, completed, no-shows |
| Admissions/Discharges | `/admin/dashboard` | Today, this week |
| Staff | `/admin/dashboard` | On duty, available |
| Laboratory Activity | `/admin/dashboard` | Ordered, completed, pending |
| Pharmacy Activity | `/admin/dashboard` | Prescriptions filled, pending |
| Billing Metrics | `/admin/dashboard` | Revenue, outstanding, collections |
| Operational Analytics | `/admin/dashboard` | Bed occupancy, avg wait time |

### Admin Dashboard Response

```typescript
interface AdminDashboardData {
    patient_count: { total: number; new_today: number }
    appointments: { scheduled: number; completed: number; no_shows: number }
    admissions: { today: number; this_week: number }
    staff: { on_duty: number; available: number }
    laboratory: { ordered: number; completed: number; pending: number }
    pharmacy: { filled: number; pending: number }
    billing: { revenue: number; outstanding: number }
}
```

### Key Workflows

1. **View Operations** — Dashboard → Overview → Drill down
2. **Manage Staff** — Staff → View list → Assign departments
3. **Track Admissions** — Admissions → View today → Manage
4. **Review Billing** — Billing → View invoices → Process payments
5. **View Reports** — Reports → Select report type → Generate

### Access Restrictions

- Organization-scoped data only
- Cannot access clinical details without proper permissions
- Cannot access platform-wide settings
- Cannot modify clinical records

---

## ROLE 5: Super Admin

### Workspace Root: `/superadmin/*`

### Navigation

```
Platform Dashboard     /superadmin/dashboard
Organizations          /superadmin/organizations
Users                  /superadmin/users
Roles & Permissions    /superadmin/roles
System Configuration   /superadmin/system/config
AI Configuration       /superadmin/ai/config
Usage                  /superadmin/usage
Security               /superadmin/security
System Health          /superadmin/health
Audit Logs             /superadmin/audit-logs
Integrations           /superadmin/integrations
```

### SuperAdminNavigation.vue

```typescript
const superAdminNav = [
    { label: 'Platform Dashboard', route: 'superadmin.dashboard', icon: '...' },
    { label: 'Organizations', route: 'superadmin.organizations', icon: '...' },
    { label: 'Users', route: 'superadmin.users', icon: '...' },
    { label: 'Roles & Permissions', route: 'superadmin.roles', icon: '...' },
    { label: 'System Configuration', route: 'superadmin.systemConfig', icon: '...' },
    { label: 'AI Configuration', route: 'superadmin.aiConfig', icon: '...' },
    { label: 'Usage', route: 'superadmin.usage', icon: '...' },
    { label: 'Security', route: 'superadmin.security', icon: '...' },
    { label: 'System Health', route: 'superadmin.health', icon: '...' },
    { label: 'Audit Logs', route: 'superadmin.auditLogs', icon: '...' },
    { label: 'Integrations', route: 'superadmin.integrations', icon: '...' },
]
```

### Dashboard Widgets

| Widget | Data Source | Description |
|--------|------------|-------------|
| Platform Overview | `/superadmin/dashboard` | Orgs, users, sessions |
| Organization Health | `/superadmin/dashboard` | Per-org metrics |
| User Growth | `/superadmin/dashboard` | Registrations over time |
| AI Usage | `/superadmin/dashboard` | Queries, costs, latency |
| System Health | `/superadmin/dashboard` | Uptime, response times, errors |
| Security Alerts | `/superadmin/dashboard` | Failed logins, suspicious activity |
| Recent Audit Logs | `/superadmin/dashboard` | Platform-wide audit trail |
| Integration Status | `/superadmin/dashboard` | Connected services |

### SuperAdmin Dashboard Response

```typescript
interface SuperAdminDashboardData {
    platform_overview: {
        organizations: number
        total_users: number
        active_sessions: number
    }
    ai_usage: {
        queries_today: number
        cost_today: number
        avg_latency: number
    }
    system_health: {
        uptime: string
        response_time: string
        error_rate: string
    }
}
```

### Key Workflows

1. **Manage Organizations** — Organizations → View list → Create/edit
2. **Manage Users** — Users → View list → Create/edit/deactivate
3. **Configure Roles** — Roles & Permissions → Select role → Update permissions
4. **Monitor System** — System Health → View metrics → Respond to issues
5. **Review Security** — Security → View alerts → Take action

### Access Restrictions

- Platform-wide access
- Cannot access clinical data directly
- Cannot modify patient records
- Can manage organizations and users

---

## Shared Components Across Workspaces

| Component | Used By | Purpose |
|-----------|---------|---------|
| PatientCard | Clinician, Nursing, Admin | Display patient summary |
| LabResultCard | Patient, Clinician, Nursing | Display lab result |
| DataTable | All | Generic data table |
| StatCard | All dashboards | Display statistics |
| Modal | All | Confirmation dialogs |
| Chart | All dashboards | Data visualization |
| AIChat | Patient, Clinician, Nursing | AI assistant interface |
| DocumentViewer | All | View documents |
| PatientSelector | Clinician, Nursing | Patient context selection |
| Timeline | Patient, Clinician | Health event timeline |
| AlertBanner | Nursing, Admin | Display alerts |
| ConfirmDialog | All | Confirm actions |

## Testing Strategy

### Per-Workspace Tests

1. **Navigation** — Verify all nav items render correctly
2. **Routing** — Verify routes work with proper role guards
3. **Dashboard** — Verify widgets load with correct data
4. **Workflows** — Verify key user journeys work end-to-end
5. **Access Control** — Verify unauthorized access returns 403

### Cross-Workspace Tests

1. **Patient Context** — Verify context persists across views
2. **Role Switching** — Verify users can't access other roles' workspaces
3. **Shared Components** — Verify components work across workspaces
4. **AI Integration** — Verify AI queries scope correctly per role
