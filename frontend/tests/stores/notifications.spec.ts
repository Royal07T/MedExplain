import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'

vi.mock('@/api/notifications', () => ({
  fetchNotifications: vi.fn(),
  fetchUnreadCount: vi.fn(),
  markNotificationRead: vi.fn(),
  markAllNotificationsRead: vi.fn(),
}))

import * as notificationsApi from '@/api/notifications'
import { useNotificationsStore } from '@/stores/notifications'
import type { AppNotification } from '@/types'

const unread: AppNotification = {
  id: 1,
  title: 'Analysis ready',
  body: 'The analysis for "report.pdf" is ready to view.',
  type: 'analysis',
  data: { document_id: 7 },
  read_at: null,
  created_at: '2026-08-19T20:00:00Z',
}

const read: AppNotification = {
  id: 2,
  title: 'Document uploaded',
  body: null,
  type: 'document',
  data: { document_id: 8 },
  read_at: '2026-08-19T19:00:00Z',
  created_at: '2026-08-19T18:00:00Z',
}

describe('notifications store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('fetchNotifications loads items and the unread count', async () => {
    vi.mocked(notificationsApi.fetchNotifications).mockResolvedValue({
      data: [unread, read],
      unread_count: 1,
    })

    const store = useNotificationsStore()
    await store.fetchNotifications()

    expect(store.items).toHaveLength(2)
    expect(store.unreadCount).toBe(1)
  })

  it('refreshUnreadCount updates the badge count', async () => {
    vi.mocked(notificationsApi.fetchUnreadCount).mockResolvedValue(5)

    const store = useNotificationsStore()
    await store.refreshUnreadCount()

    expect(store.unreadCount).toBe(5)
  })

  it('markRead clears a single item and decrements the count', async () => {
    vi.mocked(notificationsApi.markNotificationRead).mockResolvedValue({
      ...unread,
      read_at: '2026-08-19T20:05:00Z',
    })

    const store = useNotificationsStore()
    store.items = [unread, read]
    store.unreadCount = 1

    await store.markRead(unread.id)

    expect(store.items[0].read_at).not.toBeNull()
    expect(store.unreadCount).toBe(0)
  })

  it('markAllRead clears every item and zeroes the count', async () => {
    vi.mocked(notificationsApi.markAllNotificationsRead).mockResolvedValue(undefined)

    const store = useNotificationsStore()
    store.items = [unread, read]
    store.unreadCount = 1

    await store.markAllRead()

    expect(store.items.every((n) => n.read_at !== null)).toBe(true)
    expect(store.unreadCount).toBe(0)
  })
})