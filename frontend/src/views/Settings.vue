<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'

import Disclaimer from '@/components/Disclaimer.vue'
import ToggleSwitch from '@/components/ToggleSwitch.vue'
import { useAuth } from '@/composables/useAuth'

const SETTINGS_KEY = 'medexplain:settings'

interface SavedSettings {
  emailSummary: boolean
  reportReady: boolean
  productUpdates: boolean
  language: string
}

const { store: auth, isEmailVerified } = useAuth()
const router = useRouter()

const emailSummary = ref(true)
const reportReady = ref(true)
const productUpdates = ref(false)
const language = ref('en')

const saved = ref(false)

onMounted(() => {
  if (!auth.user) {
    void auth.fetchUser().catch(() => {})
  }
  load()
})

function load() {
  try {
    const raw = localStorage.getItem(SETTINGS_KEY)
    if (!raw) return
    const data = JSON.parse(raw) as Partial<SavedSettings>
    if (typeof data.emailSummary === 'boolean') emailSummary.value = data.emailSummary
    if (typeof data.reportReady === 'boolean') reportReady.value = data.reportReady
    if (typeof data.productUpdates === 'boolean') productUpdates.value = data.productUpdates
    if (typeof data.language === 'string') language.value = data.language
  } catch {
    // Ignore malformed local settings.
  }
}

function save() {
  const data: SavedSettings = {
    emailSummary: emailSummary.value,
    reportReady: reportReady.value,
    productUpdates: productUpdates.value,
    language: language.value,
  }
  localStorage.setItem(SETTINGS_KEY, JSON.stringify(data))
  saved.value = true
  window.setTimeout(() => {
    saved.value = false
  }, 2000)
}

async function handleLogout() {
  await auth.logout()
  router.push({ name: 'login' })
}

const initials = (() => {
  const name = auth.user?.name?.trim() ?? ''
  if (!name) return ''
  return name
    .split(/\s+/)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? '')
    .join('')
})()

const memberSince = (() => {
  const created = auth.user?.created_at
  return created ? new Date(created).toLocaleDateString() : '—'
})()

const savedLabel = computed(() => (saved.value ? 'Saved' : 'Save preferences'))
</script>

<template>
  <div class="mx-auto max-w-3xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h1 class="text-2xl font-bold text-slate-900">Settings</h1>
    </div>

    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
      <h2 class="font-semibold text-slate-900">Account</h2>
      <div class="mt-4 flex items-center gap-4">
        <div
          class="flex h-14 w-14 items-center justify-center rounded-full bg-teal-600 text-lg font-bold text-white"
        >
          {{ initials }}
        </div>
        <div class="min-w-0">
          <p class="font-medium text-slate-900">{{ auth.user?.name ?? 'Loading…' }}</p>
          <p class="truncate text-sm text-slate-500">{{ auth.user?.email ?? '' }}</p>
          <p class="mt-0.5 text-sm">
            <span
              class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
              :class="isEmailVerified ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'"
            >
              {{ isEmailVerified ? 'Email verified' : 'Email not verified' }}
            </span>
          </p>
        </div>
      </div>
      <dl class="mt-5 grid gap-4 border-t border-slate-100 pt-5 sm:grid-cols-2">
        <div>
          <dt class="text-sm text-slate-500">Member since</dt>
          <dd class="font-medium text-slate-900">{{ memberSince }}</dd>
        </div>
        <div>
          <dt class="text-sm text-slate-500">Account type</dt>
          <dd class="font-medium text-slate-900">Free</dd>
        </div>
      </dl>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
      <h2 class="font-semibold text-slate-900">Notifications</h2>
      <p class="mt-1 text-sm text-slate-500">
        Choose what you'd like to hear about. Preferences are saved on this device.
      </p>
      <div class="mt-5 space-y-5 border-t border-slate-100 pt-5">
        <ToggleSwitch
          v-model="emailSummary"
          label="Email summary"
          description="Send me a short summary after a report is analyzed."
        />
        <ToggleSwitch
          v-model="reportReady"
          label="Report ready alerts"
          description="Notify me when a report is ready to view."
        />
        <ToggleSwitch
          v-model="productUpdates"
          label="Product updates"
          description="Occasional news about MedExplain features."
        />
      </div>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
      <h2 class="font-semibold text-slate-900">Preferences</h2>
      <div class="mt-4 border-t border-slate-100 pt-5">
        <label for="language" class="block text-sm font-medium text-slate-700">Language</label>
        <select
          id="language"
          v-model="language"
          class="mt-1 block w-full max-w-xs rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500"
        >
          <option value="en">English</option>
          <option value="fr">Français</option>
          <option value="es">Español</option>
        </select>
      </div>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
      <h2 class="font-semibold text-slate-900">Data &amp; privacy</h2>
      <p class="mt-1 text-sm text-slate-500">
        Your uploaded reports are stored privately and only you can access them.
      </p>
      <Disclaimer class="mt-4" />
    </section>

    <section class="rounded-xl border border-red-200 bg-white p-6 shadow-sm">
      <h2 class="font-semibold text-red-700">Danger zone</h2>
      <p class="mt-1 text-sm text-slate-500">
        Sign out of this device. Your reports remain stored on your account.
      </p>
      <button
        type="button"
        class="mt-4 rounded-md border border-red-300 px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50"
        @click="handleLogout"
      >
        Sign out
      </button>
    </section>

    <div class="sticky bottom-4 flex justify-end">
      <button
        type="button"
        class="rounded-md bg-teal-600 px-5 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-teal-700"
        @click="save"
      >
        {{ savedLabel }}
      </button>
    </div>
  </div>
</template>