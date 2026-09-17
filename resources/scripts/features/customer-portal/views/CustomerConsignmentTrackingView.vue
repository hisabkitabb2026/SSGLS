<template>
  <div>
    <!-- Top Bar -->
    <div class="cp-topbar">
      <div class="cp-topbar-left">
        <h1>Track Consignment</h1>
        <p>Enter your docket number to track your shipment in real-time</p>
      </div>
    </div>

    <!-- Search -->
    <div class="cp-tracking-card">
      <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <input
          v-model="docketNumber"
          type="text"
          class="cp-tracking-input"
          placeholder="Enter Docket Number (e.g. LR-2026-0148)"
          @keyup.enter="trackConsignment"
        />
        <button class="cp-btn cp-btn-primary" :disabled="loading || !docketNumber.trim()" @click="trackConsignment">
          {{ loading ? '⏳ Tracking...' : '🔍 Track Now' }}
        </button>
      </div>
      <p v-if="error" style="color: var(--color-status-red); font-size: 13px; margin-top: 12px;">{{ error }}</p>
    </div>

    <!-- Results -->
    <div v-if="trackingData" class="cp-timeline-card" style="margin-bottom: 28px;">
      <!-- Header -->
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
        <div>
          <div style="font-size: 16px; font-weight: 700; color: var(--color-heading);">{{ trackingData.docket_number }}</div>
          <div style="font-size: 13px; color: var(--color-muted);">{{ trackingData.from_name || '—' }} → {{ trackingData.to_name || '—' }}</div>
        </div>
        <span :class="['cp-badge', statusBadgeClass(trackingData.status)]" style="font-size: 13px; padding: 6px 14px;">
          {{ statusIcon(trackingData.status) }} {{ statusLabel(trackingData.status) }}
        </span>
      </div>

      <!-- Timeline -->
      <div class="cp-timeline">
        <div v-for="(event, index) in trackingData.timeline" :key="index" class="cp-timeline-item">
          <div :class="['cp-timeline-dot', event.status]">
            <span v-if="event.status === 'done'">✓</span>
            <span v-else-if="event.status === 'current'">●</span>
          </div>
          <div class="cp-timeline-title">{{ event.event }}</div>
          <div v-if="event.date" class="cp-timeline-date">{{ event.date }}</div>
          <div v-if="event.detail" class="cp-timeline-detail">{{ event.detail }}</div>
        </div>
      </div>

      <!-- Details Grid -->
      <div class="cp-detail-grid" style="margin-top: 20px; margin-bottom: 0;">
        <!-- Warehouse Info -->
        <div v-if="trackingData.warehouse_item" class="cp-detail-card">
          <div class="cp-detail-card-title">Warehouse Details</div>
          <div class="cp-detail-row"><span class="cp-detail-row-label">Location</span><span class="cp-detail-row-value">{{ trackingData.warehouse_item.warehouse_location || '—' }}</span></div>
          <div class="cp-detail-row"><span class="cp-detail-row-label">Destination</span><span class="cp-detail-row-value">{{ trackingData.warehouse_item.destination_city || '—' }}</span></div>
          <div class="cp-detail-row"><span class="cp-detail-row-label">Weight</span><span class="cp-detail-row-value">{{ trackingData.warehouse_item.weight_kg }} kg</span></div>
          <div class="cp-detail-row"><span class="cp-detail-row-label">Days in Warehouse</span><span class="cp-detail-row-value">{{ trackingData.warehouse_item.days_in_warehouse }}</span></div>
        </div>

        <!-- Trip Info -->
        <div v-if="trackingData.load_trip" class="cp-detail-card">
          <div class="cp-detail-card-title">Trip Details</div>
          <div class="cp-detail-row"><span class="cp-detail-row-label">Truck</span><span class="cp-detail-row-value">{{ trackingData.load_trip.truck_number || '—' }}</span></div>
          <div class="cp-detail-row"><span class="cp-detail-row-label">Driver</span><span class="cp-detail-row-value">{{ trackingData.load_trip.driver_name || '—' }}</span></div>
          <div class="cp-detail-row"><span class="cp-detail-row-label">Dispatch Date</span><span class="cp-detail-row-value">{{ trackingData.load_trip.dispatch_date || '—' }}</span></div>
          <div class="cp-detail-row"><span class="cp-detail-row-label">Expected Delivery</span><span class="cp-detail-row-value">{{ trackingData.load_trip.expected_delivery_date || '—' }}</span></div>
          <div v-if="trackingData.load_trip.actual_delivery_date" class="cp-detail-row">
            <span class="cp-detail-row-label">Delivered On</span>
            <span class="cp-detail-row-value cp-amount-positive">{{ trackingData.load_trip.actual_delivery_date }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { client } from '@/scripts/api/client'
import { resolveCompanySlug } from '../utils/routes'

const route = useRoute()


const docketNumber = ref('')

// Pre-fill from query param (e.g. when navigated from dashboard)
onMounted(() => {
  const queryDocket = route.query.docket as string
  if (queryDocket) {
    docketNumber.value = queryDocket
    trackConsignment()
  }
})
const trackingData = ref<any>(null)
const loading = ref(false)
const error = ref('')

async function trackConsignment() {
  if (!docketNumber.value.trim()) return
  loading.value = true
  error.value = ''
  trackingData.value = null

  try {
    const companySlug = resolveCompanySlug(route.params.company)
    const { data } = await client.get(
      `/api/v1/${companySlug}/customer/track/${docketNumber.value.trim()}`,
    )

    trackingData.value = data
  } catch (e: any) {
    error.value = e?.response?.data?.message || 'Failed to track consignment. Please try again.'
    console.error('Tracking error:', e)
  } finally {
    loading.value = false
  }
}

function statusLabel(status: string): string {
  const map: Record<string, string> = {
    created: 'Created',
    at_warehouse: 'At Warehouse',
    picked: 'Picked for Consolidation',
    loaded: 'Loaded on Truck',
    in_transit: 'In Transit',
    delivered: 'Delivered',
    unknown: 'Unknown',
  }
  return map[status] || status || '—'
}

function statusIcon(status: string): string {
  const map: Record<string, string> = {
    created: '📝',
    at_warehouse: '📦',
    picked: '📋',
    loaded: '🚚',
    in_transit: '🛣️',
    delivered: '✅',
    unknown: '❓',
  }
  return map[status] || '📄'
}

function statusBadgeClass(status: string): string {
  const map: Record<string, string> = {
    created: 'cp-badge-info',
    at_warehouse: 'cp-badge-info',
    picked: 'cp-badge-purple',
    loaded: 'cp-badge-warning',
    in_transit: 'cp-badge-warning',
    delivered: 'cp-badge-success',
    unknown: 'cp-badge-muted',
  }
  return map[status] || 'cp-badge-muted'
}
</script>
