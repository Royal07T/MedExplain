<script setup lang="ts">
defineProps<{ label?: string }>()
</script>

<template>
  <div class="flex flex-col items-center justify-center gap-3 py-16 text-slate-500">
    <div class="h-8 w-8 animate-spin rounded-full border-2 border-teal-600 border-t-transparent" />
    <p class="text-sm">{{ label ?? 'Loading…' }}</p>
  </div>
</template>