<template>
  <div>
    <!-- Top Bar -->
    <div class="cp-topbar">
      <div class="cp-topbar-left">
        <h1>Load Trips — Truck Dispatch Tracking</h1>
        <p>Track your dispatched shipments from planning to delivery</p>
      </div>
    </div>

    <!-- Status Filter Tabs -->
    <div class="cp-filter-bar">
      <button
        :class="['cp-filter-tab', activeStatus === '' ? 'cp-filter-tab-active' : '']"
        @click="setStatus('')"
      >
        All <span class="cp-filter-count">{{ totalCount }}</span>
      </button>
      <button
        :class="['cp-filter-tab', activeStatus === 'planned' ? 'cp-filter-tab-active' : '']"
        @click="setStatus('planned')"
      >
        Planned <span v-if="statusCounts.planned" class="cp-filter-count">{{ statusCounts.planned }}</span>
      </button>
      <button
        :class="['cp-filter-tab', activeStatus === 'dispatched' ? 'cp-filter-tab-active' : '']"
        @click="setStatus('dispatched')"
      >
        In Transit <span v-if="statusCounts.dispatched" class="cp-filter-count">{{ statusCounts.dispatched }}</span>
      </button>
      <button
        :class="['cp-filter-tab', activeStatus === 'delivered' ? 'cp-filter-tab-active' : '']"
        @click="setStatus('delivered')"
      >
        Delivered <span v-if="statusCounts.delivered" class="cp-filter-count">{{ statusCounts.delivered }}</span>
      </button>
      <button
        :class="['cp-filter-tab', activeStatus === 'cancelled' ? 'cp-filter-tab-active' : '']"
        @click="setStatus('cancelled')"
      >
        Cancelled <span v-if="statusCounts.cancelled" class="cp-filter-count">{{ statusCounts.cancelled }}</span>
      </button>
    </div>

    <!-- Search + Sort Bar -->
    <div class="cp-search-bar">
      <input
        v-model="searchText"
        type="text"
        class="cp-search-input"
        placeholder="Search by trip no, truck, destination, driver..."
        @input="onSearchDebounced"
      />
      <select v-model="sortField" class="cp-sort-select" @change="fetchTrips">
        <option value="created_at">Sort: Latest</option>
        <option value="dispatch_date">Sort: Dispatch Date</option>
        <option value="expected_delivery_date">Sort: Expected Delivery</option>
        <option value="trip_number">Sort: Trip Number</option>
      </select>
      <button class="cp-sort-toggle" @click="toggleSortOrder">
        <span v-if="sortOrder === 'asc'">↑ Asc</span>
        <span v-else>↓ Desc</span>
      </button>
    </div>

    <!-- Table -->
    <div class="cp-table-card" style="margin-bottom: 28px;">
      <div style="overflow-x: auto;">
        <table class="cp-table">
          <thead>
            <tr>
              <th>Trip No</th>
              <th>Truck</th>
              <th>Driver</th>
              <th>Route</th>
              <th>Dispatched</th>
              <th>ETA</th>
              <th>Delivered</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="8" style="text-align: center; color: var(--color-muted); padding: 40px;">Loading...</td>
            </tr>
            <tr v-if="!loading && !trips.length">
              <td colspan="8" style="text-align: center; color: var(--color-muted); padding: 40px;">No load trips found</td>
            </tr>
            <tr v-for="trip in trips" :key="trip.id" class="cp-table-row-clickable" @click="goToDetail(trip.id)">
              <td><span class="cp-table-link">{{ trip.trip_number || `LT-${String(trip.id).padStart(4, '0')}` }}</span></td>
              <td>{{ trip.truck_number || '—' }}</td>
              <td>{{ trip.driver_name || '—' }}</td>
              <td>{{ trip.origin_city || '—' }} → {{ trip.destination_city || '—' }}</td>
              <td>{{ formatDate(trip.dispatch_date) }}</td>
              <td>{{ formatDate(trip.expected_delivery_date) }}</td>
              <td v-if="trip.actual_delivery_date" style="color: var(--color-status-green); font-weight: 600;">{{ formatDate(trip.actual_delivery_date) }}</td>
              <td v-else>—</td>
              <td><span :class="['cp-badge', statusBadgeClass(trip.status)]">{{ statusLabel(trip.status) }}</span></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Pagination -->
    <div v-if="lastPage > 1" class="cp-pagination">
      <button class="cp-page-btn" :disabled="currentPage <= 1" @click="changePage(currentPage - 1)">← Prev</button>
      <span class="cp-page-info">Page {{ currentPage }} of {{ lastPage }}</span>
      <button class="cp-page-btn" :disabled="currentPage >= lastPage" @click="changePage(currentPage + 1)">Next →</button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useDebounceFn } from '@vueuse/core'
import { client } from '@/scripts/api/client'

const route = useRoute()
const router = useRouter()

const trips = ref<any[]>([])
const loading = ref(false)
const totalCount = ref(0)
const statusCounts = ref<Record<string, number>>({})

const activeStatus = ref('')
const searchText = ref('')
const sortField = ref('created_at')
const sortOrder = ref('desc')
const currentPage = ref(1)
const lastPage = ref(1)

