<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { getMyInvoices, makePayment, type Invoice, type PaymentRequest } from '@/api/billing'

const invoices = ref<Invoice[]>([])
const loading = ref(false)
const error = ref<string | null>(null)

// Payment form state
const showPaymentForm = ref(false)
const selectedInvoice = ref<Invoice | null>(null)
const paymentForm = ref({
  paid_amount: 0,
  payment_method: 'credit_card' as PaymentRequest['payment_method'],
})
const submitting = ref(false)

// Load invoices on mount
async function loadInvoices() {
  loading.value = true
  error.value = null
  try {
    invoices.value = await getMyInvoices()
  } catch {
    error.value = 'Failed to load invoices'
  } finally {
    loading.value = false
  }
}

// Open payment form
function openPaymentForm(invoice: Invoice) {
  selectedInvoice.value = invoice
  paymentForm.value = {
    paid_amount: invoice.amount - invoice.paid_amount,
    payment_method: 'credit_card',
  }
  showPaymentForm.value = true
}

// Submit payment
async function submitPayment() {
  if (!selectedInvoice.value || submitting.value) return

  submitting.value = true
  error.value = null

  try {
    await makePayment(selectedInvoice.value.id, paymentForm.value)
    
    showPaymentForm.value = false
    selectedInvoice.value = null
    paymentForm.value = {
      paid_amount: 0,
      payment_method: 'credit_card',
    }
    
    await loadInvoices()
    alert('Payment recorded successfully!')
  } catch (err: any) {
    error.value = err?.response?.data?.message || 'Failed to process payment'
  } finally {
    submitting.value = false
  }
}

// Format date for display
function formatDate(dateString: string | null): string {
  if (!dateString) return '—'
  return new Date(dateString).toLocaleDateString()
}

// Format currency
function formatCurrency(amount: number): string {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
  }).format(amount)
}

// Get status color
function getStatusColor(status: Invoice['status']): string {
  switch (status) {
    case 'pending': return 'bg-yellow-100 text-yellow-700'
    case 'partial': return 'bg-blue-100 text-blue-700'
    case 'paid': return 'bg-green-100 text-green-700'
    case 'overdue': return 'bg-red-100 text-red-700'
    case 'cancelled': return 'bg-gray-100 text-gray-700'
    default: return 'bg-gray-100 text-gray-700'
  }
}

// Initialize
loadInvoices()
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-slate-900">Billing & Payments</h1>
      <p class="mt-1 text-sm text-slate-500">
        View and pay your medical invoices.
      </p>
    </div>

    <div v-if="error" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
      {{ error }}
    </div>

    <!-- Payment Form Modal -->
    <div v-if="showPaymentForm && selectedInvoice" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-lg">
        <div class="mb-4 flex items-center justify-between">
          <h2 class="text-lg font-semibold text-slate-900">Make Payment</h2>
          <button
            @click="showPaymentForm = false"
            class="text-slate-400 hover:text-slate-600"
          >
            ✕
          </button>
        </div>

        <div class="mb-4 rounded-lg bg-slate-50 p-4">
          <p class="text-sm text-slate-600">Invoice: {{ selectedInvoice.invoice_number }}</p>
          <p class="text-sm text-slate-600">Description: {{ selectedInvoice.description }}</p>
          <p class="mt-2 text-lg font-semibold text-slate-900">
            Due: {{ formatCurrency(selectedInvoice.amount - selectedInvoice.paid_amount) }}
          </p>
        </div>

        <form @submit.prevent="submitPayment" class="space-y-4">
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">
              Payment Amount
            </label>
            <input
              v-model.number="paymentForm.paid_amount"
              type="number"
              step="0.01"
              :max="selectedInvoice.amount - selectedInvoice.paid_amount"
              required
              class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none"
            />
          </div>

          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">
              Payment Method
            </label>
            <select
              v-model="paymentForm.payment_method"
              class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none"
            >
              <option value="credit_card">Credit Card</option>
              <option value="cash">Cash</option>
              <option value="transfer">Bank Transfer</option>
            </select>
          </div>

          <div class="flex gap-3">
            <button
              type="button"
              @click="showPaymentForm = false"
              class="flex-1 rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50"
            >
              Cancel
            </button>
            <button
              type="submit"
              class="flex-1 rounded-lg bg-teal-600 px-5 py-2.5 text-sm font-medium text-white transition-colors hover:bg-teal-700 disabled:cursor-not-allowed disabled:opacity-50"
              :disabled="submitting"
            >
              {{ submitting ? 'Processing…' : 'Pay Now' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Invoices List -->
    <div v-if="loading" class="rounded-xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500">
      Loading invoices…
    </div>
    
    <div v-else-if="invoices.length === 0" class="rounded-xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500">
      <p class="text-base font-medium text-slate-700">No invoices yet</p>
      <p class="mt-1">Your invoices will appear here when they are generated.</p>
    </div>
    
    <div v-else class="space-y-3">
      <div
        v-for="invoice in invoices"
        :key="invoice.id"
        class="rounded-lg border border-slate-200 p-4"
      >
        <div class="flex items-start justify-between gap-4">
          <div class="flex-1">
            <div class="flex items-center gap-2 mb-2">
              <span
                class="rounded-full px-2.5 py-1 text-xs font-medium"
                :class="getStatusColor(invoice.status)"
              >
                {{ invoice.status.toUpperCase() }}
              </span>
              <span class="text-xs text-slate-500">{{ invoice.invoice_number }}</span>
            </div>
            
            <p class="text-sm font-medium text-slate-800">
              {{ invoice.description }}
            </p>
            
            <div class="mt-2 flex items-center gap-4 text-sm text-slate-600">
              <p>Total: {{ formatCurrency(invoice.amount) }}</p>
              <p>Paid: {{ formatCurrency(invoice.paid_amount) }}</p>
              <p v-if="invoice.amount - invoice.paid_amount > 0" class="font-medium text-red-600">
                Remaining: {{ formatCurrency(invoice.amount - invoice.paid_amount) }}
              </p>
            </div>
            
            <p class="mt-1 text-xs text-slate-400">
              Due: {{ formatDate(invoice.due_at) }}
            </p>
            <p v-if="invoice.paid_at" class="text-xs text-slate-400">
              Paid: {{ formatDate(invoice.paid_at) }}
            </p>
          </div>
          
          <button
            v-if="invoice.status !== 'paid' && invoice.status !== 'cancelled'"
            @click="openPaymentForm(invoice)"
            class="rounded-lg border border-teal-300 px-3 py-1.5 text-xs font-medium text-teal-700 hover:bg-teal-50"
          >
            Pay Now
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
