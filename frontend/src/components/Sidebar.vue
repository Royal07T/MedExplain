<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useAuth } from '@/composables/useAuth'

const route = useRoute()
const { user } = useAuth()

interface NavItem {
  name: string
  route: string
  icon: string
  match: string[]
}

interface NavGroup {
  label: string
  items: NavItem[]
}

const icons = {
  home: 'M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25',
  document:
    'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z',
  chart:
    'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z',
  clock: 'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z',
  heart: 'M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z',
  plus: 'M12 4.5v15m7.5-7.5h-15',
  chat: 'M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z',
  users:
    'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z',
  user: 'M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z',
  cog: 'M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281zM15 12a3 3 0 11-6 0 3 3 0 016 0z',
  link: 'M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244',
}

const isClinician = computed(() => user.value?.role === 'clinician')

const navGroups = computed<NavGroup[]>(() => {
  const groups: NavGroup[] = [
    {
      label: 'Overview',
      items: [{ name: 'Dashboard', route: 'dashboard', icon: icons.home, match: ['dashboard'] }],
    },
    {
      label: 'Reports & Labs',
      items: [
        {
          name: 'Reports',
          route: 'reports',
          icon: icons.document,
          match: ['reports', 'reports.upload', 'reports.detail'],
        },
        { name: 'Trends', route: 'trends', icon: icons.chart, match: ['trends'] },
        { name: 'Timeline', route: 'timeline', icon: icons.clock, match: ['timeline'] },
      ],
    },
    {
      label: 'Health',
      items: [
        { name: 'Health record', route: 'healthRecord', icon: icons.heart, match: ['healthRecord'] },
        { name: 'Medications', route: 'medications', icon: icons.plus, match: ['medications'] },
      ],
    },
    {
      label: 'AI Assistant',
      items: [{ name: 'Assistant', route: 'assistant', icon: icons.chat, match: ['assistant'] }],
    },
  ]

  if (isClinician.value) {
    groups.push({
      label: 'Care team',
      items: [
        {
          name: 'Clinician portal',
          route: 'clinicianPortal',
          icon: icons.users,
          match: ['clinicianPortal'],
        },
      ],
    })
  }

  groups.push({
    label: 'Account',
    items: [
      { name: 'Profile', route: 'profile', icon: icons.user, match: ['profile'] },
      { name: 'Settings', route: 'settings', icon: icons.cog, match: ['settings'] },
      { name: 'Connected apps', route: 'connections', icon: icons.link, match: ['connections'] },
    ],
  })

  return groups
})

function isActive(item: NavItem): boolean {
  return item.match.includes(String(route.name))
}

const initials = computed(() => {
  const name = user.value?.name?.trim() ?? ''
  if (!name) return '?'
  return name
    .split(/\s+/)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? '')
    .join('')
})

const plan = computed(() => user.value?.plan ?? 'free')

const planLabel = computed(() => (plan.value === 'pro' ? 'Pro plan' : 'Free plan'))
</script>

<template>
  <div class="flex h-full flex-col">
    <!-- Logo -->
    <div class="flex h-16 shrink-0 items-center gap-2 border-b border-slate-200 px-5">
      <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-teal-600">
        <svg
          xmlns="http://www.w3.org/2000/svg"
          class="h-5 w-5 text-white"
          fill="none"
          viewBox="0 0 24 24"
          stroke="currentColor"
          stroke-width="2"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
          />
        </svg>
      </div>
      <span class="text-lg font-bold text-slate-900">MedExplain</span>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-4">
      <div v-for="group in navGroups" :key="group.label">
        <p class="px-3 pb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">
          {{ group.label }}
        </p>
        <div class="space-y-0.5">
          <router-link
            v-for="item in group.items"
            :key="item.route"
            :to="{ name: item.route }"
            class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors"
            :class="
              isActive(item)
                ? 'bg-teal-50 text-teal-700'
                : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'
            "
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              class="h-5 w-5 shrink-0"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
              stroke-width="2"
            >
              <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
            </svg>
            {{ item.name }}
          </router-link>
        </div>
      </div>
    </nav>

    <!-- User section -->
    <div class="border-t border-slate-200 p-3">
      <div class="flex items-center gap-3 rounded-lg p-2">
        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-teal-100 font-semibold text-teal-700">
          <img
            v-if="user?.profile?.avatar_url"
            :src="user.profile.avatar_url"
            :alt="`${user?.name ?? ''} profile picture`"
            class="h-full w-full rounded-full object-cover"
          />
          <span v-else>{{ initials }}</span>
        </div>
        <div class="min-w-0 flex-1">
          <p class="truncate text-sm font-medium text-slate-900">{{ user?.name || 'User' }}</p>
          <p class="truncate text-xs text-slate-500">{{ user?.email || '' }}</p>
          <span
            class="mt-1.5 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold"
            :class="
              plan === 'pro'
                ? 'bg-amber-100 text-amber-800'
                : 'bg-slate-100 text-slate-600'
            "
          >
            <svg
              v-if="plan === 'pro'"
              xmlns="http://www.w3.org/2000/svg"
              class="h-3 w-3"
              fill="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                fill-rule="evenodd"
                clip-rule="evenodd"
                d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.007 5.404.433c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.433 2.082-5.006z"
              />
            </svg>
            {{ planLabel }}
          </span>
        </div>
      </div>
    </div>
  </div>
</template>