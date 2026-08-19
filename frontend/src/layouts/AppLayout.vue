<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuth } from '@/composables/useAuth'
import { useNotificationsStore } from '@/stores/notifications'
import type { AppNotification } from '@/types'
import Sidebar from '@/components/Sidebar.vue'

const route = useRoute()
const router = useRouter()
const { logout } = useAuth()

const notifications = useNotificationsStore()
const notificationsOpen = ref(false)
let pollTimer: ReturnType<typeof setInterval> | undefined

const pageTitle = computed(() => {
  const titles: Record<string, string> = {
    dashboard: 'Dashboard',
    reports: 'Reports',
    'reports.upload': 'Upload Report',
    'reports.detail': 'Report Details',
    trends: 'Lab Trends',
    timeline: 'Health Timeline',
    healthRecord: 'My Health Record',
    medications: 'Medications',
    assistant: 'AI Assistant',
    clinicianPortal: 'Clinician Portal',
    connections: 'Connected Apps',
    profile: 'Profile',
    settings: 'Settings',
  }
  return titles[route.name as string] || 'Dashboard'
})

const sidebarOpen = ref(false)

function toggleSidebar() {
  sidebarOpen.value = !sidebarOpen.value
}

function closeSidebar() {
  sidebarOpen.value = false
}

watch(
  () => route.fullPath,
  () => closeSidebar(),
)

watch(sidebarOpen, (open) => {
  document.body.style.overflow = open ? 'hidden' : ''
})

