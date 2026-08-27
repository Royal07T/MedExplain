<script setup lang="ts">
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-slate-50">
    <div class="bg-white rounded-lg p-8 shadow-xl text-center max-w-md">
      <div class="h-12 w-12 rounded-full bg-red-100 flex items-center justify-center mx-auto mb-6">
        <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
      </div>
      <h2 class="text-2xl font-bold text-slate-900 mb-3">403 - Forbidden</h2>
      <p class="text-slate-600 mb-6">You don't have permission to access this page.</p>
      <button
        class="rounded-lg border border-teal-500 px-4 py-2 text-sm font-medium text-teal-700 hover:bg-teal-50 transition-colors"
        @click="$router.push('/dashboard')"
      >
        Go to Dashboard
      </button>
    </div>
  </div>
</template>
