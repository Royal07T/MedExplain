import { afterEach, describe, expect, it, vi } from 'vitest'

import * as planApi from '@/api/plan'
import { apiClient } from '@/api/client'

function axiosLike(data: unknown) {
  return { data, status: 200, statusText: 'OK', headers: {}, config: {} }
}

const user = {
  id: 1,
  name: 'Ada Lovelace',
  email: 'ada@example.com',
  role: 'patient',
  plan: 'pro',
  email_verified_at: null,
  created_at: '2026-01-01T00:00:00Z',
  profile: null,
}

describe('plan api', () => {
  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('fetches the current plan', async () => {
    const info = { plan: 'free', label: 'Free', is_pro: false }
    const spy = vi.spyOn(apiClient, 'get').mockResolvedValue(axiosLike({ data: info }) as never)

    const result = await planApi.getCurrentPlan()

    expect(spy).toHaveBeenCalledWith('/plan')
    expect(result.plan).toBe('free')
    expect(result.is_pro).toBe(false)
  })

  it('upgrades to pro', async () => {
    const spy = vi.spyOn(apiClient, 'post').mockResolvedValue(axiosLike({ user }) as never)

    const result = await planApi.upgradePlan()

    expect(spy).toHaveBeenCalledWith('/plan/upgrade')
    expect(result.plan).toBe('pro')
  })

  it('cancels the subscription', async () => {
    const spy = vi.spyOn(apiClient, 'post').mockResolvedValue(
      axiosLike({ user: { ...user, plan: 'free' } }) as never,
    )

    const result = await planApi.cancelPlan()

    expect(spy).toHaveBeenCalledWith('/plan/cancel')
    expect(result.plan).toBe('free')
  })
})