<script setup lang="ts">
import { computed, ref, reactive, watch, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { debouncedWatch } from '@vueuse/core'
import { useInvoiceStore } from '../../invoices/store'
import { useUserStore } from '@/scripts/stores/user.store'
import { useDialogStore } from '@/scripts/stores/dialog.store'
import { useCompanyStore } from '@/scripts/stores/company.store'
import LrReceiptDropdown from '../../invoices/components/LrReceiptDropdown.vue'
import LorryReceiptDropdown from '../../invoices/components/LorryReceiptDropdown.vue'



const { t } = useI18n()
const invoiceStore = useInvoiceStore()
const userStore = useUserStore()
const dialogStore = useDialogStore()
const companyStore = useCompanyStore()
const route = useRoute()

const isPartyMode = computed<boolean>(() => {
  return companyStore.selectedCompanySettings?.enable_standard_invoices !== 'YES'
})

const customerColumnLabel = computed<string>(() => {
  return isPartyMode.value ? 'Party' : 'Customer'
})

const tableRef = ref<{ refresh: () => void } | null>(null)
const tableKey = ref(0)
const showFilters = ref(false)
const isRequestOngoing = ref(true)
const activeTab = ref('general.all')

const isLorryReceiptRoute = computed<boolean>(() =>
  route.name?.toString().startsWith('lorry-receipts') ?? false,
)
const templateName = computed<string>(() =>
  isLorryReceiptRoute.value ? 'lorry_receipt' : 'lr_receipt',
)
const tableInstanceKey = computed<string>(() => `${templateName.value}-${tableKey.value}`)
const basePath = computed<string>(() =>
  isLorryReceiptRoute.value ? '/admin/lorry-receipts' : '/admin/lr-receipts',
)
const pageTitle = computed<string>(() =>
  isLorryReceiptRoute.value ? 'Lorry Receipts' : 'LR Receipts',
)
const singularTitle = computed<string>(() =>
  isLorryReceiptRoute.value ? 'Lorry Receipt' : 'LR Receipt',
)
const receiptNumberLabel = computed<string>(() =>
  isLorryReceiptRoute.value ? 'Challan No.' : 'Docket No.',
)
const newButtonLabel = computed<string>(() => `New ${singularTitle.value}`)
const createButtonLabel = computed<string>(() => `Create ${singularTitle.value}`)
const emptyTitle = computed<string>(() => `No ${pageTitle.value}`)
const emptyDescription = computed<string>(() =>
  `Create your first ${singularTitle.value.toLowerCase()}`,
)

const status = computed(() => [
  {
    label: t('invoices.status'),
    options: [
      { label: t('general.draft'), value: 'DRAFT' },
      { label: t('general.sent'), value: 'SENT' },
      { label: t('invoices.viewed'), value: 'VIEWED' },
      { label: t('invoices.completed'), value: 'COMPLETED' },
    ],
  },
  {
    label: t('invoices.paid_status'),
    options: [
      { label: t('invoices.unpaid'), value: 'UNPAID' },
      { label: t('invoices.paid'), value: 'PAID' },
      { label: t('invoices.partially_paid'), value: 'PARTIALLY_PAID' },
    ],
  },
])

const filters = reactive({
  customer_id: '',
  status: '',
  from_date: '',
  to_date: '',
  invoice_number: '',
})

const showEmptyScreen = computed<boolean>(
  () => !invoiceStore.invoiceTotalCount && !isRequestOngoing.value,
)

const selectField = computed({
  get: () => invoiceStore.selectedInvoices,
  set: (value: number[]) => {
    return invoiceStore.selectInvoice(value)
  },
})

const invoiceColumns = computed(() => [
  {
    key: 'checkbox',
    thClass: 'extra w-10',
    tdClass: 'font-medium text-heading',
    placeholderClass: 'w-10',
    sortable: false,
  },
  { key: 'invoice_date', label: t('invoices.date'), thClass: 'extra', tdClass: 'font-medium' },
  { key: 'invoice_number', label: receiptNumberLabel.value },
  { key: 'name', label: isLorryReceiptRoute.value ? 'Party Name' : customerColumnLabel.value },
  { key: 'status', label: t('invoices.status') },
  {
    key: 'actions',
    label: t('invoices.action'),
    tdClass: 'text-right text-sm font-medium',
    thClass: 'text-right',
    sortable: false,
  },
])

function partyName(invoice: Record<string, unknown>): string {
  // For Lorry Receipts, the "Party" is the Owner — show owner_name if available
  if (isLorryReceiptRoute.value) {
    const ownerName = invoice.owner_name as string
    if (ownerName) return ownerName
  }
  const customer = invoice.customer as Record<string, unknown> | null
  return (customer?.name as string) || (customer?.display_name as string) || '-'
}

debouncedWatch(
  filters,
  () => {
    setFilters()
  },
  { debounce: 500 },
)

watch(templateName, () => {
  resetFilters()
  resetSelection()
  tableKey.value += 1
  isRequestOngoing.value = true
})

onUnmounted(() => {
  if (invoiceStore.selectAllField) {
    invoiceStore.selectAllInvoices()
  }
})

function refreshTable(): void {
  tableRef.value?.refresh()
}

async function clearStatusSearch(): Promise<void> {
  filters.status = ''
  refreshTable()
}

async function fetchData({ page, sort }: { page: number; sort: { fieldName: string; order: string } }): Promise<{
  data: unknown[]
  pagination: { totalPages: number; currentPage: number; totalCount: number; limit: number }
}> {
  const data = {
    customer_id: filters.customer_id,
    status: filters.status,
    from_date: filters.from_date,
    to_date: filters.to_date,
    invoice_number: filters.invoice_number,
    template_name: templateName.value,
    orderByField: sort.fieldName || 'created_at',
    orderBy: sort.order || 'desc',
    page,
  }

  isRequestOngoing.value = true
  const response = await invoiceStore.fetchInvoices(data)
  isRequestOngoing.value = false

  return {
    data: response.data.data,
    pagination: {
      totalPages: response.data.meta.last_page,
      currentPage: page,
      totalCount: response.data.meta.total,
      limit: 10,
    },
  }
}

function setStatusFilter(val: { title: string }): void {
  if (activeTab.value === val.title) {
    return
  }

  activeTab.value = val.title

  switch (val.title) {
    case t('general.draft'):
      filters.status = 'DRAFT'
      break
    case t('general.sent'):
      filters.status = 'SENT'
      break
    case t('general.due'):
      filters.status = 'DUE'
      break
    default:
      filters.status = ''
      break
  }
}

function setFilters(): void {
  resetSelection()
  tableKey.value += 1
  refreshTable()
}

function clearFilter(): void {
  resetFilters()
}

function resetFilters(): void {
  filters.customer_id = ''
  filters.status = ''
  filters.from_date = ''
  filters.to_date = ''
  filters.invoice_number = ''
  activeTab.value = t('general.all')
}

function resetSelection(): void {
  invoiceStore.selectedInvoices = []
  invoiceStore.selectAllField = false
}

async function removeMultipleInvoices(): Promise<void> {
  dialogStore
    .openDialog({
      title: t('general.are_you_sure'),
      message: `Are you sure you want to delete these ${pageTitle.value}?`,
      yesLabel: t('general.ok'),
      noLabel: t('general.cancel'),
      variant: 'danger',
      hideNoButton: false,
      size: 'lg',
    })
    .then(async (res) => {
      if (res) {
        await invoiceStore.deleteMultipleInvoices()
        refreshTable()
        resetSelection()
      }
    })
}

function toggleFilter(): void {
  if (showFilters.value) {
    clearFilter()
  }
  showFilters.value = !showFilters.value
}
</script>

<template>
  <BasePage>
    <BasePageHeader :title="pageTitle">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem :title="$t('general.home')" to="dashboard" />
        <BaseBreadcrumbItem :title="pageTitle" to="#" active />
      </BaseBreadcrumb>

      <template #actions>
        <BaseButton
          v-show="invoiceStore.invoiceTotalCount"
          variant="primary-outline"
          @click="toggleFilter"
        >
          {{ $t('general.filter') }}
          <template #right="slotProps">
            <BaseIcon
              v-if="!showFilters"
              name="FunnelIcon"
              :class="slotProps.class"
            />
            <BaseIcon v-else name="XMarkIcon" :class="slotProps.class" />
          </template>
        </BaseButton>

        <router-link :to="`${basePath}/create`">
          <BaseButton variant="primary" class="ml-4">
            <template #left="slotProps">
              <BaseIcon name="PlusIcon" :class="slotProps.class" />
            </template>
            {{ newButtonLabel }}
          </BaseButton>
        </router-link>
      </template>
    </BasePageHeader>

    <BaseFilterWrapper
      v-show="showFilters"
      :row-on-xl="true"
      @clear="clearFilter"
    >
      <BaseInputGroup :label="isLorryReceiptRoute ? 'Party' : $t('customers.customer', 1)">
        <BaseInput v-model="filters.customer_id" />
      </BaseInputGroup>

      <BaseInputGroup :label="$t('invoices.status')">
        <BaseMultiselect
          v-model="filters.status"
          :groups="true"
          :options="status"
          searchable
          :placeholder="$t('general.select_a_status')"
          @remove="clearStatusSearch()"
        />
      </BaseInputGroup>

      <BaseInputGroup :label="$t('general.from')">
        <BaseDatePicker
          v-model="filters.from_date"
          :calendar-button="true"
          calendar-button-icon="calendar"
        />
      </BaseInputGroup>

      <div
        class="hidden w-8 h-0 mx-4 border border-line-default border-solid xl:block"
        style="margin-top: 1.5rem"
      />

      <BaseInputGroup :label="$t('general.to')" class="mt-2">
        <BaseDatePicker
          v-model="filters.to_date"
          :calendar-button="true"
          calendar-button-icon="calendar"
        />
      </BaseInputGroup>

      <BaseInputGroup :label="receiptNumberLabel">
        <BaseInput v-model="filters.invoice_number">
          <template #left="slotProps">
            <BaseIcon name="HashtagIcon" :class="slotProps.class" />
          </template>
        </BaseInput>
      </BaseInputGroup>
    </BaseFilterWrapper>

    <BaseEmptyPlaceholder
      v-show="showEmptyScreen"
      :title="emptyTitle"
      :description="emptyDescription"
    >
      <template #actions>
        <BaseButton
          variant="primary-outline"
          @click="$router.push(`${basePath}/create`)"
        >
          <template #left="slotProps">
            <BaseIcon name="PlusIcon" :class="slotProps.class" />
          </template>
          {{ createButtonLabel }}
        </BaseButton>
      </template>
    </BaseEmptyPlaceholder>

    <div v-show="!showEmptyScreen" class="relative table-container">
      <div
        class="relative flex items-center justify-between h-10 mt-5 list-none border-b-2 border-line-default border-solid"
      >
        <BaseTabGroup class="-mb-5" @change="setStatusFilter">
          <BaseTab :title="$t('general.all')" filter="" />
          <BaseTab :title="$t('general.draft')" filter="DRAFT" />
          <BaseTab :title="$t('general.sent')" filter="SENT" />
          <BaseTab :title="$t('general.due')" filter="DUE" />
        </BaseTabGroup>

        <BaseDropdown
          v-if="invoiceStore.selectedInvoices.length"
          class="absolute float-right"
        >
          <template #activator>
            <span class="flex text-sm font-medium cursor-pointer select-none text-primary-500">
              {{ $t('general.actions') }}
              <BaseIcon name="ChevronDownIcon" />
            </span>
          </template>

          <BaseDropdownItem @click="removeMultipleInvoices">
            <BaseIcon name="TrashIcon" class="mr-3 text-muted" />
            {{ $t('general.delete') }}
          </BaseDropdownItem>
        </BaseDropdown>
      </div>

      <BaseTable
        ref="tableRef"
        :key="tableInstanceKey"
        :data="fetchData"
        :columns="invoiceColumns"
        :placeholder-count="invoiceStore.invoiceTotalCount >= 20 ? 10 : 5"
        class="mt-10"
      >
        <template #header>
          <div class="absolute items-center left-6 top-2.5 select-none">
            <BaseCheckbox
              v-model="invoiceStore.selectAllField"
              variant="primary"
              @change="invoiceStore.selectAllInvoices"
            />
          </div>
        </template>

        <template #cell-checkbox="{ row }">
          <div class="relative block">
            <BaseCheckbox
              :id="String(row.data.id)"
              v-model="selectField"
              :value="row.data.id"
            />
          </div>
        </template>

        <template #cell-name="{ row }">
          <BaseText :text="partyName(row.data)" />
        </template>

        <template #cell-invoice_number="{ row }">
          <router-link
            :to="{ path: `${basePath}/${row.data.id}/${isLorryReceiptRoute ? 'edit' : 'view'}` }"
            class="font-medium text-primary-500"
          >
            {{ row.data.invoice_number }}
          </router-link>
        </template>

        <template #cell-invoice_date="{ row }">
          {{ row.data.formatted_invoice_date }}
        </template>

        <template #cell-status="{ row }">
          <BaseInvoiceStatusBadge :status="row.data.status" class="px-3 py-1">
            <BaseInvoiceStatusLabel :status="row.data.status" />
          </BaseInvoiceStatusBadge>
        </template>

        <template #cell-actions="{ row }">
          <div class="text-right">
            <!-- LR Receipt: full dropdown with View, Send LR, Download, Download Multi -->
            <LrReceiptDropdown
              v-if="!isLorryReceiptRoute"
              :row="row.data"
              :table="tableRef"
              :can-edit="true"
              :can-view="true"
              :can-delete="true"
              :can-send="true"
            />
            <!-- Lorry Receipt: dropdown with View, Send Lorry Receipt, Download Lorry Receipt -->
            <LorryReceiptDropdown
              v-else
              :row="row.data"
              :table="tableRef"
              :can-edit="true"
              :can-view="true"
              :can-delete="true"
              :can-send="true"
            />

          </div>
        </template>
      </BaseTable>
    </div>
  </BasePage>
</template>
