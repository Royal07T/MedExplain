import type { AssistantReply } from '@/types'

import { apiClient } from './client'

export async function sendChatMessage(message: string): Promise<AssistantReply> {
  const { data } = await apiClient.post<AssistantReply>('/assistant/chat', { message })
  return data
}