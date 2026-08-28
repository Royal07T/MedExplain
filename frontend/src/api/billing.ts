import { apiClient } from './client'

export interface Invoice {
  id: number
  invoice_number: string
  description: string
  amount: number
  paid_amount: number
  status: 'pending' | 'partial' | 'paid' | 'overdue' | 'cancelled'
  payment_method: string | null
  issued_at: string
  due_at: string
  paid_at: string | null
  notes: string | null
}

export interface PaymentRequest {
  paid_amount: number
  payment_method: 'cash' | 'credit_card' | 'transfer'
}

export async function getMyInvoices(): Promise<Invoice[]> {
  const { data } = await apiClient.get<{ success: boolean; data: Invoice[] }>('/patient/billing')
  return data.data
}

export async function makePayment(invoiceId: number, request: PaymentRequest): Promise<Invoice> {
  const { data } = await apiClient.post<{ success: boolean; data: Invoice }>(`/patient/billing/${invoiceId}/pay`, request)
  return data.data
}
