import type { HealthRecord, LabTestName, LabTrend, TimelineEvent } from '@/types'

import { apiClient } from './client'

export async function getLabTestNames(): Promise<LabTestName[]> {
  const { data } = await apiClient.get<{ data: LabTestName[] }>('/labs/names')
  return data.data
}

export async function getLabTrend(name: string): Promise<LabTrend> {
  const { data } = await apiClient.get<LabTrend>('/labs/trends', { params: { name } })
  return data
}

export async function getTimeline(): Promise<TimelineEvent[]> {
  const { data } = await apiClient.get<{ data: TimelineEvent[] }>('/health/timeline')
  return data.data
}

export async function getHealthRecord(): Promise<HealthRecord> {
  const { data } = await apiClient.get<{ data: HealthRecord }>('/health/record')
  return data.data
}