import { describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'

vi.mock('@/api/assistant', () => ({
  sendChatMessage: vi.fn(),
}))

import { sendChatMessage } from '@/api/assistant'
import Assistant from '@/views/Assistant.vue'
import type { HealthQueryAnswer } from '@/types'

const answer: HealthQueryAnswer = {
  summary: 'Your glucose rose between the two reports.',
  facts: ['Your glucose changed from 90 to 110 mg/dL.'],
  changes: ['Glucose increased by 22%.'],
  context: [{ text: 'Glucose is your blood sugar.', category: 'education' }],
  educational_explanation: ['Glucose is measured in mg/dL.'],
  questions_for_professional: ['Should I retest my glucose?'],
  sources: ['Understanding glucose'],
  disclaimer: 'Educational only. Not a diagnosis.',
  data_used: [{ type: 'report', label: 'Report from 2026-08-20', reference: '1' }],
}

describe('Assistant', () => {
  it('sends a message and renders the structured answer', async () => {
    vi.mocked(sendChatMessage).mockResolvedValue(answer)

    const wrapper = mount(Assistant)
    await wrapper.find('input').setValue('What changed between my last two reports?')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(sendChatMessage).toHaveBeenCalledWith('What changed between my last two reports?')
    expect(wrapper.text()).toContain('Your glucose rose between the two reports.')
    expect(wrapper.text()).toContain('Your glucose changed from 90 to 110 mg/dL.')
    expect(wrapper.text()).toContain('Glucose increased by 22%.')
    expect(wrapper.text()).toContain('Education')
    expect(wrapper.text()).toContain('Glucose is your blood sugar.')
    expect(wrapper.text()).toContain('Should I retest my glucose?')
    expect(wrapper.text()).toContain('Understanding glucose')
    expect(wrapper.text()).toContain('Report from 2026-08-20')
    expect(wrapper.text()).toContain('Educational only. Not a diagnosis.')
  })

  it('can ask via a suggested question', async () => {
    vi.mocked(sendChatMessage).mockResolvedValue(answer)

    const wrapper = mount(Assistant)
    const suggestions = wrapper.findAll('button[type="button"]')
    await suggestions[0].trigger('click')
    await flushPromises()

    expect(sendChatMessage).toHaveBeenCalledWith('What changed between my last two reports?')
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