async function handleLogout() {
  await logout()
  router.push({ name: 'login' })
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

onMounted(async () => {
  try {
    await notifications.refreshUnreadCount()
  } catch {
    // The badge just stays empty when the refresh fails.
  }
  pollTimer = setInterval(() => {
    notifications.refreshUnreadCount().catch(() => {})
  }, 30000)
})

onUnmounted(() => {
  if (pollTimer) clearInterval(pollTimer)
})
</script>

<template>
  <div class="min-h-screen bg-slate-50">
    <!-- Desktop sidebar -->
    <aside class="fixed inset-y-0 left-0 z-30 hidden w-64 border-r border-slate-200 bg-white lg:block">
      <Sidebar />
    </aside>

    <!-- Mobile drawer -->
    <teleport to="body">
      <transition
        enter-active-class="transition-opacity duration-200 ease-out"
        leave-active-class="transition-opacity duration-150 ease-in"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div
          v-if="sidebarOpen"
          class="fixed inset-0 z-40 bg-slate-900/50 lg:hidden"
          aria-hidden="true"
          @click="closeSidebar"
        ></div>
      </transition>

      <transition
        enter-active-class="transition-transform duration-200 ease-out"
        leave-active-class="transition-transform duration-150 ease-in"
        enter-from-class="-translate-x-full"
        enter-to-class="translate-x-0"
        leave-from-class="translate-x-0"
        leave-to-class="-translate-x-full"
      >
        <aside
          v-if="sidebarOpen"
          class="fixed inset-y-0 left-0 z-50 w-72 max-w-[85vw] border-r border-slate-200 bg-white shadow-xl lg:hidden"
        >
          <div class="flex h-full flex-col">
            <div class="flex h-14 items-center justify-end border-b border-slate-200 px-4">
              <button
                type="button"
                class="rounded-lg p-2 text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-700"
                aria-label="Close navigation menu"
                @click="closeSidebar"
              >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
            <div class="flex-1 overflow-y-auto">
              <Sidebar />
            </div>
          </div>
        </aside>
      </transition>
    </teleport>

    <div class="flex min-h-screen flex-col lg:pl-64">
      <!-- Header -->
      <header class="sticky top-0 z-20 flex h-16 items-center justify-between gap-3 border-b border-slate-200 bg-white px-4 sm:px-6">
        <div class="flex min-w-0 items-center gap-3">
          <button
            type="button"
            class="shrink-0 rounded-lg p-2 text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-700 lg:hidden"
            aria-label="Open navigation menu"
            @click="toggleSidebar"
          >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
            </svg>
          </button>
          <h1 class="truncate text-lg font-semibold text-slate-900 sm:text-xl">{{ pageTitle }}</h1>
        </div>

        <div class="flex shrink-0 items-center gap-2 sm:gap-3">
          <div class="relative hidden md:block">
            <input
              type="text"
              placeholder="Search reports..."
              class="w-56 rounded-lg border border-slate-200 py-2 pl-10 pr-3 text-sm focus:border-teal-500 focus:outline-none lg:w-64"
            />
            <svg xmlns="http://www.w3.org/2000/svg" class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>
          <div class="relative">
            <button
              type="button"
              class="relative rounded-lg p-2 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600"
              :aria-label="notificationsOpen ? 'Close notifications' : 'Notifications'"
              :aria-expanded="notificationsOpen"
              @click="toggleNotifications"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
              </svg>
              <span
                v-if="notifications.unreadCount > 0"
                class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold leading-none text-white"
                aria-label="Unread notifications"
              >
                {{ notifications.unreadCount > 9 ? '9+' : notifications.unreadCount }}
              </span>
            </button>

            <teleport to="body">
              <div
                v-if="notificationsOpen"
                class="fixed inset-0 z-40"
                aria-hidden="true"
                @click="closeNotifications"
              ></div>
              <div
                v-if="notificationsOpen"
                class="fixed right-4 top-16 z-50 w-80 max-w-[calc(100vw-2rem)] sm:right-6 sm:w-96"
              >
                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl">
                  <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                    <h2 class="text-sm font-semibold text-slate-900">Notifications</h2>
                    <div class="flex items-center gap-2">
                      <span
                        v-if="notifications.unreadCount > 0"
                        class="rounded-full bg-red-50 px-2 py-0.5 text-xs font-semibold text-red-600"
                      >
                        {{ notifications.unreadCount }} unread
                      </span>
                      <button
                        type="button"
                        class="text-xs font-medium text-teal-600 transition hover:text-teal-700 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="notifications.unreadCount === 0 || notifications.loading"
                        @click="handleMarkAllRead"
                      >
                        Mark all read
                      </button>
                    </div>
                  </div>
                  <ul class="max-h-96 divide-y divide-slate-100 overflow-y-auto">
                    <li
                      v-if="notifications.loading && notifications.items.length === 0"
                      class="px-4 py-8 text-center text-sm text-slate-400"
                    >
                      Loading…
                    </li>
                    <li
                      v-else-if="notifications.items.length === 0"
                      class="px-4 py-8 text-center text-sm text-slate-400"
                    >
                      You're all caught up.
                    </li>
                    <li v-for="n in notifications.items" :key="n.id">
                      <button
                        type="button"
                        class="flex w-full items-start gap-3 px-4 py-3 text-left transition-colors hover:bg-slate-50"
                        @click="handleNotificationClick(n)"
                      >
                        <span
                          class="mt-1.5 h-2 w-2 shrink-0 rounded-full"
                          :class="n.read_at ? 'bg-transparent' : 'bg-teal-500'"
                          aria-hidden="true"
                        ></span>
                        <span class="min-w-0 flex-1">
                          <span
                            class="block text-sm font-medium text-slate-900"
                            :class="n.read_at ? 'font-normal text-slate-500' : ''"
                          >
                            {{ n.title }}
                          </span>
                          <span v-if="n.body" class="mt-0.5 block text-xs text-slate-500">
                            {{ n.body }}
                          </span>
                          <span class="mt-1 block text-[11px] text-slate-400">
                            {{ formatNotificationTime(n.created_at) }}
                          </span>
                        </span>
                      </button>
                    </li>
                  </ul>
                </div>
              </div>
            </teleport>
          </div>
          <button
            type="button"
            class="rounded-lg p-2 text-slate-400 transition-colors hover:bg-slate-100 hover:text-red-600"
            aria-label="Log out"
            title="Logout"
            @click="handleLogout"
          >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M5.636 5.636a9 9 0 1012.728 0M12 3v9" />
            </svg>
          </button>
        </div>
      </header>

      <!-- Main content -->
      <main class="flex-1 p-4 sm:p-6">
        <div class="mx-auto w-full max-w-7xl">
          <router-view />
        </div>
      </main>
    </div>
  </div>
</template>