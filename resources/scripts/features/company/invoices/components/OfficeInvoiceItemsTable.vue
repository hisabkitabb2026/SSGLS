<template>
  <BaseCard class="mb-6">
    <template #header>
      <div class="flex items-center gap-2">
        <BaseIcon name="TruckIcon" class="h-5 w-5 text-primary-500" />
        <h3 class="text-base font-semibold text-heading">Consignment Details</h3>
      </div>
    </template>

    <div v-if="isLoading" class="space-y-3">
      <BaseContentPlaceholders v-for="i in 2" :key="i">
        <BaseContentPlaceholdersBox :rounded="true" class="w-full" style="min-height: 60px" />
      </BaseContentPlaceholders>
    </div>

    <div v-else>
      <!-- Item rows -->
      <div
        v-for="(item, index) in items"
        :key="item.id ?? index"
        class="border border-line-light rounded-lg p-4 mb-4 relative"
      >
        <div class="flex items-center justify-between mb-3">
          <span class="text-sm font-semibold text-muted">Item {{ index + 1 }}</span>
          <button
            v-if="items.length > 1"
            type="button"
            class="text-danger hover:text-danger-hover"
            @click="removeItem(index)"
          >
            <BaseIcon name="TrashIcon" class="h-5 w-5" />
          </button>
        </div>

        <div class="grid gap-x-4 gap-y-4 grid-cols-1 md:grid-cols-2 xl:grid-cols-3">
          <!-- Consignment No — Dropdown of recent LR Receipts for the selected party -->
          <div>
            <ConsignmentSelector
              v-model="item.consignment_number"
              :customer-id="formData?.customer_id"
              label="Consignment No"
              @select="onConsignmentSelected(index, $event)"
              @update:model-value="onConsignmentNoChange(index)"
            />
            <p
              v-if="getConsignmentValidationState(index) === 'invalid'"
              class="mt-1 text-sm text-danger"
            >
              LR Receipt not found
            </p>
            <p
              v-else-if="getConsignmentValidationState(index) === 'valid'"
              class="mt-1 text-sm text-success"
            >
              ✓ LR Receipt found
            </p>
          </div>

          <!-- Consignment Date -->
          <BaseInputGroup label="Consignment Date">
            <BaseInput
              v-model="item.consignment_date"
              type="date"
            />
          </BaseInputGroup>

          <!-- Party Inv No -->
          <BaseInputGroup label="Party Inv No">
            <BaseInput v-model="item.party_inv_no" />
          </BaseInputGroup>

          <!-- From -->
          <BaseInputGroup label="From">
            <BaseInput v-model="item.from_code" />
          </BaseInputGroup>

          <!-- Destination -->
          <BaseInputGroup label="Destination">
            <BaseInput v-model="item.to_code" />
          </BaseInputGroup>

          <!-- Vehicle No -->
          <BaseInputGroup label="Vehicle No">
            <BaseInput v-model="item.truck_no" />
          </BaseInputGroup>

          <!-- Pkg -->
          <BaseInputGroup label="Pkg">
            <BaseInput v-model="item.pkg" />
          </BaseInputGroup>

          <!-- Weight -->
          <BaseInputGroup label="Weight">
            <BaseInput v-model="item.weight" />
          </BaseInputGroup>

          <!-- Rate -->
          <BaseInputGroup label="Rate">
            <BaseInput
              v-model="item.rate"
              type="number"
              @update:model-value="recalcAmount(index)"
            />
          </BaseInputGroup>

          <!-- Other Charge -->
          <BaseInputGroup label="Other Charge">
            <BaseInput
              v-model="item.other_charge"
              type="number"
              @update:model-value="recalcAmount(index)"
            />
          </BaseInputGroup>

          <!-- LR Charge -->
          <BaseInputGroup label="LR Charge">
            <BaseInput
              v-model="item.lr_charge"
              type="number"
              @update:model-value="recalcAmount(index)"
            />
          </BaseInputGroup>

          <!-- DD Charge -->
          <BaseInputGroup label="DD Charge">
            <BaseInput
              v-model="item.dd_charge"
              type="number"
              @update:model-value="recalcAmount(index)"
            />
          </BaseInputGroup>

          <!-- Amount (auto-calculated, read-only) -->
          <BaseInputGroup label="Amount">
            <BaseInput
              :model-value="item.amount"
              disabled
              class="bg-surface-muted font-semibold"
            />
          </BaseInputGroup>
        </div>
      </div>

      <!-- Add New Item button -->
      <button
        type="button"
        class="flex items-center justify-center w-full px-6 py-3 text-base border border-dashed border-line-default rounded-lg cursor-pointer text-primary-500 hover:bg-hover"
        @click="addItem"
      >
        <BaseIcon name="PlusIcon" class="h-5 w-5 mr-2" />
        Add New Item
      </button>
    </div>
  </BaseCard>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { invoiceService } from '@/scripts/api/services/invoice.service'

