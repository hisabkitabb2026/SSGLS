<template>
  <!-- Hidden File Input for Auto Fill -->
  <input
    ref="fileInput"
    type="file"
    accept="image/*,application/pdf"
    class="hidden"
    @change="onAutoFillFileSelected"
  />

  <!-- Pause/Freezing Screen Loader Overlay -->
  <div
    v-if="isAutoFilling"
    class="fixed inset-0 z-[9999] flex flex-col items-center justify-center bg-surface-muted/60 backdrop-blur-xs"
  >
    <div class="p-8 bg-surface ronded-xl shadow-2xl flex flex-col items-center max-w-sm w-full mx-4 border border-line-light">
      <div class="animate-spin rounded-full h-14 w-14 border-t-4 border-b-4 border-primary-500 mb-6"></div>
      <h3 class="text-xl font-bold text-heading mb-2">LR Receipt is being Auto Filled</h3>
      <p class="text-sm text-muted text-center leading-relaxed">
        Please wait a moment while we process your document. To ensure all fields are populated correctly, please do not close or navigate away from this screen.
      </p>
    </div>
  </div>

  <!-- Single shared LorryPartyProfileModal — rendered at the page level so it
       is completely outside any HeadlessUI Popover.  This prevents the
       Popover/Dialog focus conflict that caused the modal to disappear when
       clicking on form fields inside it. -->
  <LorryPartyProfileModal />

  <BasePage class="relative invoice-create-page">
    <form @submit.prevent="submitForm" @keydown.enter="handleEnterKey">

      <BasePageHeader :title="pageTitle">
        <BaseBreadcrumb>
          <BaseBreadcrumbItem :title="$t('general.home')" to="/admin/dashboard" />
          <BaseBreadcrumbItem :title="indexTitle" :to="indexPath" />
          <BaseBreadcrumbItem
            v-if="isEdit"
            :title="editTitle"
            to="#"
            active
          />
          <BaseBreadcrumbItem v-else :title="createTitle" to="#" active />
        </BaseBreadcrumb>

        <template #actions>
          <!-- Make Recurring Toggle (regular invoices only) -->
          <div v-if="!isEdit && !isTransportReceipt" class="flex items-center mr-4">
            <BaseSwitch v-model="isRecurring" class="mr-2" />
            <span class="text-sm font-medium text-heading whitespace-nowrap">{{ $t('recurring_invoices.make_recurring') }}</span>
          </div>

          <router-link
            v-if="isEdit"
            :to="`/invoices/pdf/${invoiceStore.newInvoice.unique_hash}`"
            target="_blank"
          >
            <BaseButton class="mr-3" variant="primary-outline" type="button">
              <span class="flex">
                {{ $t('general.view_pdf') }}
              </span>
            </BaseButton>
          </router-link>

          <BaseButton
            v-if="isLrReceipt"
            class="mr-3"
            variant="primary-outline"
            type="button"
            :loading="isAutoFilling"
            :disabled="isAutoFilling"
            @click="triggerAutoFill"
          >
            <template #left="slotProps">
              <BaseIcon
                v-if="!isAutoFilling"
                name="SparklesIcon"
                :class="slotProps.class"
              />
            </template>
            Auto Fill LR
          </BaseButton>

          <BaseButton
            :loading="isSaving"
            :disabled="isSaving"
            variant="primary"
            type="submit"
          >
            <template #left="slotProps">
              <BaseIcon
                v-if="!isSaving"
                name="ArrowDownOnSquareIcon"
                :class="slotProps.class"
              />
            </template>
            {{ isTransportReceipt ? saveButtonLabel : (isRecurring ? $t('recurring_invoices.save_invoice') : $t('invoices.save_invoice')) }}
          </BaseButton>
        </template>
      </BasePageHeader>

      <!-- ── Lorry Receipt Pending Payment Alert ────────────────────── -->
      <!-- Shown when saving an Office Invoice whose docket's Lorry Receipt
           is missing Section C (advance_amount) or Section E (net_amount_payable).
           Uses the same visual pattern as the dashboard's overdue alert banner. -->
      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0 -translate-y-2"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div
          v-if="showLorryReceiptAlert"
          class="mt-5 flex items-center justify-between rounded-xl border border-status-red/30 bg-status-red/5 px-5 py-4"
        >
          <div class="flex items-center gap-3">
            <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-status-red/10">
              <BaseIcon name="ExclamationTriangleIcon" class="h-5 w-5 text-status-red" />
            </div>
            <div>
              <p class="text-sm font-semibold text-status-red">
                Lorry Receipt Payment Incomplete — Docket {{ pendingLorryReceipt.docketNumber }}
              </p>
              <p class="text-xs text-muted mt-0.5">
                {{ pendingLorryReceipt.message }}
              </p>
            </div>
          </div>
          <div class="flex items-center gap-3">
            <BaseButton
              v-if="pendingLorryReceipt.lorry_receipt_id"
              size="sm"
              variant="primary"
              @click="goToLorryReceiptEdit"
            >
              <BaseIcon name="PencilSquareIcon" class="w-4 h-4 mr-1.5" />
              Edit Lorry Receipt
            </BaseButton>
            <BaseButton
              size="sm"
              variant="danger"
              @click="showLorryReceiptAlert = false"
            >
              Dismiss
            </BaseButton>
          </div>
        </div>
      </Transition>

      <!-- Single white card wrapping all form content — matches CustomerCreateView layout -->
      <BaseCard class="mt-5">
        <!-- Section 1: Booking / Invoice Information -->
        <div class="grid grid-cols-5 gap-4 mb-8">
          <h6 class="col-span-5 text-lg font-semibold text-left lg:col-span-1">
            {{ isLrReceipt ? 'Booking Information' : isOfficeInvoice ? 'Invoice Information' : 'Invoice Details' }}
          </h6>

          <div class="col-span-5 lg:col-span-4">
            <InvoiceBasicFields
              :v="v$"
              :is-loading="isLoadingContent"
              :is-edit="isEdit"
              :is-recurring="isRecurring"
              :show-consignee="isLrReceipt"
              :is-transport-receipt="isTransportReceipt"
            />
          </div>
        </div>





        <!-- Transport-specific sections -->
        <template v-if="isTransportReceipt">
          <!-- Lorry Receipt: Owner/Driver/Broker party selector -->
          <template v-if="isLorryReceipt">
            <BaseDivider class="mb-5 md:mb-8" />

            <div class="grid grid-cols-5 gap-4 mb-8">
              <h6 class="col-span-5 text-lg font-semibold text-left lg:col-span-1">
                Party Details
              </h6>

              <div class="col-span-5 lg:col-span-4">
                <LorryReceiptPartyFields />
              </div>
            </div>
          </template>

          <!-- LR Receipt: Truck selector is now in TransportCustomFields
               (Trip Details → Truck No uses TruckSelectPopup with Add New Truck) -->


          <!-- Office Invoice: Consignment items table with Add New Item -->
          <template v-if="isOfficeInvoice">
            <BaseDivider class="mb-5 md:mb-8" />

            <div class="grid grid-cols-5 gap-4 mb-8">
              <h6 class="col-span-5 text-lg font-semibold text-left lg:col-span-1">
                Consignment Items
              </h6>

              <div class="col-span-5 lg:col-span-4">
                <OfficeInvoiceItemsTable
                  :is-edit="isEdit"
                  :is-loading="isLoadingContent"
                  :store="invoiceStore"
                  store-prop="newInvoice"
                  :item-validation-scope="invoiceValidationScope"
                  @consignment-valid="onConsignmentValidChange"
                />
              </div>
            </div>
          </template>

          <!-- Transport fields (lr_receipt, lorry_receipt only) -->
          <template v-if="!isOfficeInvoice">
            <BaseDivider class="mb-5 md:mb-8" />

            <div class="grid grid-cols-5 gap-4 mb-8">
              <h6 class="col-span-5 text-lg font-semibold text-left lg:col-span-1">
                Transport Details
              </h6>

              <div class="col-span-5 lg:col-span-4">
                <TransportCustomFields
                  :is-loading="isLoadingContent"
                  :store="invoiceStore"
                  store-prop="newInvoice"
                  :template-name="transportTemplateName"
                />
              </div>
            </div>
          </template>

          <!-- Lorry Receipt: Document uploads removed — documents (RC, PAN, Insurance,
               License, etc.) are now captured when creating/editing Owner, Driver,
               and Broker profiles via the LorryPartyProfileModal. -->
        </template>

        <!-- Standard Invoice: Items, Notes, Custom Fields, Totals -->
        <template v-if="!isTransportEntryTemplate">
          <BaseDivider class="mb-5 md:mb-8" />

          <!-- Items Table -->
          <div class="grid grid-cols-5 gap-4 mb-8">
            <h6 class="col-span-5 text-lg font-semibold text-left lg:col-span-1">
              Items
            </h6>

            <div class="col-span-5 lg:col-span-4">
              <DocumentItemsTable
                :currency="invoiceStore.newInvoice.selectedCurrency"
                :is-loading="isLoadingContent"
                :item-validation-scope="invoiceValidationScope"
                :store="invoiceStore"
                store-prop="newInvoice"
              />
            </div>
          </div>

          <BaseDivider class="mb-5 md:mb-8" />

          <!-- Notes, Custom Fields & Totals -->
          <div class="grid grid-cols-5 gap-4 mb-8">
            <h6 class="col-span-5 text-lg font-semibold text-left lg:col-span-1">
              Notes & Totals
            </h6>

            <div class="col-span-5 lg:col-span-4">
              <div class="block invoice-foot lg:flex lg:justify-between lg:items-start">
                <div class="relative w-full lg:w-1/2 lg:mr-4">
                  <DocumentNotes
                    :store="invoiceStore"
                    store-prop="newInvoice"
                    :fields="invoiceNoteFieldList"
                    type="Invoice"
                  />

                  <InvoiceCustomFields
                    v-if="!isTransportReceipt"
                    type="Invoice"
                    :is-edit="isEdit"
                    :is-loading="isLoadingContent"
                    :store="invoiceStore"
                    store-prop="newInvoice"
                    :custom-field-scope="invoiceValidationScope"
                    class="mb-6"
                  />
                </div>

                <DocumentTotals
                  :currency="invoiceStore.newInvoice.selectedCurrency"
                  :is-loading="isLoadingContent"
                  :store="invoiceStore"
                  store-prop="newInvoice"
                  tax-popup-type="invoice"
                />
              </div>
            </div>
          </div>
        </template>
      </BaseCard>
    </form>
  </BasePage>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import cloneDeep from 'lodash/cloneDeep'
