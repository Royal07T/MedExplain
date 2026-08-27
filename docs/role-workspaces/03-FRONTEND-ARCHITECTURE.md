# Frontend Architecture

## Overview

The MedExplain frontend is a Vue 3 SPA with TypeScript, built with Vite, using Pinia for state management and vue-router for routing. This document covers the refactored frontend architecture for role-specific workspaces.

## Tech Stack

| Technology | Version | Purpose |
|-----------|---------|---------|
| Vue | 3.5.13 | UI framework (Composition API, `<script setup>`) |
| TypeScript | 5.7.3 | Type safety |
| Vite | 6.0.7 | Build tool |
| Tailwind CSS | 4.0 | Styling (utility-first) |
| Pinia | 2.3.0 | State management |
| vue-router | 4.5.0 | Routing |
| axios | 1.7.9 | HTTP client |
| Vitest | 3.0.4 | Testing |

## Current Structure (Before)

```
frontend/src/
├── api/              (9 files)
├── components/       (11 files)
├── composables/      (3 files)
├── layouts/          (1 file)
├── router/           (1 file)
├── shared/           (EMPTY)
├── stores/           (5 files)
├── types/            (1 file)
├── views/            (16 files)
├── App.vue
├── main.ts
└── style.css
```

## Refactored Structure

