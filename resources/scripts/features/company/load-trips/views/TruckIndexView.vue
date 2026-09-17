<template>
  <BasePage>
    <BasePageHeader title="Fleet Management">
      <p class="mt-1 text-sm text-muted">
        Plan vehicles, track availability, and monitor trips from one place.
      </p>

      <template #actions>
        <div class="flex items-center justify-end gap-3">
          <BaseButton
            v-if="activeTab === 'vehicles'"
            variant="primary-outline"
            @click="toggleFilter"
          >
            Filter
            <template #right="slotProps">
              <BaseIcon
                v-if="!showFilters"
                name="FunnelIcon"
                :class="slotProps.class"
              />
              <BaseIcon v-else name="XMarkIcon" :class="slotProps.class" />
            </template>
          </BaseButton>
          <BaseButton
            v-if="activeTab === 'financial'"
            variant="primary-outline"
            @click="toggleTripFilters"
          >
            Filter
            <template #right="slotProps">
              <BaseIcon
                v-if="!showTripFilters"
                name="FunnelIcon"
                :class="slotProps.class"
              />
              <BaseIcon v-else name="XMarkIcon" :class="slotProps.class" />
            </template>
          </BaseButton>
          <BaseButton
            v-if="activeTab === 'vehicles'"
            variant="primary-outline"
            @click="openOwnerCreate"
          >
            <template #left="slotProps">
              <BaseIcon name="PlusIcon" :class="slotProps.class" />
            </template>
            Add Owner
          </BaseButton>
          <BaseButton
            v-if="activeTab === 'vehicles'"
            variant="primary"
            @click="openCreateTruck"
          >
            <template #left="slotProps">
              <BaseIcon name="PlusIcon" :class="slotProps.class" />
            </template>
            Add Truck
          </BaseButton>
        </div>
      </template>
    </BasePageHeader>

    <!-- A. Fleet KPI Strip (always visible above tabs) -->
    <div class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-6">
      <div
        v-for="card in overviewCards"
        :key="card.label"
        class="rounded-lg border-l-4 bg-surface p-4 shadow-sm"
        :class="card.borderClass"
        :class-extra="card.clickable ? 'cursor-pointer hover:bg-hover' : ''"
        @click="card.clickable ? toggleStatusFilter(card.filterValue) : null"
      >
        <p class="text-xs text-muted">{{ card.label }}</p>
        <p class="mt-1 text-2xl font-bold text-heading">{{ card.value }}</p>
      </div>
    </div>

    <!-- B. Tab Bar -->
    <div class="mb-6 mt-6 flex gap-1 border-b border-line-default">
      <BaseButton
        v-for="tab in tabs"
        :key="tab.key"
        type="button"
        variant="white"
        class="whitespace-nowrap border-b-2 px-4 py-2 text-sm font-semibold transition !rounded-none !shadow-none"
        :class="
          tab.key === activeTab
            ? 'border-primary-500 text-primary-500'
            : 'border-transparent text-muted hover:text-body'
        "
        @click="activeTab = tab.key"
      >
        {{ tab.label }}
        <span
          v-if="tab.count !== null && tab.count !== undefined"
          class="ml-1 rounded-full px-1.5 py-0.5 text-xs"
          :class="
            tab.key === activeTab
              ? 'bg-primary-500/10 text-primary-500'
              : 'bg-surface-tertiary text-muted'
          "
        >
          {{ tab.count }}
        </span>
      </BaseButton>
    </div>

    <!-- ==================== TAB 1: VEHICLES ==================== -->
    <div v-if="activeTab === 'vehicles'">
      <!-- Filter Panel -->
      <BaseFilterWrapper :show="showFilters" :row-on-xl="true" @clear="clearFilters">
        <BaseInputGroup label="Owner">
          <BaseMultiselect
            v-model="filters.owner_id"
            :options="ownerOptions"
            value-prop="id"
            label="name"
            track-by="id"
            searchable
            placeholder="All owners"
            @update:model-value="applyFilters"
          />
        </BaseInputGroup>

        <BaseInputGroup label="Status">
          <BaseMultiselect
            v-model="filters.status"
            :options="statusOptions"
            value-prop="value"
            label="label"
            track-by="value"
            placeholder="All statuses"
            @update:model-value="applyFilters"
          />
        </BaseInputGroup>

        <BaseInputGroup label="Min Capacity (kg)">
          <BaseInput
            v-model.number="filters.min_capacity"
            type="number"
            min="0"
            placeholder="0"
            @change="applyFilters"
          />
        </BaseInputGroup>

        <BaseInputGroup label="Max Capacity (kg)">
          <BaseInput
            v-model.number="filters.max_capacity"
            type="number"
            min="0"
            placeholder="∞"
            @change="applyFilters"
          />
        </BaseInputGroup>

        <BaseInputGroup label="Search">
          <BaseInput
            v-model="filters.search"
            type="text"
            placeholder="Truck number..."
            @input="applyFilters"
          />
        </BaseInputGroup>
      </BaseFilterWrapper>

      <!-- Truck Cards Grid -->
      <div v-if="trucks.length > 0" class="mt-5">
        <h2 class="mb-3 text-lg font-bold text-heading">Fleet Vehicles</h2>
      </div>
      <div
        v-if="trucks.length === 0"
        class="mt-5 rounded-lg border border-dashed border-line-default bg-surface-secondary py-14 text-center text-muted"
      >
        Add your first vehicle to begin planning warehouse load trips.
      </div>

      <div v-else class="mt-3 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        <div
          v-for="truck in trucks"
          :key="truck.id"
          class="cursor-pointer rounded-lg border-l-4 bg-surface p-4 shadow-sm transition hover:shadow-md"
          :class="getStatusBorderClass(truck.status)"
          @click="selectTruck(truck)"
        >
          <!-- Header row -->
          <div class="flex items-start justify-between gap-3">
            <div>
              <p class="text-lg font-bold text-heading">{{ truck.truck_number }}</p>
              <p class="mt-0.5 text-sm text-body">
                {{ truck.capacity_kg }} kg ·
                {{ truck.vehicle_type || 'Vehicle type not set' }}
              </p>
            </div>
            <span
              class="rounded-full px-2 py-1 text-xs font-bold capitalize"
              :class="getStatusPillClass(truck.status)"
            >
              {{ truck.status.replace('_', ' ') }}
            </span>
          </div>

          <!-- Body -->
          <div class="mt-3 space-y-1 text-xs text-muted">
            <p>Owner: {{ truck.owner_profile?.name || 'Not linked' }}</p>
          </div>

          <!-- Footer actions -->
          <div class="mt-4 flex items-center gap-2 border-t border-line-light pt-3">
            <BaseButton variant="white" size="sm" @click.stop="selectTruck(truck)">
              View Details
            </BaseButton>
            <BaseButton variant="white" size="sm" @click.stop="openEditTruck(truck)">
              <template #left="slotProps">
                <BaseIcon name="PencilSquareIcon" :class="slotProps.class" />
              </template>
              Edit
            </BaseButton>
            <BaseButton variant="danger" size="sm" @click.stop="confirmDelete(truck)">
              <template #left="slotProps">
                <BaseIcon name="TrashIcon" :class="slotProps.class" />
              </template>
            </BaseButton>
          </div>
        </div>
      </div>
    </div>

    <!-- ==================== TAB 2: FINANCIAL OVERVIEW ==================== -->
    <div v-if="activeTab === 'financial'">
      <!-- Trip Filter Panel -->
      <BaseFilterWrapper :show="showTripFilters" :row-on-xl="true" @clear="clearTripFilters">
        <BaseInputGroup label="Status">
          <BaseMultiselect
            v-model="tripFilters.status"
            :options="tripStatusOptions"
            value-prop="value"
            label="label"
            track-by="value"
            placeholder="All statuses"
            @update:model-value="fetchTrips(1)"
          />
        </BaseInputGroup>

        <BaseInputGroup label="LR Number">
          <BaseInput
            v-model="tripFilters.lr"
            type="text"
            placeholder="Search LR number..."
            @input="onTripFilterInput"
          />
        </BaseInputGroup>

        <BaseInputGroup label="Driver">
          <BaseInput
            v-model="tripFilters.driver"
            type="text"
            placeholder="Search driver..."
            @input="onTripFilterInput"
          />
        </BaseInputGroup>

        <BaseInputGroup label="Truck No">
          <BaseInput
            v-model="tripFilters.truck_number"
            type="text"
            placeholder="Search truck..."
            @input="onTripFilterInput"
          />
        </BaseInputGroup>

        <BaseInputGroup label="Route">
          <BaseInput
            v-model="tripFilters.route"
            type="text"
            placeholder="Search origin/destination..."
            @input="onTripFilterInput"
          />
        </BaseInputGroup>
      </BaseFilterWrapper>

      <!-- Fleet Financial Overview -->
      <BaseCard container-class="px-5 py-5">
        <template #header>
          <div class="flex items-center justify-between">
            <div>
              <h2 class="font-semibold text-heading">Fleet financial overview</h2>
              <p class="text-sm text-muted">
                Revenue is allocated by the actual LR weight carried on each trip.
              </p>
            </div>
          </div>
        </template>

        <!-- Summary Numbers -->
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
          <div class="rounded-lg bg-surface-secondary p-3">
            <p class="text-xs text-muted">Trip revenue</p>
            <p class="text-lg font-semibold text-heading">
              <BaseFormatMoney :amount="report.total_trip_revenue || 0" />
            </p>
          </div>
          <div class="rounded-lg bg-surface-secondary p-3">
            <p class="text-xs text-muted">Fleet expense</p>
            <p class="text-lg font-semibold text-heading">
              <BaseFormatMoney :amount="report.total_fleet_expense || 0" />
            </p>
          </div>
          <div class="rounded-lg bg-surface-secondary p-3">
            <p class="text-xs text-muted">Net margin</p>
            <p
              class="text-lg font-semibold"
              :class="(report.total_trip_margin || 0) < 0 ? 'text-status-red' : 'text-status-green'"
            >
              <BaseFormatMoney :amount="report.total_trip_margin || 0" />
            </p>
          </div>
        </div>

        <!-- Trip Pipeline -->
        <div v-if="report.trip_pipeline" class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
          <div
            v-for="stage in pipelineStages"
            :key="stage.key"
            class="cursor-pointer rounded-lg border p-3 transition hover:bg-hover"
            :class="tripFilters.status === stage.key ? 'border-primary-500 bg-surface-secondary' : 'border-line-light'"
            @click="toggleTripFilter(stage.key)"
          >
            <p class="text-xs" :class="stage.textClass">{{ stage.label }}</p>
            <p class="mt-1 text-xl font-bold text-heading">{{ report.trip_pipeline[stage.key] || 0 }}</p>
          </div>
        </div>

        <!-- Enhanced Trips Table -->
        <div class="mt-4 overflow-x-auto">
          <!-- Loading state -->
          <div
            v-if="tripsLoading"
            class="flex items-center justify-center py-10 text-muted"
          >
            <svg
              class="h-6 w-6 animate-spin text-primary-500"
              xmlns="http://www.w3.org/2000/svg"
              fill="none"
              viewBox="0 0 24 24"
            >
              <circle
                class="opacity-25"
                cx="12"
                cy="12"
                r="10"
                stroke="currentColor"
                stroke-width="4"
              />
              <path
                class="opacity-75"
                fill="currentColor"
                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
              />
            </svg>
            <span class="ml-2 text-sm">Loading trips…</span>
          </div>

          <!-- Empty state -->
          <div
            v-else-if="!filteredTrips.length"
            class="rounded-lg border border-dashed border-line-default bg-surface-secondary py-10 text-center text-muted"
          >
            No trips found for the selected filter.
          </div>

          <!-- Trips table -->
          <table v-else class="w-full text-left text-sm">
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
                v-for="trip in filteredTrips"
                :key="trip.load_trip_id"
                class="cursor-pointer border-t border-line-light hover:bg-hover"
                @click="goToTrip(trip.load_trip_id)"
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
        </div>

        <!-- Server-side pagination -->
        <TablePagination
          v-if="!tripsLoading && filteredTrips.length"
          :pagination="{
            currentPage: tripPage,
            totalPages: tripTotalPages,
            totalCount: tripTotalCount,
            count: filteredTrips.length,
            limit: tripPerPage,
          }"
          @page-change="onTripsPageChange"
        />
      </BaseCard>

      <!-- Active Trips Panel -->
      <div v-if="report.active_trips?.length" class="mt-6">
        <h2 class="mb-3 text-lg font-bold text-heading">Active Trips On The Road</h2>
        <div class="flex gap-4 overflow-x-auto pb-2">
          <div
            v-for="trip in report.active_trips"
            :key="trip.id"
            class="min-w-[260px] cursor-pointer rounded-lg border border-line-light bg-surface p-4 shadow-sm transition hover:shadow-md"
            @click="goToTrip(trip.id)"
          >
            <div class="flex items-start justify-between">
              <div>
                <p class="font-semibold text-heading">{{ trip.trip_number }}</p>
                <p class="text-sm text-muted">{{ trip.route }}</p>
              </div>
              <span
                class="rounded-full px-2 py-1 text-xs font-bold"
                :class="getDaysElapsedClass(trip.days_elapsed)"
              >
                {{ trip.days_elapsed }}d
              </span>
            </div>
            <div class="mt-3 space-y-1 text-xs text-body">
              <p>🚛 {{ trip.truck_number || '—' }}</p>
              <p>👤 {{ trip.driver_name || '—' }}</p>
              <p v-if="trip.driver_phone">📞 {{ trip.driver_phone }}</p>
            </div>
            <div class="mt-3 flex gap-4 text-xs text-muted">
              <span>{{ trip.lr_count }} LR(s)</span>
              <span>{{ formatWeight(trip.total_weight_kg) }} kg</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Add/Edit Truck Modal (using shared TruckFormModal) -->
    <TruckFormModal
      :show="showTruckForm"
      :truck="editingTruck"
      @close="closeTruckForm"
      @saved="onTruckSaved"
    />

    <LorryPartyProfileModal />
  </BasePage>
