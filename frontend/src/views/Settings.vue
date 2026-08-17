<script setup lang="ts">
import Disclaimer from '@/components/Disclaimer.vue'
</script>

<template>
  <div class="mx-auto max-w-2xl space-y-6">
    <h1 class="text-2xl font-bold text-slate-900">Settings</h1>

    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
      <h2 class="font-semibold text-slate-900">Notifications</h2>
      <p class="mt-1 text-sm text-slate-500">
        Email preferences are not yet available. This area will be extended in a future
        milestone.
      </p>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
      <h2 class="font-semibold text-slate-900">Data &amp; privacy</h2>
      <p class="mt-1 text-sm text-slate-500">
        Your uploaded reports are stored privately and only you can access them.
      </p>
      <Disclaimer class="mt-4" />
    </div>
  </div>
</template>