```
frontend/src/
├── api/                          # API service layer (existing)
│   ├── client.ts                 # Axios instance with auth interceptor
│   ├── auth.ts                   # Authentication endpoints
│   ├── documents.ts              # Document endpoints
│   ├── health.ts                 # Health endpoints
│   ├── medications.ts            # Medication endpoints
│   ├── assistant.ts              # AI assistant endpoints
│   ├── clinician.ts              # Clinician endpoints (to be expanded)
│   ├── nursing.ts                # NEW: Nursing endpoints
│   ├── admin.ts                  # NEW: Admin endpoints
│   ├── superadmin.ts             # NEW: SuperAdmin endpoints
│   ├── patient.ts                # NEW: Patient workspace endpoints
│   ├── notifications.ts          # Notification endpoints
│   ├── partner.ts                # Partner endpoints
│   └── plan.ts                   # Plan endpoints
│
├── components/                   # Shared components (existing + new)
│   ├── Disclaimer.vue
│   ├── EmptyState.vue
│   ├── ErrorState.vue
│   ├── LineChart.vue
│   ├── LoadingState.vue
│   ├── NavbarMenu.vue            # To be replaced by sidebar
│   ├── ReportCard.vue
│   ├── ResultCard.vue
│   ├── StatusBadge.vue
│   ├── ToggleSwitch.vue
│   └── UploadDropzone.vue
│
├── composables/                  # Composables (existing + new)
│   ├── useAuth.ts                # Auth composable
│   ├── usePolling.ts             # Polling composable
│   ├── useSearch.ts              # Search composable
│   ├── usePermissions.ts         # NEW: Permission checking
│   └── usePatientContext.ts      # NEW: Patient context management
│
├── layouts/                      # Layouts (to be refactored)
│   └── AppLayout.vue             # Old layout (to be replaced)
│
├── router/                       # Router (to be restructured)
│   └── index.ts
│
├── shared/                       # NEW: Shared resources
│   ├── components/               # NEW: Reusable components
│   │   ├── PatientCard.vue
│   │   ├── LabResultCard.vue
│   │   ├── DataTable.vue
│   │   ├── StatCard.vue
│   │   ├── Modal.vue
│   │   ├── Chart.vue
│   │   ├── AIChat.vue
│   │   ├── DocumentViewer.vue
│   │   ├── PatientSelector.vue
│   │   ├── Timeline.vue
│   │   ├── AlertBanner.vue
│   │   └── ConfirmDialog.vue
│   │
│   ├── layouts/                  # NEW: Base layouts
│   │   ├── WorkspaceLayout.vue   # Base workspace layout with sidebar
│   │   └── Sidebar.vue           # Reusable sidebar component
│   │
│   └── composables/              # NEW: Shared composables
│       └── useDashboard.ts       # Dashboard data fetching
│
├── stores/                       # Pinia stores (existing + new)
│   ├── auth.ts                   # Auth store
│   ├── reports.ts                # Reports store
│   ├── health.ts                 # Health store
│   ├── medications.ts            # Medications store
│   ├── notifications.ts          # Notifications store
│   ├── patientContext.ts         # NEW: Patient context store
│   └── dashboard.ts              # NEW: Dashboard data store
│
├── types/                        # TypeScript types (to be updated)
│   └── index.ts
│
├── views/                        # Views (to be migrated to workspaces)
│   ├── Login.vue
│   ├── Register.vue
│   ├── Profile.vue
│   ├── Settings.vue
│   ├── Connections.vue
│   └── errors/
│       └── 403.vue
│
├── workspaces/                   # NEW: Role-specific workspaces
│   ├── patient/
│   │   ├── PatientLayout.vue
│   │   ├── PatientDashboard.vue
│   │   ├── PatientNavigation.vue
│   │   ├── PatientHealth.vue
│   │   ├── PatientAppointments.vue
│   │   ├── PatientRecords.vue
│   │   ├── PatientLabs.vue
│   │   ├── PatientMedications.vue
│   │   ├── PatientDocuments.vue
│   │   ├── PatientTimeline.vue
│   │   └── PatientAI.vue
│   │
│   ├── clinician/
│   │   ├── ClinicianLayout.vue
│   │   ├── ClinicianDashboard.vue
│   │   ├── ClinicianNavigation.vue
│   │   ├── PatientWorkspace.vue
│   │   ├── ClinicalIntelligence.vue
│   │   ├── Encounters.vue
│   │   ├── TriageQueue.vue
│   │   ├── LabOrders.vue
│   │   ├── Prescriptions.vue
│   │   ├── ClinicianAppointments.vue
│   │   └── ClinicianDocuments.vue
│   │
│   ├── nursing/
│   │   ├── NursingLayout.vue
│   │   ├── NursingDashboard.vue
│   │   ├── NursingNavigation.vue
│   │   ├── NursingTasks.vue
│   │   ├── VitalsEntry.vue
│   │   ├── MedicationAdmin.vue
│   │   ├── CarePlans.vue
│   │   ├── NursingNotes.vue
│   │   └── NursingAlerts.vue
│   │
│   ├── admin/
│   │   ├── AdminLayout.vue
│   │   ├── AdminDashboard.vue
│   │   ├── AdminNavigation.vue
│   │   ├── Operations.vue
│   │   ├── StaffManagement.vue
│   │   ├── DepartmentManagement.vue
│   │   ├── BillingManagement.vue
│   │   ├── InventoryManagement.vue
│   │   ├── AdminReports.vue
│   │   └── AdminAnalytics.vue
│   │
│   └── superadmin/
│       ├── SuperAdminLayout.vue
│       ├── SuperAdminDashboard.vue
│       ├── SuperAdminNavigation.vue
│       ├── OrganizationManagement.vue
│       ├── UserManagement.vue
│       ├── RoleManagement.vue
│       ├── SystemConfiguration.vue
│       ├── AIConfiguration.vue
│       ├── PlatformUsage.vue
│       ├── SecurityManagement.vue
│       ├── SystemHealth.vue
│       └── PlatformAuditLogs.vue
│
├── App.vue
├── main.ts
├── style.css
└── env.d.ts
```

## TypeScript Types Updates

### User Type

```typescript
// types/index.ts
export type UserRole = 'patient' | 'clinician' | 'admin' | 'super_admin' | 'nursing_staff'

export interface User {
    id: number
    name: string
    email: string
    role: UserRole
    plan: Plan
    organization_id: number | null
    permissions: string[]
    email_verified_at: string | null
    created_at: string
    profile: UserProfile | null
}
```

### Patient Context Types

```typescript
export interface PatientContext {
    patient_id: number
    patient_user_id: number
    mrn: string
    full_name: string
    date_of_birth: string | null
    gender: string | null
    phone: string | null
    email: string | null
}

export interface PatientSearchResult {
    id: number
    user_id: number
    mrn: string
    full_name: string
    date_of_birth: string | null
}
```

### Dashboard Types

