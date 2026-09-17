<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import { invoiceService } from '@/scripts/api/services/invoice.service'
import type { Invoice } from '@/scripts/types/domain/invoice'

/**
 * ConsignmentSelector — Dropdown selector for recent consignments (LR Receipts)
 * Fetches the last 5 LR Receipts for a selected customer and allows selection.
 *
 * Used in:
 * - LR Receipt form (TransportCustomFields.vue) — invoice-level consignment_no
 * - Office Invoice form (OfficeInvoiceItemsTable.vue) — item-level consignment_number
 *
 * Behavior:
 * 1. When a customer/party is selected, fetches the last 5 LR Receipts for that customer.
 * 2. Shows a dropdown of those LR Receipt docket numbers.
 * 3. On selection, emits 'update:modelValue' (the docket number string) and 'select'
 *    (the full Invoice object) so the parent can auto-fill other fields.
 * 4. If no LR Receipts exist for the customer, falls back to a manual text input.
 * 5. Manual text input also emits 'update:modelValue' so validation/auto-fill still works.
 */

interface Props {
  modelValue?: string | null
  customerId?: number | null
  label?: string
}

interface Emits {
  (e: 'update:modelValue', value: string | null): void
  (e: 'select', consignment: Invoice | null): void
}

const props = withDefaults(defineProps<Props>(), {
  modelValue: null,
  customerId: null,
  label: 'Consignment No',
})

const emit = defineEmits<Emits>()

const consignments = ref<Invoice[]>([])
const isLoading = ref(false)
const selectedConsignment = ref<Invoice | null>(null)

// Fetch consignments (LR Receipts) for the selected customer
async function fetchConsignments(): Promise<void> {
  if (!props.customerId) {
    consignments.value = []
    return
  }

  isLoading.value = true
  try {
    const response = await invoiceService.list({
      customer_id: props.customerId,
      template_name: 'lr_receipt' as unknown as undefined,
      limit: 5,
      orderByField: 'created_at',
      orderBy: 'desc',
    } as Record<string, unknown>)

    // Filter for LR Receipts only (safety — the API should already filter by template_name)
    const allInvoices = (response.data as Invoice[]) || []
    consignments.value = allInvoices.filter(
      (inv) => inv.template_name === 'lr_receipt'
    )
  } catch (error) {
    console.error('Error fetching consignments:', error)
    consignments.value = []
  } finally {
    isLoading.value = false
  }
}

// Fetch on mount if customer already selected
onMounted(() => {
  if (props.customerId) {
    fetchConsignments()
  }
})

// Watch for customer change and fetch consignments
watch(
  () => props.customerId,
  () => {
    // Reset selection when customer changes
    selectedConsignment.value = null
    fetchConsignments()
  },
)

// Watch for external/programmatic modelValue changes (e.g. when the parent
// sets consignment_number directly from an LR Receipt via ?lr_id=X).
// Tries to find a matching consignment in the loaded list and selects it.
// If no match is found, selectedConsignment stays null and the template
// falls back to showing the text input with the modelValue.
watch(
  () => props.modelValue,
  (newVal) => {
    if (!newVal) {
      selectedConsignment.value = null
      return
    }
    // Already selected — skip
    if (selectedConsignment.value?.invoice_number === newVal) return

    // Try to find a matching consignment in the list
    const match = consignments.value.find(
      (c) => c.invoice_number === newVal,
    )
    if (match) {
      selectedConsignment.value = match
    }
    // If no match, leave selectedConsignment null — the template will
    // show the text input with :value="modelValue"
  },
)

// Also try to match modelValue when the consignments list loads
// (handles the race condition where modelValue is set before the
// consignments API call completes)
watch(
  () => consignments.value,
  () => {
    if (props.modelValue && !selectedConsignment.value) {
      const match = consignments.value.find(
        (c) => c.invoice_number === props.modelValue,
      )
      if (match) {
        selectedConsignment.value = match
      }
    }
  },
)


// Select a consignment from the dropdown
function selectConsignment(consignment: Invoice): void {
  selectedConsignment.value = consignment
  emit('update:modelValue', consignment.invoice_number)
  emit('select', consignment)
}

// Clear selection
function clearSelection(): void {
  selectedConsignment.value = null
  emit('update:modelValue', null)
  emit('select', null)
}

// Manual text input — emits update:modelValue so the parent's
// validation and auto-fill logic still works
function onManualInput(event: Event): void {
  const value = (event.target as HTMLInputElement).value
  emit('update:modelValue', value)
  // Clear selected consignment object since user is typing manually
  selectedConsignment.value = null
  emit('select', null)
}
</script>

<template>
  <div>
    <!-- Dropdown showing recent consignments -->
    <BaseInputGroup :label="label">
      <div class="relative">
        <!-- Selected Consignment Display OR Input -->
        <div v-if="!selectedConsignment" class="flex gap-2">
          <!-- Show dropdown when consignments exist AND modelValue is not
               set to a value that doesn't match any of them.
               When modelValue is set programmatically (e.g. from ?lr_id=X)
               but doesn't match a recent consignment, fall back to the text
               input so the value is visible. -->
          <select
            v-if="consignments.length > 0 && !modelValue"
            class="
              flex-1
              px-3
              py-2
              border
              border-line-default
              rounded-lg
              bg-surface
              text-heading
              focus:outline-none
              focus:ring-2
              focus:ring-primary-400
            "
            @change="selectConsignment(consignments[Number(($event.target as HTMLSelectElement).value)])"
          >
            <option value="">-- Select from recent consignments --</option>
            <option
              v-for="(consignment, index) in consignments"
              :key="consignment.id"
              :value="index"
            >
              {{ consignment.invoice_number }}
              ({{ new Date(consignment.created_at).toLocaleDateString() }})
            </option>
          </select>

          <!-- Manual input: shown when no consignments exist, OR when modelValue
               is set but doesn't match any recent consignment (programmatic set) -->
          <input
            v-else
            type="text"
            :value="modelValue"
            placeholder="Enter Consignment No"
            class="
              flex-1
              px-3
              py-2
              border
              border-line-default
              rounded-lg
              bg-surface
              text-heading
              focus:outline-none
              focus:ring-2
              focus:ring-primary-400
            "
            @input="onManualInput"
          />
        </div>


        <!-- Selected Consignment Chip -->
        <div
          v-else
          class="
            flex
            items-center
            gap-2
            px-3
            py-2
            border
            border-primary-500
            bg-primary-50
            rounded-lg
          "
        >
          <span class="text-sm font-medium text-heading">
            {{ selectedConsignment.invoice_number }}
          </span>
          <button
            type="button"
            class="text-primary-500 hover:text-primary-700"
            @click="clearSelection"
          >
            <BaseIcon name="XMarkIcon" class="h-4 w-4" />
          </button>
        </div>

        <!-- Loading indicator -->
        <div v-if="isLoading" class="absolute right-3 top-2.5">
          <div class="animate-spin h-5 w-5 text-primary-500">
            <BaseIcon name="ArrowPathIcon" />
          </div>
        </div>
      </div>

      <!-- Help text -->
      <p class="mt-2 text-xs text-muted">
        {{ consignments.length > 0 ? 'Select from recent LR Receipts or enter manually' : 'Select a party first to see recent consignments' }}
      </p>
    </BaseInputGroup>
  </div>
</template>
