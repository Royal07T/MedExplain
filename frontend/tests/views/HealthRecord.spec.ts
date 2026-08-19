import { describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'

vi.mock('@/api/health', () => ({
  getHealthRecord: vi.fn(),
}))

import { getHealthRecord } from '@/api/health'
import HealthRecord from '@/views/HealthRecord.vue'
import type { HealthRecord as HealthRecordType } from '@/types'

const record: HealthRecordType = {
  profile: { name: 'Ada Lovelace', email: 'ada@example.com', date_of_birth: '1980-01-01T00:00:00Z', gender: 'female' },
  labs: [{ name: 'Glucose', value: '95', unit: 'mg/dL', status: 'normal', reference_range: '70-99 mg/dL', last_collected_at: '2026-01-01T00:00:00Z' }],
  medications: [{ id: 1, name: 'Metformin', strength: '500 mg', dosage_form: 'tablet', dose: '500', frequency: 'twice daily', route: 'oral', indications: null, medical_document_id: null }],
  timeline: [{ type: 'lab_result', occurred_at: '2026-01-01T00:00:00Z', title: 'Glucose recorded', description: '95 mg/dL', document_id: 1 }],
}

describe('HealthRecord', () => {
  it('renders profile, labs, medications, and timeline', async () => {
    vi.mocked(getHealthRecord).mockResolvedValue(record)

    const wrapper = mount(HealthRecord)
    await flushPromises()

    expect(wrapper.text()).toContain('Ada Lovelace')
    expect(wrapper.text()).toContain('Glucose')
    expect(wrapper.text()).toContain('95')
    expect(wrapper.text()).toContain('Metformin')
    expect(wrapper.text()).toContain('Glucose recorded')
  })

  it('shows an empty state when there is no data', async () => {
    vi.mocked(getHealthRecord).mockResolvedValue({
      ...record,
      labs: [],
      medications: [],
      timeline: [],
    })

    const wrapper = mount(HealthRecord)
    await flushPromises()

    expect(wrapper.text()).toContain('No lab results recorded yet.')
    expect(wrapper.text()).toContain('No medications recorded yet.')
    expect(wrapper.text()).toContain('No activity recorded yet.')
  })
})