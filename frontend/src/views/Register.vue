<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'

import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()

const form = reactive({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
})
const error = ref<string | null>(null)

async function submit() {
  error.value = null
  if (form.password !== form.password_confirmation) {
    error.value = 'Passwords do not match.'
    return
  }
  try {
    await auth.register(form)
    router.push({ name: 'dashboard' })
  } catch {
    error.value = 'Registration failed. Please check your details and try again.'
  }
}
</script>

<template>
  <div class="flex min-h-screen items-center justify-center bg-slate-50 px-4">
    <div class="w-full max-w-sm rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
      <h1 class="text-2xl font-bold text-teal-700">Create your account</h1>
      <p class="mt-1 text-sm text-slate-500">Free and private &mdash; your reports stay yours.</p>

      <form class="mt-6 space-y-4" @submit.prevent="submit">
        <label class="block">
          <span class="text-sm font-medium text-slate-700">Name</span>
          <input
            v-model="form.name"
            type="text"
            required
            autocomplete="name"
            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-teal-500 focus:outline-none"
          />
        </label>

        <label class="block">
          <span class="text-sm font-medium text-slate-700">Email</span>
          <input
            v-model="form.email"
            type="email"
            required
            autocomplete="email"
            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-teal-500 focus:outline-none"
          />
        </label>

        <label class="block">
          <span class="text-sm font-medium text-slate-700">Password</span>
          <input
            v-model="form.password"
            type="password"
            required
            autocomplete="new-password"
            minlength="8"
            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-teal-500 focus:outline-none"
          />
        </label>

        <label class="block">
          <span class="text-sm font-medium text-slate-700">Confirm password</span>
          <input
            v-model="form.password_confirmation"
            type="password"
            required
            autocomplete="new-password"
            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-teal-500 focus:outline-none"
          />
        </label>

        <p v-if="error" class="text-sm text-red-600">{{ error }}</p>

        <button
          type="submit"
          :disabled="auth.loading"
          class="w-full rounded-md bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700 disabled:opacity-60"
        >
          {{ auth.loading ? 'Creating account…' : 'Create account' }}
        </button>
      </form>

      <p class="mt-4 text-center text-sm text-slate-500">
        Already have an account?
        <router-link :to="{ name: 'login' }" class="font-medium text-teal-700 hover:underline">
          Sign in
        </router-link>
      </p>
    </div>
  </div>
</template>