import { parseISO, isValid, addDays, format, differenceInCalendarDays } from 'date-fns'
import {
  required,
  maxLength,
  helpers,
  requiredIf,
  decimal,
} from '@vuelidate/validators'
import useVuelidate from '@vuelidate/core'
import { useInvoiceStore } from '../store'
import { useRecurringInvoiceStore } from '@/scripts/features/company/recurring-invoices/store'
import { useCompanyStore } from '@/scripts/stores/company.store'
import { useNotificationStore } from '@/scripts/stores/notification.store'
import { useDashboardStore } from '@/scripts/features/company/dashboard/store'
import { client as httpClient } from '@/scripts/api/client'
import { invoiceService } from '@/scripts/api/services/invoice.service'
import InvoiceBasicFields from '../components/InvoiceBasicFields.vue'

import InvoiceCustomFields from '@/scripts/features/company/customers/components/CreateCustomFields.vue'
import TransportCustomFields from '../components/TransportCustomFields.vue'
import LorryReceiptPartyFields from '../components/LorryReceiptPartyFields.vue'
import LorryPartyProfileModal from '../components/LorryPartyProfileModal.vue'

import OfficeInvoiceItemsTable from '../components/OfficeInvoiceItemsTable.vue'

import {
  DocumentItemsTable,
  DocumentTotals,
  DocumentNotes,
} from '../../../shared/document-form'

