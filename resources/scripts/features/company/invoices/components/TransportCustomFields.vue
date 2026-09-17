<script setup lang="ts">
import { computed, watch, onMounted } from 'vue'

import { usePaymentStore } from '@/scripts/features/company/payments/store'
import { useModalStore } from '@/scripts/stores/modal.store'
import { useI18n } from 'vue-i18n'
import PaymentModeModal from '@/scripts/features/company/settings/components/PaymentModeModal.vue'
import ConsignmentSelector from './ConsignmentSelector.vue'
import TruckSelectPopup from '@/scripts/features/company/shared/TruckSelectPopup.vue'



/**
 * TransportCustomFields — renders transport receipt fields (LR Receipt,
 * Lorry Receipt) as native form inputs bound directly to the invoice store's
 * transport properties (from_code, to_code, truck_no, etc.).
 *
 * Phase 3 cleanup: This component no longer fetches custom field definitions
 * from the API. All transport fields are native columns on the invoices table
 * and are bound directly via v-model to the store.
 */

interface TransportField {
  key: string
  label: string
  type?: 'text' | 'number' | 'date' | 'textarea' | 'dropdown' | 'payment_mode' | 'truck_selector' | 'consignment_selector'

  options?: string[]
  readonly?: boolean
  fullWidth?: boolean
}


interface FieldSection {
  title: string
  icon?: string
  fields: TransportField[]
}

const props = defineProps<{
  store: Record<string, any>
  storeProp: string
  templateName?: string | null
  isLoading?: boolean | null
}>()

const { t } = useI18n()
const paymentStore = usePaymentStore()
const modalStore = useModalStore()

const invoiceData = computed(() => props.store[props.storeProp])

// --- Payment Modes ---
// Fetch payment modes on mount so they're available for payment_mode fields
onMounted(() => {
  paymentStore.fetchPaymentModes({ limit: 'all' })
})

function addPaymentMode(): void {
  modalStore.openModal({
    title: t('settings.payment_modes.add_payment_mode'),
    componentName: 'PaymentModeModal',
    refreshData: () => paymentStore.fetchPaymentModes({ limit: 'all' }),
  })
}


// --- LR Receipt field sections ---
const lrReceiptSections: FieldSection[] = [
  {
    title: 'Trip Details',
    icon: 'TruckIcon',
    fields: [
      { key: 'from_code', label: 'From' },
      { key: 'to_code', label: 'To' },
      { key: 'truck_no', label: 'Truck No', type: 'truck_selector' },

      {
        key: 'mode_of_payment',
        label: 'Mode of Payment',
        type: 'dropdown',
        options: ['To Pay', 'To Be Billed', 'Paid'],
      },
      {
        key: 'gst_tax_payable_by',
        label: 'GST Tax Payable By',
        type: 'dropdown',
        options: ['Consignor', 'Consignee'],
      },
    ],
  },
  {
    title: 'Consignment Details',
    icon: 'ClipboardDocumentListIcon',
    fields: [
      { key: 'description_of_goods', label: 'Description of Goods', type: 'textarea', fullWidth: true },

      { key: 'hsn_code', label: 'HSN Code' },
      { key: 'eway_bill_no', label: 'E-way Bill No' },
      { key: 'actual_weight', label: 'Actual Weight' },
      { key: 'charged_weight', label: 'Charged Weight' },
      { key: 'no_of_articles', label: 'No of Articles' },
      { key: 'packing', label: 'Packing' },
    ],
  },
  {
    title: 'Freight Details',
    icon: 'CurrencyRupeeIcon',
    fields: [
      { key: 'basic_freight', label: 'Basic Freight', type: 'number' },
      { key: 'hamali', label: 'Hamali', type: 'number' },
      { key: 'fov', label: 'FOV', type: 'number' },
      { key: 'local_collection', label: 'Local Collection', type: 'number' },
      { key: 'door_delivery', label: 'Door Delivery', type: 'number' },
      { key: 'docket_charge', label: 'Docket Charge', type: 'number' },
      { key: 'other_charge', label: 'Other Charge', type: 'number' },
      { key: 'net_amount', label: 'Net Amount', type: 'number', readonly: true },
    ],
  },
]

