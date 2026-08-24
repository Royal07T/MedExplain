<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuth } from '@/composables/useAuth'
import { useNotificationsStore } from '@/stores/notifications'
import { useSearch } from '@/composables/useSearch'
import type { AppNotification } from '@/types'
import type { SearchResult } from '@/composables/useSearch'
import NavbarMenu from '@/components/NavbarMenu.vue'

const route = useRoute()
const router = useRouter()
const { user, logout, isAuthenticated } = useAuth()

const notifications = useNotificationsStore()
const notificationsOpen = ref(false)
const menuOpen = ref(false)
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

const {
  query: searchQuery,
  results: searchResults,
  loading: searchLoading,
  open: searchOpen,
  selectedIndex: searchSelectedIndex,
  hasResults: searchHasResults,
  handleInput: handleSearchInput,
  handleFocus: handleSearchFocus,
  handleBlur: handleSearchBlur,
  handleKeydown: handleSearchKeydown,
  selectResult: selectSearchResult,
  close: closeSearch,
} = useSearch()

function closeMenu() {
  menuOpen.value = false
}

function closeNotifications() {
  notificationsOpen.value = false
}

async function handleLogout() {
  await logout()
  router.push({ name: 'login' })
}

function toggleMenu(e: Event) {
  e.preventDefault()
  e.stopPropagation()
  menuOpen.value = !menuOpen.value
}

function toggleNotifications() {
  notificationsOpen.value = !notificationsOpen.value
  if (notificationsOpen.value) {
    notifications.fetchNotifications()
  }
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

function handleKeydown(e: KeyboardEvent) {
  if (e.key === 'Escape') {
    closeMenu()
    closeNotifications()
    closeSearch()
  }
}

function handleSearchInputEvent(e: Event) {
  const target = e.target as HTMLInputElement
  handleSearchInput(target.value)
}

function handleSearchResultClick(result: SearchResult) {
  selectSearchResult(result)
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

  document.addEventListener('keydown', handleKeydown)
})

onUnmounted(() => {
  if (pollTimer) clearInterval(pollTimer)
  document.removeEventListener('keydown', handleKeydown)
})

watch(() => route.fullPath, () => {
  closeMenu()
  closeNotifications()
  closeSearch()
})
</script>

<template>
  <div class="min-h-screen bg-slate-50">
    <div class="flex min-h-screen flex-col">
      <!-- Header -->
      <header class="sticky top-0 z-20 flex h-16 items-center justify-between gap-3 border-b border-slate-200 bg-white px-4 sm:px-6">
        <div class="flex min-w-0 items-center gap-3">
          <h1 class="truncate text-lg font-semibold text-slate-900 sm:text-xl">{{ pageTitle }}</h1>
        </div>

        <div class="flex shrink-0 items-center gap-2 sm:gap-3">
          <div class="relative hidden md:block">
            <input
              type="text"
              placeholder="Search..."
              :value="searchQuery"
              @input="handleSearchInputEvent"
              @focus="handleSearchFocus"
              @blur="handleSearchBlur"
              @keydown="handleSearchKeydown"
              class="w-56 rounded-lg border border-slate-200 py-2 pl-10 pr-3 text-sm focus:border-teal-500 focus:outline-none lg:w-64"
              aria-autocomplete="list"
              aria-controls="search-results"
              :aria-expanded="searchOpen && searchHasResults ? 'true' : 'false'"
            />
            <svg xmlns="http://www.w3.org/2000/svg" class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>

            <teleport to="body">
              <div
                v-if="searchOpen && searchHasResults"
                class="fixed inset-0 z-40"
                aria-hidden="true"
                @click="closeSearch"
              ></div>
              <div
                v-if="searchOpen && searchHasResults"
                id="search-results"
                class="fixed top-16 z-50 w-64 max-w-[calc(100vw-2rem)] md:left-auto md:right-4 lg:right-20"
              >
                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl">
                  <ul class="max-h-60 divide-y divide-slate-100 overflow-y-auto">
                    <li
                      v-if="searchLoading"
                      class="px-4 py-8 text-center text-sm text-slate-400"
                    >
                      Searching…
                    </li>
                    <li v-for="(result, index) in searchResults" :key="result.id">
                      <button
                        type="button"
                        class="flex w-full items-center gap-3 px-4 py-3 text-left transition-colors hover:bg-slate-50"
                        :class="{ 'bg-slate-50': index === searchSelectedIndex }"
                        @click="handleSearchResultClick(result)"
                        @mouseenter="searchSelectedIndex = index"
                        role="option"
                        :aria-selected="index === searchSelectedIndex"
                      >
                        <svg
                          xmlns="http://www.w3.org/2000/svg"
                          class="h-5 w-5 shrink-0 text-slate-400"
                          fill="none"
                          viewBox="0 0 24 24"
                          stroke="currentColor"
                          stroke-width="2"
                          aria-hidden="true"
                        >
                          <path stroke-linecap="round" stroke-linejoin="round" :d="result.icon" />
                        </svg>
                        <div class="min-w-0 flex-1 text-sm">
                          <p class="truncate font-medium text-slate-900">{{ result.title }}</p>
                          <p v-if="result.subtitle" class="truncate text-xs text-slate-500">{{ result.subtitle }}</p>
                          <p class="truncate text-[11px] text-slate-400 capitalize">{{ result.type }}</p>
                        </div>
                      </button>
                    </li>
                  </ul>
                </div>
              </div>
            </teleport>
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

          <!-- User Menu Trigger -->
          <div class="relative">
            <button
              type="button"
              class="flex items-center gap-2 rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600"
              :aria-expanded="menuOpen"
              :aria-haspopup="true"
              aria-label="User menu"
              @click="toggleMenu"
            >
              <div class="h-8 w-8 shrink-0 rounded-full bg-teal-100 flex items-center justify-center">
                <img
                  v-if="user?.profile?.avatar_url"
                  :src="user.profile.avatar_url"
                  :alt="`${user?.name ?? ''} profile picture`"
                  class="h-full w-full rounded-full object-cover"
                />
                <svg
                  v-else
                  xmlns="http://www.w3.org/2000/svg"
                  class="h-5 w-5 text-teal-600"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                  stroke-width="2"
                  aria-hidden="true"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"
                  />
                </svg>
              </div>
              <span class="hidden sm:block truncate max-w-[160px] text-sm font-medium text-slate-700">{{ user?.name || 'User' }}</span>
              <svg class="h-4 w-4 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
              </svg>
            </button>

            <teleport to="body">
              <div
                v-if="menuOpen"
                class="fixed inset-0 z-40"
                aria-hidden="true"
                @click="menuOpen = false"
              ></div>
              <NavbarMenu v-if="menuOpen" @close="menuOpen = false" />
            </teleport>
          </div>
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