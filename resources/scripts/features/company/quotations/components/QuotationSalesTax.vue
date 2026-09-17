<template>
  <QuotationTaxationAddressModal @add-tax="addSalesTax" />
</template>

<script setup lang="ts">
import { computed, watch, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useModalStore } from '@/scripts/stores/modal.store'
import { useCompanyStore } from '@/scripts/stores/company.store'
import { isEqual, pick } from 'lodash'
import QuotationTaxationAddressModal from './QuotationTaxationAddressModal.vue'

// Types
interface Address {
  address_street_1?: string
  city?: string
  state?: string
  zip?: string
}

interface Customer {
  id: number
  billing?: Address
  shipping?: Address
}

interface SalesTaxData {
  id: number
  name: string
  type: string
  [key: string]: unknown
}

interface StoreProp {
  sales_tax_address_type: string
  sales_tax_type: string
  taxes: Array<{ name: string; type: string; [key: string]: unknown }>
}

interface Props {
  isEdit?: boolean | null
  type?: string | null
  customer?: Customer | null
  store?: Record<string, StoreProp>
  storeProp?: string | null
}

// Constants
const SALES_TAX_US = 'Sales Tax'
const SALES_TAX_MODULE = 'MODULE'

// Define Props
const props = withDefaults(defineProps<Props>(), {
  isEdit: null,
  type: null,
  customer: null,
  store: () => ({}) as Record<string, StoreProp>,
  storeProp: null,
})

// Stores
const modalStore = useModalStore()
const companyStore = useCompanyStore()
const { t } = useI18n()

// Reactive state
const fetchingTax = ref(false)

// Computed properties
const isSalesTaxTypeBilling = computed(() => {
  if (!props.store || !props.storeProp) return false
  return props.isEdit
    ? props.store[props.storeProp]?.sales_tax_address_type === 'billing'
    : companyStore.selectedCompanySettings?.sales_tax_address_type === 'billing'
})

const salesTaxEnabled = computed(() => {
  return companyStore.selectedCompanySettings?.sales_tax_us_enabled === 'YES'
})

const salesTaxCustomerLevel = computed(() => {
  if (!props.store || !props.storeProp) return false
  return props.isEdit
    ? props.store[props.storeProp]?.sales_tax_type === 'customer_level'
    : companyStore.selectedCompanySettings?.sales_tax_type === 'customer_level'
})

const salesTaxCompanyLevel = computed(() => {
  if (!props.store || !props.storeProp) return false
  return props.isEdit
    ? props.store[props.storeProp]?.sales_tax_type === 'company_level'
    : companyStore.selectedCompanySettings?.sales_tax_type === 'company_level'
})

const addressData = computed(() => {
  if (salesTaxCustomerLevel.value && isAddressAvailable.value && props.customer) {
    const address = isSalesTaxTypeBilling.value
      ? props.customer.billing
      : props.customer.shipping
    return {
      address: pick(address, ['address_street_1', 'city', 'state', 'zip']),
      customer_id: props.customer.id,
    }
  } else if (salesTaxCompanyLevel.value && isAddressAvailable.value) {
    const address = companyStore.selectedCompany?.address
    return {
      address: pick(address, ['address_street_1', 'city', 'state', 'zip']),
    }
  }
  return null
})

const isAddressAvailable = computed(() => {
  if (salesTaxCustomerLevel.value && props.customer) {
    const address = isSalesTaxTypeBilling.value
      ? props.customer.billing
      : props.customer.shipping

    return hasAddress(address)
  } else if (salesTaxCompanyLevel.value) {
    return hasAddress(companyStore.selectedCompany?.address)
  }
  return false
})

// Watchers
watch(
  () => props.customer,
  (newVal, oldVal) => {
    if (newVal && oldVal && salesTaxCustomerLevel.value) {
      // call if customer changed address
      isCustomerAddressChanged(newVal, oldVal)
      return
    }
    if (!isAddressAvailable.value && salesTaxCustomerLevel.value && newVal) {
      setTimeout(() => {
        openAddressModal()
      }, 500)
    } else if (salesTaxCustomerLevel.value && newVal) {
      fetchSalesTax()
    } else if (salesTaxCustomerLevel.value && !newVal) {
      removeSalesTax()
    }
  }
)

// Lifecycle
onMounted(() => {
  if (salesTaxCompanyLevel.value) {
    isAddressAvailable.value ? fetchSalesTax() : openAddressModal()
  }
})

// Functions
function hasAddress(address: Address | undefined): boolean {
  if (!address) return false

  return !!(
    address.address_street_1 && address.city && address.state && address.zip
  )
}

function isCustomerAddressChanged(newV: Customer, oldV: Customer): void {
  const newData = isSalesTaxTypeBilling.value ? newV.billing : newV.shipping
  const oldData = isSalesTaxTypeBilling.value ? oldV.billing : oldV.shipping

  const newAdd = pick(newData, ['address_street_1', 'city', 'state', 'zip'])
  const oldAdd = pick(oldData, ['address_street_1', 'city', 'state', 'zip'])
  if (!isEqual(newAdd, oldAdd)) {
    fetchSalesTax()
  }
}

function openAddressModal(): void {
  if (!salesTaxEnabled.value) return
  if (!props.store || !props.storeProp) return

  let modalData: Address | undefined = null
  let title = ''
  if (salesTaxCustomerLevel.value && props.customer) {
    if (isSalesTaxTypeBilling.value) {
      modalData = props.customer.billing
      title = t('settings.taxations.add_billing_address')
    } else {
      modalData = props.customer.shipping
      title = t('settings.taxations.add_shipping_address')
    }
  } else {
    modalData = companyStore.selectedCompany?.address
    title = t('settings.taxations.add_company_address')
  }

  modalStore.openModal({
    title: title,
    content: t('settings.taxations.modal_description'),
    componentName: 'TaxationAddressModal',
    data: modalData,
    id: salesTaxCustomerLevel.value ? props.customer?.id : '',
  })
}

async function fetchSalesTax(): Promise<void> {
  if (!salesTaxEnabled.value || !addressData.value) return

  fetchingTax.value = true
  try {
    // Note: taxTypeStore.fetchSalesTax needs to be called from appropriate service
    // This is a placeholder - actual implementation depends on service availability
    // await taxTypeStore.fetchSalesTax(addressData.value)
  } catch (err) {
    if ((err as any).response?.data?.error) {
      setTimeout(() => {
        openAddressModal()
      }, 500)
    }
  } finally {
    fetchingTax.value = false
  }
}

function addSalesTax(tax: SalesTaxData): void {
  if (!props.store || !props.storeProp) return

  const taxData = { ...tax, tax_type_id: tax.id }

  const i = props.store[props.storeProp].taxes.findIndex(
    (_t) => _t.name === SALES_TAX_US && _t.type === SALES_TAX_MODULE
  )

  if (i > -1) {
    Object.assign(props.store[props.storeProp].taxes[i], taxData)
  } else {
    props.store[props.storeProp].taxes.push(taxData)
  }
}

function removeSalesTax(): void {
  if (!props.store || !props.storeProp) return

  // remove from total taxes
  const i = props.store[props.storeProp].taxes.findIndex(
    (_t) => _t.name === SALES_TAX_US && _t.type === SALES_TAX_MODULE
  )
  if (i > -1) {
    props.store[props.storeProp].taxes.splice(i, 1)
  }
}
</script>
