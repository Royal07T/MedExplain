import { defineStore } from 'pinia'

import * as notificationsApi from '@/api/notifications'
import type { AppNotification } from '@/types'

export const useNotificationsStore = defineStore('notifications', {
  state: () => ({
    items: [] as AppNotification[],
    unreadCount: 0,
    loading: false,
  }),

  actions: {
    async fetchNotifications() {
      this.loading = true
      try {
        const { data, unread_count } = await notificationsApi.fetchNotifications()
        this.items = data
        this.unreadCount = unread_count
      } finally {
        this.loading = false
      }
    },

    async refreshUnreadCount() {
      this.unreadCount = await notificationsApi.fetchUnreadCount()
    },

    async markRead(id: number) {
      await notificationsApi.markNotificationRead(id)
      const item = this.items.find((n) => n.id === id)
      if (item) item.read_at = new Date().toISOString()
      this.unreadCount = Math.max(0, this.unreadCount - 1)
    },

    async markAllRead() {
      await notificationsApi.markAllNotificationsRead()
      for (const n of this.items) n.read_at = new Date().toISOString()
      this.unreadCount = 0
    },
  },
})