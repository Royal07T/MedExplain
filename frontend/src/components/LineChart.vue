<script setup lang="ts">
import { computed } from 'vue'

interface ChartPoint {
  label: string
  value: number
}

const props = defineProps<{
  points: ChartPoint[]
  unit?: string | null
  refLow?: number | null
  refHigh?: number | null
}>()

const WIDTH = 640
const HEIGHT = 240
const PAD_X = 40
const PAD_TOP = 16
const PAD_BOTTOM = 32

const numeric = computed(() => {
  const lo = props.refLow ?? null
  const hi = props.refHigh ?? null
  return [lo, hi, ...props.points.map((p) => p.value)].filter(
    (v): v is number => typeof v === 'number' && Number.isFinite(v),
  )
})

const minValue = computed(() => (numeric.value.length ? Math.min(...numeric.value) : 0))
const maxValue = computed(() => (numeric.value.length ? Math.max(...numeric.value) : 1))

const yMin = computed(() => {
  const span = maxValue.value - minValue.value || 1
  return minValue.value - span * 0.15
})
const yMax = computed(() => {
  const span = maxValue.value - minValue.value || 1
  return maxValue.value + span * 0.15
})

const innerWidth = computed(() => WIDTH - PAD_X * 2)
const innerHeight = computed(() => HEIGHT - PAD_TOP - PAD_BOTTOM)

const plotW = computed(() => Math.max(innerWidth.value, 1))
const plotH = computed(() => Math.max(innerHeight.value, 1))
const span = computed(() => yMax.value - yMin.value || 1)

function xAt(index: number, count: number): number {
  if (count <= 1) return PAD_X + plotW.value / 2
  return PAD_X + (index / (count - 1)) * plotW.value
}

function yAt(value: number): number {
  return PAD_TOP + ((yMax.value - value) / span.value) * plotH.value
}

const path = computed(() => {
  const pts = props.points
  if (pts.length === 0) return ''
  return pts
    .map((p, i) => `${i === 0 ? 'M' : 'L'}${xAt(i, pts.length).toFixed(1)},${yAt(p.value).toFixed(1)}`)
    .join(' ')
})

const dots = computed(() =>
  props.points.map((p, i) => ({
    cx: xAt(i, props.points.length),
    cy: yAt(p.value),
    label: p.label,
  })),
)

const yTicks = computed(() => {
  const ticks: { value: number; y: number }[] = []
  for (let i = 0; i <= 4; i += 1) {
    const value = yMin.value + (span.value * i) / 4
    ticks.push({ value, y: yAt(value) })
  }
  return ticks
})

const xLabels = computed(() => {
  const pts = props.points
  if (pts.length === 0) return []
  const count = Math.min(pts.length, 6)
  const step = Math.ceil(pts.length / count)
  const chosen = pts.filter((_, i) => i % step === 0)
  if (!chosen.includes(pts[pts.length - 1])) chosen.push(pts[pts.length - 1])
  return chosen.map((p) => ({ text: p.label, x: xAt(pts.indexOf(p), pts.length) }))
})

const refBand = computed(() => {
  const lo = props.refLow
  const hi = props.refHigh
  if (lo == null || hi == null || !Number.isFinite(lo) || !Number.isFinite(hi)) return null
  return {
    y: yAt(Math.max(lo, yMin.value)),
    height: Math.max(yAt(Math.min(hi, yMax.value)) - yAt(Math.max(lo, yMin.value)), 0),
  }
})

const shortLabel = (value: number): string => {
  if (Math.abs(value) >= 1000) return value.toFixed(0)
  return value.toFixed(Number.isInteger(value) ? 0 : 1)
}
</script>

<template>
  <div class="w-full">
    <svg
      :viewBox="`0 0 ${WIDTH} ${HEIGHT}`"
      class="h-auto w-full"
      role="img"
      aria-label="Lab value trend line chart"
    >
      <rect
        v-for="tick in yTicks"
        :key="tick.value"
        :x="PAD_X"
        :y="tick.y"
        :width="plotW"
        height="1"
        class="fill-slate-100"
      />
      <text
        v-for="tick in yTicks"
        :key="`l-${tick.value}`"
        :x="PAD_X - 8"
        :y="tick.y + 4"
        class="fill-slate-400 text-[10px]"
        text-anchor="end"
      >
        {{ shortLabel(tick.value) }}
      </text>

      <rect
        v-if="refBand"
        :x="PAD_X"
        :y="refBand.y"
        :width="plotW"
        :height="refBand.height"
        class="fill-teal-50"
        rx="2"
      />

      <path :d="path" class="fill-none stroke-teal-600" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" />

      <circle
        v-for="dot in dots"
        :key="dot.cx"
        :cx="dot.cx"
        :cy="dot.cy"
        r="3.5"
        class="fill-white stroke-teal-600"
        stroke-width="2"
      >
        <title>{{ dot.label }}: {{ shortLabel(props.points[dots.indexOf(dot)].value) }}{{ unit ? ` ${unit}` : '' }}</title>
      </circle>

      <text
        v-for="label in xLabels"
        :key="label.text"
        :x="label.x"
        :y="HEIGHT - 10"
        class="fill-slate-400 text-[10px]"
        text-anchor="middle"
      >
        {{ label.text }}
      </text>
    </svg>
  </div>
</template>