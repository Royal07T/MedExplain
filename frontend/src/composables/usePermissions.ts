import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import type { UserRole } from '@/types'

export function usePermissions() {
  const auth = useAuthStore()

  const user = computed(() => auth.user)
  const role = computed(() => auth.user?.role)
  const permissions = computed(() => auth.user?.permissions ?? [])

  // Role checks
  const isPatient = computed(() => role.value === 'patient')
  const isClinician = computed(() => role.value === 'clinician')
  const isNursingStaff = computed(() => role.value === 'nursing_staff')
  const isAdmin = computed(() => role.value === 'admin')
  const isSuperAdmin = computed(() => role.value === 'super_admin')

  const isHealthcareStaff = computed(() =>
    isClinician.value || isNursingStaff.value || isAdmin.value || isSuperAdmin.value,
  )

  // Permission checks
  function hasPermission(permission: string): boolean {
    return permissions.value.includes(permission)
  }

  function hasAnyPermission(perms: string[]): boolean {
    return perms.some((p) => permissions.value.includes(p))
  }

  function hasAllPermissions(perms: string[]): boolean {
    return perms.every((p) => permissions.value.includes(p))
  }

  // Workspace route mapping
  const workspaceRoute = computed(() => {
    switch (role.value) {
      case 'patient':
        return 'patient.dashboard'
      case 'clinician':
        return 'clinician.dashboard'
      case 'nursing_staff':
        return 'nursing.dashboard'
      case 'admin':
        return 'admin.dashboard'
      case 'super_admin':
        return 'superadmin.dashboard'
      default:
        return 'login'
    }
  })

  return {
    user,
    role,
    permissions,
    isPatient,
    isClinician,
    isNursingStaff,
    isAdmin,
    isSuperAdmin,
    isHealthcareStaff,
    hasPermission,
    hasAnyPermission,
    hasAllPermissions,
    workspaceRoute,
  }
}