import { useNotificationStore } from '@/scripts/stores/notification.store'
import { generateClientId } from '@/scripts/utils'
import { useDebounceFn } from '@vueuse/core'
import ConsignmentSelector from './ConsignmentSelector.vue'
import type { Invoice } from '@/scripts/types/domain/invoice'

/**
 * OfficeInvoiceItemsTable — renders consignment item rows for the
 * office_invoice template. Each item has native transport fields
 * (consignment_number, from_code, to_code, truck_no, rate, etc.)
 * bound directly via v-model to the item object in the store.
 *
 * Phase 3 cleanup: This component no longer fetches Item-level custom
 * field definitions from the API. All transport fields are native columns
 * on the invoice_items table.
 */

interface TransportItem {
  id?: string | number
  name?: string
  quantity?: number
  price?: number
  total?: number
  discount?: number
  discount_type?: string
  taxes?: unknown[]
  consignment_number?: string | null
  consignment_date?: string | null
  party_inv_no?: string | null
  from_code?: string | null
  to_code?: string | null
  truck_no?: string | null
  pkg?: string | null
  weight?: string | null
  rate?: string | number | null
  other_charge?: string | number | null
  lr_charge?: string | number | null
  dd_charge?: string | number | null
  amount?: string | number | null
}

interface StoreWithProp {
  [key: string]: {
    items: TransportItem[]
    customer?: unknown
    customer_id?: number | null
    selectCustomer?: (id: number) => Promise<unknown>
    [key: string]: unknown
  }
}


const props = withDefaults(
  defineProps<{
    store: StoreWithProp
    storeProp: string
    isEdit?: boolean
    isLoading?: boolean | null
    itemValidationScope: string
  }>(),
  {
    isEdit: false,
    isLoading: null,
  },
)

const formData = computed(() => props.store[props.storeProp])
const items = computed(() => formData.value?.items ?? [])

// --- Amount auto-calculation ---
// Amount = Rate + Other Charge + LR Charge + DD Charge
const chargeKeys = ['rate', 'other_charge', 'lr_charge', 'dd_charge']

function getNum(value: string | number | null | undefined): number {
  if (!value) return 0
  const num = Number(value)
  return isNaN(num) ? 0 : num
}

function recalcAmount(index: number): void {
  const item = items.value[index]
  if (!item) return

  const sum = chargeKeys.reduce(
    (acc, key) => acc + getNum(item[key as keyof TransportItem] as string | number | null),
    0,
  )
  item.amount = String(sum)
}

function addItem(): void {
  if (!formData.value) return

  formData.value.items.push({
    id: generateClientId(),
    name: '',
    quantity: 1,
    price: 0,
    total: 0,
    discount: 0,
    discount_type: 'fixed',
    taxes: [],
    consignment_number: null,
    consignment_date: null,
    party_inv_no: null,
    from_code: null,
    to_code: null,
    truck_no: null,
    pkg: null,
    weight: null,
    rate: null,
    other_charge: null,
    lr_charge: null,
    dd_charge: null,
    amount: null,
  })
}

function removeItem(index: number): void {
  if (items.value.length <= 1) return
  formData.value?.items.splice(index, 1)
}

// --- Auto-fill from LR Receipt when Consignment No matches ---
const notificationStore = useNotificationStore()
const isAutoFillingFromLr = ref(false)

// --- Consignment number validation state ---
// Tracks per-item validation: 'idle' | 'checking' | 'valid' | 'invalid'
const consignmentValidationStates = ref<Record<number, 'idle' | 'checking' | 'valid' | 'invalid'>>({})

function getConsignmentValidationState(index: number): string {
  return consignmentValidationStates.value[index] ?? 'idle'
}

function getConsignmentValidationClass(index: number): string {
  const state = getConsignmentValidationState(index)
  if (state === 'invalid') return 'border-danger'
  if (state === 'valid') return 'border-success'
  return ''
}

// Emits to parent so the form can block submission if any consignment is invalid
const emit = defineEmits<{
  (e: 'consignment-valid', allValid: boolean): void
}>()

function checkAllConsignmentsValid(): void {
  const allValid = items.value.every((_, index) =>
    getConsignmentValidationState(index) === 'valid',
  )
  emit('consignment-valid', allValid)
}

