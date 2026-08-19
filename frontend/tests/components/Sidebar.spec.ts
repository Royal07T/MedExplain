import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'

vi.mock('@/api/auth', () => ({
  logout: vi.fn(),
}))

import { useAuthStore } from '@/stores/auth'
import Sidebar from '@/components/Sidebar.vue'
import type { User } from '@/types'

const RouterLinkStub = {
  props: ['to'],
  template: '<a class="router-link"><slot /></a>',
}

function buildRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/', component: { template: '<div />' } }],
  })
}

function makeUser(role: 'patient' | 'clinician', plan: 'free' | 'pro' = 'free'): User {
  return {
    id: 1,
    name: 'Ada Lovelace',
    email: 'ada@example.com',
    role,
    plan,
    email_verified_at: null,
    created_at: '2026-01-01T00:00:00Z',
    profile: null,
  }
}

function mountSidebar(role: 'patient' | 'clinician', plan: 'free' | 'pro' = 'free') {
  const pinia = createPinia()
  const auth = useAuthStore(pinia)
  auth.setAuth('token', makeUser(role, plan))

  return mount(Sidebar, {
    global: {
      plugins: [pinia, buildRouter()],
      stubs: { RouterLink: RouterLinkStub },
    },
  })
}

describe('Sidebar', () => {
  it('renders grouped navigation sections', () => {
    const wrapper = mountSidebar('patient')

    for (const label of ['Overview', 'Reports & Labs', 'Health', 'AI Assistant', 'Account']) {
      expect(wrapper.text()).toContain(label)
    }
    for (const item of ['Dashboard', 'Reports', 'Trends', 'Timeline', 'Health record', 'Medications', 'Assistant', 'Profile', 'Settings', 'Connected apps']) {
      expect(wrapper.text()).toContain(item)
    }
  })

  it('hides the clinician portal for patients', () => {
    const wrapper = mountSidebar('patient')
    expect(wrapper.text()).not.toContain('Clinician portal')
  })

  it('shows the clinician portal for clinicians', () => {
    const wrapper = mountSidebar('clinician')
    expect(wrapper.text()).toContain('Clinician portal')
  })

  it('shows the signed-in user', () => {
    const wrapper = mountSidebar('patient')
    expect(wrapper.text()).toContain('Ada Lovelace')
    expect(wrapper.text()).toContain('ada@example.com')
    expect(wrapper.find('button[aria-label="Log out"]').exists()).toBe(false)
  })

  it('shows the free plan for a free user', () => {
    const wrapper = mountSidebar('patient', 'free')
    expect(wrapper.text()).toContain('Free plan')
  })

  it('shows the pro plan for a subscribed user', () => {
    const wrapper = mountSidebar('patient', 'pro')
    expect(wrapper.text()).toContain('Pro plan')
  })
})