</template>


<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { client } from '@/scripts/api/client'
import LorryPartyProfileModal from '@/scripts/features/company/invoices/components/LorryPartyProfileModal.vue'
import TruckFormModal from '@/scripts/features/company/shared/TruckFormModal.vue'
import { useLorryPartyProfileStore } from '@/scripts/features/company/lorry-party-profiles/store'
import { useModalStore } from '@/scripts/stores/modal.store'
import { useDialogStore } from '@/scripts/stores/dialog.store'
import BaseFormatMoney from '@/scripts/components/base/BaseFormatMoney.vue'
import TablePagination from '@/scripts/components/table/TablePagination.vue'


interface Truck {
  id: number
  truck_number: string
  capacity_kg: number | string
  current_odometer_km?: number
  vehicle_type?: string
  status: string
  owner_profile?: { name: string }
  owner_profile_id?: number | string
}

const trucks = ref<Truck[]>([])
const owners = ref<{ id: number; name: string }[]>([])
const dashboard = ref<Record<string, number>>({})
const report = ref<any>({})
const showTruckForm = ref(false)
const showFilters = ref(false)
const showTripFilters = ref(false)
const editingTruck = ref<any>(null)
const activeTab = ref('vehicles')

// Filter state for financial overview trips table (server-side)
const tripFilters = ref({
  status: '' as string,
  lr: '' as string,
  driver: '' as string,
  truck_number: '' as string,
  route: '' as string,
})

