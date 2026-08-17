<script setup lang="ts">
defineProps<{ title?: string; message?: string }>()
</script>

<template>
  <div class="flex flex-col items-center gap-2 py-16 text-center text-red-600">
    <p class="font-medium">{{ title ?? 'Something went wrong.' }}</p>
    <p v-if="message" class="text-sm text-red-500">{{ message }}</p>
  </div>
</template>