// Flag: set to true when onConsignmentNoChange handles a user-driven change
// so the programmatic-change watcher knows to skip (avoid double-trigger).
let userChangeHandled = false

function onConsignmentNoChange(index: number): void {
  // Mark as user-handled so the watcher doesn't double-trigger
  userChangeHandled = true

  // Reset validation state for this item
  consignmentValidationStates.value[index] = 'checking'

  // Trigger auto-fill for the first item (manual typing fallback)
  if (index === 0 && !props.isEdit) {
    debouncedAutoFill()
  }

  // Validate the consignment number
  debouncedValidateConsignment(index)
}


/**
 * Called when a consignment is selected from the ConsignmentSelector dropdown.
 * The full LR Receipt Invoice object is passed from ConsignmentSelector via
 * the 'select' event, so we can auto-fill all item fields immediately without
 * an extra API call.
 */
function onConsignmentSelected(index: number, consignment: Invoice | null): void {
  if (!consignment) {
    // Selection was cleared — reset validation state
    consignmentValidationStates.value[index] = 'idle'
    checkAllConsignmentsValid()
    return
  }

  // Auto-fill all item fields from the selected LR Receipt
  autofillFromLrReceipt(consignment as unknown as Record<string, unknown>, index)

  // Mark as valid since we selected an existing LR Receipt
  consignmentValidationStates.value[index] = 'valid'
  checkAllConsignmentsValid()
}



// Map LR Receipt native fields to Office Invoice item native fields.
//
// Field mapping (LR Receipt → Office Invoice item):
//   basic_freight         → rate
//   other_charge          → other_charge (combined with hamali + fov + local_collection)
//   docket_charge         → lr_charge
//   door_delivery         → dd_charge
//   actual_weight         → weight
//   packing               → pkg
//   invoice_date          → consignment_date
//   from_code             → from_code (already mapped)
//   to_code               → to_code   (already mapped)
//   truck_no              → truck_no  (already mapped)
//   amount                → recalculated from rate + other_charge + lr_charge + dd_charge
function autofillFromLrReceipt(lrInvoice: Record<string, unknown>, index: number = 0): void {
  if (!items.value[index]) return

  const item = items.value[index]
  // Don't overwrite consignment_number itself

  // Helper to safely get a numeric value from the LR Receipt
  function getNum(key: string): number {
    const val = lrInvoice[key]
    if (val === undefined || val === null) return 0
    const num = Number(val)
    return isNaN(num) ? 0 : num
  }

  // Helper to safely get a string value
  function getStr(key: string): string | null {
    const val = lrInvoice[key]
    if (val === undefined || val === null) return null
    return String(val)
  }

  // --- Direct field mappings ---
  const fieldMap: Record<string, string> = {
    from_code: 'from_code',
    to_code: 'to_code',
    truck_no: 'truck_no',
  }

  for (const [lrKey, itemKey] of Object.entries(fieldMap)) {
    const lrValue = lrInvoice[lrKey]
    if (lrValue !== undefined && lrValue !== null) {
      ;(item as Record<string, unknown>)[itemKey] = lrValue
    }
  }

  // --- Computed/transformed field mappings ---

  // Rate ← Basic Freight
  item.rate = getNum('basic_freight') || null

  // LR Charge ← Docket Charge
  item.lr_charge = getNum('docket_charge') || null

  // DD Charge ← Door Delivery
  item.dd_charge = getNum('door_delivery') || null

  // Other Charge ← Other Charge + Hamali + FOV + Local Collection (sum)
  const combinedOtherCharge =
    getNum('other_charge') +
    getNum('hamali') +
    getNum('fov') +
    getNum('local_collection')
  item.other_charge = combinedOtherCharge || null

  // Weight ← Actual Weight
  item.weight = getStr('actual_weight')

  // Pkg ← Packing
  item.pkg = getStr('packing')

  // Consignment Date ← LR Receipt's invoice_date
  item.consignment_date = getStr('invoice_date')

  // Do NOT copy the customer (Party Name) from the LR Receipt — the Lorry
  // Receipt party is selected independently by the user and should not be
  // overwritten by the LR Receipt's customer.

  // Also copy the consignee from the LR Receipt if available
  const lrConsignee = lrInvoice.consignee_customer as Record<string, unknown> | undefined
  if (lrConsignee && formData.value) {
    formData.value.consignee_customer = lrConsignee
    formData.value.consignee_customer_id = lrConsignee.id as number
  }

  // Auto-fill "GST Through" from the LR Receipt's gst_tax_payable_by
  // native column. The Office Invoice stores this as a native column
  // on the invoices table (not a custom field), and the PDF template
  // reads it directly from $invoice->gst_tax_payable_by.
  const gstPayableBy = lrInvoice.gst_tax_payable_by as string | undefined
  if (gstPayableBy && formData.value) {
    formData.value.gst_tax_payable_by = gstPayableBy
  }

  // Auto-select the Party (customer) based on gst_tax_payable_by:
  //   - 'Consignee' → Party = consignee_customer
  //   - 'Consignor' (or null) → Party = customer (consignor)
  // This ensures the Party field is populated when the user manually
  // types a consignment number (not just when navigating from the
  // "Create Invoice Receipt" button).
  const isConsigneeGst = (gstPayableBy ?? '').toLowerCase() === 'consignee'
  const partyCustomerId = isConsigneeGst
    ? (lrInvoice.consignee_customer_id as number | undefined)
    : (lrInvoice.customer_id as number | undefined)

  if (partyCustomerId && props.store.selectCustomer) {
    // selectCustomer is an async store action that fetches the full
    // customer record and populates customer, customer_id, currency_id
    props.store.selectCustomer(partyCustomerId)
  }

  // Recalculate Amount after auto-fill:
  // amount = rate + other_charge + lr_charge + dd_charge
  recalcAmount(index)
}