// Pagination state for financial overview trips table
const tripPage = ref(1)
const tripTotalPages = ref(1)
const tripTotalCount = ref(0)
const tripPerPage = ref(10)
const tripRows = ref<any[]>([])
const tripsLoading = ref(false)


const tabs = computed(() => [
  { key: 'vehicles', label: 'Vehicles', count: trucks.value.length || null },
  { key: 'financial', label: 'Financial Overview', count: null },
])


const toggleFilter = () => {
  showFilters.value = !showFilters.value
}

const filters = ref({
  owner_id: '' as string | number,
  status: '' as string,
  min_capacity: '' as number | string,
  max_capacity: '' as number | string,
  search: '' as string,
})

const router = useRouter()
const modalStore = useModalStore()
const profileStore = useLorryPartyProfileStore()
const dialogStore = useDialogStore()

const statusOptions = [
  { value: 'available', label: 'Available' },
  { value: 'reserved', label: 'Reserved' },
  { value: 'on_trip', label: 'On Trip' },
  { value: 'maintenance', label: 'Maintenance' },
  { value: 'inactive', label: 'Inactive' },
]

const tripStatusOptions = [
  { value: 'planned', label: 'Planned' },
  { value: 'dispatched', label: 'Dispatched' },
  { value: 'delivered', label: 'Delivered' },
  { value: 'cancelled', label: 'Cancelled' },
]

