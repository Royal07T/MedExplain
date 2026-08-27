<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { fetchAdminBilling, type AdminBillingData } from '@/api/admin'

const data = ref<AdminBillingData | null>(null)
const loading = ref(true)
const error = ref('')

onMounted(async () => {
  await loadBilling()
})

async function loadBilling() {
  loading.value = true
  error.value = ''
  try {
    data.value = await fetchAdminBilling()
  } catch {
    error.value = 'Failed to load billing data'
  } finally {
    loading.value = false
  }
}

function formatCurrency(amount: number): string {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(amount)
}

function statusBadge(status: string): string {
  const map: Record<string, string> = {
    paid: 'bg-emerald-100 text-emerald-800',
    pending: 'bg-amber-100 text-amber-800',
    partial: 'bg-blue-100 text-blue-800',
    overdue: 'bg-red-100 text-red-800',
    cancelled: 'bg-slate-100 text-slate-800',
  }
  return map[status] || 'bg-slate-100 text-slate-800'
}

function formatDate(date: string | null): string {
  if (!date) return '—'
  return new Date(date).toLocaleDateString()
}
</script>

<template>
  <div class="space-y-6">
    <h1 class="text-2xl font-bold text-slate-900">Billing</h1>

    <div v-if="loading" class="text-center py-12 text-slate-500">Loading...</div>
    <div v-else-if="error" class="text-center py-12 text-red-500">{{ error }}</div>

    <template v-else-if="data">
      <!-- Summary -->
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-sm font-medium text-slate-500">Total Revenue</p>
          <p class="mt-2 text-3xl font-bold text-emerald-600">{{ formatCurrency(data.summary.total_revenue) }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-sm font-medium text-slate-500">Outstanding</p>
          <p class="mt-2 text-3xl font-bold text-amber-600">{{ formatCurrency(data.summary.outstanding) }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-sm font-medium text-slate-500">Pending Invoices</p>
          <p class="mt-2 text-3xl font-bold text-slate-900">{{ data.summary.pending_count }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-sm font-medium text-slate-500">Paid Invoices</p>
          <p class="mt-2 text-3xl font-bold text-slate-900">{{ data.summary.paid_count }}</p>
        </div>
      </div>

      <!-- Invoices Table -->
      <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <table class="min-w-full divide-y divide-slate-200">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-5 py-3 text-left text-xs font-medium text-slate-500 uppercase">Invoice #</th>
              <th class="px-5 py-3 text-left text-xs font-medium text-slate-500 uppercase">Patient</th>
              <th class="px-5 py-3 text-left text-xs font-medium text-slate-500 uppercase">Amount</th>
              <th class="px-5 py-3 text-left text-xs font-medium text-slate-500 uppercase">Paid</th>
              <th class="px-5 py-3 text-left text-xs font-medium text-slate-500 uppercase">Status</th>
              <th class="px-5 py-3 text-left text-xs font-medium text-slate-500 uppercase">Date</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200">
            <tr v-for="invoice in data.invoices.data" :key="invoice.id" class="hover:bg-slate-50">
              <td class="px-5 py-3 text-sm font-medium text-slate-900">{{ invoice.invoice_number }}</td>
              <td class="px-5 py-3 text-sm text-slate-600">
                {{ invoice.patient ? `${invoice.patient.first_name} ${invoice.patient.last_name}` : '—' }}
              </td>
              <td class="px-5 py-3 text-sm text-slate-900">{{ formatCurrency(invoice.amount) }}</td>
              <td class="px-5 py-3 text-sm text-slate-600">{{ formatCurrency(invoice.paid_amount) }}</td>
              <td class="px-5 py-3">
                <span :class="['inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium', statusBadge(invoice.status)]">
                  {{ invoice.status }}
                </span>
              </td>
              <td class="px-5 py-3 text-sm text-slate-600">{{ formatDate(invoice.issued_at) }}</td>
            </tr>
            <tr v-if="data.invoices.data.length === 0">
              <td colspan="6" class="px-5 py-8 text-center text-sm text-slate-500">No invoices found</td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>
