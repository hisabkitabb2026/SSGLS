<template>
  <div>
    <!-- Page Header -->
    <div class="cp-topbar">
      <div class="cp-topbar-left">
        <h1>Tracking — {{ lrInfo?.invoice_number || '...' }}</h1>
        <p v-if="lrInfo">{{ lrInfo.customer?.name || '—' }} → {{ lrInfo.consignee_customer?.name || '—' }}</p>
      </div>
      <div class="cp-topbar-right">
        <button class="cp-btn cp-btn-outline" @click="goBack">← Back to LR Receipts</button>
      </div>
    </div>

    <div v-if="loading" style="text-align: center; padding: 40px; color: var(--color-muted);">Loading...</div>

    <div v-else-if="item">
      <!-- Detail Grid: Shipment & Storage -->
      <div class="cp-detail-grid">
        <div class="cp-detail-card">
          <div class="cp-detail-card-title">Shipment Details</div>
          <div class="cp-detail-row"><span class="cp-detail-row-label">LR Number</span><span class="cp-detail-row-value">{{ item.lr_number || '—' }}</span></div>
          <div class="cp-detail-row"><span class="cp-detail-row-label">Destination</span><span class="cp-detail-row-value">{{ item.destination_city || '—' }}</span></div>
          <div class="cp-detail-row"><span class="cp-detail-row-label">Weight</span><span class="cp-detail-row-value">{{ item.weight_kg ? `${item.weight_kg} kg` : '—' }}</span></div>
          <div class="cp-detail-row"><span class="cp-detail-row-label">Load Type</span><span class="cp-detail-row-value">{{ item.load_type || '—' }}</span></div>
          <div class="cp-detail-row"><span class="cp-detail-row-label">No. of Packages</span><span class="cp-detail-row-value">{{ item.no_of_packages || '—' }}</span></div>
        </div>
        <div class="cp-detail-card">
          <div class="cp-detail-card-title">Storage Details</div>
          <div class="cp-detail-row"><span class="cp-detail-row-label">Warehouse</span><span class="cp-detail-row-value">{{ item.warehouse_location || '—' }}</span></div>
          <div class="cp-detail-row"><span class="cp-detail-row-label">Received Date</span><span class="cp-detail-row-value">{{ formatDate(item.received_date) }}</span></div>
          <div class="cp-detail-row"><span class="cp-detail-row-label">Days in Warehouse</span><span class="cp-detail-row-value" :style="item.is_overdue ? 'color: var(--color-status-red); font-weight: 600;' : ''">{{ item.days_in_warehouse ?? 0 }}</span></div>
          <div class="cp-detail-row"><span class="cp-detail-row-label">Overdue</span><span class="cp-detail-row-value">{{ item.is_overdue ? 'Yes' : 'No' }}</span></div>
          <div class="cp-detail-row"><span class="cp-detail-row-label">Status</span><span class="cp-detail-row-value"><span :class="['cp-badge', warehouseBadgeClass(item.status)]">{{ warehouseStatusLabel(item.status) }}</span></span></div>
        </div>
      </div>

      <!-- Status Timeline -->
      <div class="cp-timeline-card" style="margin-bottom: 28px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
          <div>
            <div style="font-size: 16px; font-weight: 700; color: var(--color-heading);">Status Timeline</div>
            <div style="font-size: 13px; color: var(--color-muted);">Track the progress of your goods</div>
          </div>
        </div>

        <div class="cp-timeline">
          <div class="cp-timeline-item">
            <div class="cp-timeline-dot" :class="isStatusReached('stored') ? 'done' : 'pending'">✓</div>
            <div class="cp-timeline-title">Goods Received at Warehouse</div>
            <div class="cp-timeline-date">{{ formatDate(item.received_date) }}</div>
            <div class="cp-timeline-detail">Warehouse: {{ item.warehouse_location || '—' }} · Weight: {{ item.weight_kg ? `${item.weight_kg} kg` : '—' }}</div>
          </div>
          <div class="cp-timeline-item">
            <div class="cp-timeline-dot" :class="getTimelineDotClass('picked_for_consolidation')">
              <span v-if="isStatusReached('picked_for_consolidation')">✓</span>
              <span v-else>●</span>
            </div>
            <div class="cp-timeline-title">Picked for Consolidation</div>
            <div class="cp-timeline-date">{{ isStatusReached('picked_for_consolidation') ? 'Completed' : 'Pending' }}</div>
            <div class="cp-timeline-detail">Goods picked from storage for consolidation</div>
          </div>
          <div class="cp-timeline-item">
            <div class="cp-timeline-dot" :class="getTimelineDotClass('loaded_on_vehicle')">
              <span v-if="isStatusReached('loaded_on_vehicle')">✓</span>
              <span v-else>●</span>
            </div>
            <div class="cp-timeline-title">Loaded on Vehicle</div>
            <div class="cp-timeline-date">{{ isStatusReached('loaded_on_vehicle') ? 'Completed' : 'Pending' }}</div>
            <div class="cp-timeline-detail">Goods loaded onto truck for dispatch</div>
          </div>
          <div class="cp-timeline-item">
            <div class="cp-timeline-dot" :class="getTimelineDotClass('in_transit')">
              <span v-if="isStatusReached('in_transit')">✓</span>
              <span v-else>●</span>
            </div>
            <div class="cp-timeline-title">In Transit</div>
            <div class="cp-timeline-date">{{ isStatusReached('in_transit') ? 'Completed' : 'Pending' }}</div>
            <div class="cp-timeline-detail">Dispatched from warehouse · Destination: {{ item.destination_city || '—' }}</div>
          </div>
          <div class="cp-timeline-item">
            <div class="cp-timeline-dot" :class="getTimelineDotClass('delivered')">
              <span v-if="isStatusReached('delivered')">✓</span>
              <span v-else>●</span>
            </div>
            <div class="cp-timeline-title">Delivered</div>
            <div class="cp-timeline-date">{{ isStatusReached('delivered') ? 'Completed' : 'Pending' }}</div>
            <div class="cp-timeline-detail">Goods delivered to destination</div>
          </div>
        </div>
      </div>
    </div>

    <div v-else style="text-align: center; padding: 40px; color: var(--color-muted);">
      No tracking data available for this LR Receipt.
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { client } from '@/scripts/api/client'
import { buildCustomerPortalPath } from '../utils/routes'