```typescript
export interface PatientDashboardData {
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

export interface ClinicianDashboardData {
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

export interface NursingDashboardData {
    assigned_patients: Patient[]
    pending_vitals: Patient[]
    medication_rounds: MedicationAdministration[]
    nursing_tasks: NursingTask[]
    active_alerts: Alert[]
    admissions_discharges: Admission[]
}

export interface AdminDashboardData {
    patient_count: { total: number; new_today: number }
    appointments: { scheduled: number; completed: number; no_shows: number }
    admissions: { today: number; this_week: number }
    staff: { on_duty: number; available: number }
    laboratory: { ordered: number; completed: number; pending: number }
    pharmacy: { filled: number; pending: number }
    billing: { revenue: number; outstanding: number }
}

export interface SuperAdminDashboardData {
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

## Router Restructuring

### New Route Structure

```typescript
const router = createRouter({
    routes: [
        // Public routes
        { path: '/login', name: 'login', component: Login, meta: { guestOnly: true } },
        { path: '/register', name: 'register', component: Register, meta: { guestOnly: true } },
        { path: '/errors/403', name: 'error.403', component: Error403 },

        // Patient workspace
        {
            path: '/patient',
            component: () => import('@/workspaces/patient/PatientLayout.vue'),
            meta: { requiresAuth: true, role: ['patient'] },
            children: [
                { path: '', redirect: '/patient/dashboard' },
                { path: 'dashboard', name: 'patient.dashboard', component: PatientDashboard },
                { path: 'health', name: 'patient.health', component: PatientHealth },
                { path: 'appointments', name: 'patient.appointments', component: PatientAppointments },
                { path: 'records', name: 'patient.records', component: PatientRecords },
                { path: 'labs', name: 'patient.labs', component: PatientLabs },
                { path: 'medications', name: 'patient.medications', component: PatientMedications },
                { path: 'documents', name: 'patient.documents', component: PatientDocuments },
                { path: 'timeline', name: 'patient.timeline', component: PatientTimeline },
                { path: 'ai', name: 'patient.ai', component: PatientAI },
            ],
        },

        // Clinician workspace
        {
            path: '/clinician',
            component: () => import('@/workspaces/clinician/ClinicianLayout.vue'),
            meta: { requiresAuth: true, role: ['clinician'] },
            children: [
                { path: '', redirect: '/clinician/dashboard' },
                { path: 'dashboard', name: 'clinician.dashboard', component: ClinicianDashboard },
                { path: 'patients', name: 'clinician.patients', component: ClinicianPatients },
                { path: 'patients/:id', name: 'clinician.patient', component: PatientWorkspace },
                { path: 'encounters', name: 'clinician.encounters', component: Encounters },
                { path: 'triage', name: 'clinician.triage', component: TriageQueue },
                { path: 'lab-orders', name: 'clinician.labOrders', component: LabOrders },
                { path: 'prescriptions', name: 'clinician.prescriptions', component: Prescriptions },
                { path: 'appointments', name: 'clinician.appointments', component: ClinicianAppointments },
                { path: 'documents', name: 'clinician.documents', component: ClinicianDocuments },
                { path: 'intelligence', name: 'clinician.intelligence', component: ClinicalIntelligence },
            ],
        },

        // Nursing workspace
        {
            path: '/nursing',
            component: () => import('@/workspaces/nursing/NursingLayout.vue'),
            meta: { requiresAuth: true, role: ['nursing_staff'] },
            children: [
                { path: '', redirect: '/nursing/dashboard' },
                { path: 'dashboard', name: 'nursing.dashboard', component: NursingDashboard },
                { path: 'patients', name: 'nursing.patients', component: NursingPatients },
                { path: 'vitals', name: 'nursing.vitals', component: VitalsEntry },
                { path: 'tasks', name: 'nursing.tasks', component: NursingTasks },
                { path: 'medications', name: 'nursing.medications', component: MedicationAdmin },
                { path: 'care-plans', name: 'nursing.carePlans', component: CarePlans },
                { path: 'notes', name: 'nursing.notes', component: NursingNotes },
                { path: 'alerts', name: 'nursing.alerts', component: NursingAlerts },
            ],
        },

        // Admin workspace
        {
            path: '/admin',
            component: () => import('@/workspaces/admin/AdminLayout.vue'),
            meta: { requiresAuth: true, role: ['admin', 'super_admin'] },
            children: [
                { path: '', redirect: '/admin/dashboard' },
                { path: 'dashboard', name: 'admin.dashboard', component: AdminDashboard },
                { path: 'patients', name: 'admin.patients', component: AdminPatients },
                { path: 'staff', name: 'admin.staff', component: StaffManagement },
                { path: 'departments', name: 'admin.departments', component: DepartmentManagement },
                { path: 'appointments', name: 'admin.appointments', component: AdminAppointments },
                { path: 'admissions', name: 'admin.admissions', component: AdminAdmissions },
                { path: 'billing', name: 'admin.billing', component: BillingManagement },
                { path: 'inventory', name: 'admin.inventory', component: InventoryManagement },
                { path: 'reports', name: 'admin.reports', component: AdminReports },
                { path: 'analytics', name: 'admin.analytics', component: AdminAnalytics },
                { path: 'audit-logs', name: 'admin.auditLogs', component: AdminAuditLogs },
            ],
        },

        // SuperAdmin workspace
        {
            path: '/superadmin',
            component: () => import('@/workspaces/superadmin/SuperAdminLayout.vue'),
            meta: { requiresAuth: true, role: ['super_admin'] },
            children: [
                { path: '', redirect: '/superadmin/dashboard' },
                { path: 'dashboard', name: 'superadmin.dashboard', component: SuperAdminDashboard },
                { path: 'organizations', name: 'superadmin.organizations', component: OrganizationManagement },
                { path: 'users', name: 'superadmin.users', component: UserManagement },
                { path: 'roles', name: 'superadmin.roles', component: RoleManagement },
                { path: 'system/config', name: 'superadmin.systemConfig', component: SystemConfiguration },
                { path: 'ai/config', name: 'superadmin.aiConfig', component: AIConfiguration },
                { path: 'usage', name: 'superadmin.usage', component: PlatformUsage },
                { path: 'security', name: 'superadmin.security', component: SecurityManagement },
                { path: 'health', name: 'superadmin.health', component: SystemHealth },
                { path: 'audit-logs', name: 'superadmin.auditLogs', component: PlatformAuditLogs },
                { path: 'integrations', name: 'superadmin.integrations', component: PlatformIntegrations },
            ],
        },

        // Shared routes (all authenticated users)
        {
            path: '/',
            component: () => import('@/shared/layouts/WorkspaceLayout.vue'),
            meta: { requiresAuth: true },
            children: [
                { path: 'profile', name: 'profile', component: Profile },
                { path: 'settings', name: 'settings', component: Settings },
                { path: 'connections', name: 'connections', component: Connections },
            ],
        },

        // Catch-all redirect
        { path: '/:pathMatch(.*)*', redirect: '/patient/dashboard' },
    ],
})
```

### Route Guard Updates

```typescript
router.beforeEach(async (to) => {
    const auth = useAuthStore()

    // User hydration
    if (auth.isAuthenticated && !auth.user) {
        try {
            await auth.fetchUser()
        } catch {
            auth.clearAuth()
            return { name: 'login', query: { redirect: to.fullPath } }
        }
    }

    // Auth check
    if (to.meta.requiresAuth && !auth.isAuthenticated) {
        return { name: 'login', query: { redirect: to.fullPath } }
    }

    // Guest guard
    if (to.meta.guestOnly && auth.isAuthenticated) {
        return { name: getDashboardRoute(auth.user?.role) }
    }

    // Role-based access control
    if (to.meta.role && auth.user) {
        const allowedRoles = to.meta.role as string[]
        if (!allowedRoles.includes(auth.user.role)) {
            return { name: 'error.403' }
        }
    }

    // Redirect root to role-specific dashboard
    if (to.path === '/') {
        return { name: getDashboardRoute(auth.user?.role) }
    }
})

