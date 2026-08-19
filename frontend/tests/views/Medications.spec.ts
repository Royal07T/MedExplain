import { describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia } from 'pinia'

vi.mock('@/api/medications', () => ({
  listMedications: vi.fn(),
}))

import * as medicationsApi from '@/api/medications'
import Medications from '@/views/Medications.vue'
import type { Medication } from '@/types'

const medication: Medication = {
  id: 1,
  name: 'Metformin',
  strength: '500 mg',
  dosage_form: 'tablet',
  dose: '500',
  frequency: 'twice daily',
  route: 'oral',
  prescriber: null,
  indications: null,
  start_date: null,
  end_date: null,
  medical_document_id: 2,
  created_at: '2026-01-01T00:00:00Z',
}

describe('Medications', () => {
  it('renders extracted medications', async () => {
    vi.mocked(medicationsApi.listMedications).mockResolvedValue([medication])

    const wrapper = mount(Medications, { global: { plugins: [createPinia()] } })
    await flushPromises()

    expect(wrapper.text()).toContain('Metformin')
    expect(wrapper.text()).toContain('500 mg')
    expect(wrapper.text()).toContain('twice daily')
    expect(wrapper.text()).toContain('oral')
  })

  it('shows an empty state when there are no medications', async () => {
    vi.mocked(medicationsApi.listMedications).mockResolvedValue([])

    const wrapper = mount(Medications, { global: { plugins: [createPinia()] } })
    await flushPromises()

    expect(wrapper.text()).toContain('No medications yet')
  })
})