const ownerOptions = computed(() => owners.value)

const overviewCards = computed(() => [
  { label: 'Total fleet', value: dashboard.value.total_trucks || 0, borderClass: 'border-line-default', clickable: false, filterValue: '' },
  { label: 'Available', value: dashboard.value.available_trucks || 0, borderClass: 'border-status-green', clickable: true, filterValue: 'available' },
  { label: 'On trip', value: dashboard.value.on_trip_trucks || 0, borderClass: 'border-status-blue', clickable: true, filterValue: 'on_trip' },
  { label: 'Active trips', value: report.value.trip_pipeline?.dispatched || 0, borderClass: 'border-status-purple', clickable: false, filterValue: '' },
  { label: 'Total revenue', value: formatCompactMoney(report.value.total_trip_revenue || 0), borderClass: 'border-status-green', clickable: false, filterValue: '' },
])

const pipelineStages = [
  { key: 'planned', label: 'Planned', textClass: 'text-status-blue' },
  { key: 'dispatched', label: 'Dispatched', textClass: 'text-status-purple' },
  { key: 'delivered', label: 'Delivered', textClass: 'text-status-green' },
  { key: 'cancelled', label: 'Cancelled', textClass: 'text-status-red' },
]

// Server-side paginated trips — uses tripRows (fetched from API), not report.by_trip
const filteredTrips = computed(() => tripRows.value)

