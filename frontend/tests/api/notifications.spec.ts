import { afterEach, describe, expect, it, vi } from 'vitest'

import * as notificationsApi from '@/api/notifications'
import { apiClient } from '@/api/client'

function axiosLike(data: unknown) {
  return { data, status: 200, statusText: 'OK', headers: {}, config: {} }
}

const notification = {
  id: 1,
  title: 'Analysis ready',
  body: 'The analysis for "report.pdf" is ready to view.',
  type: 'analysis',
  data: { document_id: 7 },
  read_at: null,
  created_at: '2026-08-19T20:00:00Z',
}

describe('notifications api', () => {
  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('fetches notifications with the unread count', async () => {
    const payload = { data: [notification], unread_count: 1 }
    const spy = vi.spyOn(apiClient, 'get').mockResolvedValue(axiosLike(payload) as never)

    const result = await notificationsApi.fetchNotifications()

    expect(spy).toHaveBeenCalledWith('/notifications')
    expect(result.data).toHaveLength(1)
    expect(result.unread_count).toBe(1)
    expect(result.data[0].title).toBe('Analysis ready')
  })

  it('fetches the unread count', async () => {
    const spy = vi.spyOn(apiClient, 'get').mockResolvedValue(axiosLike({ unread_count: 3 }) as never)

    const result = await notificationsApi.fetchUnreadCount()

    expect(spy).toHaveBeenCalledWith('/notifications/unread-count')
    expect(result).toBe(3)
  })

  it('marks a single notification as read', async () => {
    const spy = vi.spyOn(apiClient, 'post').mockResolvedValue(
      axiosLike({ ...notification, read_at: '2026-08-19T20:05:00Z' }) as never,
    )

    const result = await notificationsApi.markNotificationRead(1)

    expect(spy).toHaveBeenCalledWith('/notifications/1/read')
    expect(result.read_at).not.toBeNull()
  })

  it('marks all notifications as read', async () => {
    const spy = vi.spyOn(apiClient, 'post').mockResolvedValue(axiosLike({ unread_count: 0 }) as never)

    await notificationsApi.markAllNotificationsRead()

    expect(spy).toHaveBeenCalledWith('/notifications/read-all')
  })
})