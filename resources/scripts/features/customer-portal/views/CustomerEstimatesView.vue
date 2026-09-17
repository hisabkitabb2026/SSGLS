<template>
  <BasePage>
    <BasePageHeader title="Quotation">
      <template #default>
        <BaseBreadcrumb>
          <BaseBreadcrumbItem title="Home" to="customer-portal.dashboard" />
          <BaseBreadcrumbItem title="Quotation" to="#" active />
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
      <BaseInputGroup label="Status">
        <BaseMultiselect
          v-model="filters.status"
          :options="statusOptions"
          searchable
          placeholder="Select a status"
          @update:model-value="refreshTable"
          @remove="clearStatusSearch"
        />
      </BaseInputGroup>

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

      <BaseInputGroup label="Quotation Number">
        <BaseInput v-model="filters.estimate_number">
          <template #left="slotProps">
            <BaseIcon name="HashtagIcon" :class="slotProps.class" />
          </template>
        </BaseInput>
      </BaseInputGroup>
    </BaseFilterWrapper>

    <!-- Empty State -->
    <BaseEmptyPlaceholder
      v-show="showEmptyScreen"
      title="No Quotations"
      description="Your quotations will appear here"
    />

    <!-- Table -->
    <div v-show="!showEmptyScreen" class="relative table-container">
      <div class="relative flex items-center justify-between mt-5 list-none">
        <BaseTabGroup @change="setStatusFilter">
          <BaseTab title="All" filter="" />
          <BaseTab title="Accepted" filter="ACCEPTED" />
          <BaseTab title="Rejected" filter="REJECTED" />
        </BaseTabGroup>

      </div>

      <BaseTable
        ref="tableRef"
        :key="tableKey"
        :data="fetchData"
        :columns="estimateColumns"
        :placeholder-count="5"
        class="mt-4"
      >
        <template #cell-estimate_number="{ row }">
          <router-link
            :to="buildPath(`estimates/${row.data.id}/view`)"
            class="font-medium text-primary-500"
          >
            {{ row.data.estimate_number }}
          </router-link>
        </template>

        <template #cell-estimate_date="{ row }">
          {{ row.data.formatted_estimate_date || formatDate(row.data.estimate_date) }}
        </template>

        <template #cell-expiry_date="{ row }">
          {{ row.data.formatted_expiry_date || formatDate(row.data.expiry_date) }}
        </template>

        <template #cell-status="{ row }">
          <BaseEstimateStatusBadge :status="row.data.status" class="px-3 py-1">
            <BaseEstimateStatusLabel :status="row.data.status" />
          </BaseEstimateStatusBadge>
        </template>

        <template #cell-total="{ row }">
          <BaseFormatMoney
            :amount="row.data.total"
            :currency="row.data.currency"
          />
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
  { label: 'Viewed', value: 'VIEWED' },
  { label: 'Expired', value: 'EXPIRED' },
  { label: 'Accepted', value: 'ACCEPTED' },
  { label: 'Rejected', value: 'REJECTED' },
])

interface EstimateFilters {
  status: string
  from_date: string
  to_date: string
  estimate_number: string
}

const filters = reactive<EstimateFilters>({
  status: '',
  from_date: '',
  to_date: '',
  estimate_number: '',
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

const estimateColumns = computed<TableColumn[]>(() => [
  { key: 'estimate_date', label: 'Date', thClass: 'extra', tdClass: 'font-medium' },
  { key: 'estimate_number', label: 'Quotation No' },
  { key: 'expiry_date', label: 'Expiry Date' },
  { key: 'status', label: 'Status' },
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
  const { data } = await client.get(`/api/v1/${companySlug}/customer/estimates`, {
    params: {
      status: filters.status || undefined,
      from_date: filters.from_date || undefined,
      to_date: filters.to_date || undefined,
      estimate_number: filters.estimate_number || undefined,
      estimate_type: 'quotation',
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
    case 'Sent':
      filters.status = 'SENT'
      break
    case 'Accepted':
      filters.status = 'ACCEPTED'
      break
    case 'Rejected':
      filters.status = 'REJECTED'
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
  filters.estimate_number = ''
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
  () => filters.estimate_number,
  () => {
    refreshTable()
  },
)

onMounted(() => {
  // Initial load happens via BaseTable's fetchData
})
</script>