function getDashboardRoute(role?: string): string {
    const routes: Record<string, string> = {
        patient: 'patient.dashboard',
        clinician: 'clinician.dashboard',
        nursing_staff: 'nursing.dashboard',
        admin: 'admin.dashboard',
        super_admin: 'superadmin.dashboard',
    }
    return routes[role || ''] || 'login'
}
```

## State Management Updates

### Patient Context Store

```typescript
// stores/patientContext.ts
import { defineStore } from 'pinia'
import type { PatientContext, PatientSearchResult } from '@/types'
import * as patientApi from '@/api/patient'

export const usePatientContextStore = defineStore('patientContext', {
    state: () => ({
        currentPatient: null as PatientContext | null,
        searchResults: [] as PatientSearchResult[],
        loading: false,
        searchLoading: false,
    }),

    getters: {
        hasActivePatient: (state) => state.currentPatient !== null,
        patientId: (state) => state.currentPatient?.patient_id ?? null,
        patientUserId: (state) => state.currentPatient?.patient_user_id ?? null,
    },

    actions: {
        async selectPatient(patientId: number) {
            this.loading = true
            try {
                const response = await patientApi.selectPatient(patientId)
                this.currentPatient = response.data
            } finally {
                this.loading = false
            }
        },

        async clearPatient() {
            await patientApi.clearPatientContext()
            this.currentPatient = null
        },

        async searchPatients(query: string) {
            this.searchLoading = true
            try {
                const response = await patientApi.searchPatients(query)
                this.searchResults = response.data
            } finally {
                this.searchLoading = false
            }
        },

        async fetchContext() {
            try {
                const response = await patientApi.getCurrentContext()
                this.currentPatient = response.data
            } catch {
                this.currentPatient = null
            }
        },
    },
})
```

### Permissions Composable

```typescript
// composables/usePermissions.ts
import { useAuthStore } from '@/stores/auth'

