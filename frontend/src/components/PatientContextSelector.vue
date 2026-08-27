<script setup lang="ts">
import { ref, watch } from 'vue'
import { usePatientContextStore } from '@/stores/patientContext'

const ctx = usePatientContextStore()
const query = ref('')
const isOpen = ref(false)

let debounceTimer: ReturnType<typeof setTimeout>

watch(query, (val) => {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => {
    ctx.searchPatients(val)
  }, 200)
})

function selectPatient(id: number) {
  ctx.selectContext(id)
  query.value = ''
  isOpen.value = false
}

function clearContext() {
  ctx.clearContext()
}
</script>

<template>
  <div class="relative">
    <!-- Current context display -->
    <div v-if="ctx.currentContext" class="rounded-lg bg-teal-50 p-2.5">
      <div class="flex items-center justify-between">
        <div class="min-w-0">
          <p class="text-xs font-medium text-teal-700">Viewing patient</p>
          <p class="truncate text-sm font-semibold text-teal-900">{{ ctx.currentContext.full_name }}</p>
          <p class="text-xs text-teal-600">MRN: {{ ctx.currentContext.mrn }}</p>
        </div>
        <button @click="clearContext" class="ml-2 text-teal-600 hover:text-teal-800" title="Clear context">
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>
    </div>

    <!-- Search input -->
    <div v-else>
      <label class="mb-1 block text-xs font-medium text-slate-600">Select patient</label>
      <div class="relative">
        <input
          v-model="query"
          @focus="isOpen = true"
          type="text"
          placeholder="Search by name or MRN..."
          class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-teal-500 focus:outline-none"
        />
        <svg class="absolute right-2.5 top-2.5 h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
      </div>

      <!-- Search results -->
      <div v-if="isOpen && ctx.searchResults.length > 0" class="absolute left-0 right-0 top-full z-50 mt-1 rounded-lg border border-slate-200 bg-white shadow-lg">
        <ul class="max-h-48 overflow-y-auto">
          <li v-for="p in ctx.searchResults" :key="p.id">
            <button
              @click="selectPatient(p.id)"
              class="flex w-full items-center gap-3 px-3 py-2 text-left text-sm hover:bg-slate-50"
            >
              <div class="min-w-0 flex-1">
                <p class="font-medium text-slate-900">{{ p.full_name }}</p>
                <p class="text-xs text-slate-500">MRN: {{ p.mrn }}</p>
              </div>
            </button>
          </li>
        </ul>
      </div>
    </div>
  </div>
</template>
