import { useAuthStore } from '@/stores/auth'
import { createRouter, createWebHistory } from 'vue-router'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    { path: '/', redirect: '/dashboard' },
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/Login.vue'),
      meta: { guestOnly: true },
    },
    {
      path: '/register',
      name: 'register',
      component: () => import('@/views/Register.vue'),
      meta: { guestOnly: true },
    },
    {
      path: '/errors/403',
      name: 'error.403',
      component: () => import('@/views/errors/403.vue'),
    },

    // ─── PATIENT WORKSPACE ─────────────────────────────────
    {
      path: '/patient',
      component: () => import('@/layouts/WorkspaceLayout.vue'),
      meta: { requiresAuth: true, role: ['patient'] },
      children: [
        { path: '', redirect: { name: 'patient.dashboard' } },
        { path: 'dashboard', name: 'patient.dashboard', component: () => import('@/views/dashboards/PatientDashboard.vue'), meta: { title: 'Dashboard' } },
        { path: 'appointments', name: 'patient.appointments', component: () => import('@/views/PatientAppointments.vue'), meta: { title: 'Appointments' } },
        { path: 'prescription-refills', name: 'patient.prescriptionRefills', component: () => import('@/views/PrescriptionRefills.vue'), meta: { title: 'Prescription Refills' } },
        { path: 'messages', name: 'patient.messages', component: () => import('@/views/Messages.vue'), meta: { title: 'Messages' } },
        { path: 'billing', name: 'patient.billing', component: () => import('@/views/Billing.vue'), meta: { title: 'Billing' } },
        { path: 'reports', name: 'patient.reports', component: () => import('@/views/Reports.vue'), meta: { title: 'Reports' } },
        { path: 'reports/upload', name: 'patient.reports.upload', component: () => import('@/views/Upload.vue'), meta: { title: 'Upload Report' } },
        { path: 'reports/:id', name: 'patient.reports.detail', component: () => import('@/views/ReportDetail.vue'), props: true, meta: { title: 'Report Details' } },
        { path: 'health-record', name: 'patient.healthRecord', component: () => import('@/views/HealthRecord.vue'), meta: { title: 'My Health Record' } },
        { path: 'trends', name: 'patient.trends', component: () => import('@/views/Trends.vue'), meta: { title: 'Lab Trends' } },
        { path: 'timeline', name: 'patient.timeline', component: () => import('@/views/Timeline.vue'), meta: { title: 'Timeline' } },
        { path: 'medications', name: 'patient.medications', component: () => import('@/views/Medications.vue'), meta: { title: 'Medications' } },
        { path: 'assistant', name: 'patient.assistant', component: () => import('@/views/Assistant.vue'), meta: { title: 'AI Assistant' } },
        { path: 'virtual-assistant', name: 'patient.virtualAssistant', component: () => import('@/views/VirtualAssistant.vue'), meta: { title: 'Virtual Health Assistant' } },
        { path: 'connections', name: 'patient.connections', component: () => import('@/views/Connections.vue'), meta: { title: 'Connected Apps' } },
        { path: 'profile', name: 'patient.profile', component: () => import('@/views/Profile.vue'), meta: { title: 'Profile' } },
        { path: 'settings', name: 'patient.settings', component: () => import('@/views/Settings.vue'), meta: { title: 'Settings' } },
      ],
    },

    // ─── CLINICIAN WORKSPACE ───────────────────────────────
    {
      path: '/clinician',
      component: () => import('@/layouts/WorkspaceLayout.vue'),
      meta: { requiresAuth: true, role: ['clinician'] },
      children: [
        { path: '', redirect: { name: 'clinician.dashboard' } },
        { path: 'dashboard', name: 'clinician.dashboard', component: () => import('@/views/dashboards/ClinicianDashboard.vue'), meta: { title: 'Dashboard' } },
        { path: 'patients', name: 'clinician.patients', component: () => import('@/views/ClinicianPortal.vue'), meta: { title: 'Patients' } },
        { path: 'appointments/book', name: 'clinician.appointments.book', component: () => import('@/views/AppointmentBooking.vue'), meta: { title: 'Book Appointment' } },
        { path: 'appointments', name: 'clinician.appointments', component: () => import('@/views/AppointmentManagement.vue'), meta: { title: 'Appointments' } },
        { path: 'messages', name: 'clinician.messages', component: () => import('@/views/Messages.vue'), meta: { title: 'Messages' } },
        { path: 'clinical-note-templates', name: 'clinician.clinicalNoteTemplates', component: () => import('@/views/ClinicalNoteTemplates.vue'), meta: { title: 'Clinical Note Templates' } },
        { path: 'problems-allergies', name: 'clinician.problemsAllergies', component: () => import('@/views/ProblemsAllergies.vue'), meta: { title: 'Problems & Allergies' } },
        { path: 'vital-signs', name: 'clinician.vitalSigns', component: () => import('@/views/VitalSigns.vue'), meta: { title: 'Vital Signs' } },
        { path: 'prescriptions', name: 'clinician.prescriptions', component: () => import('@/views/Prescriptions.vue'), meta: { title: 'Prescriptions' } },
        { path: 'pharmacy', name: 'clinician.pharmacy', component: () => import('@/views/Pharmacy.vue'), meta: { title: 'Pharmacy' } },
        { path: 'patient-context', name: 'clinician.patientContext', component: () => import('@/views/HealthRecord.vue'), meta: { title: 'Patient Context' } },
        { path: 'clinical-record', name: 'clinician.clinicalRecord', component: () => import('@/views/HealthRecord.vue'), meta: { title: 'Clinical Record' } },
        { path: 'lab-orders', name: 'clinician.labOrders', component: () => import('@/views/Reports.vue'), meta: { title: 'Lab Orders' } },
        { path: 'imaging', name: 'clinician.imaging', component: () => import('@/views/ImagingDashboard.vue'), meta: { title: 'Imaging & Radiology' } },
        { path: 'ai-tools', name: 'clinician.aiTools', component: () => import('@/views/ClinicalAITools.vue'), meta: { title: 'AI Clinical Tools' } },
        { path: 'reports', name: 'clinician.reports', component: () => import('@/views/Reports.vue'), meta: { title: 'Reports' } },
        { path: 'reports/upload', name: 'clinician.reports.upload', component: () => import('@/views/Upload.vue'), meta: { title: 'Upload Report' } },
        { path: 'reports/:id', name: 'clinician.reports.detail', component: () => import('@/views/ReportDetail.vue'), props: true, meta: { title: 'Report Details' } },
        { path: 'health-record', name: 'clinician.healthRecord', component: () => import('@/views/HealthRecord.vue'), meta: { title: 'Health Record' } },
        { path: 'trends', name: 'clinician.trends', component: () => import('@/views/Trends.vue'), meta: { title: 'Lab Trends' } },
        { path: 'timeline', name: 'clinician.timeline', component: () => import('@/views/Timeline.vue'), meta: { title: 'Timeline' } },
        { path: 'medications', name: 'clinician.medications', component: () => import('@/views/Medications.vue'), meta: { title: 'Medications' } },
        { path: 'assistant', name: 'clinician.assistant', component: () => import('@/views/Assistant.vue'), meta: { title: 'AI Assistant' } },
        { path: 'profile', name: 'clinician.profile', component: () => import('@/views/Profile.vue'), meta: { title: 'Profile' } },
        { path: 'settings', name: 'clinician.settings', component: () => import('@/views/Settings.vue'), meta: { title: 'Settings' } },
      ],
    },

    // ─── NURSING WORKSPACE ─────────────────────────────────
    {
      path: '/nursing',
      component: () => import('@/layouts/WorkspaceLayout.vue'),
      meta: { requiresAuth: true, role: ['nursing_staff'] },
      children: [
        { path: '', redirect: { name: 'nursing.dashboard' } },
        { path: 'dashboard', name: 'nursing.dashboard', component: () => import('@/views/dashboards/NursingDashboard.vue'), meta: { title: 'Dashboard' } },
        { path: 'patient-context', name: 'nursing.patientContext', component: () => import('@/views/HealthRecord.vue'), meta: { title: 'Patient Context' } },
        { path: 'vitals', name: 'nursing.vitals', component: () => import('@/views/HealthRecord.vue'), meta: { title: 'Vitals' } },
        { path: 'medications', name: 'nursing.medications', component: () => import('@/views/Medications.vue'), meta: { title: 'Medications' } },
        { path: 'notifications', name: 'nursing.notifications', component: () => import('@/views/Dashboard.vue'), meta: { title: 'Notifications' } },
        { path: 'bed-management', name: 'nursing.bedManagement', component: () => import('@/views/BedManagement.vue'), meta: { title: 'Bed Management' } },
        { path: 'emergency-department', name: 'nursing.emergencyDepartment', component: () => import('@/views/EmergencyDepartment.vue'), meta: { title: 'Emergency Department' } },
        { path: 'nursing-documentation', name: 'nursing.documentation', component: () => import('@/views/NursingDocumentation.vue'), meta: { title: 'Nursing Documentation' } },
        { path: 'health-record', name: 'nursing.healthRecord', component: () => import('@/views/HealthRecord.vue'), meta: { title: 'Health Record' } },
        { path: 'reports', name: 'nursing.reports', component: () => import('@/views/Reports.vue'), meta: { title: 'Reports' } },
        { path: 'reports/upload', name: 'nursing.reports.upload', component: () => import('@/views/Upload.vue'), meta: { title: 'Upload Report' } },
        { path: 'reports/:id', name: 'nursing.reports.detail', component: () => import('@/views/ReportDetail.vue'), props: true, meta: { title: 'Report Details' } },
        { path: 'trends', name: 'nursing.trends', component: () => import('@/views/Trends.vue'), meta: { title: 'Lab Trends' } },
        { path: 'timeline', name: 'nursing.timeline', component: () => import('@/views/Timeline.vue'), meta: { title: 'Timeline' } },
        { path: 'assistant', name: 'nursing.assistant', component: () => import('@/views/Assistant.vue'), meta: { title: 'AI Assistant' } },
        { path: 'profile', name: 'nursing.profile', component: () => import('@/views/Profile.vue'), meta: { title: 'Profile' } },
        { path: 'settings', name: 'nursing.settings', component: () => import('@/views/Settings.vue'), meta: { title: 'Settings' } },
      ],
    },

    // ─── ADMIN WORKSPACE ───────────────────────────────────
    {
      path: '/admin',
      component: () => import('@/layouts/WorkspaceLayout.vue'),
      meta: { requiresAuth: true, role: ['admin', 'super_admin'] },
      children: [
        { path: '', redirect: { name: 'admin.dashboard' } },
        { path: 'dashboard', name: 'admin.dashboard', component: () => import('@/views/dashboards/AdminDashboard.vue'), meta: { title: 'Dashboard' } },
        { path: 'organizations', name: 'admin.organizations', component: () => import('@/views/admin/AdminOrganizations.vue'), meta: { title: 'Organizations' } },
        { path: 'departments', name: 'admin.departments', component: () => import('@/views/admin/AdminDepartments.vue'), meta: { title: 'Departments' } },
        { path: 'staff', name: 'admin.staff', component: () => import('@/views/admin/AdminStaff.vue'), meta: { title: 'Staff Management' } },
        { path: 'inventory', name: 'admin.inventory', component: () => import('@/views/admin/AdminInventory.vue'), meta: { title: 'Inventory' } },
        { path: 'billing', name: 'admin.billing', component: () => import('@/views/admin/AdminBilling.vue'), meta: { title: 'Billing' } },
        { path: 'profile', name: 'admin.profile', component: () => import('@/views/Profile.vue'), meta: { title: 'Profile' } },
        { path: 'settings', name: 'admin.settings', component: () => import('@/views/Settings.vue'), meta: { title: 'Settings' } },
      ],
    },

    // ─── SUPER ADMIN WORKSPACE ─────────────────────────────
    {
      path: '/superadmin',
      component: () => import('@/layouts/WorkspaceLayout.vue'),
      meta: { requiresAuth: true, role: ['super_admin'] },
      children: [
        { path: '', redirect: { name: 'superadmin.dashboard' } },
        { path: 'dashboard', name: 'superadmin.dashboard', component: () => import('@/views/dashboards/SuperAdminDashboard.vue'), meta: { title: 'Dashboard' } },
        { path: 'overview', name: 'superadmin.overview', component: () => import('@/views/superadmin/SuperAdminOverview.vue'), meta: { title: 'Platform Overview' } },
        { path: 'organizations', name: 'superadmin.organizations', component: () => import('@/views/superadmin/SuperAdminOrganizations.vue'), meta: { title: 'Organizations' } },
        { path: 'users', name: 'superadmin.users', component: () => import('@/views/superadmin/SuperAdminUsers.vue'), meta: { title: 'Users' } },
        { path: 'ai-usage', name: 'superadmin.aiUsage', component: () => import('@/views/superadmin/SuperAdminAIUsage.vue'), meta: { title: 'AI Usage' } },
        { path: 'system-health', name: 'superadmin.health', component: () => import('@/views/superadmin/SuperAdminSystemHealth.vue'), meta: { title: 'System Health' } },
        { path: 'profile', name: 'superadmin.profile', component: () => import('@/views/Profile.vue'), meta: { title: 'Profile' } },
        { path: 'settings', name: 'superadmin.settings', component: () => import('@/views/Settings.vue'), meta: { title: 'Settings' } },
      ],
    },

    // Legacy dashboard redirect (handles old URLs)
    {
      path: '/dashboard',
      name: 'dashboard.legacy',
      component: { template: '<div />' },
      beforeEnter: (_to, _from, next) => {
        const auth = useAuthStore()
        if (auth.user) {
          const roleRoutes: Record<string, string> = {
            patient: '/patient/dashboard',
            clinician: '/clinician/dashboard',
            nursing_staff: '/nursing/dashboard',
            admin: '/admin/dashboard',
            super_admin: '/superadmin/dashboard',
          }
          next(roleRoutes[auth.user.role] || '/login')
        } else {
          next('/login')
        }
      },
    },

    { path: '/:pathMatch(.*)*', redirect: '/dashboard' },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  // Fetch user if token exists but user not loaded
  if (auth.isAuthenticated && !auth.user) {
    try {
      await auth.fetchUser()
    } catch {
      auth.clearAuth()
      return { name: 'login', query: { redirect: to.fullPath } }
    }
  }

  // Require auth
  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  // Guest only
  if (to.meta.guestOnly && auth.isAuthenticated) {
    const roleRoutes: Record<string, string> = {
      patient: '/patient/dashboard',
      clinician: '/clinician/dashboard',
      nursing_staff: '/nursing/dashboard',
      admin: '/admin/dashboard',
      super_admin: '/superadmin/dashboard',
    }
    return roleRoutes[auth.user?.role || ''] || '/dashboard'
  }

  // Role-based access control
  if (auth.user) {
    // Check current route role
    if (to.meta.role) {
      const allowedRoles = to.meta.role as string[]
      if (!allowedRoles.includes(auth.user.role)) {
        return { name: 'error.403' }
      }
    }
    // Check parent route role (for nested routes)
    const matchedRoute = to.matched.find(route => route.meta.role)
    if (matchedRoute && matchedRoute.meta.role) {
      const allowedRoles = matchedRoute.meta.role as string[]
      if (!allowedRoles.includes(auth.user.role)) {
        return { name: 'error.403' }
      }
    }
  }
})

export default router