const invoiceStore = useInvoiceStore()
const recurringInvoiceStore = useRecurringInvoiceStore()
const companyStore = useCompanyStore()
const notificationStore = useNotificationStore()
const dashboardStore = useDashboardStore()
const { t } = useI18n()
const route = useRoute()
const router = useRouter()

const invoiceValidationScope = 'newInvoice'
const isSaving = ref<boolean>(false)
const isMarkAsDefault = ref<boolean>(false)
const isRecurring = ref<boolean>(false)

// Tracks whether all consignment numbers in the Office Invoice are valid
// (i.e., each matches an existing LR Receipt). Updated via emit from
// OfficeInvoiceItemsTable.
const allConsignmentsValid = ref<boolean>(false)

function onConsignmentValidChange(allValid: boolean): void {
  allConsignmentsValid.value = allValid
}

// ── Lorry Receipt pending payment alert ──────────────────────────
// When saving an Office Invoice, we check if the Lorry Receipt for each
// docket has both Section C (advance_amount) and Section E (net_amount_payable)
// filled. If not, we block the save and show an alert with a link to edit
// the Lorry Receipt.
interface PendingLorryReceipt {
  message: string
  lorry_receipt_id: number | null
  lorry_receipt_number: string | null
  docket_number: string
}

const showLorryReceiptAlert = ref<boolean>(false)
const pendingLorryReceipt = ref<PendingLorryReceipt>({
  message: '',
  lorry_receipt_id: null,
  lorry_receipt_number: null,
  docket_number: '',
})

function goToLorryReceiptEdit(): void {
  if (pendingLorryReceipt.value.lorry_receipt_id) {
    router.push(`/admin/lorry-receipts/${pendingLorryReceipt.value.lorry_receipt_id}/edit`)
  }
}

// --- Auto Fill LR ---
const isAutoFilling = ref<boolean>(false)
const fileInput = ref<HTMLInputElement | null>(null)

function triggerAutoFill(): void {
  fileInput.value?.click()
}

async function onAutoFillFileSelected(event: Event): Promise<void> {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]
  if (!file) return

  isAutoFilling.value = true

  const formData = new FormData()
  formData.append('file', file)

  try {
    const response = await httpClient.post('/api/v1/invoices/auto-fill', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })

    const data = response.data as Record<string, unknown>

    // 1. Populate Consignor and Consignee (customer)
    if (data.consignor) {
      invoiceStore.newInvoice.customer = data.consignor as typeof invoiceStore.newInvoice.customer
      invoiceStore.newInvoice.customer_id = (data.consignor as Record<string, unknown>).id as string
    }
    if (data.consignee) {
      invoiceStore.newInvoice.consignee_customer = data.consignee as typeof invoiceStore.newInvoice.consignee_customer
      invoiceStore.newInvoice.consignee_customer_id = (data.consignee as Record<string, unknown>).id as string
    }

    // 2. Populate basic fields
    if (data.date) {
      invoiceStore.newInvoice.invoice_date = data.date as string
    }
    if (data.due_date) {
      invoiceStore.newInvoice.due_date = data.due_date as string
    }
    if (data.docket_no) {
      invoiceStore.newInvoice.invoice_number = data.docket_no as string
    }

    // 3. Populate custom fields (From, To, Truck No, Mode of Payment, GST Tax Payable By, etc.)
    if (data.fields) {
      Object.entries(data.fields as Record<string, string>).forEach(([label, value]) => {
        setInvoiceCustomField(label, value)
      })
    }

    // 4. Populate item custom fields on the first item row
    if (data.item_fields && invoiceStore.newInvoice.items?.[0]) {
      Object.entries(data.item_fields as Record<string, string>).forEach(([label, value]) => {
        setItemCustomField(0, label, value)
      })
    }
  } catch (error) {
    console.error('Failed to auto-fill LR receipt:', error)
    notificationStore.showNotification({
      type: 'error',
      message: (error as { response?: { data?: { error?: string } } })?.response?.data?.error || 'Failed to auto-fill LR receipt. Please try again.',
    })
  } finally {
    isAutoFilling.value = false
    if (fileInput.value) {
      fileInput.value.value = ''
    }
  }
}

