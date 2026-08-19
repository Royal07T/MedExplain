import { afterEach, describe, expect, it, vi } from 'vitest'

import * as assistantApi from '@/api/assistant'
import { apiClient } from '@/api/client'
import type { AssistantReply } from '@/types'

function axiosLike(data: unknown) {
  return { data, status: 200, statusText: 'OK', headers: {}, config: {} }
}

describe('assistant api', () => {
  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('sends a chat message', async () => {
    const reply: AssistantReply = {
      reply: 'Educational answer.',
      disclaimer: 'Not a diagnosis.',
      sources: ['Understanding your cholesterol panel'],
    }
    const spy = vi.spyOn(apiClient, 'post').mockResolvedValue(axiosLike(reply) as never)

    const result = await assistantApi.sendChatMessage('What is cholesterol?')

    expect(spy).toHaveBeenCalledWith('/assistant/chat', { message: 'What is cholesterol?' })
    expect(result.reply).toBe('Educational answer.')
  })
})