// --- Lorry Receipt field sections ---
const lorryReceiptSections: FieldSection[] = [
  {
    title: 'Trip Details',
    icon: 'MapPinIcon',
    fields: [
      { key: 'from_code', label: 'From' },
      { key: 'to_code', label: 'To' },
      { key: 'no_of_pages', label: 'No Of Pages' },
      { key: 'no_of_packages', label: 'No Of Packages' },
      { key: 'actual_weight', label: 'Actual Weight' },
      { key: 'charged_weight', label: 'Charge Weight' },
    ],
  },
  {
    title: 'Vehicle Details',
    icon: 'TruckIcon',
    fields: [
      { key: 'truck_no', label: 'Lorry No' },
      { key: 'regd_at', label: 'Regd at' },
      { key: 'body_type', label: 'Body Type' },
      { key: 'make', label: 'Make' },
      { key: 'vehicle_model', label: 'Model' },
      { key: 'colour', label: 'Colour' },
      { key: 'chasis_no', label: 'Chasis No' },
      { key: 'engine_no', label: 'Engine No' },
    ],
  },
  {
    title: 'Hire Particulars',
    icon: 'CurrencyRupeeIcon',
    fields: [
      { key: 'paid_to', label: 'Paid To' },
      { key: 'lorry_hire_amount', label: 'Lorry Hire', type: 'number' },
      { key: 'other_charges_amount', label: 'Add Other Charges', type: 'number' },
      { key: 'advance_cash_cheque_no', label: 'Advance Payment Mode', type: 'payment_mode' },
      { key: 'advance_on', label: 'Advance On', type: 'date' },
      { key: 'advance_bank', label: 'Bank' },
      { key: 'advance_amount', label: 'Advance Paid Rs', type: 'number' },
      { key: 'balance_payable_at', label: 'Balance Payable at', type: 'dropdown', options: ['UMB', 'VAPI'] },
      { key: 'loaded_by', label: 'Loaded By' },
    ],
  },
  {
    title: 'Final Payment Details',
    icon: 'BanknotesIcon',
    fields: [
      { key: 'final_paid_to', label: 'Final Paid To' },
      { key: 'detention_amount', label: 'Add Detention Rs.', type: 'number' },
      { key: 'extra_hire_amount', label: 'Extra Hire Rs', type: 'number' },
      { key: 'final_other_amount', label: 'Other Rs', type: 'number' },
      { key: 'less_advance_other_branch_amount', label: 'Less Adv. at other branch', type: 'number' },
      { key: 'less_deduction_claims_amount', label: 'Less Deduction for Claims', type: 'number' },
      { key: 'final_balance_paid_at', label: 'Final Balance Amount Paid at', type: 'dropdown', options: ['UMB', 'VAPI'] },
      { key: 'final_balance_on', label: 'Final Balance Date', type: 'date' },
      { key: 'final_cash_cheque_no', label: 'Final Payment Mode', type: 'payment_mode' },
      { key: 'final_bank', label: 'Final Bank' },
    ],
  },
  {
    title: 'Owner Details',
    icon: 'UserIcon',
    fields: [
      { key: 'owner_name', label: 'Owner Name' },
      { key: 'owner_address', label: 'Owner Address', type: 'textarea', fullWidth: true },
      { key: 'owner_phone', label: 'Owner Phone No' },
      { key: 'owner_bank_account_no', label: 'Owner Bank Account No' },
      { key: 'owner_pan_no', label: 'Owner PAN No' },
    ],
  },
  {
    title: 'Driver Details',
    icon: 'IdentificationIcon',
    fields: [
      { key: 'driver_name', label: 'Driver Name' },
      { key: 'driver_address', label: 'Driver Address', type: 'textarea', fullWidth: true },
      { key: 'driver_licence_no', label: 'Driver Licence No' },
      { key: 'driver_licence_date', label: 'Driver Licence Date', type: 'date' },
      { key: 'driver_rto_address', label: 'Driver RTO' },
      { key: 'driver_valid_up_to', label: 'Driver Valid Up To', type: 'date' },
      { key: 'driver_bank_account_no', label: 'Driver Bank Account No' },
    ],
  },
  {
    title: 'Broker Details',
    icon: 'BriefcaseIcon',
    fields: [
      { key: 'broker_name', label: 'Broker Name' },
      { key: 'broker_address', label: 'Broker Address', type: 'textarea', fullWidth: true },
      { key: 'broker_pan_no', label: 'Broker Pan No' },
      { key: 'advice_date', label: 'Advice Date', type: 'date' },
      { key: 'broker_phone_no', label: 'Broker Phone No' },
      { key: 'broker_bank_account_no', label: 'Broker Bank Account No' },
    ],
  },
]

const sections = computed<FieldSection[]>(() => {
  if (props.templateName === 'lr_receipt') return lrReceiptSections
  if (props.templateName === 'lorry_receipt') return lorryReceiptSections
  return []
})

