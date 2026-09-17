<template>
  <div>
    <!-- Top Bar -->
    <div class="cp-topbar">
      <div class="cp-topbar-left">
        <h1>Trip — {{ trip?.trip_number || '...' }}</h1>
        <p>{{ trip?.origin_city || '—' }} → {{ trip?.destination_city || '—' }}</p>
      </div>
      <div class="cp-topbar-right">
        <span :class="['cp-badge', statusBadgeClass(trip?.status)]" style="font-size: 13px; padding: 6px 14px;">
          {{ statusLabel(trip?.status) }}
        </span>
      </div>
    </div>

    <div v-if="trip" style="margin-bottom: 28px;">
      <!-- Detail Cards -->
      <div class="cp-detail-grid">
        <div class="cp-detail-card">
          <div class="cp-detail-card-title">Trip Details</div>
          <div class="cp-detail-row"><span class="cp-detail-row-label">Trip No</span><span class="cp-detail-row-value">{{ trip.trip_number }}</span></div>
          <div class="cp-detail-row"><span class="cp-detail-row-label">Status</span><span class="cp-detail-row-value">{{ statusLabel(trip.status) }}</span></div>
          <div class="cp-detail-row"><span class="cp-detail-row-label">Origin</span><span class="cp-detail-row-value">{{ trip.origin_city || '—' }}</span></div>
          <div class="cp-detail-row"><span class="cp-detail-row-label">Destination</span><span class="cp-detail-row-value">{{ trip.destination_city || '—' }}</span></div>
        </div>
        <div class="cp-detail-card">
          <div class="cp-detail-card-title">Vehicle & Driver</div>
          <div class="cp-detail-row"><span class="cp-detail-row-label">Truck</span><span class="cp-detail-row-value">{{ trip.truck_number || '—' }}</span></div>
          <div class="cp-detail-row"><span class="cp-detail-row-label">Driver</span><span class="cp-detail-row-value">{{ trip.driver_name || '—' }}</span></div>
          <div class="cp-detail-row"><span class="cp-detail-row-label">Driver Phone</span><span class="cp-detail-row-value">{{ trip.driver_phone || '—' }}</span></div>
          <div class="cp-detail-row"><span class="cp-detail-row-label">Broker</span><span class="cp-detail-row-value">{{ trip.broker_name || '—' }}</span></div>
        </div>
      </div>

      <div class="cp-detail-grid">
        <div class="cp-detail-card">
          <div class="cp-detail-card-title">Schedule</div>
          <div class="cp-detail-row"><span class="cp-detail-row-label">Dispatch Date</span><span class="cp-detail-row-value">{{ formatDate(trip.dispatch_date) }}</span></div>
          <div class="cp-detail-row"><span class="cp-detail-row-label">Expected Delivery</span><span class="cp-detail-row-value">{{ formatDate(trip.expected_delivery_date) }}</span></div>
          <div v-if="trip.actual_delivery_date" class="cp-detail-row">
            <span class="cp-detail-row-label">Delivered On</span>
            <span class="cp-detail-row-value cp-amount-positive">{{ formatDate(trip.actual_delivery_date) }}</span>
          </div>
        </div>
        <div v-if="trip.notes" class="cp-detail-card">
          <div class="cp-detail-card-title">Notes</div>
          <div class="cp-detail-row"><span class="cp-detail-row-value">{{ trip.notes }}</span></div>
        </div>
      </div>

      <!-- Status Timeline -->
      <div class="cp-timeline-card" style="margin-bottom: 28px;">
        <div style="font-size: 16px; font-weight: 700; color: var(--color-heading); margin-bottom: 20px;">Trip Timeline</div>
        <div class="cp-timeline">
          <div class="cp-timeline-item">
            <div :class="['cp-timeline-dot', 'done']">✓</div>
            <div class="cp-timeline-title">Trip Planned</div>
            <div class="cp-timeline-date">Trip created and truck assigned</div>
          </div>
          <div class="cp-timeline-item">
            <div :class="['cp-timeline-dot', trip.status === 'dispatched' || trip.status === 'delivered' ? 'done' : trip.status === 'planned' ? 'current' : 'pending']">
              <span v-if="trip.status === 'dispatched' || trip.status === 'delivered'">✓</span>
              <span v-else-if="trip.status === 'planned'">●</span>
            </div>
            <div class="cp-timeline-title">Dispatched</div>
            <div class="cp-timeline-date">{{ trip.dispatch_date ? formatDate(trip.dispatch_date) : 'Pending' }}</div>
            <div v-if="trip.dispatch_date" class="cp-timeline-detail">Truck departed from {{ trip.origin_city || 'origin' }}</div>
          </div>
          <div class="cp-timeline-item">
            <div :class="['cp-timeline-dot', trip.status === 'delivered' ? 'done' : trip.status === 'dispatched' ? 'current' : 'pending']">
              <span v-if="trip.status === 'delivered'">✓</span>
              <span v-else-if="trip.status === 'dispatched'">●</span>
            </div>
            <div class="cp-timeline-title">In Transit</div>
            <div class="cp-timeline-date">{{ trip.status === 'dispatched' ? 'Currently on the road' : trip.status === 'delivered' ? 'Completed' : 'Pending' }}</div>
            <div v-if="trip.status === 'dispatched' && trip.expected_delivery_date" class="cp-timeline-detail">Expected delivery: {{ formatDate(trip.expected_delivery_date) }}</div>
          </div>
          <div class="cp-timeline-item">
            <div :class="['cp-timeline-dot', trip.status === 'delivered' ? 'done' : 'pending']">
              <span v-if="trip.status === 'delivered'">✓</span>
            </div>
            <div class="cp-timeline-title">Delivered</div>
            <div class="cp-timeline-date">{{ trip.actual_delivery_date ? formatDate(trip.actual_delivery_date) : 'Pending' }}</div>
            <div v-if="trip.actual_delivery_date" class="cp-timeline-detail">Reached {{ trip.destination_city || 'destination' }}</div>
          </div>
        </div>
      </div>

      <!-- Linked Warehouse Items / LR Receipts -->
      <div v-if="trip.warehouse_items?.length" class="cp-table-card">
        <div class="cp-table-card-header">
          <div class="cp-table-card-title">Shipments on this Trip</div>
        </div>
        <div style="overflow-x: auto;">
          <table class="cp-table">
            <thead>
              <tr>
                <th>LR Number</th>
                <th>Destination</th>
                <th>Weight</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in trip.warehouse_items" :key="item.id">
                <td>{{ item.lr?.invoice_number || '—' }}</td>
                <td>{{ item.destination_city || '—' }}</td>
                <td>{{ item.weight_kg ? `${item.weight_kg} kg` : '—' }}</td>
                <td><span :class="['cp-badge', warehouseBadgeClass(item.status)]">{{ warehouseStatusLabel(item.status) }}</span></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div v-if="loading" style="text-align: center; padding: 60px; color: var(--color-muted);">Loading trip details...</div>
    <div v-if="error" style="text-align: center; padding: 60px; color: var(--color-status-red);">{{ error }}</div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { client } from '@/scripts/api/client'

