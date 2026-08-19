<script setup lang="ts">
import { onMounted, ref } from 'vue'

import { listConsents, revokeConsent } from '@/api/partner'
import type { PartnerConsent } from '@/types'

const consents = ref<PartnerConsent[]>([])
const loading = ref(true)
const error = ref<string | null>(null)
const revokingId = ref<number | null>(null)

onMounted(load)

async function load() {
  loading.value = true
  error.value = null
  try {
    consents.value = await listConsents()
  } catch {
    error.value = 'Unable to load your connected apps.'
  } finally {
    loading.value = false
  }
}

async function revoke(consent: PartnerConsent) {
  if (revokingId.value !== null) return
  revokingId.value = consent.partner_id
  try {
    await revokeConsent(consent.partner_id)
    consent.revoked_at = new Date().toISOString()
  } catch {
    error.value = 'Unable to revoke access. Please try again.'
  } finally {
    revokingId.value = null
  }
}

function formatDate(value: string | null): string {
  if (!value) return '—'
  return new Date(value).toLocaleDateString()
}

function scopeLabel(scope: string): string {
  switch (scope) {
    case 'health_record:read':
      return 'Read your health record'
    default:
      return scope
  }
}
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-slate-900">Connected apps</h1>
      <p class="mt-1 text-sm text-slate-500">
        Healthtech apps you have given access to your health record. You can
        revoke access at any time.
      </p>
    </div>

    <p v-if="error" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
      {{ error }}
    </p>

    <div
      v-if="loading"
      class="rounded-xl border border-slate-200 bg-white p-10 text-center text-sm text-slate-500"
    >
      Loading connected apps…
    </div>

    <div
      v-else-if="consents.length === 0"
      class="rounded-xl border border-slate-200 bg-white p-10 text-center text-sm text-slate-500"
    >
      <p class="text-base font-medium text-slate-700">No connected apps</p>
      <p class="mt-1">When a healthtech app asks for access, it will appear here.</p>
    </div>

    <div v-else class="space-y-4">
      <div
        v-for="consent in consents"
        :key="consent.partner_id"
        class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"
      >
        <div class="flex items-start justify-between gap-4">
          <div class="min-w-0">
            <p class="font-semibold text-slate-900">{{ consent.partner_name }}</p>
            <p class="mt-0.5 text-sm text-slate-500">Granted {{ formatDate(consent.granted_at) }}</p>
            <ul class="mt-3 space-y-1">
              <li
                v-for="scope in consent.scopes"
                :key="scope"
                class="text-sm text-slate-600"
              >
                <span class="mr-2 text-teal-600">✓</span>{{ scopeLabel(scope) }}
              </li>
            </ul>
          </div>

          <div v-if="consent.revoked_at === null" class="shrink-0">
            <button
              type="button"
              class="rounded-md border border-red-200 px-3 py-1.5 text-sm font-medium text-red-600 transition-colors hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-50"
              :disabled="revokingId !== null"
              @click="revoke(consent)"
            >
              {{ revokingId === consent.partner_id ? 'Revoking…' : 'Revoke' }}
            </button>
          </div>
          <span
            v-else
            class="shrink-0 rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-500"
          >
            Revoked
          </span>
        </div>
      </div>
    </div>
  </div>
</template>