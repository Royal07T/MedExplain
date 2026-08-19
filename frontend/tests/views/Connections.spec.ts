import { describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'

vi.mock('@/api/partner', () => ({
  listConsents: vi.fn(),
  revokeConsent: vi.fn(),
}))

import * as partnerApi from '@/api/partner'
import Connections from '@/views/Connections.vue'
import type { PartnerConsent } from '@/types'

describe('Connections', () => {
  it('lists active consents', async () => {
    const consents: PartnerConsent[] = [
      { partner_id: 1, partner_name: 'HealthApp', scopes: ['health_record:read'], granted_at: '2026-01-01T00:00:00Z', revoked_at: null },
    ]
    vi.mocked(partnerApi.listConsents).mockResolvedValue(consents)

    const wrapper = mount(Connections)
    await flushPromises()

    expect(wrapper.text()).toContain('HealthApp')
    expect(wrapper.text()).toContain('Read your health record')
  })

  it('revokes a consent and marks it revoked', async () => {
    const consents: PartnerConsent[] = [
      { partner_id: 1, partner_name: 'HealthApp', scopes: ['health_record:read'], granted_at: '2026-01-01T00:00:00Z', revoked_at: null },
    ]
    vi.mocked(partnerApi.listConsents).mockResolvedValue(consents)
    vi.mocked(partnerApi.revokeConsent).mockResolvedValue()

    const wrapper = mount(Connections)
    await flushPromises()

    await wrapper.find('button').trigger('click')
    await flushPromises()

    expect(partnerApi.revokeConsent).toHaveBeenCalledWith(1)
    expect(wrapper.text()).toContain('Revoked')
  })

  it('shows an empty state', async () => {
    vi.mocked(partnerApi.listConsents).mockResolvedValue([])

    const wrapper = mount(Connections)
    await flushPromises()

    expect(wrapper.text()).toContain('No connected apps')
  })
})