<script setup lang="ts">
/**
 * DashboardOperations — Fleet & Warehouse operations panels + Recent Trips.
 *
 * Part of the redesigned "Logistics Control Tower" dashboard.
 * Fetches data from 3 existing APIs in parallel on mount:
 *   - GET /api/v1/warehouse-items/dashboard
 *   - GET /api/v1/trucks/dashboard
 *   - GET /api/v1/load-trips?status=dispatched&per_page=5
 *
 * Emits `overdue-updated` with the overdue warehouse count so the parent
 * DashboardView can show the alert banner without a duplicate API call.
 */
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { client } from '@/scripts/api/client'
import { useOperationsHelpers } from '@/scripts/composables/useOperationsHelpers'

const emit = defineEmits<{
  (e: 'overdue-updated', count: number): void
}>()

const router = useRouter()
const { t } = useI18n()
const { formatDate, getTripStatusClass } = useOperationsHelpers()

// ── Types ──────────────────────────────────────────────────────
interface WarehouseDashboardStats {
  stored_items: number
  overdue_items: number
  unique_destinations: number
  total_weight_kg: number | string | null
  aging_buckets?: Record<string, number>
}

interface TruckDashboardStats {
  total_trucks: number
  available_trucks: number
  on_trip_trucks: number
  [key: string]: number
}

interface RecentTrip {
  id: number
  trip_number: string
  origin_city: string | null
  destination_city: string | null
  truck_number: string | null
  status: string
  dispatch_date: string | null
}

// ── State ──────────────────────────────────────────────────────
const loading = ref(true)

const warehouseStats = ref<WarehouseDashboardStats | null>(null)
const truckDashboard = ref<TruckDashboardStats>({})
const recentTrips = ref<RecentTrip[]>([])


// ── Fetch all data in parallel ────────────────────────────────
async function loadOperationsData() {
  loading.value = true
  try {
    const [warehouseRes, truckRes, tripsRes] = await Promise.all([
      client.get('/api/v1/warehouse-items/dashboard'),
      client.get('/api/v1/trucks/dashboard'),
      client.get('/api/v1/load-trips', { params: { status: 'dispatched', per_page: 5 } }),
    ])

    warehouseStats.value = warehouseRes.data?.data || warehouseRes.data || null
    truckDashboard.value = truckRes.data?.data || truckRes.data || {}

    // Load trips: support both paginated ({ data, meta }) and array responses
    const tripsData = tripsRes.data?.data || tripsRes.data
    recentTrips.value = Array.isArray(tripsData) ? tripsData : (tripsData?.data || [])

    // Emit overdue count for the parent alert banner
    const overdue = warehouseStats.value?.overdue_items || 0
    emit('overdue-updated', overdue)
  } catch (error) {
    console.error('Error loading operations data:', error)
    emit('overdue-updated', 0)
  } finally {
    loading.value = false
  }
}

// ── Navigation helpers ─────────────────────────────────────────
function goToWarehouse() {
  router.push({ name: 'warehouse-items.index' })
}

function goToFleet() {
  router.push({ name: 'trucks.index' })
}

function goToLoadTrips() {
  router.push({ name: 'load-trips.index' })
}

function goToTripDetail(id: number) {
  router.push({ name: 'load-trips.show', params: { id } })
}

// ── Aging bucket color helper ──────────────────────────────────
function getAgingColorClass(bucket: string): string {
  if (bucket.includes('15')) return 'bg-status-red'
  if (bucket.includes('8') || bucket.includes('14')) return 'bg-status-yellow'
  if (bucket.includes('4') || bucket.includes('7')) return 'bg-status-blue'
  return 'bg-status-green'
}

// ── Fleet utilization percentage ───────────────────────────────
function getUtilizationPercent(): number {
  const total = truckDashboard.value.total_trucks || 0
  if (total === 0) return 0
  const onTrip = truckDashboard.value.on_trip_trucks || 0
  return Math.round((onTrip / total) * 100)
}

onMounted(() => {
  loadOperationsData()
})
</script>

