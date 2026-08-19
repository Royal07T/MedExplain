import { afterEach, describe, expect, it, vi } from 'vitest'

import * as healthApi from '@/api/health'
import { apiClient } from '@/api/client'
import type { HealthRecord, LabTestName, LabTrend, TimelineEvent } from '@/types'

function axiosLike(data: unknown) {
  return { data, status: 200, statusText: 'OK', headers: {}, config: {} }
}

describe('health api', () => {
  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('fetches lab test names', async () => {
    const names: LabTestName[] = [
      { name: 'Glucose', unit: 'mg/dL', last_collected_at: '2026-01-01T00:00:00Z', count: 3 },
    ]
    const spy = vi.spyOn(apiClient, 'get').mockResolvedValue(axiosLike({ data: names }) as never)

    const result = await healthApi.getLabTestNames()

    expect(spy).toHaveBeenCalledWith('/labs/names')
    expect(result).toHaveLength(1)
    expect(result[0].name).toBe('Glucose')
  })

  it('fetches a lab trend with the name param', async () => {
    const trend: LabTrend = {
      test: 'Glucose',
      unit: 'mg/dL',
      series: [{ date: '2026-01-01T00:00:00Z', value: '95', status: 'normal', reference_range: '70-99', document_id: 1, document_filename: 'a.pdf' }],
    }
    const spy = vi.spyOn(apiClient, 'get').mockResolvedValue(axiosLike(trend) as never)

    const result = await healthApi.getLabTrend('Glucose')

    expect(spy).toHaveBeenCalledWith('/labs/trends', { params: { name: 'Glucose' } })
    expect(result.series[0].value).toBe('95')
  })

  it('fetches the timeline', async () => {
    const events: TimelineEvent[] = [
      { type: 'lab_result', occurred_at: '2026-01-01T00:00:00Z', title: 'Glucose recorded', description: '95 mg/dL', document_id: 1 },
    ]
    const spy = vi.spyOn(apiClient, 'get').mockResolvedValue(axiosLike({ data: events }) as never)

    const result = await healthApi.getTimeline()

    expect(spy).toHaveBeenCalledWith('/health/timeline')
    expect(result[0].title).toBe('Glucose recorded')
  })

  it('fetches the aggregated health record', async () => {
    const record: HealthRecord = {
      profile: { name: 'Ada', email: 'ada@example.com', date_of_birth: null, gender: null },
      labs: [{ name: 'Glucose', value: '95', unit: 'mg/dL', status: 'normal', reference_range: '70-99', last_collected_at: '2026-01-01T00:00:00Z' }],
      medications: [],
      timeline: [],
    }
    const spy = vi.spyOn(apiClient, 'get').mockResolvedValue(axiosLike({ data: record }) as never)

    const result = await healthApi.getHealthRecord()

    expect(spy).toHaveBeenCalledWith('/health/record')
    expect(result.profile.name).toBe('Ada')
    expect(result.labs[0].value).toBe('95')
  })
})