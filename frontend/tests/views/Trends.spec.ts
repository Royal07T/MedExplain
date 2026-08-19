import { describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia } from 'pinia'

vi.mock('@/api/health', () => ({
  getLabTestNames: vi.fn(),
  getLabTrend: vi.fn(),
  getTimeline: vi.fn(),
}))

import * as healthApi from '@/api/health'
import Trends from '@/views/Trends.vue'
import type { LabTestName, LabTrend } from '@/types'

const names: LabTestName[] = [
  { name: 'Glucose', unit: 'mg/dL', last_collected_at: '2026-01-02T00:00:00Z', count: 2 },
  { name: 'Cholesterol', unit: 'mg/dL', last_collected_at: '2026-01-01T00:00:00Z', count: 1 },
]

const trend: LabTrend = {
  test: 'Glucose',
  unit: 'mg/dL',
  series: [
    { date: '2026-01-01T00:00:00Z', value: '90', status: 'normal', reference_range: '70-99', document_id: 1, document_filename: 'first.pdf' },
    { date: '2026-01-02T00:00:00Z', value: '105', status: 'high', reference_range: '70-99', document_id: 2, document_filename: 'second.pdf' },
  ],
}

describe('Trends', () => {
  it('loads test names and renders the selected trend', async () => {
    vi.mocked(healthApi.getLabTestNames).mockResolvedValue(names)
    vi.mocked(healthApi.getLabTrend).mockResolvedValue(trend)

    const wrapper = mount(Trends, {
      global: {
        plugins: [createPinia()],
        stubs: {
          RouterLink: {
            props: ['to'],
            template: '<a><slot /></a>',
          },
        },
      },
    })
    await flushPromises()

    expect(wrapper.text()).toContain('Lab Trends')
    expect(healthApi.getLabTrend).toHaveBeenCalledWith('Glucose')
    expect(wrapper.text()).toContain('Glucose')
    expect(wrapper.text()).toContain('105 mg/dL')
    expect(wrapper.text()).toContain('second.pdf')
  })

  it('shows an empty state when there are no tests', async () => {
    vi.mocked(healthApi.getLabTestNames).mockResolvedValue([])

    const wrapper = mount(Trends, { global: { plugins: [createPinia()] } })
    await flushPromises()

    expect(wrapper.text()).toContain('No trends yet')
  })
})