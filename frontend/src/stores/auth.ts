import { defineStore } from 'pinia'

import type {
  RegisterPayload,
  LoginCredentials,
  UpdateProfilePayload,
} from '@/api/auth'
import * as authApi from '@/api/auth'
import { TOKEN_KEY } from '@/api/client'
import type { User } from '@/types'

export interface AuthState {
  token: string | null
  user: User | null
  loading: boolean
}

export const useAuthStore = defineStore('auth', {
  state: (): AuthState => ({
    token: localStorage.getItem(TOKEN_KEY),
    user: null,
    loading: false,
  }),

  getters: {
    isAuthenticated: (state) => state.token !== null,
    isEmailVerified: (state) => state.user?.email_verified_at != null,
  },

  actions: {
    async login(credentials: LoginCredentials) {
      this.loading = true
      try {
        const { token, user } = await authApi.login(credentials)
        this.setAuth(token, user)
      } finally {
        this.loading = false
      }
    },

    async register(payload: RegisterPayload) {
      this.loading = true
      try {
        const { token, user } = await authApi.register(payload)
        this.setAuth(token, user)
      } finally {
        this.loading = false
      }
    },

    async fetchUser() {
      this.user = await authApi.fetchCurrentUser()
    },

    async updateProfile(payload: UpdateProfilePayload) {
      this.user = await authApi.updateProfile(payload)
    },

    async updateAvatar(file: File) {
      this.user = await authApi.updateAvatar(file)
    },

    async resendVerificationEmail() {
      await authApi.resendVerificationEmail()
    },

    async logout() {
      try {
        await authApi.logout()
      } catch {
        // Local auth is cleared regardless of server reachability.
      } finally {
        this.clearAuth()
      }
    },

    setAuth(token: string, user: User) {
      this.token = token
      this.user = user
      localStorage.setItem(TOKEN_KEY, token)
    },

    clearAuth() {
      this.token = null
      this.user = null
      localStorage.removeItem(TOKEN_KEY)
    },
  },
})