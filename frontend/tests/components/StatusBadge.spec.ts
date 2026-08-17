import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'

import StatusBadge from '@/components/StatusBadge.vue'

describe('StatusBadge', () => {
  it('renders a processed label', () => {
    const wrapper = mount(StatusBadge, { props: { status: 'processed' } })
    expect(wrapper.text()).toContain('Processed')
  })

  it('renders a failed label', () => {
    const wrapper = mount(StatusBadge, { props: { status: 'failed' } })
    expect(wrapper.text()).toContain('Failed')
  })

  it('renders a processing label', () => {
    const wrapper = mount(StatusBadge, { props: { status: 'processing' } })
    expect(wrapper.text()).toContain('Processing')
  })
})