<script setup lang="ts">
import { Popover, PopoverButton, PopoverPanel } from '@headlessui/vue'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDebounceFn } from '@vueuse/core'
import { useRoute } from 'vue-router'
import { useUserStore } from '@/scripts/stores/user.store'
import { useModalStore } from '@/scripts/stores/modal.store'
import { ABILITIES } from '@/scripts/config/abilities'
import { useCustomerStore } from '@/scripts/features/company/customers/store'
import { useInvoiceStore } from '@/scripts/features/company/invoices/store'
import { useEstimateStore } from '@/scripts/features/company/estimates/store'
import { useRecurringInvoiceStore } from '@/scripts/features/company/recurring-invoices/store'
import { useLorryPartyProfileStore } from '@/scripts/features/company/lorry-party-profiles/store'
import { useQuotationStore } from '@/scripts/features/company/quotations/store'
import { useCompanyStore } from '@/scripts/stores/company.store'
import type { LorryPartyProfile } from '@/scripts/types/domain/lorry-party-profile'
import CustomerModal from '@/scripts/features/company/customers/components/CustomerModal.vue'
import PartyInformationModal from '@/scripts/features/company/customers/components/PartyInformationModal.vue'
import LorryPartyProfileModal from '@/scripts/features/company/invoices/components/LorryPartyProfileModal.vue'


type DocumentType = 'estimate' | 'invoice' | 'recurring-invoice' | 'quotation'

interface ValidationError {
  $message: string
}

interface Validation {
  $error: boolean
  $errors: ValidationError[]
}

interface Props {
  valid?: Validation
  customerId?: number | null
  type?: DocumentType | null
  contentLoading?: boolean
  label?: string
  customerType?: string | null
  showLorryProfiles?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  valid: () => ({ $error: false, $errors: [] }),
  customerId: null,
  type: null,
  contentLoading: false,
  label: '',
  customerType: null,
  showLorryProfiles: false,
})


const userStore = useUserStore()
const modalStore = useModalStore()
const { t } = useI18n()
const route = useRoute()

const customerStore = useCustomerStore()
const invoiceStore = useInvoiceStore()
const estimateStore = useEstimateStore()
const recurringInvoiceStore = useRecurringInvoiceStore()
const quotationStore = useQuotationStore()
const lorryPartyProfileStore = useLorryPartyProfileStore()
const companyStore = useCompanyStore()

const search = ref<string | null>(null)
const isSearchingCustomer = ref<boolean>(false)
const lorryProfiles = ref<LorryPartyProfile[]>([])

interface SearchResult {
  id: number
  name: string
  contact_name?: string
  phone?: string
  isLorryProfile?: boolean
  profileType?: string
  profile?: LorryPartyProfile
}

const combinedResults = computed<SearchResult[]>(() => {
  const customers: SearchResult[] = customerStore.customers.map((c: { id: number; name: string; contact_name?: string; phone?: string }) => ({
    id: c.id,
    name: c.name,
    contact_name: c.contact_name,
    phone: c.phone,
  }))

  const profiles: SearchResult[] = lorryProfiles.value.map((p: LorryPartyProfile) => ({
    id: p.id ?? 0,
    name: p.name ?? '',
    phone: p.phone ?? '',
    isLorryProfile: true,
    profileType: p.type ?? '',
    profile: p,
  }))

  return [...customers, ...profiles]
})


const selectedCustomer = computed(() => {
  switch (props.type) {
    case 'invoice':
      return invoiceStore.newInvoice.customer
    case 'estimate':
      return estimateStore.newEstimate.customer
    case 'quotation':
      return quotationStore.newQuotation.customer
    case 'recurring-invoice':
      return recurringInvoiceStore.newRecurringInvoice.customer
    default:
      return null
  }
})

const isPartyMode = computed(() => {
  return companyStore.selectedCompanySettings?.enable_standard_invoices !== 'YES'
})

const isTransportTemplate = computed(() => {
  if (props.type !== 'invoice') return false
  const templateName = invoiceStore.newInvoice.template_name
  return (
    templateName === 'lr_receipt' ||
    templateName === 'lorry_receipt' ||
    templateName === 'office_invoice'
  )
})