// --- LR Receipt: Net Amount auto-calculation ---
// Net Amount = Basic Freight + Local Collection + Door Delivery + Hamali
//              + Docket Charge + Other Charge + FOV
const lrReceiptChargeKeys = [
  'basic_freight',
  'local_collection',
  'door_delivery',
  'hamali',
  'docket_charge',
  'other_charge',
  'fov',
]

function getFieldValue(key: string): number {
  const val = invoiceData.value?.[key]
  if (!val) return 0
  const num = Number(val)
  return isNaN(num) ? 0 : num
}

function recalcNetAmount(): void {
  if (props.templateName !== 'lr_receipt') return
  if (!invoiceData.value) return

  const sum = lrReceiptChargeKeys.reduce(
    (acc, key) => acc + getFieldValue(key),
    0,
  )
  invoiceData.value.net_amount = String(sum)
}

// Watch charge fields for changes
watch(
  () => lrReceiptChargeKeys.map((key) => invoiceData.value?.[key]),
  () => {
    recalcNetAmount()
  },
)

// --- Truck Selector ---
// When a truck is selected from the TruckSelectPopup, store the truck_number
// in the invoice's truck_no field. The TruckSelectPopup emits the truck object.
function onTruckSelect(truck: { truck_number: string } | null): void {
  if (invoiceData.value) {
    invoiceData.value.truck_no = truck?.truck_number || ''
  }
}
</script>


<template>
  <div
    v-if="invoiceData && !isLoading"
    class="space-y-6"
  >
    <BaseCard
      v-for="section in sections"
      :key="section.title"
      class="mb-6"
    >
      <template #header>
        <div class="flex items-center gap-2">
          <BaseIcon
            v-if="section.icon"
            :name="section.icon"
            class="h-5 w-5 text-primary-500"
          />
          <h3 class="text-base font-semibold text-heading">{{ section.title }}</h3>
        </div>
      </template>

      <div class="grid gap-x-4 gap-y-5 grid-cols-1 md:grid-cols-2 xl:grid-cols-3">
        <div
          v-for="field in section.fields"
          :key="field.key"
          :class="[
            field.fullWidth ? 'md:col-span-2 xl:col-span-3' : '',
          ]"
        >
          <BaseInputGroup :label="field.label">
            <!-- Textarea -->
            <BaseTextarea
              v-if="field.type === 'textarea'"
              v-model="invoiceData[field.key]"
              rows="2"
            />
            <!-- Dropdown -->
            <BaseSelectInput
              v-else-if="field.type === 'dropdown'"
              v-model="invoiceData[field.key]"
              :options="(field.options || []).map(opt => ({ id: opt, label: opt }))"
              value-prop="id"
              label-key="label"
              placeholder="Select..."
            />
            <!-- Date -->
            <BaseInput
              v-else-if="field.type === 'date'"
              v-model="invoiceData[field.key]"
              type="date"
            />
            <!-- Number -->
            <BaseInput
              v-else-if="field.type === 'number'"
              v-model="invoiceData[field.key]"
              type="number"
              :disabled="field.readonly"
              :class="field.readonly ? 'bg-surface-muted font-semibold' : ''"
            />
            <!-- Payment Mode (BaseMultiselect with add-new action) -->
            <BaseMultiselect
              v-else-if="field.type === 'payment_mode'"
              v-model="invoiceData[field.key]"
              :options="paymentStore.paymentModes"
              value-prop="name"
              label="name"
              track-by="name"
              searchable
              :placeholder="t('payments.select_payment_mode')"
            >
              <template #action>
                <BaseSelectAction @mousedown.prevent="addPaymentMode">
                  <BaseIcon
                    name="PlusIcon"
                    class="h-4 mr-2 -ml-2 text-center text-primary-400"
                  />
                  {{ t('settings.payment_modes.add_payment_mode') }}
                </BaseSelectAction>
              </template>
            </BaseMultiselect>
            <!-- Consignment Selector (fetches recent LR Receipts for customer) -->
            <ConsignmentSelector
              v-else-if="field.type === 'consignment_selector'"
              v-model="invoiceData[field.key]"
              :customer-id="invoiceData.customer_id"
              :label="field.label"
            />
            <!-- Truck Selector (popover with search + Add New Truck) -->
            <TruckSelectPopup
              v-else-if="field.type === 'truck_selector'"
              v-model="invoiceData[field.key]"
              :label="field.label"
              @select="onTruckSelect"
            />
            <!-- Text (default) -->
            <BaseInput
              v-else
              v-model="invoiceData[field.key]"
            />
          </BaseInputGroup>

        </div>
      </div>
    </BaseCard>

    <!-- Payment Mode Modal (for adding new payment modes inline) -->
    <PaymentModeModal />
  </div>
</template>
