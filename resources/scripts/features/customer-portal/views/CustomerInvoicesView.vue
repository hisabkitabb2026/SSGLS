<template>
  <BasePage>
    <BasePageHeader title="Invoices">
      <template #default>
        <BaseBreadcrumb>
          <BaseBreadcrumbItem title="Home" to="customer-portal.dashboard" />
          <BaseBreadcrumbItem title="Invoices" to="#" active />
        </BaseBreadcrumb>
      </template>

      <template #actions>
        <BaseButton variant="primary-outline" @click="showFilters = !showFilters">
          Filter
          <template #right="slotProps">
            <BaseIcon v-if="!showFilters" name="FunnelIcon" :class="slotProps.class" />
            <BaseIcon v-else name="XMarkIcon" :class="slotProps.class" />
          </template>
        </BaseButton>
      </template>
    </BasePageHeader>

    <!-- Filters -->
    <BaseFilterWrapper v-show="showFilters" @clear="clearFilter">
      <BaseInputGroup label="From">

        <BaseDatePicker
          v-model="filters.from_date"
          :calendar-button="true"
          calendar-button-icon="calendar"
        />
      </BaseInputGroup>

      <div class="hidden w-8 h-0 mx-4 border border-line-strong border-solid xl:block" style="margin-top: 1.5rem" />

      <BaseInputGroup label="To" class="mt-2">
        <BaseDatePicker
          v-model="filters.to_date"
          :calendar-button="true"
          calendar-button-icon="calendar"
        />
      </BaseInputGroup>

      <BaseInputGroup label="Invoice Number">
        <BaseInput v-model="filters.invoice_number">
          <template #left="slotProps">
            <BaseIcon name="HashtagIcon" :class="slotProps.class" />
          </template>
        </BaseInput>
      </BaseInputGroup>
    </BaseFilterWrapper>

    <!-- Tabs + Table (always visible, even when empty) -->
    <div class="relative table-container">
      <div class="relative flex items-center justify-between mt-5 list-none">
        <BaseTabGroup @change="setStatusFilter">
          <BaseTab title="All" filter="" />
          <BaseTab title="Unpaid" filter="DUE" />
          <BaseTab title="Paid" filter="COMPLETED" />
        </BaseTabGroup>
      </div>

      <!-- Empty State (inside table container, below tabs) -->
      <BaseEmptyPlaceholder
        v-show="showEmptyScreen"
        title="No Invoices"
        description="Your invoices will appear here"
      />

      <BaseTable
        v-show="!showEmptyScreen"
        ref="tableRef"
        :key="tableKey"
        :data="fetchData"
        :columns="invoiceColumns"
        :placeholder-count="5"
        class="mt-4"
      >

        <template #cell-invoice_number="{ row }">
          <router-link
            :to="buildPath(`invoices/${row.data.id}/view`)"
            class="font-medium text-primary-500"
          >
            {{ row.data.invoice_number }}
          </router-link>
        </template>

        <template #cell-invoice_date="{ row }">
          {{ row.data.formatted_invoice_date || formatDate(row.data.invoice_date) }}
        </template>

        <template #cell-reference_number="{ row }">
          {{ row.data.reference_number || '—' }}
        </template>

        <template #cell-route="{ row }">
          {{ row.data.from_name || '—' }} → {{ row.data.to_name || '—' }}
        </template>

        <template #cell-total="{ row }">
          <BaseFormatMoney
            :amount="row.data.total"
            :currency="row.data.currency"
          />
        </template>

        <template #cell-due_amount="{ row }">
          <div class="flex justify-between">
            <BaseFormatMoney
              :amount="row.data.due_amount"
              :currency="row.data.currency"
            />
            <BasePaidStatusBadge
              v-if="row.data.overdue"
              status="OVERDUE"
              class="px-1 py-0.5 ml-2"
            >
              Overdue
            </BasePaidStatusBadge>
            <BasePaidStatusBadge
              :status="row.data.paid_status"
              class="px-1 py-0.5 ml-2"
            >
              <BaseInvoiceStatusLabel :status="row.data.paid_status" />
            </BasePaidStatusBadge>
          </div>
        </template>

        <template #cell-status="{ row }">
          <BaseInvoiceStatusBadge :status="row.data.status" class="px-3 py-1">
            <BaseInvoiceStatusLabel :status="row.data.status" />
          </BaseInvoiceStatusBadge>
        </template>
      </BaseTable>
    </div>
  </BasePage>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { client } from '@/scripts/api/client'
