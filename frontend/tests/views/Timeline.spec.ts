import { describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia } from 'pinia'

vi.mock('@/api/health', () => ({
  getLabTestNames: vi.fn(),
  getLabTrend: vi.fn(),
  getTimeline: vi.fn(),
}))

import * as healthApi from '@/api/health'
import Timeline from '@/views/Timeline.vue'
import type { TimelineEvent } from '@/types'

const events: TimelineEvent[] = [
  { type: 'document_uploaded', occurred_at: '2026-01-02T00:00:00Z', title: 'Report uploaded', description: 'newer.pdf', document_id: 2 },
  { type: 'lab_result', occurred_at: '2026-01-01T00:00:00Z', title: 'Glucose recorded', description: '95 mg/dL', document_id: 1 },
]

describe('Timeline', () => {
  it('renders events newest first', async () => {
    vi.mocked(healthApi.getTimeline).mockResolvedValue(events)

    const wrapper = mount(Timeline, { global: { plugins: [createPinia()] } })
    await flushPromises()

    expect(wrapper.text()).toContain('Health Timeline')
    expect(wrapper.text()).toContain('Report uploaded')
    expect(wrapper.text()).toContain('Glucose recorded')
  })

  it('shows an empty state when there are no events', async () => {
    vi.mocked(healthApi.getTimeline).mockResolvedValue([])

    const wrapper = mount(Timeline, { global: { plugins: [createPinia()] } })
    await flushPromises()

    expect(wrapper.text()).toContain('No events yet')
  })
})