// Fetch initial customers on setup
async function fetchInitialCustomers(): Promise<void> {
  await customerStore.fetchCustomers({
    orderByField: '',
    orderBy: '',
    ...(props.customerType ? { type: props.customerType } : {}),
  })

  if (props.showLorryProfiles) {
    await fetchLorryProfiles()
  }
}

async function fetchLorryProfiles(): Promise<void> {
  try {
    const response = await lorryPartyProfileStore.fetchProfiles({
      search: search.value ?? '',
      limit: 'all',
    })
    lorryProfiles.value = response.data || []
  } catch {
    lorryProfiles.value = []
  }
}


// Select customer on setup if customerId is provided
if (props.customerId) {
  if (props.type === 'invoice') {
    invoiceStore.selectCustomer(props.customerId)
  } else if (props.type === 'estimate') {
    estimateStore.selectCustomer(props.customerId)
  } else if (props.type === 'quotation') {
    quotationStore.selectCustomer(props.customerId)
  } else if (props.type === 'recurring-invoice') {
    recurringInvoiceStore.selectCustomer(props.customerId)
  }
}

fetchInitialCustomers()

const debounceSearchCustomer = useDebounceFn(() => {
  isSearchingCustomer.value = true
  searchCustomer()
}, 500)

async function searchCustomer(): Promise<void> {
  await customerStore.fetchCustomers({
    display_name: search.value ?? '',
    page: 1,
    ...(props.customerType ? { type: props.customerType } : {}),
  })

  if (props.showLorryProfiles) {
    await fetchLorryProfiles()
  }

  isSearchingCustomer.value = false
}

function selectLorryProfile(profile: LorryPartyProfile, close: () => void): void {
  // Create a pseudo-customer object from the lorry party profile
  // so the invoice form can display it in the selected customer card
  const pseudoCustomer = {
    id: 0,
    name: profile.name ?? '',
    phone: profile.phone ?? '',
    billing: {
      name: profile.name ?? '',
      address_street_1: profile.address ?? '',
      city: '',
      state: '',
      zip: '',
    },
    shipping: {
      name: profile.name ?? '',
      address_street_1: profile.address ?? '',
      city: '',
      state: '',
      zip: '',
    },
  }

  if (props.type === 'invoice') {
    invoiceStore.newInvoice.customer = pseudoCustomer as never
  }

  close()
  search.value = null
}


function selectNewCustomer(id: number, close: () => void): void {
  const params: Record<string, unknown> = { userId: id }
  if (route.params.id) params.model_id = route.params.id

  if (props.type === 'invoice') {
    // Transport receipt templates (lr_receipt, lorry_receipt, office_invoice)
    // have their own per-template number format (e.g. lr_receipt_number_format).
    // Their document numbers don't depend on the customer, so we skip the
    // getNextNumber call — otherwise the backend falls back to the default
    // invoice_number_format (→ "INV-000001") and overwrites the correct
    // number that was already generated on page load.
    const templateName = invoiceStore.newInvoice.template_name
    const isTransportTemplate =
      templateName === 'lr_receipt' ||
      templateName === 'lorry_receipt' ||
      templateName === 'office_invoice'

    if (!isTransportTemplate) {
      invoiceStore.getNextNumber(params, true)
    }
    invoiceStore.selectCustomer(id)
  } else if (props.type === 'estimate') {
    estimateStore.getNextNumber(params, true)
    estimateStore.selectCustomer(id)
  } else if (props.type === 'quotation') {
    quotationStore.getNextNumber(params, true)
    quotationStore.selectCustomer(id)
  } else if (props.type === 'recurring-invoice') {
    recurringInvoiceStore.selectCustomer(id)
  }

  close()
  search.value = null
}

function resetSelectedCustomer(): void {
  if (props.type === 'invoice') {
    invoiceStore.resetSelectedCustomer()
  } else if (props.type === 'estimate') {
    estimateStore.resetSelectedCustomer()
  } else if (props.type === 'quotation') {
    quotationStore.resetSelectedCustomer()
  } else if (props.type === 'recurring-invoice') {
    recurringInvoiceStore.resetSelectedCustomer()
  }
}