import { buildCustomerPortalPath } from '../utils/routes'
import { useCustomerPortalStore } from '../store'

const route = useRoute()
const store = useCustomerPortalStore()

const tableRef = ref<{ refresh: () => void } | null>(null)
const tableKey = ref<number>(0)
const showFilters = ref<boolean>(false)
const isRequestOngoing = ref<boolean>(true)
const totalCount = ref<number>(0)

interface StatusOption {
  label: string
  value: string
}

const statusOptions = ref<StatusOption[]>([
  { label: 'Draft', value: 'DRAFT' },
  { label: 'Sent', value: 'SENT' },
  { label: 'Due', value: 'DUE' },
  { label: 'Viewed', value: 'VIEWED' },
  { label: 'Completed', value: 'COMPLETED' },
])

interface InvoiceFilters {
  status: string
  from_date: string
  to_date: string
  invoice_number: string
}

const filters = reactive<InvoiceFilters>({
  status: '',
  from_date: '',
  to_date: '',
  invoice_number: '',
})

const showEmptyScreen = computed<boolean>(
  () => !totalCount.value && !isRequestOngoing.value,
)

interface TableColumn {
  key: string
  label?: string
  thClass?: string
  tdClass?: string
  sortable?: boolean
}

const invoiceColumns = computed<TableColumn[]>(() => [
  { key: 'invoice_date', label: 'Date', thClass: 'extra', tdClass: 'font-medium' },
  { key: 'invoice_number', label: 'Invoice No' },
  { key: 'status', label: 'Status' },
  { key: 'due_amount', label: 'Due Amount' },
  { key: 'total', label: 'Amount', tdClass: 'font-medium text-heading' },
])


interface FetchParams {
  page: number
  sort: { fieldName?: string; order?: string }
}

interface FetchResult {
  data: any[]
  pagination: {
    totalPages: number
    currentPage: number
    totalCount: number
    limit: number
  }
}

async function fetchData({ page, sort }: FetchParams): Promise<FetchResult> {
  const companySlug = route.params.company as string

  isRequestOngoing.value = true
  // Fetch office invoices (Invoice Receipts) — same data as Regular Portal /admin/invoices
  const { data } = await client.get(`/api/v1/${companySlug}/customer/office-invoices`, {
    params: {
      status: filters.status || undefined,
      from_date: filters.from_date || undefined,
      to_date: filters.to_date || undefined,
      invoice_number: filters.invoice_number || undefined,
      orderByField: sort.fieldName || 'created_at',
      orderBy: sort.order || 'desc',
      page,
      limit: 10,
    },
  })
  isRequestOngoing.value = false

  totalCount.value = data.meta?.total || data.data?.length || 0

  return {
    data: data.data || [],
    pagination: {
      totalPages: data.meta?.last_page || 1,
      currentPage: page,
      totalCount: data.meta?.total || data.data?.length || 0,
      limit: 10,
    },
  }
}

function setStatusFilter(val: { title: string }): void {
  switch (val.title) {
    case 'Unpaid':
      filters.status = 'DUE'
      break
    case 'Paid':
      filters.status = 'COMPLETED'
      break
    default:
      filters.status = ''
      break
  }
  refreshTable()
}

function clearStatusSearch(): void {
  filters.status = ''
  refreshTable()
}

function clearFilter(): void {
  filters.status = ''
  filters.from_date = ''
  filters.to_date = ''
  filters.invoice_number = ''
  refreshTable()
}

function refreshTable(): void {
  tableKey.value += 1
  tableRef.value?.refresh()
}

function buildPath(page: string): string {
  return buildCustomerPortalPath(store.companySlug, page)
}

function formatDate(date: string): string {
  if (!date) return '—'
  return new Date(date).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' })
}

watch(
  () => filters.invoice_number,
  () => {
    refreshTable()
  },
)

onMounted(() => {
  // Initial load happens via BaseTable's fetchData
})
</script>
