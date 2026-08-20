import type { HealthQueryAnswer } from '@/types'

import { apiClient } from './client'

export async function sendChatMessage(message: string): Promise<HealthQueryAnswer> {
  const { data } = await apiClient.post<{ answer: HealthQueryAnswer }>('/health/query', {
    question: message,
  })
  return data.answer
}