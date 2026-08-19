import type { AppNotification } from '@/types'

import { apiClient } from './client'

export interface NotificationsResponse {
  data: AppNotification[]
  unread_count: number
}

export async function fetchNotifications(): Promise<NotificationsResponse> {
  const { data } = await apiClient.get<NotificationsResponse>('/notifications')
  return data
}

export async function fetchUnreadCount(): Promise<number> {
  const { data } = await apiClient.get<{ unread_count: number }>('/notifications/unread-count')
  return data.unread_count
}

export async function markNotificationRead(id: number): Promise<AppNotification> {
  const { data } = await apiClient.post<AppNotification>(`/notifications/${id}/read`)
  return data
}

export async function markAllNotificationsRead(): Promise<void> {
  await apiClient.post('/notifications/read-all')
}