<template>
  <!-- Operations Panels + Recent Trips -->
  <div class="mt-8">
    <!-- Section Header -->
    <div class="mb-4 flex items-center gap-2">
      <h3 class="text-lg font-bold text-heading">{{ $t('dashboard.operations.title') }}</h3>

      <div class="h-px flex-1 bg-line-light"></div>
    </div>

    <!-- ── Loading skeleton ────────────────────────────────────── -->
    <div v-if="loading" class="grid grid-cols-1 gap-6 lg:grid-cols-2">
      <BaseContentPlaceholders
        v-for="i in 2"
        :key="i"
        :rounded="true"
        class="p-5 bg-surface rounded-xl border border-line-light"
      >
        <div class="flex items-center justify-between mb-4">
          <BaseContentPlaceholdersText class="h-4 w-32" :lines="1" />
          <BaseContentPlaceholdersText class="h-6 w-16" :lines="1" />
        </div>
        <div class="grid grid-cols-3 gap-3">
          <BaseContentPlaceholdersText
            v-for="j in 3"
            :key="j"
            class="h-12"
            :lines="2"
          />
        </div>
      </BaseContentPlaceholders>
    </div>

    <template v-else>
      <!-- ── Two side-by-side panels ──────────────────────────── -->
      <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <!-- Left Panel: Fleet Status -->
        <BaseCard container-class="p-5">
          <div class="mb-4 flex items-center justify-between">
            <h4 class="flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-muted">
              <BaseIcon name="TruckIcon" class="h-4 w-4" />
              {{ $t('dashboard.operations.fleet_status') }}
            </h4>
            <BaseButton size="xs" variant="primary-outline" @click="goToFleet">
              {{ $t('dashboard.operations.view_fleet') }}
            </BaseButton>

          </div>

          <!-- Empty state: no trucks -->
          <div
            v-if="(truckDashboard.total_trucks || 0) === 0"
            class="flex flex-col items-center justify-center py-8 text-center"
          >
            <div class="flex items-center justify-center w-12 h-12 rounded-full bg-surface-secondary mb-3">
              <BaseIcon name="TruckIcon" class="h-6 w-6 text-subtle" />
            </div>
            <p class="text-sm font-medium text-muted">{{ $t('dashboard.operations.no_trucks') }}</p>
            <p class="text-xs text-subtle mt-1">{{ $t('dashboard.operations.no_trucks_desc') }}</p>
            <BaseButton size="xs" variant="primary-outline" class="mt-3" @click="goToFleet">
              {{ $t('dashboard.operations.add_truck') }}
            </BaseButton>
          </div>

          <template v-else>
            <!-- Fleet mini KPIs (3) -->
            <div class="grid grid-cols-3 gap-3">
              <div class="text-center">
                <p class="text-xs text-muted">{{ $t('dashboard.operations.total') }}</p>
                <p class="text-xl font-bold text-heading">{{ truckDashboard.total_trucks || 0 }}</p>
              </div>
              <div class="text-center">
                <p class="text-xs text-muted">{{ $t('dashboard.operations.available') }}</p>
                <p class="text-xl font-bold text-status-green">{{ truckDashboard.available_trucks || 0 }}</p>
              </div>
              <div class="text-center">
                <p class="text-xs text-muted">{{ $t('dashboard.operations.on_trip') }}</p>
                <p class="text-xl font-bold text-status-blue">{{ truckDashboard.on_trip_trucks || 0 }}</p>
              </div>
            </div>

            <!-- Status distribution bar -->
            <div class="mt-4">
              <div class="mb-1.5 flex items-center justify-between text-xs">
                <span class="text-muted">{{ $t('dashboard.operations.fleet_utilization') }}</span>
                <span class="font-semibold text-heading">{{ getUtilizationPercent() }}%</span>
              </div>
              <div class="flex h-2.5 overflow-hidden rounded-full bg-surface-muted">
                <div
                  v-if="truckDashboard.available_trucks"
                  class="bg-status-green transition-all duration-500"
                  :style="{ width: `${(truckDashboard.available_trucks / truckDashboard.total_trucks) * 100}%` }"
                ></div>
                <div
                  v-if="truckDashboard.on_trip_trucks"
                  class="bg-status-blue transition-all duration-500"
                  :style="{ width: `${(truckDashboard.on_trip_trucks / truckDashboard.total_trucks) * 100}%` }"
                ></div>
              </div>
              <!-- Legend -->
              <div class="mt-2 flex items-center gap-4 text-xs text-muted">
                <span class="flex items-center gap-1.5">
                  <span class="w-2 h-2 rounded-full bg-status-green"></span>
                  {{ $t('dashboard.operations.available') }}
                </span>
                <span class="flex items-center gap-1.5">
                  <span class="w-2 h-2 rounded-full bg-status-blue"></span>
                  {{ $t('dashboard.operations.on_trip') }}
                </span>
              </div>
            </div>
          </template>
        </BaseCard>

        <!-- Right Panel: Warehouse & Dispatch Status -->
        <BaseCard container-class="p-5">
          <div class="mb-4 flex items-center justify-between">
            <h4 class="flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-muted">
              <BaseIcon name="CubeIcon" class="h-4 w-4" />
              {{ $t('dashboard.operations.warehouse_dispatch') }}
            </h4>
            <BaseButton size="xs" variant="primary-outline" @click="goToWarehouse">
              {{ $t('dashboard.operations.view_warehouse') }}
            </BaseButton>
          </div>

          <!-- Empty state: no warehouse items -->
          <div
            v-if="(warehouseStats?.stored_items || 0) === 0 && (warehouseStats?.overdue_items || 0) === 0"
            class="flex flex-col items-center justify-center py-8 text-center"
          >
            <div class="flex items-center justify-center w-12 h-12 rounded-full bg-surface-secondary mb-3">
              <BaseIcon name="CubeIcon" class="h-6 w-6 text-subtle" />
            </div>
            <p class="text-sm font-medium text-muted">{{ $t('dashboard.operations.warehouse_empty') }}</p>
            <p class="text-xs text-subtle mt-1">{{ $t('dashboard.operations.warehouse_empty_desc') }}</p>
            <BaseButton size="xs" variant="primary-outline" class="mt-3" @click="goToWarehouse">
              {{ $t('dashboard.operations.view_warehouse') }}
            </BaseButton>
          </div>

          <template v-else>
            <!-- Warehouse mini KPIs -->
            <div class="grid grid-cols-4 gap-3">
              <div class="text-center">
                <p class="text-xs text-muted">{{ $t('dashboard.operations.stored') }}</p>
                <p class="text-xl font-bold text-heading">{{ warehouseStats?.stored_items || 0 }}</p>
              </div>
              <div class="text-center">
                <p class="text-xs text-muted">{{ $t('dashboard.operations.overdue') }}</p>
                <p
                  class="text-xl font-bold"
                  :class="(warehouseStats?.overdue_items || 0) > 0 ? 'text-status-red' : 'text-heading'"
                >
                  {{ warehouseStats?.overdue_items || 0 }}
                </p>
              </div>
              <div class="text-center">
                <p class="text-xs text-muted">{{ $t('dashboard.operations.destinations') }}</p>
                <p class="text-xl font-bold text-heading">{{ warehouseStats?.unique_destinations || 0 }}</p>
              </div>
              <div class="text-center">
                <p class="text-xs text-muted">{{ $t('dashboard.operations.weight_kg') }}</p>
                <p class="text-xl font-bold text-heading">
                  {{ warehouseStats?.total_weight_kg ? Math.round(warehouseStats.total_weight_kg) : 0 }}
                </p>
              </div>
            </div>

            <!-- Aging buckets mini-bar -->
            <div v-if="warehouseStats?.aging_buckets" class="mt-4">
              <p class="mb-2 text-xs text-muted">{{ $t('dashboard.operations.aging_buckets') }}</p>
              <div class="flex gap-2">
                <div
                  v-for="(count, bucket) in warehouseStats.aging_buckets"
                  :key="bucket"
                  class="flex-1 rounded-md border border-line-light p-2 text-center"
                >
                  <div
                    class="mx-auto mb-1 h-2 w-8 rounded-full"
                    :class="getAgingColorClass(String(bucket))"
                  ></div>
                  <p class="text-xs text-muted">{{ bucket }} {{ $t('dashboard.operations.days') }}</p>
                  <p class="text-sm font-bold text-heading">{{ count }}</p>
                </div>
              </div>
            </div>
          </template>
        </BaseCard>
      </div>

      <!-- ── Recent Active Trips (compact table) ────────────────── -->
      <div class="mt-6">
        <div class="mb-3 flex items-center justify-between">
          <h4 class="text-sm font-bold uppercase tracking-wide text-muted">{{ $t('dashboard.operations.recent_active_trips') }}</h4>
          <BaseButton size="xs" variant="primary-outline" @click="goToLoadTrips">
            {{ $t('dashboard.operations.view_all') }}
          </BaseButton>
        </div>

        <!-- Empty state: no active trips -->
        <div
          v-if="recentTrips.length === 0"
          class="flex flex-col items-center justify-center py-8 rounded-lg border border-dashed border-line-default bg-surface-secondary"
        >
          <div class="flex items-center justify-center w-12 h-12 rounded-full bg-surface mb-3">
            <BaseIcon name="MapPinIcon" class="h-6 w-6 text-subtle" />
          </div>
          <p class="text-sm font-medium text-muted">{{ $t('dashboard.operations.no_active_trips') }}</p>
          <p class="text-xs text-subtle mt-1">{{ $t('dashboard.operations.no_active_trips_desc') }}</p>
          <BaseButton size="xs" variant="primary-outline" class="mt-3" @click="goToLoadTrips">
            {{ $t('dashboard.operations.create_trip') }}
          </BaseButton>
        </div>

        <!-- Trips table -->
        <div
          v-else
          class="overflow-hidden rounded-lg bg-surface shadow-sm border border-line-light"
        >
          <table class="w-full text-sm">
            <thead class="bg-surface-secondary text-xs uppercase text-muted">
              <tr>
                <th class="px-4 py-2.5 text-left font-semibold">{{ $t('dashboard.operations.trip_number') }}</th>
                <th class="px-4 py-2.5 text-left font-semibold">{{ $t('dashboard.operations.route') }}</th>
                <th class="px-4 py-2.5 text-left font-semibold">{{ $t('dashboard.operations.truck') }}</th>
                <th class="px-4 py-2.5 text-center font-semibold">{{ $t('dashboard.operations.status') }}</th>
                <th class="px-4 py-2.5 text-center font-semibold">{{ $t('dashboard.operations.dispatch_date') }}</th>
              </tr>
            </thead>

            <tbody class="divide-y divide-line-light">
              <tr
                v-for="trip in recentTrips"
                :key="trip.id"
                class="cursor-pointer hover:bg-hover transition-colors"
                @click="goToTripDetail(trip.id)"
              >
                <td class="px-4 py-3 font-semibold text-heading">{{ trip.trip_number }}</td>
                <td class="px-4 py-3 text-body">
                  <span class="inline-flex items-center gap-1">
                    {{ trip.origin_city || '—' }}
                    <BaseIcon name="ArrowRightIcon" class="w-3 h-3 text-subtle" />
                    {{ trip.destination_city || '—' }}
                  </span>
                </td>
                <td class="px-4 py-3 text-body">{{ trip.truck_number || '—' }}</td>
                <td class="px-4 py-3 text-center">
                  <span
                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold"
                    :class="getTripStatusClass(trip.status)"
                  >
                    {{ trip.status }}
                  </span>
                </td>
                <td class="px-4 py-3 text-center text-body">
                  {{ trip.dispatch_date ? formatDate(trip.dispatch_date) : '—' }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>
  </div>
</template>