// Read initial status from query param (e.g. ?status=delivered from dashboard)
onMounted(() => {
  if (route.query.status) {
    activeStatus.value = route.query.status as string
  }
  fetchTrips()
})

// Also watch for query param changes (e.g. when navigating from dashboard while already on this page)
watch(() => route.query.status, (newStatus) => {
  if (newStatus && newStatus !== activeStatus.value) {
    activeStatus.value = newStatus as string
    currentPage.value = 1
    fetchTrips()
  }
})

async function fetchTrips() {
  loading.value = true
  try {
    const companySlug = route.params.company as string
    const params: Record<string, string | number> = {
      limit: 10,
      page: currentPage.value,
      orderByField: sortField.value,
      orderBy: sortOrder.value,
    }
    if (activeStatus.value) params.status = activeStatus.value
    if (searchText.value.trim()) params.search = searchText.value.trim()

    const { data } = await client.get(`/api/v1/${companySlug}/customer/load-trips`, { params })
    trips.value = data.data || []
    totalCount.value = data.meta?.loadTripTotalCount || 0
    statusCounts.value = data.meta?.status_counts || {}
    lastPage.value = data.meta?.last_page || 1
  } catch (e) {
    console.error('Failed to fetch load trips:', e)
  } finally {
    loading.value = false
  }
}

function setStatus(status: string) {
  activeStatus.value = status
  currentPage.value = 1
  // Update URL query param
  router.replace({ query: status ? { status } : {} })
  fetchTrips()
}

function toggleSortOrder() {
  sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc'
  fetchTrips()
}

function changePage(page: number) {
  currentPage.value = page
  fetchTrips()
}

function goToDetail(id: number) {
  const companySlug = route.params.company as string
  router.push(`/${companySlug}/customer/load-trips/${id}/view`)
}

const onSearchDebounced = useDebounceFn(() => {
  currentPage.value = 1
  fetchTrips()
}, 500)

function formatDate(date: string) {
  if (!date) return '—'
  return new Date(date).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' })
}

function statusLabel(status: string): string {
  const map: Record<string, string> = { planned: 'Planned', dispatched: 'In Transit', delivered: 'Delivered', cancelled: 'Cancelled' }
  return map[status] || status?.replace(/_/g, ' ') || '—'
}

function statusBadgeClass(status: string): string {
  const map: Record<string, string> = { planned: 'cp-badge-muted', dispatched: 'cp-badge-info', delivered: 'cp-badge-success', cancelled: 'cp-badge-danger' }
  return map[status] || 'cp-badge-muted'
}
</script>

<style scoped>
.cp-filter-bar {
  display: flex;
  gap: 8px;
  margin-bottom: 16px;
  flex-wrap: wrap;
}
.cp-filter-tab {
  padding: 8px 16px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 500;
  border: 1px solid var(--color-line-default);
  background: var(--color-surface);
  color: var(--color-body);
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  gap: 6px;
}
.cp-filter-tab:hover {
  background: var(--color-hover);
}
.cp-filter-tab-active {
  background: var(--color-primary);
  color: white;
  border-color: var(--color-primary);
}
.cp-filter-count {
  background: rgba(255, 255, 255, 0.2);
  padding: 1px 7px;
  border-radius: 10px;
  font-size: 11px;
  font-weight: 600;
}
.cp-filter-tab:not(.cp-filter-tab-active) .cp-filter-count {
  background: var(--color-surface-tertiary);
  color: var(--color-muted);
}
.cp-search-bar {
  display: flex;
  gap: 10px;
  margin-bottom: 16px;
  flex-wrap: wrap;
  align-items: center;
}
.cp-search-input {
  flex: 1;
  min-width: 200px;
  padding: 10px 14px;
  border: 1px solid var(--color-line-default);
  border-radius: 8px;
  font-size: 14px;
  background: var(--color-surface);
  color: var(--color-heading);
}
.cp-search-input:focus {
  outline: none;
  border-color: var(--color-primary);
}
.cp-sort-select {
  padding: 10px 14px;
  border: 1px solid var(--color-line-default);
  border-radius: 8px;
  font-size: 13px;
  background: var(--color-surface);
  color: var(--color-heading);
  cursor: pointer;
}
.cp-sort-toggle {
  padding: 10px 14px;
  border: 1px solid var(--color-line-default);
  border-radius: 8px;
  font-size: 13px;
  background: var(--color-surface);
  color: var(--color-body);
  cursor: pointer;
  white-space: nowrap;
}
.cp-sort-toggle:hover {
  background: var(--color-hover);
}
.cp-table-row-clickable {
  cursor: pointer;
}
.cp-table-row-clickable:hover {
  background: var(--color-hover);
}
.cp-pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 16px;
  margin-top: 20px;
}
.cp-page-btn {
  padding: 8px 16px;
  border: 1px solid var(--color-line-default);
  border-radius: 8px;
  font-size: 13px;
  font-weight: 500;
  background: var(--color-surface);
  color: var(--color-body);
  cursor: pointer;
}
.cp-page-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.cp-page-btn:not(:disabled):hover {
  background: var(--color-hover);
}
.cp-page-info {
  font-size: 13px;
  color: var(--color-muted);
}
</style>