function setInvoiceCustomField(label: string, value: string | null): void {
  const normalizedLabel = label.toLowerCase().replace(/[^a-z0-9]/g, '')
  const field = invoiceStore.newInvoice.customFields?.find(
    (f) => (f.custom_field?.label ?? '').toLowerCase().replace(/[^a-z0-9]/g, '') === normalizedLabel,
  )
  if (field) {
    if (field.custom_field?.type === 'Number') {
      field.number_answer = value ? parseFloat(value) || 0 : 0
    } else {
      field.string_answer = value || ''
    }
  }
}

function setItemCustomField(itemIndex: number, label: string, value: string | null): void {
  const item = invoiceStore.newInvoice.items?.[itemIndex]
  if (!item) return

  const normalizedLabel = label.toLowerCase().replace(/[^a-z0-9]/g, '')
  const field = item.customFields?.find(
    (f) => (f.custom_field?.label ?? '').toLowerCase().replace(/[^a-z0-9]/g, '') === normalizedLabel,
  )
  if (field) {
    if (field.custom_field?.type === 'Number') {
      field.number_answer = value ? parseFloat(value) || 0 : 0
    } else {
      field.string_answer = value || ''
    }
  }
}


// Auto-due-date bookkeeping: tracks whether the user has manually edited the
// due date, so the invoice_date watcher can avoid clobbering a manual value.
const dueDateManuallyChanged = ref<boolean>(false)
let isAutoUpdatingDueDate = false
const expectedAutoDueDate = ref<string | null>(null)

const invoiceNoteFieldList = ref<string[]>(['customer', 'company', 'invoice'])

const isLoadingContent = computed<boolean>(
  () => invoiceStore.isFetchingInvoice || invoiceStore.isFetchingInitialSettings,
)

// --- Transport receipt detection (mirrors ssgls InvoiceCreate.vue) ---
const isLrReceipt = computed<boolean>(() => route.path.includes('/admin/lr-receipts'))
const isLorryReceipt = computed<boolean>(() => route.path.includes('/admin/lorry-receipts'))
const isStandardInvoice = computed<boolean>(() => route.path.includes('/admin/standard-invoices'))
// Standard invoices (via /admin/standard-invoices) use the upstream
// InvoiceShelf flow (items table, taxes, discounts, template selector).
// Invoice Receipts (/admin/invoices), LR Receipts, and Lorry Receipts
// are transport receipts with their own custom field layouts.
const isTransportReceipt = computed<boolean>(() => !isStandardInvoice.value)
const transportTemplateName = computed<string>(() => {
  // Standard invoices use the upstream InvoiceShelf flow (items table, taxes,
  // discounts). They must NOT fall through to 'office_invoice', otherwise the
  // consignment-number validation and Office Invoice items table are activated.
  if (isStandardInvoice.value) {
    return invoiceStore.newInvoice.template_name || 'invoice1'
  }
  if (isLorryReceipt.value) {
    return companyStore.selectedCompanySettings.default_lorry_receipt_template || 'lorry_receipt'
  }
  if (isLrReceipt.value) {
    return companyStore.selectedCompanySettings.default_lr_receipt_template || 'lr_receipt'
  }
  return companyStore.selectedCompanySettings.default_office_invoice_template || 'office_invoice'
})


const isOfficeInvoice = computed<boolean>(() =>
  transportTemplateName.value === 'office_invoice',
)



// Templates that use custom fields instead of the standard items table
const isTransportEntryTemplate = computed<boolean>(() => {
  return ['office_invoice', 'lr_receipt', 'lorry_receipt'].includes(
    invoiceStore.newInvoice.template_name ?? '',
  )
})

// Templates that have NO items at all (lr_receipt, lorry_receipt use only
// Invoice-level custom fields). office_invoice DOES use Item-level fields.
const isItemlessTransportTemplate = computed<boolean>(() => {
  return ['lr_receipt', 'lorry_receipt'].includes(
    invoiceStore.newInvoice.template_name ?? '',
  )
})

const indexTitle = computed<string>(() => {
  if (isLorryReceipt.value) return 'Lorry Receipts'
  if (isLrReceipt.value) return 'LR Receipts'
  if (isStandardInvoice.value) return t('invoices.invoice', 2)
  return t('invoices.invoice', 2)
})

const indexPath = computed<string>(() => {
  if (isLorryReceipt.value) return '/admin/lorry-receipts'
  if (isLrReceipt.value) return '/admin/lr-receipts'
  if (isStandardInvoice.value) return '/admin/standard-invoices'
  return '/admin/invoices'
})

const createTitle = computed<string>(() => {
  if (isLorryReceipt.value) return 'New Lorry Receipt'
  if (isLrReceipt.value) return 'New LR Receipt'
  return t('invoices.new_invoice')
})

