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
    <div v-if="summary" class="mb-6 grid grid-cols-2 gap-4 md:grid-cols-6">
      <BaseCard container-class="px-4 py-4">
        <p class="text-sm text-muted">Total Trips</p>
        <p class="text-2xl font-bold text-heading">{{ summary.total_trips || 0 }}</p>
      </BaseCard>
      <BaseCard container-class="px-4 py-4">
        <p class="text-sm text-muted">Total Revenue</p>
        <p class="text-2xl font-bold text-status-green">
          <BaseFormatMoney :amount="summary.total_revenue || 0" />
        </p>
      </BaseCard>
      <BaseCard container-class="px-4 py-4">
        <p class="text-sm text-muted">Total Expense</p>
        <p class="text-2xl font-bold text-status-red">
          <BaseFormatMoney :amount="summary.total_expense || 0" />
        </p>
      </BaseCard>
      <BaseCard container-class="px-4 py-4">
        <p class="text-sm text-muted">Net Margin</p>
        <p
          class="text-2xl font-bold"
          :class="(summary.net_margin || 0) < 0 ? 'text-status-red' : 'text-status-green'"
        >
          <BaseFormatMoney :amount="summary.net_margin || 0" />
        </p>
      </BaseCard>
      <BaseCard container-class="px-4 py-4">
        <p class="text-sm text-muted">Avg Margin/Trip</p>
        <p class="text-2xl font-bold text-heading">
          <BaseFormatMoney :amount="summary.avg_margin_per_trip || 0" />
        </p>
      </BaseCard>
      <BaseCard container-class="px-4 py-4">
        <p class="text-sm text-muted">Fleet Utilization</p>
        <p class="text-2xl font-bold text-heading">{{ summary.fleet_utilization || 0 }}%</p>
      </BaseCard>
    </div>

    <!-- Section 1: Trip Financial Summary -->
    <BaseCard container-class="px-5 py-5 mb-6">
      <template #header>
        <h3 class="text-lg font-semibold text-heading">Trip Financial Summary</h3>
      </template>
      <div v-if="loading.trips" class="py-8 text-center text-muted">Loading...</div>
      <div v-else-if="!tripRows.length" class="py-8 text-center text-muted">No trips for selected period.</div>
      <div v-else class="overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead class="text-muted">
            <tr>
              <th class="pb-2">Trip</th>
              <th class="pb-2">Route</th>
              <th class="pb-2">Truck</th>
              <th class="pb-2">Driver</th>
              <th class="pb-2">Status</th>
              <th class="pb-2">LRs</th>
              <th class="pb-2 text-right">Weight</th>
              <th class="pb-2 text-right">Revenue</th>
              <th class="pb-2 text-right">Expense</th>
              <th class="pb-2 text-right">Margin</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="trip in tripRows"
              :key="trip.load_trip_id"
              class="border-t border-line-light"
            >
              <td class="py-2 font-semibold text-heading">{{ trip.trip_number }}</td>
              <td class="py-2 text-body">{{ trip.route || '—' }}</td>
              <td class="py-2 text-body">{{ trip.truck_number || '—' }}</td>
              <td class="py-2 text-body">{{ trip.driver_name || '—' }}</td>
              <td class="py-2">
                <span
                  class="rounded-full px-2 py-0.5 text-xs font-bold capitalize"
                  :class="getTripStatusClass(trip.status)"
                >
                  {{ trip.status }}
                </span>
              </td>
              <td class="py-2">
                <div class="flex flex-wrap gap-1">
                  <span
                    v-for="lr in (trip.lr_numbers || []).slice(0, 3)"
                    :key="lr"
                    class="rounded bg-surface-tertiary px-1.5 py-0.5 text-xs text-body"
                  >
                    {{ lr }}
                  </span>
                  <span v-if="(trip.lr_numbers || []).length > 3" class="text-xs text-muted">
                    +{{ trip.lr_numbers.length - 3 }}
                  </span>
                </div>
              </td>
              <td class="py-2 text-right text-body">{{ formatWeight(trip.total_weight_kg) }} kg</td>
              <td class="py-2 text-right text-body"><BaseFormatMoney :amount="trip.revenue_total" /></td>
              <td class="py-2 text-right text-body"><BaseFormatMoney :amount="trip.expense_total" /></td>
              <td
                class="py-2 text-right font-semibold"
                :class="trip.margin_total < 0 ? 'text-status-red' : 'text-status-green'"
              >
                <BaseFormatMoney :amount="trip.margin_total" />
              </td>
            </tr>
          </tbody>
          <tfoot class="border-t-2 border-line-default bg-surface-secondary font-bold">
            <tr>
              <td class="py-2 text-heading" colspan="6">Page Total</td>
              <td class="py-2 text-right text-heading">{{ formatWeight(totalTripWeight) }} kg</td>
              <td class="py-2 text-right text-heading"><BaseFormatMoney :amount="totalTripRevenue" /></td>
              <td class="py-2 text-right text-heading"><BaseFormatMoney :amount="totalTripExpense" /></td>
              <td
                class="py-2 text-right"
                :class="totalTripMargin < 0 ? 'text-status-red' : 'text-status-green'"
              >
                <BaseFormatMoney :amount="totalTripMargin" />
              </td>
            </tr>
          </tfoot>
        </table>
        <TablePagination
          v-if="tripMeta.total > tripMeta.per_page"
          :pagination="{
            currentPage: tripMeta.current_page,
            totalPages: tripMeta.last_page,
            totalCount: tripMeta.total,
            count: tripRows.length,
            limit: tripMeta.per_page,
          }"
          @page-change="onTripsPageChange"
        />
      </div>
    </BaseCard>

    <!-- Section 2: Truck-wise Performance -->
    <BaseCard container-class="px-5 py-5 mb-6">
      <template #header>
        <h3 class="text-lg font-semibold text-heading">Truck-wise Performance</h3>
      </template>
      <div v-if="loading.trucks" class="py-8 text-center text-muted">Loading...</div>
      <div v-else-if="!truckPerformance.length" class="py-8 text-center text-muted">No truck data for selected period.</div>
      <div v-else class="overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead class="text-muted">
            <tr>
              <th class="pb-2">Truck No</th>
              <th class="pb-2 text-right">Trips</th>
              <th class="pb-2 text-right">Revenue</th>
              <th class="pb-2 text-right">Expense</th>
              <th class="pb-2 text-right">Margin</th>
              <th class="pb-2 text-right">Avg Margin/Trip</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="truck in truckPerformance"
              :key="truck.truck_id"
              class="border-t border-line-light"
            >
              <td class="py-2 font-semibold text-heading">{{ truck.truck_number }}</td>
              <td class="py-2 text-right text-body">{{ truck.trip_count }}</td>
              <td class="py-2 text-right text-body"><BaseFormatMoney :amount="truck.total_revenue" /></td>
              <td class="py-2 text-right text-body"><BaseFormatMoney :amount="truck.total_expense" /></td>
              <td
                class="py-2 text-right font-semibold"
                :class="truck.margin < 0 ? 'text-status-red' : 'text-status-green'"
              >
                <BaseFormatMoney :amount="truck.margin" />
              </td>
              <td class="py-2 text-right text-body"><BaseFormatMoney :amount="truck.avg_margin_per_trip" /></td>
            </tr>
          </tbody>
        </table>
      </div>
    </BaseCard>

    <!-- Section 3: Expense Breakdown by Category -->
    <BaseCard container-class="px-5 py-5 mb-6">
      <template #header>
        <h3 class="text-lg font-semibold text-heading">Expense Breakdown by Category</h3>
      </template>
      <div v-if="loading.categories" class="py-8 text-center text-muted">Loading...</div>
      <div v-else-if="!expenseCategories.length" class="py-8 text-center text-muted">No expenses for selected period.</div>
      <div v-else>
        <div class="overflow-x-auto">
          <table class="w-full text-left text-sm">
            <thead class="text-muted">
              <tr>
                <th class="pb-2">Category</th>
                <th class="pb-2 text-right">Total Amount</th>
                <th class="pb-2 text-right">% of Total</th>
                <th class="pb-2 text-right">Trips</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="cat in expenseCategories"
                :key="cat.expense_category_id"
                class="border-t border-line-light"
              >
                <td class="py-2 font-semibold text-heading">{{ cat.category_name }}</td>
                <td class="py-2 text-right text-body"><BaseFormatMoney :amount="cat.total_amount" /></td>
                <td class="py-2 text-right text-body">{{ cat.percentage }}%</td>
                <td class="py-2 text-right text-body">{{ cat.trip_count }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <!-- Visual bars -->
        <div class="mt-4 space-y-2">
          <div v-for="cat in expenseCategories" :key="cat.expense_category_id" class="flex items-center gap-3">
            <span class="w-32 truncate text-xs text-muted">{{ cat.category_name }}</span>
            <div class="h-3 flex-1 overflow-hidden rounded-full bg-surface-tertiary">
              <div
                class="h-full rounded-full bg-primary-500 transition-all"
                :style="{ width: cat.percentage + '%' }"
              />
            </div>
            <span class="w-12 text-right text-xs text-body">{{ cat.percentage }}%</span>
          </div>
        </div>
      </div>
    </BaseCard>

    <!-- Section 4: Route Performance -->
    <BaseCard container-class="px-5 py-5">
      <template #header>
        <h3 class="text-lg font-semibold text-heading">Route Performance</h3>
      </template>
      <div v-if="loading.routes" class="py-8 text-center text-muted">Loading...</div>
      <div v-else-if="!routePerformance.length" class="py-8 text-center text-muted">No route data for selected period.</div>
      <div v-else class="overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead class="text-muted">
            <tr>
              <th class="pb-2">Route</th>
              <th class="pb-2 text-right">Trips</th>
              <th class="pb-2 text-right">Total Revenue</th>
              <th class="pb-2 text-right">Avg Rev/Trip</th>
              <th class="pb-2 text-right">Total Weight</th>
              <th class="pb-2">Most Used Truck</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="route in routePerformance"
              :key="route.route"
              class="border-t border-line-light"
            >
              <td class="py-2 font-semibold text-heading">{{ route.route }}</td>
              <td class="py-2 text-right text-body">{{ route.trip_count }}</td>
              <td class="py-2 text-right text-body"><BaseFormatMoney :amount="route.total_revenue" /></td>
              <td class="py-2 text-right text-body"><BaseFormatMoney :amount="route.avg_revenue_per_trip" /></td>
              <td class="py-2 text-right text-body">{{ formatWeight(route.total_weight_kg) }} kg</td>
              <td class="py-2 text-body">{{ route.most_used_truck }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </BaseCard>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { client } from '@/scripts/api/client'
import BaseFormatMoney from '@/scripts/components/base/BaseFormatMoney.vue'
import TablePagination from '@/scripts/components/table/TablePagination.vue'

const fromDate = ref('')
const toDate = ref('')

const summary = ref<any>(null)
const tripRows = ref<any[]>([])
const tripMeta = ref<any>({ current_page: 1, last_page: 1, per_page: 10, total: 0 })
const truckPerformance = ref<any[]>([])
const expenseCategories = ref<any[]>([])
const routePerformance = ref<any[]>([])

const loading = ref({
  summary: false,
  trips: false,
  trucks: false,
  categories: false,
  routes: false,
})

const formatWeight = (weight: any) => {
  const w = parseFloat(weight) || 0
  return w.toLocaleString('en-IN', { maximumFractionDigits: 2 })
}

const getTripStatusClass = (status: string) => {
  const classes: Record<string, string> = {
    planned: 'bg-status-blue/10 text-status-blue',
    dispatched: 'bg-status-purple/10 text-status-purple',
    delivered: 'bg-status-green/10 text-status-green',
    cancelled: 'bg-status-red/10 text-status-red',
  }
  return classes[status] || ''
}

const totalTripWeight = computed(() =>
  tripRows.value.reduce((sum: number, t: any) => sum + (t.total_weight_kg || 0), 0)
)
const totalTripRevenue = computed(() =>
  tripRows.value.reduce((sum: number, t: any) => sum + (t.revenue_total || 0), 0)
)
const totalTripExpense = computed(() =>
  tripRows.value.reduce((sum: number, t: any) => sum + (t.expense_total || 0), 0)
)
const totalTripMargin = computed(() =>
  tripRows.value.reduce((sum: number, t: any) => sum + (t.margin_total || 0), 0)
)

const getParams = () => {
  const params: Record<string, string> = {}
  if (fromDate.value) params.from_date = fromDate.value
  if (toDate.value) params.to_date = toDate.value
  return params
}

const loadSummary = async () => {
  loading.value.summary = true
  try {
    const res = await client.get('/api/v1/fleet-reports/report-summary', { params: getParams() })
    summary.value = res.data?.data || null
  } catch {
    summary.value = null
  } finally {
    loading.value.summary = false
  }
}

const loadTrips = async (page = 1) => {
  loading.value.trips = true
  try {
    const params = { ...getParams(), page, per_page: 10 }
    const res = await client.get('/api/v1/fleet-reports/trips', { params })
    tripRows.value = res.data?.data || []
    tripMeta.value = res.data?.meta || {}
  } catch {
    tripRows.value = []
  } finally {
    loading.value.trips = false
  }
}

const loadTruckPerformance = async () => {
  loading.value.trucks = true
  try {
    const res = await client.get('/api/v1/fleet-reports/truck-performance', { params: getParams() })
    truckPerformance.value = res.data?.data || []
  } catch {
    truckPerformance.value = []
  } finally {
    loading.value.trucks = false
  }
}

const loadCategories = async () => {
  loading.value.categories = true
  try {
    const res = await client.get('/api/v1/fleet-reports/expense-by-category', { params: getParams() })
    expenseCategories.value = res.data?.data || []
  } catch {
    expenseCategories.value = []
  } finally {
    loading.value.categories = false
  }
}

const loadRoutes = async () => {
  loading.value.routes = true
  try {
    const res = await client.get('/api/v1/fleet-reports/route-performance', { params: getParams() })
    routePerformance.value = res.data?.data || []
  } catch {
    routePerformance.value = []
  } finally {
    loading.value.routes = false
  }
}

const loadAll = () => {
  loadSummary()
  loadTrips(1)
  loadTruckPerformance()
  loadCategories()
  loadRoutes()
}

const onTripsPageChange = (page: number) => {
  loadTrips(page)
}

onMounted(() => {
  loadAll()
})
</script>
