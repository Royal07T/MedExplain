import { afterEach, describe, expect, it, vi } from 'vitest'

import * as clinicianApi from '@/api/clinician'
import { apiClient } from '@/api/client'
import type { HealthRecord } from '@/types'

function axiosLike(data: unknown) {
  return { data, status: 200, statusText: 'OK', headers: {}, config: {} }
}

const record: HealthRecord = {
  profile: { name: 'Ada', email: 'ada@example.com', date_of_birth: null, gender: null },
  labs: [],
  medications: [],
  timeline: [],
}

describe('clinician api', () => {
  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('lists assigned patients', async () => {
    const patients = [{ id: 1, name: 'Ada', email: 'ada@example.com', last_lab_date: null }]
    const spy = vi.spyOn(apiClient, 'get').mockResolvedValue(axiosLike({ data: patients }) as never)

    const result = await clinicianApi.listPatients()

    expect(spy).toHaveBeenCalledWith('/clinician/patients')
    expect(result).toHaveLength(1)
    expect(result[0].name).toBe('Ada')
  })

  it('grants patient access by email', async () => {
    const spy = vi.spyOn(apiClient, 'post').mockResolvedValue(
      axiosLike({ data: { id: 1, name: 'Ada', email: 'ada@example.com', last_lab_date: null }, created: true }) as never,
    )

    const result = await clinicianApi.grantPatientAccess('ada@example.com')

    expect(spy).toHaveBeenCalledWith('/clinician/patients', { email: 'ada@example.com' })
    expect(result.created).toBe(true)
  })

  it('fetches a patient record', async () => {
    const spy = vi.spyOn(apiClient, 'get').mockResolvedValue(axiosLike({ data: record }) as never)

    const result = await clinicianApi.getPatientRecord(1)

    expect(spy).toHaveBeenCalledWith('/clinician/patients/1/record')
    expect(result.profile.name).toBe('Ada')
  })
})