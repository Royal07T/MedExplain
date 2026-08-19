import type { PartnerConsent } from '@/types'

import { apiClient } from './client'

export async function listConsents(): Promise<PartnerConsent[]> {
  const { data } = await apiClient.get<{ data: PartnerConsent[] }>('/partner/consents')
  return data.data
}

export async function revokeConsent(partnerId: number): Promise<void> {
  await apiClient.delete(`/partner/consents/${partnerId}`)
}