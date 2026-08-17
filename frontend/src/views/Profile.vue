<script setup lang="ts">
import { onMounted } from 'vue'

import LoadingState from '@/components/LoadingState.vue'
import { useAuth } from '@/composables/useAuth'

const { store: auth } = useAuth()

onMounted(() => {
  if (!auth.user) {
    void auth.fetchUser().catch(() => {
      // The auth store is cleared by the client interceptor on 401.
    })
  }
})
</script>

<template>
  <div class="mx-auto max-w-2xl space-y-6">
    <h1 class="text-2xl font-bold text-slate-900">Profile</h1>

    <div v-if="!auth.user" class="py-8">
      <LoadingState />
    </div>

    <div v-else class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
      <dl class="grid gap-4 sm:grid-cols-2">
        <div>
          <dt class="text-sm text-slate-500">Name</dt>
          <dd class="font-medium text-slate-900">{{ auth.user.name }}</dd>
        </div>
        <div>
          <dt class="text-sm text-slate-500">Email</dt>
          <dd class="font-medium text-slate-900">{{ auth.user.email }}</dd>
        </div>
        <div>
          <dt class="text-sm text-slate-500">Email verified</dt>
          <dd
            class="font-medium"
            :class="auth.user.email_verified_at ? 'text-emerald-600' : 'text-amber-600'"
          >
            {{ auth.user.email_verified_at ? 'Yes' : 'No' }}
          </dd>
        </div>
        <div>
          <dt class="text-sm text-slate-500">Member since</dt>
          <dd class="font-medium text-slate-900">
            {{ new Date(auth.user.created_at).toLocaleDateString() }}
          </dd>
        </div>
      </dl>
    </div>
  </div>
</template>