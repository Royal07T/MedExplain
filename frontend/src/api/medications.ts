import type { Medication } from '@/types'

import { apiClient } from './client'

export async function listMedications(): Promise<Medication[]> {
  const { data } = await apiClient.get<{ data: Medication[] }>('/medications')
  return data.data
}