const editTitle = computed<string>(() => {
  if (isLorryReceipt.value) return 'Edit Lorry Receipt'
  if (isLrReceipt.value) return 'Edit LR Receipt'
  return t('invoices.edit_invoice')
})

const saveButtonLabel = computed<string>(() => {
  if (isLorryReceipt.value) return 'Save Lorry Receipt'
  if (isLrReceipt.value) return 'Save LR'
  return t('invoices.save_invoice')
})


const pageTitle = computed<string>(() =>
  isEdit.value ? editTitle.value : createTitle.value,
)

const isEdit = computed<boolean>(() =>
  route.name === 'invoices.edit' ||
  route.name === 'standard-invoices.edit' ||
  route.name === 'recurring-invoices.edit' ||
  route.name === 'lr-receipts.edit' ||
  route.name === 'lorry-receipts.edit',
)

const isRecurringEdit = computed<boolean>(() =>
  route.name === 'recurring-invoices.edit',
)

const rules = computed(() => {
  if (isRecurring.value) {
    return {
      customer_id: {
        required: helpers.withMessage(t('validation.required'), required),
      },
    }
  }

  const baseRules: Record<string, unknown> = {
    invoice_date: {
      required: helpers.withMessage(t('validation.required'), required),
    },
    reference_number: {
      maxLength: helpers.withMessage(t('validation.price_maxlength'), maxLength(255)),
    },
    customer_id: {
      required: helpers.withMessage(t('validation.required'),
        requiredIf(() => !isLrReceipt.value && !isLorryReceipt.value && !isOfficeInvoice.value)),
    },
    invoice_number: {
      required: helpers.withMessage(t('validation.required'), required),
    },
    exchange_rate: {
      required: requiredIf(() => invoiceStore.showExchangeRate && !isTransportReceipt.value),
      decimal: helpers.withMessage(t('validation.valid_exchange_rate'), decimal),
    },
  }

  // Office Invoice: each item must have a consignment number
  if (isOfficeInvoice.value) {
    baseRules.items = {
      required: helpers.withMessage('At least one consignment item is required', required),
      $each: helpers.forEach({
        consignment_number: {
          required: helpers.withMessage('Consignment No is required', required),
        },
      }),
    }
  }

  return baseRules
})

const v$ = useVuelidate(
  rules,
  computed(() => invoiceStore.newInvoice),
  { $scope: invoiceValidationScope },
)

// Initialization
invoiceStore.resetCurrentInvoice()
v$.value.$reset

// Set the transport template name before fetching initial settings
if (isTransportReceipt.value) {
  invoiceStore.newInvoice.template_name = transportTemplateName.value
}

// Check for recurring mode
if (route.query.recurring === '1' || isRecurringEdit.value) {
  isRecurring.value = true
}

// Initialize recurring store
recurringInvoiceStore.initFrequencies(t)

if (isRecurringEdit.value) {
  // Editing a recurring invoice — load its data into both stores
  recurringInvoiceStore.resetCurrentRecurringInvoice()
  recurringInvoiceStore.fetchRecurringInvoiceInitialSettings(
    true,
    { id: route.params.id as string, query: route.query as Record<string, string> },
  ).then(() => {
    // Sync recurring data to invoice store for the shared form fields
    const ri = recurringInvoiceStore.newRecurringInvoice
    invoiceStore.newInvoice.customer = ri.customer
    invoiceStore.newInvoice.customer_id = ri.customer_id
    invoiceStore.newInvoice.items = ri.items as typeof invoiceStore.newInvoice.items
    invoiceStore.newInvoice.taxes = ri.taxes as typeof invoiceStore.newInvoice.taxes
    invoiceStore.newInvoice.notes = ri.notes
    invoiceStore.newInvoice.discount = ri.discount
    invoiceStore.newInvoice.discount_type = ri.discount_type as typeof invoiceStore.newInvoice.discount_type
    invoiceStore.newInvoice.discount_val = ri.discount_val
    invoiceStore.newInvoice.discount_per_item = ri.discount_per_item
    invoiceStore.newInvoice.tax_per_item = ri.tax_per_item
    invoiceStore.newInvoice.tax_included = ri.tax_included
    invoiceStore.newInvoice.template_name = ri.template_name
    invoiceStore.newInvoice.currency_id = ri.currency_id as typeof invoiceStore.newInvoice.currency_id
    invoiceStore.newInvoice.exchange_rate = ri.exchange_rate as typeof invoiceStore.newInvoice.exchange_rate
    invoiceStore.newInvoice.customFields = ri.customFields as typeof invoiceStore.newInvoice.customFields
  })
} else if (!isRecurring.value) {
  // Normal invoice / LR / Lorry Receipt create/edit
  invoiceStore.fetchInvoiceInitialSettings(
    isEdit.value,
    { id: route.params.id as string, query: route.query as Record<string, string> },
  )
} else {
  // New recurring invoice — just initialize
  recurringInvoiceStore.resetCurrentRecurringInvoice()
  invoiceStore.fetchInvoiceInitialSettings(
    false,
    { query: route.query as Record<string, string> },
  )
}