const debouncedAutoFill = useDebounceFn(async () => {
  const consignmentNo = String(items.value[0]?.consignment_number ?? '').trim()
  if (!consignmentNo || consignmentNo.length < 2) return

  isAutoFillingFromLr.value = true
  try {
    const response = await invoiceService.findByInvoiceNumber(consignmentNo, 'lr_receipt')
    if (response?.data) {
      autofillFromLrReceipt(response.data as Record<string, unknown>)
      // Show a single notification here — autofillFromLrReceipt itself
      // is silent to avoid duplicate notifications when called from
      // onConsignmentSelected (dropdown selection) or the watcher.
      notificationStore.showNotification({
        type: 'success',
        message: `Auto-filled from LR Receipt: ${consignmentNo}`,
      })
    }
  } catch {
    // Silently fail — the user might be typing a new consignment number
  } finally {
    isAutoFillingFromLr.value = false
  }
}, 600)


// --- Consignment number existence validation ---
// Checks if the consignment number matches an existing LR Receipt.
// Updates the validation state and emits to the parent form.
const debouncedValidateConsignment = useDebounceFn(async (index: number) => {
  const consignmentNo = String(items.value[index]?.consignment_number ?? '').trim()

  if (!consignmentNo || consignmentNo.length < 2) {
    consignmentValidationStates.value[index] = 'idle'
    checkAllConsignmentsValid()
    return
  }

  try {
    const response = await invoiceService.findByInvoiceNumber(consignmentNo, 'lr_receipt')
    if (response?.data) {
      consignmentValidationStates.value[index] = 'valid'
    } else {
      consignmentValidationStates.value[index] = 'invalid'
    }
  } catch {
    consignmentValidationStates.value[index] = 'invalid'
  }

  checkAllConsignmentsValid()
}, 600)

// In edit mode, pre-validate all existing consignment numbers
if (props.isEdit) {
  items.value.forEach((_, index) => {
    const consignmentNo = String(items.value[index]?.consignment_number ?? '').trim()
    if (consignmentNo) {
      debouncedValidateConsignment(index)
    }
  })
}

// --- Watch for programmatic consignment_number changes ---
// When the parent (InvoiceCreateView) sets consignment_number directly on the
// store item — e.g. when navigating from "Create Invoice Receipt" on the LR
// Receipt list (?lr_id=X) — the ConsignmentSelector's @update:model-value
// event does NOT fire (that only fires on user interaction). This watcher
// detects such programmatic changes and triggers the same auto-fill +
// validation logic that would normally run on user input.
//
// The `userChangeHandled` flag is set by onConsignmentNoChange when a
// user-driven change is already being processed, so we skip the watcher
// in that case to avoid double-triggering auto-fill + validation.
watch(
  () => items.value.map((item) => item.consignment_number),
  (newValues, oldValues) => {
    if (props.isEdit) return

    newValues.forEach((consignmentNo, index) => {
      const oldNo = oldValues?.[index]

      // Skip if already handled by onConsignmentNoChange (user interaction)
      if (userChangeHandled) {
        userChangeHandled = false
        return
      }

      // Only react to programmatic changes (value set without user event)
      if (consignmentNo && consignmentNo !== oldNo) {
        // Trigger the same auto-fill + validation as user interaction
        consignmentValidationStates.value[index] = 'checking'
        if (index === 0) {
          debouncedAutoFill()
        }
        debouncedValidateConsignment(index)
      }
    })
  },
  { deep: true },
)
</script>
