<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCustomerStore } from '../../features/company/customers/store'
import { useModalStore } from '../../stores/modal.store'
import { useUserStore } from '../../stores/user.store'
import { useCompanyStore } from '../../stores/company.store'
import { ABILITIES } from '../../config/abilities'
import CustomerModal from '../../features/company/customers/components/CustomerModal.vue'
import PartyInformationModal from '../../features/company/customers/components/PartyInformationModal.vue'

interface Props {
  modelValue?: string | number | Record<string, unknown> | null
  fetchAll?: boolean
  showAction?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  modelValue: '',
  fetchAll: false,
  showAction: false,
})

const emit = defineEmits<{
  (e: 'update:modelValue', value: string | number | Record<string, unknown> | null): void
}>()

const { t } = useI18n()
const modalStore = useModalStore()
const customerStore = useCustomerStore()
const userStore = useUserStore()
const companyStore = useCompanyStore()

const selectedCustomer = computed({
  get: () => props.modelValue,
  set: (value) => {
    emit('update:modelValue', value as string | number | Record<string, unknown> | null)
  },
})

const isPartyMode = computed<boolean>(() => {
  return companyStore.selectedCompanySettings?.enable_standard_invoices !== 'YES'
})

async function searchCustomers(search: string) {
  const data: Record<string, unknown> = { search }

  if (props.fetchAll) {
    data.limit = 'all'
  }

  const response = await customerStore.fetchCustomers(data)
  const results = response.data ?? []

  if (results.length > 0 && customerStore.editCustomer) {
    const customerFound = results.find(
      (c: Record<string, unknown>) => c.id === customerStore.editCustomer?.id
    )
    if (!customerFound) {
      const editCopy = { ...customerStore.editCustomer }
      results.unshift(editCopy as typeof results[0])
    }
  }

  return results
}

/**
 * Listen for the 'customer-created-for-select-input' event dispatched by
 * PartyInformationModal / CustomerModal after a new party is created from
 * this select input. Auto-selects the newly created customer so the user
 * doesn't have to search for it again.
 */
function onCustomerCreated(e: Event): void {
  const customEvent = e as CustomEvent
  const customer = customEvent.detail?.customer
  if (customer && customer.id) {
    selectedCustomer.value = customer.id
  }
}

onMounted(() => {
  window.addEventListener('customer-created-for-select-input', onCustomerCreated)
})

onUnmounted(() => {
  window.removeEventListener('customer-created-for-select-input', onCustomerCreated)
})

function addCustomer(): void {
  // The @mousedown.prevent.stop on BaseSelectAction prevents the
  // BaseMultiselect wrapper's @mousedown="closeDropdown" from firing,
  // so we close the dropdown manually here before opening the modal.
  document.body.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }))
  ;(document.activeElement as HTMLElement)?.blur()

  nextTick(() => {
    customerStore.resetCurrentCustomer()

    if (isPartyMode.value) {
      modalStore.openModal({
        title: 'Add New Party',
        componentName: 'PartyInformationModal',
        size: 'lg',
      })
    } else {
      modalStore.openModal({
        title: t('customers.add_new_customer'),
        componentName: 'CustomerModal',
      })
    }
  })
}
</script>

<template>
  <BaseMultiselect
    v-model="selectedCustomer"
    v-bind="$attrs"
    track-by="name"
    value-prop="id"
    label="name"
    :filter-results="false"
    resolve-on-load
    :delay="500"
    :searchable="true"
    :options="searchCustomers"
    label-value="name"
    :placeholder="$t('customers.type_or_click')"
    :can-deselect="false"
    class="w-full"
  >
    <template v-if="showAction" #action>
      <BaseSelectAction
        v-if="userStore.hasAbilities(ABILITIES.CREATE_CUSTOMER)"
        @mousedown.prevent.stop="addCustomer"
      >
        <BaseIcon
          name="UserPlusIcon"
          class="h-4 mr-2 -ml-2 text-center text-primary-400"
        />

        {{ isPartyMode ? 'Add New Party' : $t('customers.add_new_customer') }}
      </BaseSelectAction>
    </template>
  </BaseMultiselect>

  <CustomerModal />
  <PartyInformationModal />
</template>