// Ensure transport template is preserved after initial settings load
watch(
  () => invoiceStore.isFetchingInitialSettings,
  (loading) => {
    if (!loading && isTransportReceipt.value && !isEdit.value) {
      invoiceStore.newInvoice.template_name = transportTemplateName.value

      // Pre-select customer when navigated from Receive Material page
      // (?customer=X&from=warehouse)
      const queryCustomer = route.query.customer as string
      if (queryCustomer && !isEdit.value) {
        invoiceStore.selectCustomer(Number(queryCustomer))
      }

      // Auto-fill from an LR Receipt when navigated via "Create Lorry Receipt"
      // or "Create Invoice Receipt" action on the LR Receipt list page (?lr_id=X).
      //
      // For Lorry Receipts: fetches the referenced LR Receipt and populates:
      //   - received_no_bilties  (the LR docket number)
      //   - from_code, to_code, truck_no, no_of_packages, actual_weight,
      //     charged_weight  (transport fields shared between LR & Lorry Receipts)
      //
      // For Office Invoices (Invoice Receipts): fetches the referenced LR Receipt
      // and populates the first consignment item's consignment_number with the
      // LR docket number. The OfficeInvoiceItemsTable's watcher then detects the
      // change and triggers the existing consignment auto-fill logic (fetching
      // transport fields, rate, charges, etc. from the LR Receipt).
      const lrId = route.query.lr_id as string
      if (lrId && isLorryReceipt.value) {
        autoFillFromLrReceipt(Number(lrId))
      } else if (lrId && isOfficeInvoice.value) {
        autoFillOfficeInvoiceFromLrReceipt(Number(lrId))
      }

    }
  },
)

/**
 * Fetch an LR Receipt by ID and auto-fill the Lorry Receipt's transport fields.
 * Called when the user clicks "Create Lorry Receipt" on the LR Receipt list.
 *
 * Field mapping (LR Receipt → Lorry Receipt):
 *   invoice_number  → received_no_bilties  (Docket No → Received No Of Bilties)
 *   from_code       → from_code            (From → From)
 *   to_code         → to_code              (To → To)
 *   truck_no        → truck_no              (Truck No → Lorry No)
 *   no_of_articles  → no_of_packages        (No of Articles → No Of Packages)
 *   actual_weight   → actual_weight         (Actual Weight → Actual Weight)
 *   charged_weight  → charged_weight        (Charged Weight → Charge Weight)
 */
async function autoFillFromLrReceipt(lrId: number): Promise<void> {
  try {
    const response = await invoiceService.get(lrId)
    if (!response?.data) return

    const lr = response.data as Record<string, unknown>

    // Fill received_no_bilties with the LR docket number
    if (lr.invoice_number) {
      invoiceStore.newInvoice.received_no_bilties = lr.invoice_number as string
    }

    // Auto-fill shared transport fields
    if (lr.from_code) invoiceStore.newInvoice.from_code = lr.from_code as string
    if (lr.to_code) invoiceStore.newInvoice.to_code = lr.to_code as string
    if (lr.truck_no) invoiceStore.newInvoice.truck_no = lr.truck_no as string
    if (lr.no_of_articles) invoiceStore.newInvoice.no_of_packages = String(lr.no_of_articles)
    if (lr.actual_weight) invoiceStore.newInvoice.actual_weight = String(lr.actual_weight)
    if (lr.charged_weight) invoiceStore.newInvoice.charged_weight = String(lr.charged_weight)

    notificationStore.showNotification({
      type: 'success',
      message: `Auto-filled from LR Receipt: ${lr.invoice_number ?? ''}`,
    })
  } catch (error) {
    console.error('Failed to auto-fill from LR Receipt:', error)
    notificationStore.showNotification({
      type: 'error',
      message: 'Could not load LR Receipt details for auto-fill.',
    })
  }
}

/**
 * Fetch an LR Receipt by ID and auto-fill the Office Invoice's first
 * consignment item with the LR docket number.
 *
 * Called when the user clicks "Create Invoice Receipt" on the LR Receipt
 * list page (?lr_id=X). Sets the first item's consignment_number to the
 * LR Receipt's invoice_number (docket number). The OfficeInvoiceItemsTable
 * component watches for changes to consignment_number and triggers the
 * existing consignment auto-fill logic, which fetches the full LR Receipt
 * data and populates transport fields (from_code, to_code, truck_no, rate,
 * charges, weight, etc.).
 *
 * Also pre-selects the LR Receipt's customer (consignor) so the
 * ConsignmentSelector dropdown in OfficeInvoiceItemsTable can load
 * recent consignments for that party.
 */
