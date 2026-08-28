<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { getDrugInventory, createDrugInventory, updateDrugInventory, type DrugInventory, type CreateDrugInventoryRequest } from '@/api/pharmacy'
import { getFormulary, createFormulary, updateFormulary, type Formulary, type CreateFormularyRequest } from '@/api/pharmacy'

const inventory = ref<DrugInventory[]>([])
const formulary = ref<Formulary[]>([])
const loading = ref(false)
const error = ref<string | null>(null)

const activeTab = ref<'inventory' | 'formulary'>('inventory')

// Inventory form state
const showInventoryForm = ref(false)
const editingInventory = ref<DrugInventory | null>(null)
const inventoryForm = ref<CreateDrugInventoryRequest>({
  medication_id: 0,
  expiry_date: new Date().toISOString().split('T')[0],
  quantity_on_hand: 0,
  status: 'available',
})
const submittingInventory = ref(false)

// Formulary form state
const showFormularyForm = ref(false)
const editingFormulary = ref<Formulary | null>(null)
const formularyForm = ref<CreateFormularyRequest>({
  medication_id: 0,
  tier: 'generic',
  requires_prior_authorization: false,
  is_active: true,
})
const submittingFormulary = ref(false)

// Load data
async function loadData() {
  loading.value = true
  error.value = null
  try {
    inventory.value = await getDrugInventory({ low_stock: true, expiring_soon: true })
    formulary.value = await getFormulary({ active_only: true })
  } catch {
    error.value = 'Failed to load pharmacy data'
  } finally {
    loading.value = false
  }
}

// Inventory form handlers
function openInventoryForm(item?: DrugInventory) {
  editingInventory.value = item || null
  inventoryForm.value = {
    medication_id: item?.medication_id || 0,
    batch_number: item?.batch_number || '',
    expiry_date: item?.expiry_date?.split('T')[0] || new Date().toISOString().split('T')[0],
    quantity_on_hand: item?.quantity_on_hand || 0,
    minimum_stock_level: item?.minimum_stock_level || 10,
    maximum_stock_level: item?.maximum_stock_level || 1000,
    location: item?.location || '',
    supplier: item?.supplier || '',
    unit_cost: item?.unit_cost ?? undefined,
    status: item?.status || 'available',
    notes: item?.notes || '',
  }
  showInventoryForm.value = true
}

async function submitInventory() {
  if (submittingInventory.value) return
  submittingInventory.value = true
  error.value = null
  try {
    if (editingInventory.value) {
      await updateDrugInventory(editingInventory.value.id, inventoryForm.value)
    } else {
      await createDrugInventory(inventoryForm.value)
    }
    showInventoryForm.value = false
    editingInventory.value = null
    await loadData()
  } catch (err: any) {
    error.value = err?.response?.data?.message || 'Failed to save inventory'
  } finally {
    submittingInventory.value = false
  }
}

// Formulary form handlers
function openFormularyForm(item?: Formulary) {
  editingFormulary.value = item || null
  formularyForm.value = {
    medication_id: item?.medication_id || 0,
    formulary_code: item?.formulary_code || '',
    tier: item?.tier || 'generic',
    requires_prior_authorization: item?.requires_prior_authorization || false,
    quantity_limit: item?.quantity_limit ?? undefined,
    days_supply_limit: item?.days_supply_limit ?? undefined,
    restrictions: item?.restrictions || '',
    alternatives: item?.alternatives || '',
    is_active: item?.is_active ?? true,
    effective_date: item?.effective_date?.split('T')[0],
    notes: item?.notes || '',
  }
  showFormularyForm.value = true
}

async function submitFormulary() {
  if (submittingFormulary.value) return
  submittingFormulary.value = true
  error.value = null
  try {
    if (editingFormulary.value) {
      await updateFormulary(editingFormulary.value.id, formularyForm.value)
    } else {
      await createFormulary(formularyForm.value)
    }
    showFormularyForm.value = false
    editingFormulary.value = null
    await loadData()
  } catch (err: any) {
    error.value = err?.response?.data?.message || 'Failed to save formulary'
  } finally {
    submittingFormulary.value = false
  }
}

// Status colors
function getInventoryStatusColor(status: DrugInventory['status']): string {
  switch (status) {
    case 'available': return 'bg-green-100 text-green-700'
    case 'reserved': return 'bg-yellow-100 text-yellow-700'
    case 'expired': return 'bg-red-100 text-red-700'
    case 'recalled': return 'bg-red-100 text-red-700'
    default: return 'bg-gray-100 text-gray-700'
  }
}

