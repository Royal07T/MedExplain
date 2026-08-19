import { afterEach, describe, expect, it, vi } from 'vitest'

import * as medicationsApi from '@/api/medications'
import { apiClient } from '@/api/client'
import type { Medication } from '@/types'

function axiosLike(data: unknown) {
  return { data, status: 200, statusText: 'OK', headers: {}, config: {} }
}

describe('medications api', () => {
  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('lists medications', async () => {
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
    const spy = vi
      .spyOn(apiClient, 'get')
      .mockResolvedValue(axiosLike({ data: [medication] }) as never)

    const result = await medicationsApi.listMedications()

    expect(spy).toHaveBeenCalledWith('/medications')
    expect(result).toHaveLength(1)
    expect(result[0].name).toBe('Metformin')
  })
})