const route = useRoute()

const trip = ref<any>(null)
const loading = ref(false)
const error = ref('')

async function fetchTrip() {
  loading.value = true
  error.value = ''
  trip.value = null

  try {
    const companySlug = route.params.company as string
    const id = route.params.id
    const { data } = await client.get(`/api/v1/${companySlug}/customer/load-trips/${id}`)
    trip.value = data.data || data
  } catch (e: any) {
    error.value = e?.response?.data?.message || 'Failed to load trip details.'
    console.error('Failed to fetch trip:', e)
  } finally {
    loading.value = false
  }
}

watch(() => route.params.id, () => fetchTrip())

onMounted(() => fetchTrip())

function formatDate(date: string) {
  if (!date) return '—'
  return new Date(date).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' })
}

function statusLabel(status?: string): string {
  const map: Record<string, string> = { planned: 'Planned', dispatched: 'In Transit', delivered: 'Delivered', cancelled: 'Cancelled' }
  return map[status || ''] || status || '—'
}

function statusBadgeClass(status?: string): string {
  const map: Record<string, string> = { planned: 'cp-badge-muted', dispatched: 'cp-badge-info', delivered: 'cp-badge-success', cancelled: 'cp-badge-danger' }
  return map[status || ''] || 'cp-badge-muted'
}

function warehouseStatusLabel(status: string): string {
  const map: Record<string, string> = {
    stored: 'Stored',
    picked_for_consolidation: 'Picked',
    loaded_on_vehicle: 'Loaded',
    in_transit: 'In Transit',
    delivered: 'Delivered',
  }
  return map[status] || status || '—'
}

function warehouseBadgeClass(status: string): string {
  const map: Record<string, string> = {
    stored: 'cp-badge-warning',
    picked_for_consolidation: 'cp-badge-purple',
    loaded_on_vehicle: 'cp-badge-info',
    in_transit: 'cp-badge-info',
    delivered: 'cp-badge-success',
  }
  return map[status] || 'cp-badge-muted'
}
</script>