const route = useRoute()
const router = useRouter()

const item = ref<any>(null)
const lrInfo = ref<any>(null)
const loading = ref(true)

// Status order for timeline progression
const statusOrder = [
  'stored',
  'picked_for_consolidation',
  'loaded_on_vehicle',
  'in_transit',
  'delivered',
]

function isStatusReached(status: string): boolean {
  if (!item.value?.status) return false
  const currentIndex = statusOrder.indexOf(item.value.status)
  const checkIndex = statusOrder.indexOf(status)
  return currentIndex >= 0 && checkIndex >= 0 && currentIndex >= checkIndex
}

function getTimelineDotClass(status: string): string {
  if (isStatusReached(status)) {
    return item.value?.status === status ? 'current' : 'done'
  }
  return 'pending'
}

async function fetchTrackingData() {
  loading.value = true
  try {
    const companySlug = route.params.company as string
    const lrId = route.params.id as string

    // Fetch the LR receipt info
    const { data: lrData } = await client.get(`/api/v1/${companySlug}/customer/lr-receipts/${lrId}`)
    lrInfo.value = lrData.data || lrData

    // Fetch warehouse items for this LR
    const { data: wiData } = await client.get(`/api/v1/${companySlug}/customer/warehouse-items`, {
      params: { lr_id: lrId, limit: 100 },
    })

    const items = wiData.data || []
    if (items.length > 0) {
      item.value = items[0]
    }
  } catch (e) {
    console.error('Failed to fetch tracking data:', e)
  } finally {
    loading.value = false
  }
}

function goBack() {
  const companySlug = route.params.company as string
  router.push(buildCustomerPortalPath(companySlug, 'lr-receipts'))
}

function formatDate(date?: string) {
  if (!date) return '—'
  return new Date(date).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' })
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

onMounted(() => fetchTrackingData())
</script>
