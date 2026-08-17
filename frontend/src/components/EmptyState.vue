<script setup lang="ts">
withDefaults(
  defineProps<{
    message?: string
    actionLabel?: string
    actionTo?: { name: string }
  }>(),
  {
    message: 'Nothing here yet.',
    actionLabel: 'Get started',
  },
)
</script>

<template>
  <div class="flex flex-col items-center gap-3 py-16 text-center text-slate-500">
    <p>{{ message }}</p>
    <router-link
      v-if="actionTo"
      :to="actionTo"
      class="rounded bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700"
    >
      {{ actionLabel }}
    </router-link>
  </div>
</template>