<script setup lang="ts">
import { ref, computed } from 'vue'
import type { CDSAlert } from '@/api/clinicalDecisionSupport'

const props = defineProps<{
  alerts: CDSAlert[]
  dismissible?: boolean
}>()

const emit = defineEmits<{
  dismiss: [alert: CDSAlert]
  dismissAll: []
}>()

const dismissedAlerts = ref<Set<number>>(new Set())

const visibleAlerts = computed(() => {
  return props.alerts.filter((alert, index) => !dismissedAlerts.value.has(index))
})

const severeAlerts = computed(() => visibleAlerts.value.filter(a => a.severity === 'severe'))
const moderateAlerts = computed(() => visibleAlerts.value.filter(a => a.severity === 'moderate'))
const mildAlerts = computed(() => visibleAlerts.value.filter(a => a.severity === 'mild'))

function dismissAlert(alert: CDSAlert, index: number) {
  dismissedAlerts.value.add(index)
  emit('dismiss', alert)
}

function dismissAll() {
  props.alerts.forEach((_, index) => dismissedAlerts.value.add(index))
  emit('dismissAll')
}

function getAlertIcon(type: string): string {
  const icons: Record<string, string> = {
    allergy: '⚠️',
    drug_interaction: '💊',
    dose_adjustment: '📊',
    vital_sign: '❤️',
    guideline: '📋',
    preventive: '🛡️'
  }
  return icons[type] || 'ℹ️'
}

function getAlertColor(severity: string): string {
  const colors = {
    severe: 'border-red-300 bg-red-50 text-red-900',
    moderate: 'border-orange-300 bg-orange-50 text-orange-900',
    mild: 'border-yellow-300 bg-yellow-50 text-yellow-900'
  }
  return colors[severity as keyof typeof colors] || colors.mild
}
</script>

<template>
  <div v-if="visibleAlerts.length > 0" class="space-y-3">
    <!-- Severe Alerts -->
    <div v-if="severeAlerts.length > 0" class="rounded-lg border border-red-300 bg-red-50 p-4">
      <div class="flex items-start justify-between">
        <div class="flex items-start gap-3">
          <span class="text-2xl">🚨</span>
          <div class="flex-1">
            <h3 class="font-semibold text-red-900">Critical Alerts ({{ severeAlerts.length }})</h3>
            <ul class="mt-2 space-y-2">
              <li v-for="(alert, idx) in severeAlerts" :key="idx" class="flex items-start gap-2 text-sm text-red-800">
                <span>{{ getAlertIcon(alert.type) }}</span>
                <span class="flex-1">{{ alert.message }}</span>
                <button
                  v-if="dismissible"
                  @click="dismissAlert(alert, props.alerts.indexOf(alert))"
                  class="ml-2 rounded p-0.5 hover:bg-red-200 text-red-700"
                >
                  ✕
                </button>
              </li>
            </ul>
          </div>
        </div>
        <button
          v-if="dismissible && severeAlerts.length > 1"
          @click="dismissAll"
          class="rounded px-2 py-1 text-xs font-medium text-red-700 hover:bg-red-200"
        >
          Dismiss All
        </button>
      </div>
    </div>

    <!-- Moderate Alerts -->
    <div v-if="moderateAlerts.length > 0" class="rounded-lg border border-orange-300 bg-orange-50 p-4">
      <div class="flex items-start justify-between">
        <div class="flex items-start gap-3">
          <span class="text-2xl">⚡</span>
          <div class="flex-1">
            <h3 class="font-semibold text-orange-900">Warnings ({{ moderateAlerts.length }})</h3>
            <ul class="mt-2 space-y-2">
              <li v-for="(alert, idx) in moderateAlerts" :key="idx" class="flex items-start gap-2 text-sm text-orange-800">
                <span>{{ getAlertIcon(alert.type) }}</span>
                <span class="flex-1">{{ alert.message }}</span>
                <button
                  v-if="dismissible"
                  @click="dismissAlert(alert, props.alerts.indexOf(alert))"
                  class="ml-2 rounded p-0.5 hover:bg-orange-200 text-orange-700"
                >
                  ✕
                </button>
              </li>
            </ul>
          </div>
        </div>
        <button
          v-if="dismissible && moderateAlerts.length > 1"
          @click="dismissAll"
          class="rounded px-2 py-1 text-xs font-medium text-orange-700 hover:bg-orange-200"
        >
          Dismiss All
        </button>
      </div>
    </div>

    <!-- Mild Alerts -->
    <details v-if="mildAlerts.length > 0" class="group rounded-lg border border-yellow-300 bg-yellow-50">
      <summary class="cursor-pointer p-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <span class="text-xl">ℹ️</span>
            <h3 class="font-semibold text-yellow-900">Information ({{ mildAlerts.length }})</h3>
          </div>
          <span class="text-yellow-700 group-open:rotate-180 transition-transform">▼</span>
        </div>
      </summary>
      <ul class="px-4 pb-4 space-y-2">
        <li v-for="(alert, idx) in mildAlerts" :key="idx" class="flex items-start gap-2 text-sm text-yellow-800">
          <span>{{ getAlertIcon(alert.type) }}</span>
          <span class="flex-1">{{ alert.message }}</span>
          <button
            v-if="dismissible"
            @click="dismissAlert(alert, props.alerts.indexOf(alert))"
            class="ml-2 rounded p-0.5 hover:bg-yellow-200 text-yellow-700"
          >
            ✕
          </button>
        </li>
      </ul>
    </details>
  </div>
</template>