const totalTripWeight = computed(() =>
  filteredTrips.value.reduce((sum: number, t: any) => sum + (t.total_weight_kg || 0), 0)
)
const totalTripRevenue = computed(() =>
  filteredTrips.value.reduce((sum: number, t: any) => sum + (t.revenue_total || 0), 0)
)
const totalTripExpense = computed(() =>
  filteredTrips.value.reduce((sum: number, t: any) => sum + (t.expense_total || 0), 0)
)
const totalTripMargin = computed(() =>
  filteredTrips.value.reduce((sum: number, t: any) => sum + (t.margin_total || 0), 0)
)

const fetchTrips = async (page = 1) => {
  tripsLoading.value = true
  try {
    const params: Record<string, any> = {
      page,
      per_page: tripPerPage.value,
    }
    if (tripFilters.value.status) {
      params.status = tripFilters.value.status
    }
    if (tripFilters.value.lr) {
      params.lr = tripFilters.value.lr
    }
    if (tripFilters.value.driver) {
      params.driver = tripFilters.value.driver
    }
    if (tripFilters.value.truck_number) {
      params.truck_number = tripFilters.value.truck_number
    }
    if (tripFilters.value.route) {
      params.route = tripFilters.value.route
    }
    const response = await client.get('/api/v1/fleet-reports/trips', { params })
    tripRows.value = response.data?.data || []
    const meta = response.data?.meta
    if (meta) {
      tripPage.value = meta.current_page
      tripTotalPages.value = meta.last_page
      tripTotalCount.value = meta.total
    }
  } catch (error) {
    console.error('Error fetching trips:', error)
    tripRows.value = []
  } finally {
    tripsLoading.value = false
  }
}

const onTripsPageChange = (page: number) => {
  fetchTrips(page)
}

// Debounced auto-filter for text inputs — fires 400ms after the user stops typing
let tripFilterTimer: ReturnType<typeof setTimeout> | null = null
const onTripFilterInput = () => {
  if (tripFilterTimer) {
    clearTimeout(tripFilterTimer)
  }
  tripFilterTimer = setTimeout(() => {
    fetchTrips(1)
  }, 400)
}


const load = async () => {
  const [truckResponse, ownerResponse, dashboardResponse, reportResponse] =
    await Promise.all([
      client.get('/api/v1/trucks', { params: filters.value }),
      client.get('/api/v1/lorry-party-profiles?type=OWNER&limit=all'),
      client.get('/api/v1/trucks/dashboard'),
      client.get('/api/v1/fleet-reports/summary'),
    ])
  trucks.value = truckResponse.data?.data || truckResponse.data || []
  owners.value = ownerResponse.data?.data || ownerResponse.data || []
  dashboard.value = dashboardResponse.data?.data || {}
  report.value = reportResponse.data?.data || {}
}

const applyFilters = () => {
  load()
}

const clearFilters = () => {
  filters.value = {
    owner_id: '',
    status: '',
    min_capacity: '',
    max_capacity: '',
    search: '',
  }
  load()
}

