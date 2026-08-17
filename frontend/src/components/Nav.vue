<script setup lang="ts">
import { useRouter } from 'vue-router'

import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()

async function handleLogout() {
  await auth.logout()
  router.push({ name: 'login' })
}
</script>

<template>
  <header class="border-b border-slate-200 bg-white">
    <nav class="mx-auto flex max-w-5xl items-center justify-between px-4 py-3">
      <router-link :to="{ name: 'dashboard' }" class="text-lg font-bold text-teal-700">
        MedExplain
      </router-link>

      <div v-if="auth.isAuthenticated" class="flex items-center gap-4 text-sm">
        <router-link :to="{ name: 'dashboard' }" class="text-slate-600 hover:text-teal-700">
          Dashboard
        </router-link>
        <router-link :to="{ name: 'reports' }" class="text-slate-600 hover:text-teal-700">
          Reports
        </router-link>
        <router-link
          :to="{ name: 'reports.upload' }"
          class="rounded bg-teal-600 px-3 py-1.5 font-medium text-white hover:bg-teal-700"
        >
          Upload
        </router-link>
        <router-link :to="{ name: 'profile' }" class="text-slate-600 hover:text-teal-700">
          Profile
        </router-link>
        <button
          type="button"
          class="text-slate-500 hover:text-slate-800"
          @click="handleLogout"
        >
          Log out
        </button>
      </div>
    </nav>
  </header>
</template>