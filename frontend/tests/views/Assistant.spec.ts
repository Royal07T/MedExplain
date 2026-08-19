import { describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'

vi.mock('@/api/assistant', () => ({
  sendChatMessage: vi.fn(),
}))

import { sendChatMessage } from '@/api/assistant'
import Assistant from '@/views/Assistant.vue'
import type { AssistantReply } from '@/types'

const reply: AssistantReply = {
  reply: 'Educational answer about glucose.',
  disclaimer: 'Not a diagnosis.',
  sources: ['Understanding your cholesterol panel'],
}

describe('Assistant', () => {
  it('sends a message and renders the reply', async () => {
    vi.mocked(sendChatMessage).mockResolvedValue(reply)

    const wrapper = mount(Assistant)
    await wrapper.find('input').setValue('What is glucose?')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(sendChatMessage).toHaveBeenCalledWith('What is glucose?')
    expect(wrapper.text()).toContain('Educational answer about glucose.')
    expect(wrapper.text()).toContain('Understanding your cholesterol panel')
  })

  it('surfaces a fallback message on failure', async () => {
    vi.mocked(sendChatMessage).mockRejectedValue(new Error('boom'))

    const wrapper = mount(Assistant)
    await wrapper.find('input').setValue('hello')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(wrapper.text()).toContain('could not answer just now')
  })
})