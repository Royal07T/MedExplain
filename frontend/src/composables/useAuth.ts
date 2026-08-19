import { computed } from 'vue'

import { useAuthStore } from '@/stores/auth'

export function useAuth() {
  const store = useAuthStore()
  const user = computed(() => store.user)
  const isAuthenticated = computed(() => store.isAuthenticated)
  const isEmailVerified = computed(() => store.isEmailVerified)
  const logout = () => store.logout()

  return { store, user, isAuthenticated, isEmailVerified, logout }
}