async function editCustomer(): Promise<void> {
  if (!selectedCustomer.value) return
  await customerStore.fetchCustomer(selectedCustomer.value.id)
  customerStore.openContext = 'customer'

  if (isPartyMode.value) {
    // Party Mode: Open PartyInformationModal for editing
    modalStore.openModal({
      title: t('customers.edit_party'),
      componentName: 'PartyInformationModal',
      size: 'lg',
    })
  } else {
    // Invoice Mode: Open CustomerModal for editing
    modalStore.openModal({
      title: t('customers.edit_customer'),
      componentName: 'CustomerModal',
    })
  }
}

function openCustomerModal(close?: () => void): void {
  close?.()
  customerStore.resetCurrentCustomer()

  // Set context so the modal knows to auto-select as the main customer after save.
  // This is critical: if a previous consignee "Add New" set openContext='consignee',
  // we must reset it here so the new customer is selected as the main customer.
  customerStore.openContext = 'customer'

  if (isPartyMode.value) {
    // Open Party Information Modal when Invoice is disabled
    modalStore.openModal({
      title: t('customers.new_party'),
      componentName: 'PartyInformationModal',
      size: 'lg',
    })
  } else {
    // Open Customer Modal when Invoice is enabled
    modalStore.openModal({
      title: t('customers.add_customer'),
      componentName: 'CustomerModal',
      size: 'md',
    })
  }
}

function initGenerator(name: string): string {
  if (name) {
    const nameSplit = name.split(' ')
    return nameSplit[0].charAt(0).toUpperCase()
  }
  return ''
}
</script>

