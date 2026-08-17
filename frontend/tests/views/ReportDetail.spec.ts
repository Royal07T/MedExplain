import { describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'

vi.mock('@/api/documents', () => ({
  getDocument: vi.fn(),
  getAnalysis: vi.fn(),
  listDocuments: vi.fn(),
  uploadDocument: vi.fn(),
  deleteDocument: vi.fn(),
}))

import * as documentsApi from '@/api/documents'
import ReportDetail from '@/views/ReportDetail.vue'
import type { Analysis, Document } from '@/types'

const processedDocument: Document = {
  id: 1,
  original_filename: 'lab.pdf',
  document_type: 'lab_report',
  file_size: 2048,
  status: 'processed',
  extraction_method: 'text',
  error_message: null,
  created_at: '2026-01-01T00:00:00Z',
}

const analysis: Analysis = {
  id: 1,
  status: 'completed',
  summary: 'Educational summary of your report.',
  disclaimer: 'Not a diagnosis.',
  error_message: null,
  concerns: ['Hemoglobin is outside the reference range.'],
  items: [
    {
      test_name: 'Hemoglobin',
      category: 'reference_comparison',
      explanation: 'Your hemoglobin is lower than the reference range.',
    },
  ],
  lab_results: [
    {
      name: 'Hemoglobin',
      value: '12.1',
      unit: 'g/dL',
      reference_range: '13.0–17.0',
      status: 'low',
    },
  ],
  processed_at: '2026-01-01T00:01:00Z',
  created_at: '2026-01-01T00:00:00Z',
}

async function mountReportDetail() {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/reports/:id', name: 'reports.detail', component: ReportDetail }],
  })
  await router.push('/reports/1')
  await router.isReady()

  const wrapper = mount(ReportDetail, { global: { plugins: [router] } })
  await flushPromises()
  return wrapper
}

describe('ReportDetail', () => {
  it('shows the document and analysis once processed', async () => {
    vi.mocked(documentsApi.getDocument).mockResolvedValue(processedDocument)
    vi.mocked(documentsApi.getAnalysis).mockResolvedValue(analysis)

    const wrapper = await mountReportDetail()

    expect(wrapper.text()).toContain('lab.pdf')
    expect(wrapper.text()).toContain('Educational summary of your report.')
    expect(wrapper.text()).toContain('Hemoglobin')
    expect(wrapper.text()).toContain('low')
    expect(wrapper.text()).toContain('Not a diagnosis.')
    expect(documentsApi.getAnalysis).toHaveBeenCalledWith(1)
  })

  it('surfaces a load error when the document cannot be fetched', async () => {
    vi.mocked(documentsApi.getDocument).mockRejectedValue(new Error('boom'))

    const wrapper = await mountReportDetail()

    expect(wrapper.text()).toContain('Unable to load this report.')
  })
})