async function autoFillOfficeInvoiceFromLrReceipt(lrId: number): Promise<void> {
  try {
    const response = await invoiceService.get(lrId)
    if (!response?.data) return

    const lr = response.data as Record<string, unknown>

    // Determine which party to select as the main customer (Party field)
    // based on gst_tax_payable_by:
    //   - 'Consignee' → Party = consignee_customer
    //   - 'Consignor' (or null) → Party = customer (consignor)
    const gstPayableBy = (lr.gst_tax_payable_by as string | undefined) ?? ''
    const isConsigneeGst = gstPayableBy.toLowerCase() === 'consignee'

    const partyCustomerId = isConsigneeGst
      ? (lr.consignee_customer_id as number | undefined)
      : (lr.customer_id as number | undefined)

    if (partyCustomerId) {
      await invoiceStore.selectCustomer(partyCustomerId)
    }

    // Set the first item's consignment_number to the LR docket number.
    // OfficeInvoiceItemsTable watches for consignment_number changes and
    // will trigger the existing auto-fill logic (fetching transport fields,
    // rate, charges, etc. from the LR Receipt). The notification is shown
    // by OfficeInvoiceItemsTable.debouncedAutoFill, so we don't show
    // one here to avoid duplicate notifications.
    const firstItem = invoiceStore.newInvoice.items?.[0]
    if (firstItem && lr.invoice_number) {
      firstItem.consignment_number = lr.invoice_number as string
    }
  } catch (error) {
    console.error('Failed to auto-fill Office Invoice from LR Receipt:', error)
    notificationStore.showNotification({
      type: 'error',
      message: 'Could not load LR Receipt details for auto-fill.',
    })
  }
}




// Initialize recurring store when toggled on manually

watch(isRecurring, (newVal) => {
  if (newVal && !isRecurringEdit.value) {
    recurringInvoiceStore.resetCurrentRecurringInvoice()
  }
})

watch(
  () => invoiceStore.newInvoice.customer,
  (newVal) => {
    if (newVal && (newVal as Record<string, unknown>).currency) {
      invoiceStore.newInvoice.selectedCurrency = (
        newVal as Record<string, unknown>
      ).currency as Record<string, unknown>
    }
  },
)

// Detect manual edits to the due date so we know not to overwrite them
// when the invoice date changes afterwards.
watch(
  () => invoiceStore.newInvoice.due_date,
  (newDueDate, oldDueDate) => {
    if (
      !isAutoUpdatingDueDate
      && newDueDate !== oldDueDate
      && oldDueDate !== undefined
      && newDueDate !== expectedAutoDueDate.value
    ) {
      dueDateManuallyChanged.value = true
    }
  },
)

// Auto-compute due_date = invoice_date + invoice_due_date_days whenever the
// invoice date changes, if the company setting opts into automatic dates
// and the user hasn't locked a manual due date in the future.
watch(
  () => invoiceStore.newInvoice.invoice_date,
  (newInvoiceDate, oldInvoiceDate) => {
    if (
      companyStore.selectedCompanySettings?.invoice_set_due_date_automatically !== 'YES'
      || !newInvoiceDate
      || newInvoiceDate === oldInvoiceDate
      || oldInvoiceDate === undefined
    ) {
      return
    }

    const dueDateDays = parseInt(
      companyStore.selectedCompanySettings?.invoice_due_date_days ?? '0',
      10,
    )
    const invoiceDate = parseISO(newInvoiceDate as string)
    if (!isValid(invoiceDate)) {
      return
    }

    const calculatedDueDate = format(addDays(invoiceDate, dueDateDays), 'yyyy-MM-dd')
    expectedAutoDueDate.value = calculatedDueDate

    if (dueDateManuallyChanged.value) {
      const currentDueDate = invoiceStore.newInvoice.due_date
      if (currentDueDate) {
        const currentDue = parseISO(currentDueDate as string)
        if (isValid(currentDue) && differenceInCalendarDays(currentDue, invoiceDate) >= 0) {
          // Manual due date still valid — leave it alone
          return
        }
      }
      // Manual due date is in the past / invalid — take back control
      dueDateManuallyChanged.value = false
    }

    isAutoUpdatingDueDate = true
    invoiceStore.newInvoice.due_date = calculatedDueDate
    isAutoUpdatingDueDate = false
  },
)

/**
 * Prevent Enter key from auto-submitting the form on text inputs.
 * Textareas are excluded so Enter still creates newlines.
 */
function handleEnterKey(event: KeyboardEvent): void {
  if (!(event.target instanceof HTMLTextAreaElement)) {
    event.preventDefault()
  }
}

