<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { fetchAdminInventory, createInventoryItem, deleteInventoryItem, type AdminInventoryData } from '@/api/admin'

const data = ref<AdminInventoryData | null>(null)
const loading = ref(true)
const error = ref('')
const showForm = ref(false)
const form = ref({ name: '', sku: '', item_type: '', quantity_on_hand: '', minimum_stock_level: '', supplier: '' })
const submitting = ref(false)

onMounted(async () => {
  await loadInventory()
})

async function loadInventory() {
  loading.value = true
  error.value = ''
  try {
    data.value = await fetchAdminInventory()
  } catch {
    error.value = 'Failed to load inventory'
  } finally {
    loading.value = false
  }
}

async function handleSubmit() {
  submitting.value = true
  try {
    await createInventoryItem({
      name: form.value.name,
      sku: form.value.sku,
      item_type: form.value.item_type || undefined,
      quantity_on_hand: parseInt(form.value.quantity_on_hand) || 0,
      minimum_stock_level: form.value.minimum_stock_level ? parseInt(form.value.minimum_stock_level) : undefined,
      supplier: form.value.supplier || undefined,
    })
    showForm.value = false
    form.value = { name: '', sku: '', item_type: '', quantity_on_hand: '', minimum_stock_level: '', supplier: '' }
    await loadInventory()
  } catch {
    error.value = 'Failed to create inventory item'
  } finally {
    submitting.value = false
  }
}

async function handleDelete(id: number) {
  if (!confirm('Are you sure you want to delete this item?')) return
  try {
    await deleteInventoryItem(id)
    await loadInventory()
  } catch {
    error.value = 'Failed to delete item'
  }
}

function statusBadge(status: string): string {
  const map: Record<string, string> = {
    in_stock: 'bg-emerald-100 text-emerald-800',
    low_stock: 'bg-amber-100 text-amber-800',
    out_of_stock: 'bg-red-100 text-red-800',
    expired: 'bg-red-100 text-red-800',
  }
  return map[status] || 'bg-slate-100 text-slate-800'
}

function formatStatus(status: string): string {
  return status.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-slate-900">Inventory</h1>
      <button
        @click="showForm = !showForm"
        class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700"
      >
        {{ showForm ? 'Cancel' : 'Add Item' }}
      </button>
    </div>

    <div v-if="loading" class="text-center py-12 text-slate-500">Loading...</div>
    <div v-else-if="error" class="text-center py-12 text-red-500">{{ error }}</div>

    <template v-else-if="data">
      <!-- Summary -->
      <div class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-sm font-medium text-slate-500">Total Items</p>
          <p class="mt-2 text-3xl font-bold text-slate-900">{{ data.summary.total_items }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-sm font-medium text-slate-500">Low Stock</p>
          <p class="mt-2 text-3xl font-bold text-amber-600">{{ data.summary.low_stock }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-sm font-medium text-slate-500">Out of Stock</p>
          <p class="mt-2 text-3xl font-bold text-red-600">{{ data.summary.out_of_stock }}</p>
        </div>
      </div>

      <div v-if="showForm" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-lg font-semibold text-slate-900 mb-4">New Inventory Item</h2>
        <form @submit.prevent="handleSubmit" class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Name</label>
            <input v-model="form.name" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-500 focus:outline-none" />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">SKU</label>
            <input v-model="form.sku" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-500 focus:outline-none" />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Type</label>
            <input v-model="form.item_type" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-500 focus:outline-none" />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Quantity</label>
            <input v-model="form.quantity_on_hand" type="number" min="0" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-500 focus:outline-none" />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Min Stock Level</label>
            <input v-model="form.minimum_stock_level" type="number" min="0" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-500 focus:outline-none" />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Supplier</label>
            <input v-model="form.supplier" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-500 focus:outline-none" />
          </div>
          <div class="sm:col-span-2">
            <button
              type="submit"
              :disabled="submitting"
              class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700 disabled:opacity-50"
            >
              {{ submitting ? 'Creating...' : 'Create Item' }}
            </button>
          </div>
        </form>
      </div>

      <!-- Items Table -->
      <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <table class="min-w-full divide-y divide-slate-200">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-5 py-3 text-left text-xs font-medium text-slate-500 uppercase">Item</th>
              <th class="px-5 py-3 text-left text-xs font-medium text-slate-500 uppercase">SKU</th>
              <th class="px-5 py-3 text-left text-xs font-medium text-slate-500 uppercase">Qty</th>
              <th class="px-5 py-3 text-left text-xs font-medium text-slate-500 uppercase">Status</th>
              <th class="px-5 py-3 text-left text-xs font-medium text-slate-500 uppercase">Supplier</th>
              <th class="px-5 py-3 text-right text-xs font-medium text-slate-500 uppercase">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200">
            <tr v-for="item in data.items" :key="item.id" class="hover:bg-slate-50">
              <td class="px-5 py-3 text-sm font-medium text-slate-900">{{ item.name }}</td>
              <td class="px-5 py-3 text-sm text-slate-600">{{ item.sku }}</td>
              <td class="px-5 py-3 text-sm text-slate-600">{{ item.quantity_on_hand }}</td>
              <td class="px-5 py-3">
                <span :class="['inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium', statusBadge(item.status)]">
                  {{ formatStatus(item.status) }}
                </span>
              </td>
              <td class="px-5 py-3 text-sm text-slate-600">{{ item.supplier ?? '—' }}</td>
              <td class="px-5 py-3 text-right">
                <button @click="handleDelete(item.id)" class="text-sm text-red-600 hover:text-red-800">Delete</button>
              </td>
            </tr>
            <tr v-if="data.items.length === 0">
              <td colspan="6" class="px-5 py-8 text-center text-sm text-slate-500">No inventory items found</td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>
