import { createRouter, createWebHistory } from 'vue-router'

import { useAuthStore } from '@/stores/auth'

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
      path: '/',
      component: () => import('@/layouts/AppLayout.vue'),
      meta: { requiresAuth: true },
      children: [
        {
          path: 'dashboard',
          name: 'dashboard',
          component: () => import('@/views/Dashboard.vue'),
        },
        {
          path: 'reports',
          name: 'reports',
          component: () => import('@/views/Reports.vue'),
        },
        {
          path: 'trends',
          name: 'trends',
          component: () => import('@/views/Trends.vue'),
        },
        {
          path: 'timeline',
          name: 'timeline',
          component: () => import('@/views/Timeline.vue'),
        },
        {
          path: 'health-record',
          name: 'healthRecord',
          component: () => import('@/views/HealthRecord.vue'),
        },
        {
          path: 'medications',
          name: 'medications',
          component: () => import('@/views/Medications.vue'),
        },
        {
          path: 'assistant',
          name: 'assistant',
          component: () => import('@/views/Assistant.vue'),
        },
        {
          path: 'clinician-portal',
          name: 'clinicianPortal',
          component: () => import('@/views/ClinicianPortal.vue'),
        },
        {
          path: 'reports/upload',
          name: 'reports.upload',
          component: () => import('@/views/Upload.vue'),
        },
        {
          path: 'reports/:id',
          name: 'reports.detail',
          component: () => import('@/views/ReportDetail.vue'),
          props: true,
        },
        {
          path: 'profile',
          name: 'profile',
          component: () => import('@/views/Profile.vue'),
        },
        {
          path: 'settings',
          name: 'settings',
          component: () => import('@/views/Settings.vue'),
        },
        {
          path: 'connections',
          name: 'connections',
          component: () => import('@/views/Connections.vue'),
        },
      ],
    },
    { path: '/:pathMatch(.*)*', redirect: '/dashboard' },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (auth.isAuthenticated && !auth.user) {
    try {
      await auth.fetchUser()
    } catch {
      auth.clearAuth()
      return { name: 'login', query: { redirect: to.fullPath } }
    }
  }

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  if (to.meta.guestOnly && auth.isAuthenticated) {
    return { name: 'dashboard' }
  }
})

export default router