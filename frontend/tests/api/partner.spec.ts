import { afterEach, describe, expect, it, vi } from 'vitest'

import * as partnerApi from '@/api/partner'
import { apiClient } from '@/api/client'
import type { PartnerConsent } from '@/types'

function axiosLike(data: unknown) {
  return { data, status: 200, statusText: 'OK', headers: {}, config: {} }
}

describe('partner api', () => {
  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('lists consents', async () => {
    const consents: PartnerConsent[] = [
      { partner_id: 1, partner_name: 'HealthApp', scopes: ['health_record:read'], granted_at: '2026-01-01T00:00:00Z', revoked_at: null },
    ]
    const spy = vi.spyOn(apiClient, 'get').mockResolvedValue(axiosLike({ data: consents }) as never)

    const result = await partnerApi.listConsents()

    expect(spy).toHaveBeenCalledWith('/partner/consents')
    expect(result[0].partner_name).toBe('HealthApp')
  })

  it('revokes a consent', async () => {
    const spy = vi.spyOn(apiClient, 'delete').mockResolvedValue(axiosLike(null) as never)

    await partnerApi.revokeConsent(1)

    expect(spy).toHaveBeenCalledWith('/partner/consents/1')
  })
})