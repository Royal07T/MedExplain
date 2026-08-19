<script setup lang="ts">
defineProps<{ modelValue: boolean; label: string; description?: string }>()
defineEmits<{ 'update:modelValue': [value: boolean] }>()
</script>

<template>
  <div class="flex items-start justify-between gap-4">
    <div>
      <p class="font-medium text-slate-900">{{ label }}</p>
      <p v-if="description" class="mt-0.5 text-sm text-slate-500">{{ description }}</p>
    </div>
    <button
      type="button"
      role="switch"
      :aria-checked="modelValue"
      class="relative mt-0.5 inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors"
      :class="modelValue ? 'bg-teal-600' : 'bg-slate-300'"
      @click="$emit('update:modelValue', !modelValue)"
    >
      <span
        class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"
        :class="modelValue ? 'translate-x-6' : 'translate-x-1'"
      />
    </button>
  </div>
</template>