<template>
  <div>
    <!-- Modals rendered outside of Popover to avoid focus/z-index issues -->
    <CustomerModal />
    <PartyInformationModal />
    <LorryPartyProfileModal />

    <BaseContentPlaceholders v-if="contentLoading">
      <BaseContentPlaceholdersBox
        :rounded="true"
        class="w-full"
        style="min-height: 170px"
      />
    </BaseContentPlaceholders>

    <div v-else class="max-h-[173px]">
    <div
      v-if="selectedCustomer"
      class="
        flex flex-col
        p-4
        bg-surface
        border border-line-light border-solid
        min-h-[170px]
        rounded-xl
        shadow
      "
      @click.stop
    >
      <div class="flex relative justify-between mb-2">
        <BaseText
          :text="selectedCustomer.name"
          class="flex-1 text-base font-medium text-left text-heading"
        />
        <div class="flex">
          <a
            class="
              relative
              my-0
              ml-6
              text-sm
              font-medium
              cursor-pointer
              text-primary-500
              items-center
              flex
            "
            @click.stop="editCustomer"
          >
            <BaseIcon name="PencilIcon" class="text-muted h-4 w-4 mr-1" />

            {{ $t('general.edit') }}
          </a>
          <a
            class="
              relative
              my-0
              ml-6
              text-sm
              flex
              items-center
              font-medium
              cursor-pointer
              text-primary-500
            "
            @click="resetSelectedCustomer"
          >
            <BaseIcon name="XCircleIcon" class="text-muted h-4 w-4 mr-1" />
            {{ $t('general.deselect') }}
          </a>
        </div>
      </div>
      <div class="grid grid-cols-1 gap-4 mt-2">
        <!-- Party Mode: Show address and phone -->
        <div v-if="isPartyMode" class="flex flex-col">
          <div
            v-if="selectedCustomer.billing?.address_street_1 || selectedCustomer.phone"
            class="flex flex-col"
          >
            <label
              v-if="selectedCustomer.billing?.address_street_1"
              class="relative w-11/12 text-sm text-muted truncate"
            >
              📍 {{ selectedCustomer.billing.address_street_1 }}
            </label>
            <label
              v-if="selectedCustomer.phone"
              class="relative w-11/12 text-sm text-muted truncate mt-1"
            >
              ☎️ {{ selectedCustomer.phone }}
            </label>
          </div>
          <div v-else class="text-sm text-muted italic">
            {{ $t('general.no_details') }}
          </div>
        </div>

        <!-- Invoice Mode: Show Bill To and Ship To -->
        <template v-else>
          <div class="grid grid-cols-2 gap-8">
            <div v-if="selectedCustomer.billing" class="flex flex-col">
          <label
            class="
              mb-1
              text-sm
              font-medium
              text-left text-subtle
              uppercase
              whitespace-nowrap
            "
          >
            {{ $t('general.bill_to') }}
          </label>

          <div
            v-if="selectedCustomer.billing"
            class="flex flex-col flex-1 p-0 text-left"
          >
            <label
              v-if="selectedCustomer.billing.name"
              class="relative w-11/12 text-sm truncate"
            >
              {{ selectedCustomer.billing.name }}
            </label>

            <label class="relative w-11/12 text-sm truncate">
              <span v-if="selectedCustomer.billing.city">
                {{ selectedCustomer.billing.city }}
              </span>
              <span
                v-if="
                  selectedCustomer.billing.city &&
                  selectedCustomer.billing.state
                "
              >
                ,
              </span>
              <span v-if="selectedCustomer.billing.state">
                {{ selectedCustomer.billing.state }}
              </span>
            </label>
            <label
              v-if="selectedCustomer.billing.zip"
              class="relative w-11/12 text-sm truncate"
            >
              {{ selectedCustomer.billing.zip }}
            </label>
          </div>
        </div>

        <div v-if="selectedCustomer.shipping" class="flex flex-col">
          <label
            class="
              mb-1
              text-sm
              font-medium
              text-left text-subtle
              uppercase
              whitespace-nowrap
            "
          >
            {{ $t('general.ship_to') }}
          </label>

          <div
            v-if="selectedCustomer.shipping"
            class="flex flex-col flex-1 p-0 text-left"
          >
            <label
              v-if="selectedCustomer.shipping.name"
              class="relative w-11/12 text-sm truncate"
            >
              {{ selectedCustomer.shipping.name }}
            </label>

            <label class="relative w-11/12 text-sm truncate">
              <span v-if="selectedCustomer.shipping.city">
                {{ selectedCustomer.shipping.city }}
              </span>
              <span
                v-if="
                  selectedCustomer.shipping.city &&
                  selectedCustomer.shipping.state
                "
              >
                ,
              </span>
              <span v-if="selectedCustomer.shipping.state">
                {{ selectedCustomer.shipping.state }}
              </span>
            </label>
            <label
              v-if="selectedCustomer.shipping.zip"
              class="relative w-11/12 text-sm truncate"
            >
              {{ selectedCustomer.shipping.zip }}
            </label>
          </div>
        </div>
            </div>
          </template>
      </div>
    </div>

    <Popover v-else v-slot="{ open }" class="relative flex flex-col rounded-xl">
      <PopoverButton
        :class="{
          '': open,
          'border border-solid border-alert-error-text focus:ring-alert-error-text rounded':
            valid.$error,
          'focus:ring-2 focus:ring-primary-400': !valid.$error,
        }"
        class="w-full outline-hidden rounded-xl"
      >
        <div
          class="
            relative
            flex
            justify-center
            px-0
            p-0
            py-16
            bg-surface
            border border-line-light border-solid
            rounded-xl
            shadow
            min-h-[170px]
          "
        >
          <BaseIcon
            name="UserIcon"
            class="
              flex
              justify-center
              !w-10
              !h-10
              p-2
              mr-5
              text-sm text-white
              bg-surface-muted
              rounded-full
              font-base
            "
          />

          <div class="mt-1">
            <label class="text-lg font-medium text-heading">
              {{ label || $t('customers.new_customer') }}
              <span class="text-alert-error-text"> * </span>
            </label>

            <p
              v-if="valid.$error && valid.$errors[0]?.$message"
              class="text-alert-error-text text-sm absolute right-3 bottom-3"
            >
              {{ $t('estimates.errors.required') }}
            </p>
          </div>
        </div>
      </PopoverButton>

      <!-- Customer Select Popup -->
      <transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="translate-y-1 opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="translate-y-0 opacity-100"
        leave-to-class="translate-y-1 opacity-0"
      >
        <div v-if="open" class="absolute min-w-full z-10">
          <PopoverPanel
            v-slot="{ close }"
            static
            class="
              overflow-hidden
              rounded-xl
              shadow
              ring-1 ring-black/5
              bg-surface
            "
          >
            <div class="relative">
              <BaseInput
                v-model="search"
                container-class="m-4"
                :placeholder="$t('general.search')"
                type="text"
                icon="search"
                @update:model-value="() => debounceSearchCustomer()"
              />

              <ul
                class="
                  max-h-80
                  flex flex-col
                  overflow-auto
                  list
                  border-t border-line-light
                "
              >
                <li
                  v-for="(item, index) in (showLorryProfiles ? combinedResults : customerStore.customers.map((c: { id: number; name: string; contact_name?: string }) => ({ ...c, isLorryProfile: false })))"
                  :key="index"
                  href="#"
                  class="
                    flex
                    px-6
                    py-2
                    border-b border-line-light border-solid
                    cursor-pointer
                    hover:cursor-pointer hover:bg-hover-strong
                    focus:outline-hidden focus:bg-surface-tertiary
                    last:border-b-0
                  "
                  @click="item.isLorryProfile ? selectLorryProfile(item.profile!, close) : selectNewCustomer(item.id, close)"
                >
                  <span
                    class="
                      flex
                      items-center
                      content-center
                      justify-center
                      w-10
                      h-10
                      mr-4
                      text-xl
                      font-semibold
                      leading-9
                      text-white
                      bg-surface-muted
                      rounded-full
                      avatar
                    "
                  >
                    {{ initGenerator(item.name) }}
                  </span>

                  <div class="flex flex-col justify-center text-left">
                    <div class="flex items-center gap-2">
                      <BaseText
                        v-if="item.name"
                        :text="item.name"
                        class="
                          m-0
                          text-base
                          font-normal
                          leading-tight
                          cursor-pointer
                        "
                      />
                      <span
                        v-if="item.isLorryProfile"
                        class="text-xs px-2 py-0.5 rounded-full bg-primary-100 text-primary-700 font-medium"
                      >
                        {{ item.profileType }}
                      </span>
                    </div>
                    <BaseText
                      v-if="item.contact_name"
                      :text="item.contact_name"
                      class="
                        m-0
                        text-sm
                        font-medium
                        text-subtle
                        cursor-pointer
                      "
                    />
                    <BaseText
                      v-if="item.phone"
                      :text="item.phone"
                      class="
                        m-0
                        text-xs
                        text-muted
                        cursor-pointer
                      "
                    />
                  </div>
                </li>
                <div
                  v-if="(showLorryProfiles ? combinedResults : customerStore.customers).length === 0"
                  class="flex justify-center p-5 text-subtle"
                >
                  <label class="text-base text-muted cursor-pointer">
                    {{ isPartyMode ? 'No Party Found' : $t('customers.no_customers_found') }}
                  </label>
                </div>
              </ul>
            </div>

            <button
              v-if="userStore.hasAbilities(ABILITIES.CREATE_CUSTOMER)"
              type="button"
              class="
                h-10
                flex
                items-center
                justify-center
                w-full
                px-2
                py-3
                bg-surface-muted
                border-none
                outline-hidden
                focus:bg-surface-muted
              "
              @click="openCustomerModal(close)"
            >
              <BaseIcon name="UserPlusIcon" class="text-primary-400" />

              <label
                class="
                  m-0
                  ml-3
                  text-sm
                  leading-none
                  cursor-pointer
                  font-base
                  text-primary-400
                "
              >
                {{ isPartyMode ? 'Add New Party' : $t('customers.add_new_customer') }}
              </label>
            </button>
          </PopoverPanel>
        </div>
      </transition>
    </Popover>
  </div>
  </div>
</template>
