# MedExplain Role-Specific Workspaces — Implementation Overview

## Executive Summary

This document outlines the refactoring of MedExplain from a single-dashboard application into a full Healthcare Management & Intelligence Platform with purpose-built workspaces for each role: Patient, Clinician, Nursing Staff, Admin, and Super Admin.

## Current State Assessment

### What Exists

| Layer | Current State | Issue |
|-------|--------------|-------|
| Backend RBAC | 5 roles as enum string, `EnsureUserRole` middleware (buggy, clinician-only) | No permissions system, `hasAnyRole()` called but doesn't exist |
| Backend Routes | Doctor routes outside `auth:sanctum`, no role-specific endpoints | Security gap, no workspace APIs |
| Frontend Routing | Flat routes, all roles share same routes | No role-based workspace routing |
| Frontend Layout | Single `AppLayout` with dropdown nav | No persistent sidebar, no role-specific layouts |
| Frontend Dashboard | One generic dashboard (document stats) for all roles | No role-specific widgets or workflows |
| Patient Context | Clinicians can view granted patients | No global "active patient" state |
| AI Integration | Mature health query system, same for all roles | No role-scoped AI capabilities |
| TypeScript Types | `User.role` only typed as `patient \| clinician` | Missing admin, super_admin, nursing_staff |

### Critical Bugs Found

1. **`EnsureUserRole` middleware** — hardcoded to only work for `clinician` role (line 21: `! $user->isClinician()`)
2. **`hasAnyRole()`** — called in `AdministrationController`, `PrescriptionController`, `LabOrderController` but method doesn't exist on User model
3. **Doctor routes** — entire `/api/v1/doctor/*` group is outside `auth:sanctum` middleware (publicly accessible)
4. **`Department::clinicians()` and `nurses()`** — return same pivot data without role filtering

## Architectural Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| RBAC Package | spatie/laravel-permission | Industry-standard, handles caching, supports multi-tenant scoping |
| Navigation | Persistent sidebar | Better for complex clinical/admin workflows with many nav items |
| Patient Context | Global selector (server-side) | Clinicians/nurses select patient once, all views scope automatically |
| Patient Model | Keep User/Patient separate | User handles auth, Patient handles clinical data, linked via `user_id` |
| Implementation | Incremental phases | Each phase independently testable and deployable |
| Authorization | Backend mandatory | Frontend checks are supplementary only; every API validates role + permission + ownership |

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Frontend | Vue 3.5 + TypeScript 5.7 + Vite 6 + Tailwind CSS 4 + Pinia 2.3 |
| Backend | Laravel 13 + PHP 8.3 + Sanctum 4.3 |
| RBAC | spatie/laravel-permission |
| AI Service | FastAPI + Python 3.12 + Pydantic 2 |
| Database | MySQL 8.4 |
| Cache/Queue | Redis 7 |

## Roles

| Role | Workspace Root | Dashboard |
|------|---------------|-----------|
| `patient` | `/patient/*` | Personal health overview |
| `clinician` | `/clinician/*` | Patient care management |
| `nursing_staff` | `/nursing/*` | Care delivery tasks |
| `admin` | `/admin/*` | Hospital operations |
| `super_admin` | `/superadmin/*` | Platform management |

## Implementation Phases

### Phase 1: Backend RBAC Foundation
- Install spatie/laravel-permission
- Create permissions migration and seeder
- Update User model with HasRoles trait
- Fix EnsureUserRole middleware
- Create resource policies
- Create EnsurePermission middleware

### Phase 2: Backend API for Role-Specific Workspaces
- Fix route security (move doctor routes inside auth:sanctum)
- Patient context API
- Role-specific dashboard endpoints
- Role-specific workspace endpoints
- Update HealthQueryService for patient context

### Phase 3: Frontend Architecture
- Update TypeScript types
- Create workspace directory structure
- Create shared components
- Create sidebar and workspace layouts
- Update router for role-based workspaces
- Create patient context store and permissions composable

### Phase 4: Role-Specific Dashboards
- Patient dashboard widgets
- Clinician dashboard widgets
- Nursing dashboard widgets
- Admin dashboard widgets
- SuperAdmin dashboard widgets

### Phase 5: Role-Specific Features
- Patient features (health, appointments, records, AI)
- Clinician features (patient list, 360 view, encounters, prescriptions)
- Nursing features (vitals, medication admin, care plans)
- Admin features (staff, departments, billing, inventory)
- SuperAdmin features (organizations, users, roles, system health)

## File Structure Reference

```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/V1/
│   │   │   ├── Auth/
│   │   │   ├── Patient/           ← NEW: Patient workspace controllers
│   │   │   ├── Clinician/         ← NEW: Clinician workspace controllers
│   │   │   ├── Nursing/           ← NEW: Nursing workspace controllers
│   │   │   ├── Admin/             ← NEW: Admin workspace controllers
│   │   │   ├── SuperAdmin/        ← NEW: SuperAdmin workspace controllers
│   │   │   └── PatientContextController.php  ← NEW
│   │   └── Middleware/
│   │       ├── EnsureUserRole.php     ← FIXED
│   │       └── EnsurePermission.php   ← NEW
│   ├── Models/
│   │   └── User.php               ← UPDATED: HasRoles trait
│   └── Policies/                  ← NEW: Resource policies
├── database/
│   ├── migrations/
│   │   └── *_permission_tables.php  ← NEW: spatie tables
│   └── seeders/
│       └── PermissionSeeder.php     ← NEW
└── routes/
    └── api.php                    ← RESTRUCTURED

frontend/
├── src/
│   ├── workspaces/                ← NEW: Role-specific workspaces
│   │   ├── patient/
│   │   ├── clinician/
│   │   ├── nursing/
│   │   ├── admin/
│   │   └── superadmin/
│   ├── shared/                    ← NEW: Shared components
│   │   ├── components/
│   │   ├── layouts/
│   │   └── composables/
│   ├── stores/
│   │   └── patientContext.ts      ← NEW
│   ├── composables/
│   │   ├── usePermissions.ts      ← NEW
│   │   └── usePatientContext.ts   ← NEW
│   ├── router/
│   │   └── index.ts               ← RESTRUCTURED
│   └── types/
│       └── index.ts               ← UPDATED
```

## Definition of Done

When complete:

- A **patient** should feel like they are using a personal health application
- A **clinician** should feel like they are using a clinical workspace
- A **nurse** should feel like they are using a nursing/care workspace
- An **admin** should feel like they are managing a hospital
- A **SuperAdmin** should feel like they are managing the healthcare platform
- All of them should still feel like they are using the same MedExplain ecosystem
- The application should no longer present the same dashboard/content to every role
- Backend authorization is mandatory on every API request
- Patient context is properly scoped and audited