function getFormularyTierColor(tier: Formulary['tier']): string {
  switch (tier) {
    case 'generic': return 'bg-green-100 text-green-700'
    case 'preferred_brand': return 'bg-blue-100 text-blue-700'
    case 'non_preferred': return 'bg-yellow-100 text-yellow-700'
    case 'specialty': return 'bg-purple-100 text-purple-700'
    default: return 'bg-gray-100 text-gray-700'
  }
}

// Initialize
loadData()
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-slate-900">Pharmacy Management</h1>
      <p class="mt-1 text-sm text-slate-500">
        Manage drug inventory and formulary.
      </p>
    </div>

    <div v-if="error" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
      {{ error }}
    </div>

    <!-- Tabs -->
    <div class="border-b border-slate-200">
      <nav class="flex gap-4" aria-label="Tabs">
        <button
          @click="activeTab = 'inventory'"
          :class="activeTab === 'inventory' ? 'border-b-2 border-teal-600 text-teal-600' : 'text-slate-500 hover:text-slate-700'"
          class="px-4 py-2 text-sm font-medium"
        >
          Drug Inventory
        </button>
        <button
          @click="activeTab = 'formulary'"
          :class="activeTab === 'formulary' ? 'border-b-2 border-teal-600 text-teal-600' : 'text-slate-500 hover:text-slate-700'"
          class="px-4 py-2 text-sm font-medium"
        >
          Formulary
        </button>
      </nav>
    </div>

    <!-- Inventory Tab -->
    <div v-if="activeTab === 'inventory'">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold text-slate-900">Drug Inventory</h2>
        <button @click="openInventoryForm()" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700">Add Inventory</button>
      </div>

      <div v-if="loading" class="rounded-xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500">
        Loading…
      </div>
      
      <div v-else-if="inventory.length === 0" class="rounded-xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500">
        <p class="text-base font-medium text-slate-700">No inventory items</p>
        <p class="mt-1">Add your first inventory item to get started.</p>
      </div>
      
      <div v-else class="space-y-3">
        <div v-for="item in inventory" :key="item.id" class="rounded-lg border border-slate-200 p-4">
          <div class="flex items-start justify-between gap-4">
            <div class="flex-1">
              <div class="flex items-center gap-2 mb-2">
                <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="getInventoryStatusColor(item.status)">
                  {{ item.status.toUpperCase() }}
                </span>
                <span v-if="item.is_low_stock" class="rounded-full px-2.5 py-1 text-xs font-medium bg-orange-100 text-orange-700">LOW STOCK</span>
                <span v-if="item.is_expiring_soon" class="rounded-full px-2.5 py-1 text-xs font-medium bg-yellow-100 text-yellow-700">EXPIRING SOON</span>
                <span v-if="item.is_expired" class="rounded-full px-2.5 py-1 text-xs font-medium bg-red-100 text-red-700">EXPIRED</span>
              </div>
              
              <p class="text-sm font-medium text-slate-800">{{ item.medication_name }}</p>
              
              <div class="mt-2 grid grid-cols-3 gap-4 text-xs text-slate-500">
                <p>Batch: {{ item.batch_number || '—' }}</p>
                <p>Qty: {{ item.quantity_on_hand }}</p>
                <p>Expires: {{ item.expiry_date?.split('T')[0] }}</p>
              </div>
              
              <p v-if="item.location" class="mt-1 text-xs text-slate-400">Location: {{ item.location }}</p>
            </div>
            
            <button @click="openInventoryForm(item)" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Edit</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Formulary Tab -->
    <div v-if="activeTab === 'formulary'">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold text-slate-900">Formulary</h2>
        <button @click="openFormularyForm()" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700">Add Formulary Entry</button>
      </div>

      <div v-if="loading" class="rounded-xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500">
        Loading…
      </div>
      
      <div v-else-if="formulary.length === 0" class="rounded-xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500">
        <p class="text-base font-medium text-slate-700">No formulary entries</p>
        <p class="mt-1">Add your first formulary entry to get started.</p>
      </div>
      
      <div v-else class="space-y-3">
        <div v-for="item in formulary" :key="item.id" class="rounded-lg border border-slate-200 p-4">
          <div class="flex items-start justify-between gap-4">
            <div class="flex-1">
              <div class="flex items-center gap-2 mb-2">
                <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="getFormularyTierColor(item.tier)">
                  {{ item.tier.toUpperCase() }}
                </span>
                <span v-if="item.requires_prior_authorization" class="rounded-full px-2.5 py-1 text-xs font-medium bg-orange-100 text-orange-700">REQUIRES AUTH</span>
                <span v-if="!item.is_currently_active" class="rounded-full px-2.5 py-1 text-xs font-medium bg-gray-100 text-gray-600">INACTIVE</span>
              </div>
              
              <p class="text-sm font-medium text-slate-800">{{ item.medication_name }}</p>
              
              <div class="mt-2 grid grid-cols-3 gap-4 text-xs text-slate-500">
                <p>Code: {{ item.formulary_code || '—' }}</p>
                <p v-if="item.quantity_limit">Qty Limit: {{ item.quantity_limit }}</p>
                <p v-if="item.days_supply_limit">Days Limit: {{ item.days_supply_limit }}</p>
              </div>
              
              <p v-if="item.restrictions" class="mt-1 text-xs text-slate-500">{{ item.restrictions }}</p>
            </div>
            
            <button @click="openFormularyForm(item)" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Edit</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Inventory Form Modal -->
    <div v-if="showInventoryForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-lg">
        <div class="mb-4 flex items-center justify-between">
          <h2 class="text-lg font-semibold text-slate-900">{{ editingInventory ? 'Edit Inventory' : 'Add Inventory' }}</h2>
          <button @click="showInventoryForm = false" class="text-slate-400 hover:text-slate-600">✕</button>
        </div>

        <form @submit.prevent="submitInventory" class="space-y-4">
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Medication ID</label>
            <input v-model.number="inventoryForm.medication_id" type="number" required class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Batch Number</label>
              <input v-model="inventoryForm.batch_number" type="text" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Expiry Date</label>
              <input v-model="inventoryForm.expiry_date" type="date" required class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
            </div>
          </div>

          <div class="grid grid-cols-3 gap-4">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Quantity</label>
              <input v-model.number="inventoryForm.quantity_on_hand" type="number" required class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Min Stock</label>
              <input v-model.number="inventoryForm.minimum_stock_level" type="number" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Max Stock</label>
              <input v-model.number="inventoryForm.maximum_stock_level" type="number" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
            </div>
          </div>

          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Status</label>
            <select v-model="inventoryForm.status" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none">
              <option value="available">Available</option>
              <option value="reserved">Reserved</option>
              <option value="expired">Expired</option>
              <option value="recalled">Recalled</option>
            </select>
          </div>

          <div class="flex gap-3">
            <button type="button" @click="showInventoryForm = false" class="flex-1 rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</button>
            <button type="submit" class="flex-1 rounded-lg bg-teal-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-teal-700 disabled:cursor-not-allowed disabled:opacity-50" :disabled="submittingInventory">{{ submittingInventory ? 'Saving…' : 'Save' }}</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Formulary Form Modal -->
    <div v-if="showFormularyForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-lg">
        <div class="mb-4 flex items-center justify-between">
          <h2 class="text-lg font-semibold text-slate-900">{{ editingFormulary ? 'Edit Formulary' : 'Add Formulary' }}</h2>
          <button @click="showFormularyForm = false" class="text-slate-400 hover:text-slate-600">✕</button>
        </div>

        <form @submit.prevent="submitFormulary" class="space-y-4">
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Medication ID</label>
            <input v-model.number="formularyForm.medication_id" type="number" required class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Formulary Code</label>
              <input v-model="formularyForm.formulary_code" type="text" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Tier</label>
              <select v-model="formularyForm.tier" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none">
                <option value="generic">Generic</option>
                <option value="preferred_brand">Preferred Brand</option>
                <option value="non_preferred">Non-Preferred</option>
                <option value="specialty">Specialty</option>
              </select>
            </div>
          </div>

          <div class="flex items-center gap-2">
            <input v-model="formularyForm.requires_prior_authorization" type="checkbox" id="requires_auth" class="h-4 w-4 rounded border-slate-300 text-teal-600 focus:ring-teal-500" />
            <label for="requires_auth" class="text-sm text-slate-700">Requires Prior Authorization</label>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Quantity Limit</label>
              <input v-model.number="formularyForm.quantity_limit" type="number" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Days Supply Limit</label>
              <input v-model.number="formularyForm.days_supply_limit" type="number" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
            </div>
          </div>

          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Restrictions</label>
            <textarea v-model="formularyForm.restrictions" rows="2" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
          </div>

          <div class="flex gap-3">
            <button type="button" @click="showFormularyForm = false" class="flex-1 rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</button>
            <button type="submit" class="flex-1 rounded-lg bg-teal-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-teal-700 disabled:cursor-not-allowed disabled:opacity-50" :disabled="submittingFormulary">{{ submittingFormulary ? 'Saving…' : 'Save' }}</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