const toggleTripFilters = () => {
  showTripFilters.value = !showTripFilters.value
}

const clearTripFilters = () => {
  tripFilters.value = {
    status: '',
    lr: '',
    driver: '',
    truck_number: '',
    route: '',
  }
  fetchTrips(1)
}

const toggleStatusFilter = (status: string) => {
  filters.value.status = filters.value.status === status ? '' : status
  applyFilters()
}

const toggleTripFilter = (status: string) => {
  tripFilters.value.status = tripFilters.value.status === status ? '' : status
  fetchTrips(1)
}

const selectTruck = (truck: Truck) => {
  router.push({ name: 'trucks.show', params: { id: truck.id } })
}

const goToTrip = (tripId: number) => {
  router.push({ name: 'load-trips.show', params: { id: tripId } })
}

const openCreateTruck = () => {
  editingTruck.value = null
  showTruckForm.value = true
}

const openEditTruck = (truck: Truck) => {
  editingTruck.value = { ...truck }
  showTruckForm.value = true
}

const closeTruckForm = () => {
  showTruckForm.value = false
  editingTruck.value = null
}

const onTruckSaved = () => {
  closeTruckForm()
  load()
}

const confirmDelete = async (truck: Truck) => {
  const confirmed = await dialogStore.openDialog({
    title: 'Delete Truck',
    message: `Delete truck "${truck.truck_number}"? This cannot be undone.`,
    variant: 'danger',
    yesLabel: 'Delete',
  })
  if (!confirmed) return
  try {
    await client.delete(`/api/v1/trucks/${truck.id}`)
    await load()
  } catch (error: any) {
    const serverMessage = error?.response?.data?.message
    const serverErrors = error?.response?.data?.errors
    let message = 'Failed to delete truck'
    if (serverErrors) {
      const fieldMessages = Object.values(serverErrors).flat() as string[]
      message = fieldMessages.join('\n') || serverMessage || message
    } else if (serverMessage) {
      message = serverMessage
    }
    await dialogStore.openDialog({
      title: 'Error',
      message,
      variant: 'danger',
      hideNoButton: true,
    })
  }
}

const openOwnerCreate = () => {
  profileStore.setCurrentProfile({
    type: 'OWNER',
    name: '',
    phone: '',
    address: '',
  })
  modalStore.openModal({
    title: 'New Owner',
    componentName: 'LorryPartyProfileModal',
    size: 'lg',
  })
}

watch(
  () => profileStore.lastSavedAt,
  async (timestamp) => {
    if (timestamp && profileStore.lastSavedProfile?.type === 'OWNER') {
      await load()
    }
  }
)

// ─── Helper functions ───

const formatWeight = (weight: any) => {
  const w = parseFloat(weight) || 0
  return w.toLocaleString('en-IN', { maximumFractionDigits: 2 })
}

const formatCompactMoney = (amount: number) => {
  if (amount >= 10000000) return '₹' + (amount / 10000000).toFixed(1) + 'Cr'
  if (amount >= 100000) return '₹' + (amount / 100000).toFixed(1) + 'L'
  if (amount >= 1000) return '₹' + (amount / 1000).toFixed(0) + 'K'
  return '₹' + amount
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

const getDaysElapsedClass = (days: number) => {
  if (days > 5) return 'bg-status-red/10 text-status-red'
  if (days >= 3) return 'bg-status-yellow/10 text-status-yellow'
  return 'bg-status-green/10 text-status-green'
}

const getStatusBorderClass = (status: string) => {
  const classes: Record<string, string> = {
    available: 'border-status-green',
    reserved: 'border-status-blue',
    on_trip: 'border-status-blue',
    maintenance: 'border-status-yellow',
    inactive: 'border-line-default',
  }
  return classes[status] || 'border-line-default'
}

const getStatusPillClass = (status: string) => {
  const classes: Record<string, string> = {
    available: 'bg-status-green/10 text-status-green',
    reserved: 'bg-status-blue/10 text-status-blue',
    on_trip: 'bg-status-blue/10 text-status-blue',
    maintenance: 'bg-status-yellow/10 text-status-yellow',
    inactive: 'bg-surface-tertiary text-body',
  }
  return classes[status] || 'bg-surface-tertiary text-body'
}

onMounted(() => {
  load()
  fetchTrips(1)
})
</script>
