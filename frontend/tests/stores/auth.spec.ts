import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'

vi.mock('@/api/auth', () => ({
  login: vi.fn(),
  register: vi.fn(),
  logout: vi.fn(),
  fetchCurrentUser: vi.fn(),
  updateProfile: vi.fn(),
  updateAvatar: vi.fn(),
  resendVerificationEmail: vi.fn(),
}))

import * as authApi from '@/api/auth'
import { useAuthStore } from '@/stores/auth'
import type { User } from '@/types'

const user: User = {
  id: 1,
  name: 'Ada Lovelace',
  email: 'ada@example.com',
  role: 'patient',
  plan: 'free',
  email_verified_at: null,
  created_at: '2026-01-01T00:00:00Z',
  profile: null,
}

describe('auth store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    localStorage.clear()
    vi.clearAllMocks()
  })

  it('starts unauthenticated when no token is stored', () => {
    const store = useAuthStore()
    expect(store.isAuthenticated).toBe(false)
  })

  it('login persists the token and user', async () => {
    vi.mocked(authApi.login).mockResolvedValue({ token: 'token-123', user })

    const store = useAuthStore()
    await store.login({ email: user.email, password: 'secret' })

    expect(store.isAuthenticated).toBe(true)
    expect(store.user?.name).toBe('Ada Lovelace')
    expect(localStorage.getItem('medexplain_token')).toBe('token-123')
  })

  it('register stores the returned token', async () => {
    vi.mocked(authApi.register).mockResolvedValue({ token: 'token-456', user })

    const store = useAuthStore()
    await store.register({
      name: user.name,
      first_name: 'Ada',
      last_name: 'Lovelace',
      email: user.email,
      password: 'secret',
      password_confirmation: 'secret',
    })

    expect(store.isAuthenticated).toBe(true)
    expect(localStorage.getItem('medexplain_token')).toBe('token-456')
  })

  it('logout clears auth even if the API call fails', async () => {
    vi.mocked(authApi.logout).mockRejectedValue(new Error('network down'))

    const store = useAuthStore()
    store.setAuth('token-123', user)
    await store.logout()

    expect(store.isAuthenticated).toBe(false)
    expect(store.user).toBeNull()
    expect(localStorage.getItem('medexplain_token')).toBeNull()
  })

  it('setAuth writes the token to localStorage', () => {
    const store = useAuthStore()
    store.setAuth('token-789', user)
    expect(localStorage.getItem('medexplain_token')).toBe('token-789')
  })

  it('updateProfile refreshes the stored user', async () => {
    vi.mocked(authApi.updateProfile).mockResolvedValue({ ...user, name: 'Grace Hopper' })

    const store = useAuthStore()
    store.setAuth('token-123', user)
    await store.updateProfile({
      name: 'Grace Hopper',
      first_name: 'Grace',
      last_name: null,
      date_of_birth: null,
      gender: null,
    })

    expect(store.user?.name).toBe('Grace Hopper')
    expect(authApi.updateProfile).toHaveBeenCalledWith({
      name: 'Grace Hopper',
      first_name: 'Grace',
      last_name: null,
      date_of_birth: null,
      gender: null,
    })
  })

  it('updateAvatar refreshes the stored user', async () => {
    vi.mocked(authApi.updateAvatar).mockResolvedValue({
      ...user,
      profile: { first_name: null, last_name: null, date_of_birth: null, gender: null, avatar_url: 'http://localhost/storage/avatars/a.png' },
    })

    const store = useAuthStore()
    store.setAuth('token-123', user)
    await store.updateAvatar(new File(['x'], 'a.png', { type: 'image/png' }))

    expect(store.user?.profile?.avatar_url).toBe('http://localhost/storage/avatars/a.png')
  })
})