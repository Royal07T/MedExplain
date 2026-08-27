import { computed } from 'vue'
import { useAuth } from './useAuth'

export function useRoutePrefix() {
  const { user } = useAuth()

  const prefix = computed(() => {
    if (!user.value) return ''
    const role = user.value.role
    switch (role) {
      case 'patient':
        return 'patient'
      case 'clinician':
        return 'clinician'
      case 'nursing_staff':
        return 'nursing'
      case 'admin':
        return 'admin'
      case 'super_admin':
        return 'superadmin'
      default:
        return ''
    }
  })

  const routeName = (name: string) => {
    if (!prefix.value) return name
    return `${prefix.value}.${name}`
  }

  return { prefix, routeName }
}