async function submitForm(): Promise<void> {
  v$.value.$touch()

  if (v$.value.$invalid) {
    console.log('Invoice form invalid. Errors:', JSON.stringify(
      v$.value.$errors.map((e: { $property: string; $message: string }) => `${e.$property}: ${e.$message}`)
    ))
    return
  }

  // Office Invoice: block submission if any consignment number doesn't
  // match an existing LR Receipt
  if (isOfficeInvoice.value && !allConsignmentsValid.value) {
    notificationStore.showNotification({
      type: 'error',
      message: 'One or more consignment numbers do not match existing LR Receipts. Please fix them before saving.',
    })
    return
  }

  // Office Invoice: verify all consignment dockets have complete Lorry Receipts
  // (both Section C — advance_amount — and Section E — net_amount_payable — must
  // be filled). Without both sections, amount_debit cannot be calculated, which
  // means the dashboard LR Profit would be inflated.
  if (isOfficeInvoice.value) {
    const items = invoiceStore.newInvoice.items || []
    for (const item of items) {
      const consignmentNo = (item as Record<string, unknown>).consignment_number as string | undefined
      if (!consignmentNo) continue

      // Find the LR Receipt for this docket number
      const lrResponse = await httpClient.get('/api/v1/lr-receipts', {
        params: { invoice_number: consignmentNo, limit: 1 },
      })
      const lrReceipt = lrResponse.data?.data?.[0]
      if (!lrReceipt) continue

      // Check Lorry Receipt payment status
      const statusResponse = await httpClient.get(
        `/api/v1/lr-receipts/${lrReceipt.id}/lorry-receipt-status`,
      )
      const status = statusResponse.data

      if (!status.is_complete) {
        pendingLorryReceipt.value = {
          message: status.message || 'Lorry Receipt payment details are incomplete.',
          lorry_receipt_id: status.lorry_receipt_id,
          lorry_receipt_number: status.lorry_receipt_number,
          docket_number: consignmentNo,
        }
        showLorryReceiptAlert.value = true
        notificationStore.showNotification({
          type: 'error',
          message: `Lorry Receipt payment incomplete for docket ${consignmentNo}. Please complete Section C and Section E before saving.`,
        })
        return
      }
    }
  }

  isSaving.value = true

  try {
    if (isRecurring.value) {
      const recurringData = recurringInvoiceStore.newRecurringInvoice
      const invoiceData = invoiceStore.newInvoice

      // Build clean payload with only backend-expected fields
      const data: Record<string, unknown> = {
        // Recurring-specific fields
        starts_at: recurringData.starts_at,
        send_automatically: recurringData.send_automatically ? 1 : 0,
        frequency: recurringData.frequency,
        status: recurringData.status || 'ACTIVE',
        limit_by: recurringData.limit_by || 'NONE',
        limit_count: recurringData.limit_count,
        limit_date: recurringData.limit_date,

        // Shared fields from invoice form
        customer_id: invoiceData.customer_id,
        discount: invoiceData.discount,
        discount_type: invoiceData.discount_type,
        discount_val: invoiceData.discount_val,
        discount_per_item: invoiceData.discount_per_item,
        tax_per_item: invoiceData.tax_per_item,
        tax_included: invoiceData.tax_included,
        sales_tax_type: invoiceData.sales_tax_type,
        sales_tax_address_type: invoiceData.sales_tax_address_type,
        notes: invoiceData.notes,
        template_name: invoiceData.template_name,
        items: cloneDeep(invoiceData.items),
        taxes: cloneDeep(invoiceData.taxes),
        currency_id: invoiceData.currency_id,
        exchange_rate: invoiceData.exchange_rate,
        customFields: invoiceData.customFields,

        // Calculated totals
        sub_total: Math.round(invoiceStore.getSubTotal),
        total: Math.round(invoiceStore.getTotal),
        tax: Math.round(invoiceStore.getTotalTax),
      }

      let response
      if (isRecurringEdit.value) {
        data.id = recurringData.id
        response = await recurringInvoiceStore.updateRecurringInvoice(data)
      } else {
        response = await recurringInvoiceStore.addRecurringInvoice(data)
      }
      router.push(`/admin/recurring-invoices/${response.data.data.id}/view`)
    } else {
      const data: Record<string, unknown> = {
        ...cloneDeep(invoiceStore.newInvoice),
        sub_total: Math.round(invoiceStore.getSubTotal),
        total: Math.round(invoiceStore.getTotal),
        tax: Math.round(invoiceStore.getTotalTax),
      }

      // lr_receipt and lorry_receipt use only Invoice-level custom fields —
      // strip the default empty item stubs so the backend doesn't try to
      // insert rows with null names. office_invoice DOES use Item-level
      // custom fields, so items are kept.
      if (isItemlessTransportTemplate.value) {
        delete data.items
        delete data.taxes
      } else if (!isOfficeInvoice.value) {
        const items = data.items as Array<Record<string, unknown>>
        if (data.discount_per_item === 'YES') {
          items.forEach((item, index) => {
            if (item.discount_type === 'fixed') {
              items[index].discount = (item.discount as number) * 100
            }
          })
        } else {
          if (data.discount_type === 'fixed') {
            data.discount = (data.discount as number) * 100
          }
        }

        const taxes = data.taxes as Array<Record<string, unknown>>
        if (data.tax_per_item !== 'YES' && taxes.length) {
          data.tax_type_ids = taxes.map((tax) => tax.tax_type_id)
        }
      }

      const action = isEdit.value
        ? invoiceStore.updateInvoice
        : invoiceStore.addInvoice

      const response = await action(data)

      // Refresh dashboard data after invoice save
      await dashboardStore.loadData()

      // If navigated from Receive Material page, redirect back there
      // with the customer and newly created LR ID auto-filled
      const fromWarehouse = route.query.from as string
      if (fromWarehouse === 'warehouse' && !isEdit.value) {
        const customerId = invoiceStore.newInvoice.customer_id
        const newLrId = response.data.data.id
        router.push({
          path: '/admin/warehouse-items/create',
          query: {
            customer: customerId ? String(customerId) : undefined,
            lr: String(newLrId),
          },
        })
        return
      }

      // Redirect based on the route
      const redirectPath = isLorryReceipt.value
        ? `/admin/lorry-receipts/${response.data.data.id}/view`
        : isLrReceipt.value
          ? `/admin/lr-receipts/${response.data.data.id}/view`
          : isStandardInvoice.value
            ? `/admin/standard-invoices/${response.data.data.id}/view`
            : `/admin/invoices/${response.data.data.id}/view`
      router.push(redirectPath)
    }
  } catch (err) {
    console.error(err)
  }

  isSaving.value = false
}
</script>
