<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuth } from '@/composables/useAuth'
import { usePermissions } from '@/composables/usePermissions'
import { useNotificationsStore } from '@/stores/notifications'
import { workspaceNavConfigs } from '@/config/navigation'
import PatientContextSelector from '@/components/PatientContextSelector.vue'
import type { AppNotification } from '@/types'

const route = useRoute()
const router = useRouter()
const { user, logout } = useAuth()
const { role, isHealthcareStaff } = usePermissions()
const notifications = useNotificationsStore()

const sidebarOpen = ref(true)
const notificationsOpen = ref(false)
const userMenuOpen = ref(false)
const isLoading = ref(true)
let pollTimer: ReturnType<typeof setInterval> | undefined

const navConfig = computed(() => {
  if (!role.value) return null
  return workspaceNavConfigs[role.value] || null
})

const workspaceBase = computed(() => {
  return route.matched[0]?.path || ''
})

const commonNavItems = computed(() => {
  const rolePrefix = role.value === 'patient' ? 'patient' : role.value === 'clinician' ? 'clinician' : role.value === 'nursing_staff' ? 'nursing' : role.value === 'admin' ? 'admin' : role.value === 'super_admin' ? 'superadmin' : ''
  return [
    { label: 'Profile', to: { name: `${rolePrefix}.profile` }, icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z' },
    { label: 'Settings', to: { name: `${rolePrefix}.settings` }, icon: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z' },
  ]
})

const pageTitle = computed(() => {
  return (route.meta.title as string) || navConfig.value?.label || 'Dashboard'
})

function isActive(routeName: string | undefined): boolean {
  return routeName !== undefined && route.name === routeName
}

function toggleNotifications() {
  notificationsOpen.value = !notificationsOpen.value
  if (notificationsOpen.value) {
    notifications.fetchNotifications()
  }
}

function closeNotifications() {
  notificationsOpen.value = false
}

function toggleUserMenu() {
  userMenuOpen.value = !userMenuOpen.value
}

function closeUserMenu() {
  userMenuOpen.value = false
}

async function handleMarkAllRead() {
  await notifications.markAllRead()
}

async function handleNotificationClick(notification: AppNotification) {
  if (!notification.read_at) {
    await notifications.markRead(notification.id)
  }
}

function formatNotificationTime(iso: string): string {
  const date = new Date(iso)
  return date.toLocaleString(undefined, {
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  })
}

async function handleLogout() {
  await logout()
  router.push({ name: 'login' })
}

onMounted(async () => {
  try {
    await notifications.refreshUnreadCount()
  } catch {
    // Badge stays empty on failure
  }
  pollTimer = setInterval(() => {
    notifications.refreshUnreadCount().catch(() => {})
  }, 30000)
})

onUnmounted(() => {
  if (pollTimer) clearInterval(pollTimer)
})

watch(() => route.fullPath, () => {
  closeNotifications()
  closeUserMenu()
  sidebarOpen.value = false
})

watch(() => role.value, (newRole) => {
  if (newRole !== undefined) {
    isLoading.value = false
  }
}, { immediate: true })
</script>

<template>
  <div v-if="isLoading" class="min-h-screen flex items-center justify-center bg-slate-50">
    <div class="text-center">
      <div class="inline-block h-8 w-8 animate-spin rounded-full border-4 border-solid border-teal-600 border-r-transparent"></div>
      <p class="mt-4 text-slate-600">Loading...</p>
    </div>
  </div>
  <div v-else class="min-h-screen bg-slate-50">
    <div class="flex min-h-screen">
      <!-- Sidebar -->
      <aside
        class="fixed inset-y-0 left-0 z-30 flex w-64 flex-col border-r border-slate-200 bg-white transition-transform duration-200"
        :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
      >
        <!-- Logo -->
        <div class="flex h-16 items-center gap-2 border-b border-slate-200 px-4">
          <img src="/src/assets/logo.png" alt="MedExplain" class="h-8 w-8 object-contain" />
          <span class="text-lg font-bold text-slate-900">MedExplain</span>
        </div>

        <!-- Patient Context Selector (clinician/nursing only) -->
        <div v-if="isHealthcareStaff" class="border-b border-slate-200 p-3">
          <PatientContextSelector />
        </div>

        <!-- Primary Nav -->
        <nav class="flex-1 overflow-y-auto px-3 py-4">
          <ul class="space-y-1">
            <li v-for="item in navConfig?.items" :key="item.routeName">
              <router-link
                :to="{ name: item.routeName }"
                class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors"
                :class="isActive(item.routeName) ? 'bg-teal-50 text-teal-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'"
              >
                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                  <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
                </svg>
                {{ item.label }}
              </router-link>
            </li>
          </ul>
        </nav>

        <!-- Common Nav (bottom) -->
        <div class="border-t border-slate-200 px-3 py-4">
          <ul class="space-y-1">
            <li v-for="item in commonNavItems" :key="item.label">
              <router-link
                :to="item.to"
                class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors"
                :class="route.name === item.to.name ? 'bg-slate-100 text-slate-900' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'"
              >
                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                  <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
                </svg>
                {{ item.label }}
              </router-link>
            </li>
          </ul>
        </div>
      </aside>

      <!-- Mobile overlay -->
      <div
        v-if="sidebarOpen"
        class="fixed inset-0 z-20 bg-black/50 lg:hidden"
        @click="sidebarOpen = false"
      />

      <!-- Main content -->
      <div class="flex-1 lg:ml-64">
        <!-- Top bar -->
        <header class="sticky top-0 z-10 flex h-16 items-center justify-between gap-4 border-b border-slate-200 bg-white px-4 sm:px-6">
          <div class="flex items-center gap-3">
            <button @click="sidebarOpen = !sidebarOpen" class="text-slate-600 lg:hidden">
              <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
              </svg>
            </button>
            <h1 class="truncate text-lg font-semibold text-slate-900">{{ pageTitle }}</h1>
          </div>

          <!-- Notifications -->
          <div class="relative">
            <button
              type="button"
              class="relative rounded-lg p-2 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600"
              :aria-label="notificationsOpen ? 'Close notifications' : 'Notifications'"
              @click="toggleNotifications"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
              </svg>
              <span
                v-if="notifications.unreadCount > 0"
                class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold leading-none text-white"
              >
                {{ notifications.unreadCount > 9 ? '9+' : notifications.unreadCount }}
              </span>
            </button>

            <!-- Notifications dropdown -->
            <div
              v-if="notificationsOpen"
              class="fixed inset-0 z-40"
              @click="closeNotifications"
            />
            <div
              v-if="notificationsOpen"
              class="absolute right-0 top-full z-50 mt-2 w-80 max-w-[calc(100vw-2rem)]"
            >
              <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                  <h2 class="text-sm font-semibold text-slate-900">Notifications</h2>
                  <button
                    type="button"
                    class="text-xs font-medium text-teal-600 hover:text-teal-700 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="notifications.unreadCount === 0 || notifications.loading"
                    @click="handleMarkAllRead"
                  >
                    Mark all read
                  </button>
                </div>
                <ul class="max-h-96 divide-y divide-slate-100 overflow-y-auto">
                  <li v-if="notifications.loading && notifications.items.length === 0" class="px-4 py-8 text-center text-sm text-slate-400">
                    Loading...
                  </li>
                  <li v-else-if="notifications.items.length === 0" class="px-4 py-8 text-center text-sm text-slate-400">
                    You're all caught up.
                  </li>
                  <li v-for="n in notifications.items" :key="n.id">
                    <button
                      type="button"
                      class="flex w-full items-start gap-3 px-4 py-3 text-left transition-colors hover:bg-slate-50"
                      @click="handleNotificationClick(n)"
                    >
                      <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full" :class="n.read_at ? 'bg-transparent' : 'bg-teal-500'" />
                      <span class="min-w-0 flex-1">
                        <span class="block text-sm font-medium" :class="n.read_at ? 'text-slate-500' : 'text-slate-900'">{{ n.title }}</span>
                        <span v-if="n.body" class="mt-0.5 block text-xs text-slate-500">{{ n.body }}</span>
                        <span class="mt-1 block text-[11px] text-slate-400">{{ formatNotificationTime(n.created_at) }}</span>
                      </span>
                    </button>
                  </li>
                </ul>
              </div>
            </div>
          </div>

          <!-- User menu -->
          <div class="relative ml-1">
            <button
              type="button"
              class="flex items-center gap-2 rounded-lg p-1 transition-colors hover:bg-slate-100"
              @click="toggleUserMenu"
            >
              <div class="flex h-8 w-8 items-center justify-center rounded-full bg-teal-100">
                <span class="text-sm font-semibold text-teal-700">{{ user?.name?.charAt(0) }}</span>
              </div>
              <svg
                xmlns="http://www.w3.org/2000/svg"
                class="h-4 w-4 text-slate-400"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="2"
              >
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
              </svg>
            </button>

            <!-- User dropdown -->
            <div
              v-if="userMenuOpen"
              class="fixed inset-0 z-40"
              @click="closeUserMenu"
            />
            <div
              v-if="userMenuOpen"
              class="absolute right-0 top-full z-50 mt-2 w-56"
            >
              <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl">
                <div class="border-b border-slate-200 px-4 py-3">
                  <p class="text-sm font-medium text-slate-900">{{ user?.name }}</p>
                  <p class="text-xs text-slate-500">{{ user?.email }}</p>
                </div>
                <div class="p-1">
                  <button
                    type="button"
                    class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-red-600 hover:bg-red-50 transition-colors"
                    @click="handleLogout"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                    </svg>
                    Log out
                  </button>
                </div>
              </div>
            </div>
          </div>
        </header>

        <!-- Page content -->
        <main class="p-4 sm:p-6">
          <div class="mx-auto w-full max-w-7xl">
            <router-view />
          </div>
        </main>
      </div>
    </div>
  </div>
</template>
