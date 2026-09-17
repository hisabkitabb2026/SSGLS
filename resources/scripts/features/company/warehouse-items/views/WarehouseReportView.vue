<template>
  <div>
    <!-- Date Range Filter -->
    <BaseCard container-class="px-5 py-5 mb-6">
      <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:gap-3">
        <BaseInputGroup label="From Date" class="flex-1">
          <BaseDatePicker v-model="fromDate" :calendar-button="true" />
        </BaseInputGroup>
        <BaseInputGroup label="To Date" class="flex-1">
          <BaseDatePicker v-model="toDate" :calendar-button="true" />
        </BaseInputGroup>
        <BaseButton variant="primary" @click="loadAll">
          <template #left="slotProps">
            <BaseIcon name="ArrowPathIcon" :class="slotProps.class" />
          </template>
          Update Report
        </BaseButton>
      </div>
    </BaseCard>

    <!-- KPI Strip -->
    <div v-if="summary" class="mb-6 grid grid-cols-2 gap-4 md:grid-cols-5">
      <BaseCard container-class="px-4 py-4">
        <p class="text-sm text-muted">LRs Received</p>
        <p class="text-2xl font-bold text-heading">{{ summary.lrs_received || 0 }}</p>
      </BaseCard>
      <BaseCard container-class="px-4 py-4">
        <p class="text-sm text-muted">Items Stored</p>
        <p class="text-2xl font-bold text-heading">{{ summary.items_stored || 0 }}</p>
      </BaseCard>
      <BaseCard container-class="px-4 py-4">
        <p class="text-sm text-muted">Items Dispatched</p>
        <p class="text-2xl font-bold text-status-green">{{ summary.items_dispatched || 0 }}</p>
      </BaseCard>
      <BaseCard
        container-class="px-4 py-4"
        :class="{ 'border-2 border-status-red': (summary.overdue_items || 0) > 0 }"
      >
        <p class="text-sm text-muted">Overdue Items</p>
        <p
          class="text-2xl font-bold"
          :class="(summary.overdue_items || 0) > 0 ? 'text-status-red' : 'text-heading'"
        >
          {{ summary.overdue_items || 0 }}
        </p>
      </BaseCard>
      <BaseCard container-class="px-4 py-4">
        <p class="text-sm text-muted">Avg Days in WH</p>
        <p class="text-2xl font-bold text-heading">{{ summary.avg_days_in_warehouse || 0 }}</p>
      </BaseCard>
    </div>

    <!-- Section 1: LR Receipt Summary -->
    <BaseCard container-class="px-5 py-5 mb-6">
      <template #header>
        <h3 class="text-lg font-semibold text-heading">LR Receipt Summary</h3>
      </template>
      <div v-if="loading.lrSummary" class="py-8 text-center text-muted">Loading...</div>
      <div v-else-if="!lrSummary.length" class="py-8 text-center text-muted">No data for selected period.</div>
      <div v-else class="overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead class="text-muted">
            <tr>
              <th class="pb-2">LR No</th>
              <th class="pb-2">Customer</th>
              <th class="pb-2">Destination</th>
              <th class="pb-2 text-right">Weight</th>
              <th class="pb-2 text-right">Pkgs</th>
              <th class="pb-2 text-right">Days in WH</th>
              <th class="pb-2">Received</th>
              <th class="pb-2">Status</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="lr in lrSummary"
              :key="lr.lr_id"
              class="border-t border-line-light"
            >
              <td class="py-2 font-semibold text-heading">{{ lr.lr_number }}</td>
              <td class="py-2 text-body">{{ lr.customer_name || '—' }}</td>
              <td class="py-2 text-body">{{ lr.destination_city || '—' }}</td>
              <td class="py-2 text-right text-body">{{ formatWeight(lr.total_weight_kg) }} kg</td>
              <td class="py-2 text-right text-body">{{ lr.total_packages }}</td>
              <td class="py-2 text-right text-body">{{ lr.days_in_warehouse }}</td>
              <td class="py-2 text-body">{{ lr.received_date ? formatDate(lr.received_date) : '—' }}</td>
              <td class="py-2">
                <span
                  v-if="lr.is_overdue"
                  class="rounded-full bg-status-red/10 px-2 py-0.5 text-xs font-bold text-status-red"
                >
                  Overdue
                </span>
                <span
                  v-else-if="lr.is_delivered"
                  class="rounded-full bg-status-green/10 px-2 py-0.5 text-xs font-bold text-status-green"
                >
                  Delivered
                </span>
                <span
                  v-else
                  class="rounded-full bg-status-blue/10 px-2 py-0.5 text-xs font-bold text-status-blue"
                >
                  In Storage
                </span>
              </td>
            </tr>
          </tbody>
        </table>
        <TablePagination
          v-if="lrSummaryMeta.total > lrSummaryMeta.per_page"
          :pagination="{
            currentPage: lrSummaryMeta.current_page,
            totalPages: lrSummaryMeta.last_page,
            totalCount: lrSummaryMeta.total,
            count: lrSummary.length,
            limit: lrSummaryMeta.per_page,
          }"
          @page-change="onLrPageChange"
        />
      </div>
    </BaseCard>

    <!-- Section 2: Aging Analysis -->
    <BaseCard container-class="px-5 py-5 mb-6">
      <template #header>
        <h3 class="text-lg font-semibold text-heading">Aging Analysis</h3>
      </template>
      <div v-if="loading.aging" class="py-8 text-center text-muted">Loading...</div>
      <div v-else-if="!agingData.length" class="py-8 text-center text-muted">No items in warehouse.</div>
      <div v-else>
        <div class="overflow-x-auto">
          <table class="w-full text-left text-sm">
            <thead class="text-muted">
              <tr>
                <th class="pb-2">Bucket</th>
                <th class="pb-2 text-right">Item Count</th>
                <th class="pb-2 text-right">Total Weight</th>
                <th class="pb-2 text-right">% of Total</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="row in agingData"
                :key="row.bucket"
                class="border-t border-line-light"
              >
                <td class="py-2 font-semibold text-heading">{{ row.bucket }} days</td>
                <td class="py-2 text-right text-body">{{ row.item_count }}</td>
                <td class="py-2 text-right text-body">{{ formatWeight(row.total_weight_kg) }} kg</td>
                <td class="py-2 text-right text-body">{{ row.percentage }}%</td>
              </tr>
            </tbody>
          </table>
        </div>
        <!-- Visual bars -->
        <div class="mt-4 space-y-2">
          <div v-for="row in agingData" :key="row.bucket" class="flex items-center gap-3">
            <span class="w-20 text-xs text-muted">{{ row.bucket }} days</span>
            <div class="h-3 flex-1 overflow-hidden rounded-full bg-surface-tertiary">
              <div
                class="h-full rounded-full transition-all"
                :class="getAgingBarClass(row.bucket)"
                :style="{ width: row.percentage + '%' }"
              />
            </div>
            <span class="w-12 text-right text-xs text-body">{{ row.percentage }}%</span>
          </div>
        </div>
      </div>
    </BaseCard>

    <!-- Section 3: Destination-wise Summary -->
    <BaseCard container-class="px-5 py-5 mb-6">
      <template #header>
        <h3 class="text-lg font-semibold text-heading">Destination-wise Summary</h3>
      </template>
      <div v-if="loading.destinations" class="py-8 text-center text-muted">Loading...</div>
      <div v-else-if="!destinationData.length" class="py-8 text-center text-muted">No data for selected period.</div>
      <div v-else class="overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead class="text-muted">
            <tr>
              <th class="pb-2">Destination</th>
              <th class="pb-2 text-right">Total LRs</th>
              <th class="pb-2 text-right">Total Weight</th>
              <th class="pb-2 text-right">Items</th>
              <th class="pb-2 text-right">Overdue</th>
              <th class="pb-2 text-right">Avg Days</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="dest in destinationData"
              :key="dest.destination"
              class="border-t border-line-light"
            >
              <td class="py-2 font-semibold text-heading">{{ dest.destination }}</td>
              <td class="py-2 text-right text-body">{{ dest.total_lrs }}</td>
              <td class="py-2 text-right text-body">{{ formatWeight(dest.total_weight_kg) }} kg</td>
              <td class="py-2 text-right text-body">{{ dest.item_count }}</td>
              <td class="py-2 text-right">
                <span v-if="dest.overdue_count > 0" class="font-semibold text-status-red">
                  {{ dest.overdue_count }}
                </span>
                <span v-else class="text-body">0</span>
              </td>
              <td class="py-2 text-right text-body">{{ dest.avg_days }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </BaseCard>

    <!-- Section 4: Overdue Items Alert -->
    <BaseCard
      v-if="overdueItems.length > 0"
      container-class="px-5 py-5"
      class="border-l-4 border-status-red"
    >
      <template #header>
        <div class="flex items-center gap-2">
          <BaseIcon name="ExclamationTriangleIcon" class="h-5 w-5 text-status-red" />
          <h3 class="text-lg font-semibold text-heading">Overdue Items Alert</h3>
        </div>
      </template>
      <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead class="text-muted">
            <tr>
              <th class="pb-2">LR No</th>
              <th class="pb-2">Customer</th>
              <th class="pb-2">Destination</th>
              <th class="pb-2 text-right">Days Overdue</th>
              <th class="pb-2 text-right">Weight</th>
              <th class="pb-2">Promised Dispatch</th>
              <th class="pb-2">Location</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="item in overdueItems"
              :key="item.id"
              class="border-t border-line-light"
            >
              <td class="py-2 font-semibold text-heading">{{ item.lr_number }}</td>
              <td class="py-2 text-body">{{ item.customer_name }}</td>
              <td class="py-2 text-body">{{ item.destination_city || '—' }}</td>
              <td class="py-2 text-right">
                <span class="font-bold text-status-red">{{ item.days_overdue }} days</span>
              </td>
              <td class="py-2 text-right text-body">{{ formatWeight(item.weight_kg) }} kg</td>
              <td class="py-2 text-body">{{ item.promised_dispatch_date }}</td>
              <td class="py-2 text-body">{{ item.warehouse_location || '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </BaseCard>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { client } from '@/scripts/api/client'
import TablePagination from '@/scripts/components/table/TablePagination.vue'

const fromDate = ref('')
const toDate = ref('')

const summary = ref<any>(null)
const lrSummary = ref<any[]>([])
const lrSummaryMeta = ref<any>({ current_page: 1, last_page: 1, per_page: 15, total: 0 })
const agingData = ref<any[]>([])
const destinationData = ref<any[]>([])
const overdueItems = ref<any[]>([])

const loading = ref({
  summary: false,
  lrSummary: false,
  aging: false,
  destinations: false,
  overdue: false,
})

const formatWeight = (weight: any) => {
  const w = parseFloat(weight) || 0
  return w.toLocaleString('en-IN', { maximumFractionDigits: 2 })
}

const formatDate = (dateStr: string) => {
  if (!dateStr) return '—'
  return new Date(dateStr).toLocaleDateString('en-IN')
}

const getAgingBarClass = (bucket: string) => {
  const classes: Record<string, string> = {
    '0-3': 'bg-status-green',
    '4-7': 'bg-status-yellow',
    '8-15': 'bg-status-blue',
    '15+': 'bg-status-red',
  }
  return classes[bucket] || 'bg-primary-500'
}

const loadSummary = async () => {
  loading.value.summary = true
  try {
    const params: Record<string, string> = {}
    if (fromDate.value) params.from_date = fromDate.value
    if (toDate.value) params.to_date = toDate.value
    const res = await client.get('/api/v1/warehouse-reports/summary', { params })
    summary.value = res.data?.data || null
  } catch {
    summary.value = null
  } finally {
    loading.value.summary = false
  }
}

const loadLrSummary = async (page = 1) => {
  loading.value.lrSummary = true
  try {
    const params: Record<string, any> = { page, per_page: 15 }
    if (fromDate.value) params.from_date = fromDate.value
    if (toDate.value) params.to_date = toDate.value
    const res = await client.get('/api/v1/warehouse-reports/lr-summary', { params })
    lrSummary.value = res.data?.data || []
    lrSummaryMeta.value = res.data?.meta || {}
  } catch {
    lrSummary.value = []
  } finally {
    loading.value.lrSummary = false
  }
}

const loadAging = async () => {
  loading.value.aging = true
  try {
    const res = await client.get('/api/v1/warehouse-reports/aging')
    agingData.value = res.data?.data || []
  } catch {
    agingData.value = []
  } finally {
    loading.value.aging = false
  }
}

const loadDestinations = async () => {
  loading.value.destinations = true
  try {
    const params: Record<string, string> = {}
    if (fromDate.value) params.from_date = fromDate.value
    if (toDate.value) params.to_date = toDate.value
    const res = await client.get('/api/v1/warehouse-reports/destinations', { params })
    destinationData.value = res.data?.data || []
  } catch {
    destinationData.value = []
  } finally {
    loading.value.destinations = false
  }
}

const loadOverdue = async () => {
  loading.value.overdue = false
  try {
    const res = await client.get('/api/v1/warehouse-reports/overdue')
    overdueItems.value = res.data?.data || []
  } catch {
    overdueItems.value = []
  }
}

const loadAll = () => {
  loadSummary()
  loadLrSummary(1)
  loadAging()
  loadDestinations()
  loadOverdue()
}

const onLrPageChange = (page: number) => {
  loadLrSummary(page)
}

onMounted(() => {
  loadAll()
})
</script>