export function usePermissions() {
    const auth = useAuthStore()

    function hasPermission(permission: string): boolean {
        return auth.user?.permissions?.includes(permission) ?? false
    }

    function hasAnyPermission(permissions: string[]): boolean {
        return permissions.some(p => hasPermission(p))
    }

    function hasAllPermissions(permissions: string[]): boolean {
        return permissions.every(p => hasPermission(p))
    }

    function hasRole(role: string): boolean {
        return auth.user?.role === role
    }

    function hasAnyRole(roles: string[]): boolean {
        return roles.includes(auth.user?.role || '')
    }

    return {
        hasPermission,
        hasAnyPermission,
        hasAllPermissions,
        hasRole,
        hasAnyRole,
    }
}
```

## Shared Components

### WorkspaceLayout.vue

```vue
<!-- shared/layouts/WorkspaceLayout.vue -->
<script setup lang="ts">
import Sidebar from '@/shared/components/Sidebar.vue'
import PatientSelector from '@/shared/components/PatientSelector.vue'
import { useAuth } from '@/composables/useAuth'
import { usePatientContextStore } from '@/stores/patientContext'

defineProps<{
    navigation: NavigationItem[]
    showPatientContext?: boolean
}>()

const { user } = useAuth()
const patientContext = usePatientContextStore()
</script>

<template>
    <div class="flex h-screen bg-slate-50">
        <!-- Sidebar -->
        <Sidebar :navigation="navigation" :user="user" />

        <!-- Main content -->
        <div class="flex flex-1 flex-col overflow-hidden">
            <!-- Header with patient context -->
            <header class="flex h-16 items-center justify-between border-b border-slate-200 bg-white px-6">
                <div class="flex items-center gap-4">
                    <PatientSelector v-if="showPatientContext" />
                </div>
                <!-- Notifications, user menu, etc. -->
            </header>

            <!-- Page content -->
            <main class="flex-1 overflow-y-auto p-6">
                <div class="mx-auto max-w-7xl">
                    <slot />
                </div>
            </main>
        </div>
    </div>
</template>
```

### Sidebar.vue

```vue
<!-- shared/components/Sidebar.vue -->
<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'

interface NavigationItem {
    label: string
    route: string
    icon: string
    permission?: string
    children?: NavigationItem[]
}

defineProps<{
    navigation: NavigationItem[]
    user: User | null
}>()

const route = useRoute()

function isActive(item: NavigationItem): boolean {
    return route.name === item.route || route.path.startsWith(`/${item.route.split('.')[0]}`)
}
</script>

