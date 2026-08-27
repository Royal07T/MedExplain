import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia, type Pinia } from 'pinia'

vi.mock('@/api/auth', () => ({
  login: vi.fn(),
  register: vi.fn(),
  logout: vi.fn(),
  fetchCurrentUser: vi.fn(),
  updateProfile: vi.fn(),
  updateAvatar: vi.fn(),
  resendVerificationEmail: vi.fn(),
}))

vi.mock('@/api/notifications', () => ({
  fetchNotifications: vi.fn().mockResolvedValue({ data: [], unread_count: 0 }),
  fetchUnreadCount: vi.fn().mockResolvedValue(0),
  markNotificationRead: vi.fn().mockResolvedValue({}),
  markAllNotificationsRead: vi.fn().mockResolvedValue(undefined),
}))

vi.mock('@/api/documents', () => ({
  listDocuments: vi.fn().mockResolvedValue({
    data: [],
    links: {},
    meta: { current_page: 1, last_page: 1, per_page: 10, total: 0 },
  }),
  getDocument: vi.fn(),
  uploadDocument: vi.fn(),
  deleteDocument: vi.fn(),
}))

import * as authApi from '@/api/auth'
import App from '@/App.vue'
import router from '@/router'
import type { User } from '@/types'

const user: User = {
  id: 1,
  name: 'Ada Lovelace',
  email: 'ada@example.com',
  role: 'patient',
  plan: 'free',
  organization_id: null,
  permissions: [],
  email_verified_at: '2026-01-01T00:00:00Z',
  created_at: '2026-01-01T00:00:00Z',
  profile: null,
}

async function waitForRoute(name: string, timeoutMs = 3000) {
  const start = Date.now()
  while (router.currentRoute.value.name !== name) {
    if (Date.now() - start > timeoutMs) {
      throw new Error(`Router did not reach "${name}"; stuck on "${String(router.currentRoute.value.name)}"`)
    }
    await new Promise((resolve) => setTimeout(resolve, 5))
    await flushPromises()
  }
  await flushPromises()
}

describe('login flow', () => {
  let pinia: Pinia
  const restoreFns: Array<() => void> = []

  beforeEach(async () => {
    pinia = createPinia()
    setActivePinia(pinia)
    localStorage.clear()
    vi.clearAllMocks()
    await router.push('/login?redirect=/dashboard')
  })

  afterEach(() => {
    while (restoreFns.length) restoreFns.pop()!()
  })

  it('renders the dashboard after login instead of a blank page', async () => {
    vi.mocked(authApi.login).mockResolvedValue({ token: 'test-token', user })

    const originalPush = router.push.bind(router)
    const pushSpy = vi.fn(originalPush)
    router.push = pushSpy as typeof router.push
    restoreFns.push(() => {
      router.push = originalPush
    })

    const wrapper = mount(App, {
      global: {
        plugins: [pinia, router],
        stubs: { RouterLink: false, RouterView: false },
      },
    })
    await flushPromises()
    expect(router.currentRoute.value.name).toBe('login')

    await wrapper.find('input[type="email"]').setValue(user.email)
    await wrapper.find('input[type="password"]').setValue('secret-password')
    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    await waitForRoute('dashboard')
    const navigationFailure = await pushSpy.mock.results.at(-1)?.value
    expect(navigationFailure).toBeFalsy()

    expect(wrapper.text()).toContain('Total Reports')
    expect(wrapper.text()).toContain('Quick Actions')
    wrapper.unmount()
  })

  it('redirects authenticated users away from the login page to the dashboard', async () => {
    vi.mocked(authApi.fetchCurrentUser).mockResolvedValue(user)
    localStorage.setItem('medexplain_token', 'test-token')

    // A fresh pinia so the auth store re-reads the stored token.
    setActivePinia(createPinia())

    const navigation = await router.push('/login')

    expect(navigation).toBeFalsy()
    expect(router.currentRoute.value.name).toBe('dashboard')
  })
})
