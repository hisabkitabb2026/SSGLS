<template>
  <BasePage>
    <BasePageHeader title="Payments">
      <template #default>
        <BaseBreadcrumb>
          <BaseBreadcrumbItem title="Home" to="customer-portal.dashboard" />
          <BaseBreadcrumbItem title="Payments" to="#" active />
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

      <BaseInputGroup label="Payment Number">
        <BaseInput v-model="filters.payment_number">
          <template #left="slotProps">
            <BaseIcon name="HashtagIcon" :class="slotProps.class" />
          </template>
        </BaseInput>
      </BaseInputGroup>
    </BaseFilterWrapper>

    <!-- Table (always visible, even when empty) -->
    <div class="relative table-container">
      <!-- Empty State -->
      <BaseEmptyPlaceholder
        v-show="showEmptyScreen"
        title="No Payments"
        description="Your payments will appear here"
      />

      <BaseTable
        v-show="!showEmptyScreen"
        ref="tableRef"
        :key="tableKey"
        :data="fetchData"
        :columns="paymentColumns"
        :placeholder-count="5"
        class="mt-4"
      >
        <template #cell-payment_date="{ row }">
          {{ row.data.formatted_payment_date || formatDate(row.data.payment_date) }}
        </template>

        <template #cell-payment_number="{ row }">
          <router-link
            :to="buildPath(`payments/${row.data.id}/view`)"
            class="font-medium text-primary-500"
          >
            {{ row.data.payment_number }}
          </router-link>
        </template>

        <template #cell-invoice_number="{ row }">
          <span>
            {{ row.data.invoice?.invoice_number ?? '—' }}
          </span>
        </template>

        <template #cell-amount="{ row }">
          <BaseFormatMoney
            :amount="row.data.amount"
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

interface PaymentFilters {
  from_date: string
  to_date: string
  payment_number: string
}

const filters = reactive<PaymentFilters>({
  from_date: '',
  to_date: '',
  payment_number: '',
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

const paymentColumns = computed<TableColumn[]>(() => [
  { key: 'payment_date', label: 'Date', thClass: 'extra', tdClass: 'font-medium' },
  { key: 'payment_number', label: 'Payment No' },
  { key: 'invoice_number', label: 'Invoice No' },
  { key: 'amount', label: 'Amount', tdClass: 'font-medium text-heading' },
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
  const { data } = await client.get(`/api/v1/${companySlug}/customer/payments`, {
    params: {
      from_date: filters.from_date || undefined,
      to_date: filters.to_date || undefined,
      payment_number: filters.payment_number || undefined,
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

function clearFilter(): void {
  filters.from_date = ''
  filters.to_date = ''
  filters.payment_number = ''
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
  () => filters.payment_number,
  () => {
    refreshTable()
  },
)

onMounted(() => {
  // Initial load happens via BaseTable's fetchData
})
</script>