<template>
    <aside class="flex w-64 flex-col border-r border-slate-200 bg-white">
        <!-- Logo -->
        <div class="flex h-16 items-center px-6">
            <span class="text-xl font-bold text-teal-600">MedExplain</span>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 overflow-y-auto px-3 py-4">
            <ul class="space-y-1">
                <li v-for="item in navigation" :key="item.route">
                    <router-link
                        :to="{ name: item.route }"
                        class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors"
                        :class="isActive(item)
                            ? 'bg-teal-50 text-teal-700'
                            : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'"
                    >
                        <!-- Icon -->
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
                        </svg>
                        {{ item.label }}
                    </router-link>
                </li>
            </ul>
        </nav>

        <!-- User info -->
        <div class="border-t border-slate-200 p-4">
            <div class="flex items-center gap-3">
                <div class="h-8 w-8 rounded-full bg-teal-100 flex items-center justify-center">
                    <span class="text-sm font-medium text-teal-600">
                        {{ user?.name?.charAt(0) || 'U' }}
                    </span>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium text-slate-900">{{ user?.name }}</p>
                    <p class="truncate text-xs text-slate-500">{{ user?.role }}</p>
                </div>
            </div>
        </div>
    </aside>
</template>
```

### PatientSelector.vue

```vue
<!-- shared/components/PatientSelector.vue -->
<script setup lang="ts">
import { ref, computed } from 'vue'
import { usePatientContextStore } from '@/stores/patientContext'

const patientContext = usePatientContextStore()
const searchQuery = ref('')
const isOpen = ref(false)

const hasActivePatient = computed(() => patientContext.hasActivePatient)

async function handleSearch() {
    if (searchQuery.value.length >= 2) {
        await patientContext.searchPatients(searchQuery.value)
    }
}

function selectPatient(patientId: number) {
    patientContext.selectPatient(patientId)
    isOpen.value = false
    searchQuery.value = ''
}

function clearPatient() {
    patientContext.clearPatient()
}
</script>

<template>
    <div class="relative">
        <!-- Active patient display -->
        <div v-if="hasActivePatient" class="flex items-center gap-2">
            <span class="text-sm text-slate-500">Patient:</span>
            <span class="font-medium text-slate-900">
                {{ patientContext.currentPatient?.full_name }}
            </span>
            <span class="text-xs text-slate-400">
                ({{ patientContext.currentPatient?.mrn }})
            </span>
            <button @click="clearPatient" class="text-slate-400 hover:text-slate-600">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Patient search -->
        <div v-else>
            <input
                v-model="searchQuery"
                @input="handleSearch"
                @focus="isOpen = true"
                placeholder="Select a patient..."
                class="w-64 rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-teal-500 focus:outline-none"
            />

            <!-- Search results dropdown -->
            <div v-if="isOpen && patientContext.searchResults.length > 0" class="absolute top-full left-0 z-50 mt-1 w-full rounded-lg border border-slate-200 bg-white shadow-lg">
                <ul class="max-h-60 overflow-y-auto">
                    <li v-for="patient in patientContext.searchResults" :key="patient.id">
                        <button
                            @click="selectPatient(patient.id)"
                            class="w-full px-4 py-2 text-left text-sm hover:bg-slate-50"
                        >
                            <p class="font-medium text-slate-900">{{ patient.full_name }}</p>
                            <p class="text-xs text-slate-500">MRN: {{ patient.mrn }}</p>
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>
```

## Migration Strategy

1. **Create shared components first** — `Sidebar.vue`, `WorkspaceLayout.vue`, `PatientSelector.vue`
2. **Update TypeScript types** — Add new types for roles, permissions, patient context
3. **Update router** — Restructure routes with role-based workspaces
4. **Create workspace layouts** — Each role gets a layout extending `WorkspaceLayout`
5. **Migrate views** — Move existing views to appropriate workspaces
6. **Create new views** — Build role-specific dashboards and features
7. **Update stores** — Add patient context store, dashboard store
8. **Add composables** — `usePermissions`, `usePatientContext`
9. **Test navigation** — Verify all routes work with proper role guards
10. **Remove old layout** — Replace `AppLayout.vue` with workspace layouts
