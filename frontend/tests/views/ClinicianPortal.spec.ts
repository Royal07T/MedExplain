import { describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'

vi.mock('@/api/clinician', () => ({
  listPatients: vi.fn(),
  grantPatientAccess: vi.fn(),
  getPatientRecord: vi.fn(),
}))

import * as clinicianApi from '@/api/clinician'
import ClinicianPortal from '@/views/ClinicianPortal.vue'
import type { HealthRecord } from '@/types'

const record: HealthRecord = {
  profile: { name: 'Ada', email: 'ada@example.com', date_of_birth: null, gender: null },
  labs: [{ name: 'Glucose', value: '95', unit: 'mg/dL', status: 'normal', reference_range: '70-99', last_collected_at: '2026-01-01T00:00:00Z' }],
  medications: [{ id: 1, name: 'Metformin', strength: '500 mg', dosage_form: 'tablet', dose: '500', frequency: 'twice daily', route: 'oral', indications: null, medical_document_id: null }],
  timeline: [],
}

describe('ClinicianPortal', () => {
  it('lists patients and shows the selected patient record', async () => {
    vi.mocked(clinicianApi.listPatients).mockResolvedValue([
      { id: 1, name: 'Ada Lovelace', email: 'ada@example.com', last_lab_date: '2026-01-01T00:00:00Z' },
    ])
    vi.mocked(clinicianApi.getPatientRecord).mockResolvedValue(record)

    const wrapper = mount(ClinicianPortal)
    await flushPromises()

    expect(wrapper.text()).toContain('Ada Lovelace')

    const patientButton = wrapper.findAll('button').find((b) => b.text().includes('Ada Lovelace'))
    expect(patientButton).toBeDefined()
    await patientButton!.trigger('click')
    await flushPromises()

    expect(clinicianApi.getPatientRecord).toHaveBeenCalledWith(1)
    expect(wrapper.text()).toContain('Glucose')
    expect(wrapper.text()).toContain('Metformin')
  })

  it('grants access via email', async () => {
    vi.mocked(clinicianApi.listPatients).mockResolvedValue([])
    vi.mocked(clinicianApi.grantPatientAccess).mockResolvedValue({
      data: { id: 2, name: 'Grace Hopper', email: 'grace@example.com', last_lab_date: null },
      created: true,
    })

    const wrapper = mount(ClinicianPortal)
    await flushPromises()

    await wrapper.find('input').setValue('grace@example.com')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(clinicianApi.grantPatientAccess).toHaveBeenCalledWith('grace@example.com')
    expect(wrapper.text()).toContain('Access granted to Grace Hopper.')
  })
})