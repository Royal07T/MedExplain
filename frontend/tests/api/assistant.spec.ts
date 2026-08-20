import { afterEach, describe, expect, it, vi } from 'vitest'

import * as assistantApi from '@/api/assistant'
import { apiClient } from '@/api/client'
import type { HealthQueryAnswer } from '@/types'

function axiosLike(data: unknown) {
  return { data, status: 200, statusText: 'OK', headers: {}, config: {} }
}

describe('assistant api', () => {
  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('posts a chat message through the health query pipeline', async () => {
    const answer: HealthQueryAnswer = {
      summary: 'Educational answer.',
      facts: [],
      changes: [],
      context: [],
      educational_explanation: [],
      questions_for_professional: [],
      sources: ['Understanding your cholesterol panel'],
      disclaimer: 'Not a diagnosis.',
      data_used: [],
    }
    const spy = vi
      .spyOn(apiClient, 'post')
      .mockResolvedValue(axiosLike({ query_id: 'q-1', intent: 'GENERAL_HEALTH_QUESTION', answer }) as never)

    const result = await assistantApi.sendChatMessage('What is cholesterol?')

    expect(spy).toHaveBeenCalledWith('/health/query', { question: 'What is cholesterol?' })
    expect(result.summary).toBe('Educational answer.')
  })
})