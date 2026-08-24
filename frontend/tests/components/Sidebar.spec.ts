import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'

vi.mock('@/api/auth', () => ({
  logout: vi.fn(),
}))

import { useAuthStore } from '@/stores/auth'
import NavbarMenu from '@/components/NavbarMenu.vue'
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

function mountNavbarMenu(role: 'patient' | 'clinician', plan: 'free' | 'pro' = 'free') {
  const pinia = createPinia()
  const auth = useAuthStore(pinia)
  auth.setAuth('token', makeUser(role, plan))

  return mount(NavbarMenu, {
    global: {
      plugins: [pinia, buildRouter()],
      stubs: { RouterLink: RouterLinkStub },
      components: { teleport: { template: '<div><slot /></div>' } },
    },
  })
}

describe('NavbarMenu', () => {
  it('renders grouped navigation sections', () => {
    const wrapper = mountNavbarMenu('patient')

    for (const label of ['Overview', 'Reports & Labs', 'Health', 'AI Assistant', 'Account']) {
      expect(wrapper.text()).toContain(label)
    }
    for (const item of ['Dashboard', 'Reports', 'Trends', 'Timeline', 'Health Record', 'Medications', 'Assistant', 'Profile', 'Settings', 'Connected Apps']) {
      expect(wrapper.text()).toContain(item)
    }
  })

  it('hides the clinician portal for patients', () => {
    const wrapper = mountNavbarMenu('patient')
    expect(wrapper.text()).not.toContain('Care Team')
  })

  it('shows the clinician portal for clinicians', () => {
    const wrapper = mountNavbarMenu('clinician')
    expect(wrapper.text()).toContain('Care Team')
    expect(wrapper.text()).toContain('Clinician Portal')
  })

  it('shows the signed-in user', () => {
    const wrapper = mountNavbarMenu('patient')
    expect(wrapper.text()).toContain('Ada Lovelace')
    expect(wrapper.text()).toContain('ada@example.com')
  })

  it('shows the free plan for a free user', () => {
    const wrapper = mountNavbarMenu('patient', 'free')
    expect(wrapper.text()).toContain('Free plan')
  })

  it('shows the pro plan for a subscribed user', () => {
    const wrapper = mountNavbarMenu('patient', 'pro')
    expect(wrapper.text()).toContain('Pro plan')
  })

  it('shows logout button', () => {
    const wrapper = mountNavbarMenu('patient')
    const logoutButton = wrapper.findAll('button').find(btn => btn.text().includes('Log out'))
    expect(logoutButton).toBeDefined()
  })
})