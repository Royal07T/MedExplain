import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from '@/composables/useAuth'
import { apiClient } from '@/api/client'

export interface SearchResult {
  type: 'report' | 'medication' | 'healthRecord' | 'navigation'
  id: string
  title: string
  subtitle?: string
  route: { name: string; params?: Record<string, string> }
  icon: string
}

const icons = {
  report: 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z',
  medication: 'M12 4.5v15m7.5-7.5h-15',
  healthRecord: 'M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z',
  navigation: 'M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25',
}

let debounceTimer: ReturnType<typeof setTimeout> | undefined

export function useSearch() {
  const router = useRouter()
  const { isAuthenticated, user } = useAuth()

  const query = ref('')
  const results = ref<SearchResult[]>([])
  const loading = ref(false)
  const open = ref(false)
  const selectedIndex = ref(-1)

  const hasResults = computed(() => results.value.length > 0)

  const navigationItems = [
    { name: 'Dashboard', route: 'dashboard', keywords: ['dashboard', 'home', 'overview'] },
    { name: 'Reports', route: 'reports', keywords: ['reports', 'documents', 'labs', 'results'] },
    { name: 'Trends', route: 'trends', keywords: ['trends', 'charts', 'analytics', 'graphs'] },
    { name: 'Timeline', route: 'timeline', keywords: ['timeline', 'history', 'chronology'] },
    { name: 'Health Record', route: 'healthRecord', keywords: ['health', 'record', 'profile', 'medical'] },
    { name: 'Medications', route: 'medications', keywords: ['medications', 'medicine', 'drugs', 'prescriptions'] },
    { name: 'AI Assistant', route: 'assistant', keywords: ['assistant', 'ai', 'chat', 'help', 'support'] },
    { name: 'Profile', route: 'profile', keywords: ['profile', 'account', 'settings', 'user'] },
    { name: 'Settings', route: 'settings', keywords: ['settings', 'preferences', 'config'] },
    { name: 'Connected Apps', route: 'connections', keywords: ['connections', 'apps', 'integrations', 'linked'] },
    { name: 'Clinician Portal', route: 'clinicianPortal', keywords: ['clinician', 'portal', 'care', 'team'], clinicianOnly: true },
  ]

  const isClinician = computed(() => user.value?.role === 'clinician')

  async function search(q: string) {
    if (!isAuthenticated.value) {
      results.value = []
      return
    }

    if (!q.trim()) {
      results.value = []
      return
    }

    loading.value = true
    try {
      const [reportsRes, medicationsRes, healthRecordsRes] = await Promise.allSettled([
        apiClient.get('/reports', { params: { search: q, limit: 5 } }),
        apiClient.get('/medications', { params: { search: q, limit: 5 } }),
        apiClient.get('/health-record', { params: { search: q, limit: 5 } }),
      ])

      const newResults: SearchResult[] = []

      // Add navigation matches
      const lowerQuery = q.toLowerCase()
      for (const item of navigationItems) {
        if (item.clinicianOnly && !isClinician.value) continue
        
        const matchesName = item.name.toLowerCase().includes(lowerQuery)
        const matchesKeyword = item.keywords.some(k => k.toLowerCase().includes(lowerQuery))
        
        if (matchesName || matchesKeyword) {
          newResults.push({
            type: 'navigation',
            id: item.route,
            title: item.name,
            subtitle: 'Navigation',
            route: { name: item.route },
            icon: icons.navigation,
          })
        }
      }

      if (reportsRes.status === 'fulfilled' && reportsRes.value.data?.data) {
        for (const report of reportsRes.value.data.data) {
          newResults.push({
            type: 'report',
            id: report.id,
            title: report.title || 'Untitled Report',
            subtitle: report.created_at ? new Date(report.created_at).toLocaleDateString() : undefined,
            route: { name: 'reports.detail', params: { id: report.id } },
            icon: icons.report,
          })
        }
      }

      if (medicationsRes.status === 'fulfilled' && medicationsRes.value.data?.data) {
        for (const med of medicationsRes.value.data.data) {
          newResults.push({
            type: 'medication',
            id: med.id,
            title: med.name,
            subtitle: med.dosage,
            route: { name: 'medications' },
            icon: icons.medication,
          })
        }
      }

      if (healthRecordsRes.status === 'fulfilled' && healthRecordsRes.value.data?.data) {
        for (const record of healthRecordsRes.value.data.data) {
          newResults.push({
            type: 'healthRecord',
            id: record.id,
            title: record.title || 'Health Record Entry',
            subtitle: record.recorded_at ? new Date(record.recorded_at).toLocaleDateString() : undefined,
            route: { name: 'healthRecord' },
            icon: icons.healthRecord,
          })
        }
      }

      results.value = newResults.slice(0, 10)
      selectedIndex.value = -1
    } catch (error) {
      console.error('Search error:', error)
      results.value = []
    } finally {
      loading.value = false
    }
  }

  function debouncedSearch(q: string) {
    if (debounceTimer) clearTimeout(debounceTimer)
    debounceTimer = setTimeout(() => {
      search(q)
    }, 200)
  }

  function handleInput(value: string) {
    query.value = value
    open.value = true
    debouncedSearch(value)
  }

  function selectResult(result: SearchResult) {
    router.push(result.route)
    close()
  }

  function handleKeydown(e: KeyboardEvent) {
    if (!open.value || !hasResults.value) return

    if (e.key === 'ArrowDown') {
      e.preventDefault()
      selectedIndex.value = (selectedIndex.value + 1) % results.value.length
    } else if (e.key === 'ArrowUp') {
      e.preventDefault()
      selectedIndex.value = (selectedIndex.value - 1 + results.value.length) % results.value.length
    } else if (e.key === 'Enter' && selectedIndex.value >= 0) {
      e.preventDefault()
      selectResult(results.value[selectedIndex.value])
    } else if (e.key === 'Escape') {
      close()
    }
  }

  function close() {
    open.value = false
    results.value = []
    selectedIndex.value = -1
  }

  function handleFocus() {
    if (query.value.trim()) {
      open.value = true
    }
  }

  function handleBlur() {
    setTimeout(() => {
      close()
    }, 200)
  }

  return {
    query,
    results,
    loading,
    open,
    selectedIndex,
    hasResults,
    handleInput,
    handleFocus,
    handleBlur,
    handleKeydown,
    selectResult,
    close,
  }
}