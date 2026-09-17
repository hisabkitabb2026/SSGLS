<template>
  <div>
    <div class="cp-topbar">
      <div class="cp-topbar-left">
        <h1>Warehouse — Goods Status</h1>
        <p>Track your goods stored in our warehouse</p>
      </div>
    </div>

    <!-- Filter Bar -->
    <div class="cp-filter-bar">
      <select v-model="statusFilter" class="cp-filter-input">
        <option value="">All Status</option>
        <option value="stored">Stored</option>
        <option value="picked_for_consolidation">Picked for Consolidation</option>
        <option value="loaded_on_vehicle">Loaded on Vehicle</option>
        <option value="in_transit">In Transit</option>
        <option value="delivered">Delivered</option>
      </select>
      <input v-model="searchQuery" type="text" class="cp-filter-input" placeholder="Search LR number..." style="min-width: 200px;" />
    </div>

    <div class="cp-table-card" style="margin-bottom: 28px;">
      <div style="overflow-x: auto;">
        <table class="cp-table">
          <thead>
            <tr>
              <th>LR No</th>
              <th>Destination</th>
              <th>Warehouse</th>
              <th>Weight</th>
              <th>Load Type</th>
              <th>Received</th>
              <th>Days</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!filteredItems.length">
              <td colspan="8" style="text-align: center; color: var(--color-muted);">No warehouse items found</td>
            </tr>
            <tr v-for="item in filteredItems" :key="item.id" style="cursor: pointer;" @click="viewItem(item.id)">
              <td><span class="cp-table-link">{{ item.lr_number || '—' }}</span></td>

              <td>{{ item.destination_city || '—' }}</td>
              <td>{{ item.warehouse_location || '—' }}</td>
              <td>{{ item.weight_kg ? `${item.weight_kg} kg` : '—' }}</td>
              <td><span :class="['cp-badge', item.load_type === 'Full Load' ? 'cp-badge-info' : 'cp-badge-muted']">{{ item.load_type || '—' }}</span></td>
              <td>{{ formatDate(item.received_date) }}</td>
              <td :style="item.is_overdue ? 'color: var(--color-status-red); font-weight: 600;' : ''">{{ item.days_in_warehouse ?? 0 }}</td>
              <td><span :class="['cp-badge', warehouseBadgeClass(item.status)]">{{ warehouseStatusLabel(item.status) }}</span></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { client } from '@/scripts/api/client'
import { buildCustomerPortalPath } from '../utils/routes'

const route = useRoute()
const router = useRouter()


const items = ref<any[]>([])
const statusFilter = ref('')
const searchQuery = ref('')

const filteredItems = computed(() => {
  return items.value.filter((item) => {
    if (statusFilter.value && item.status !== statusFilter.value) return false
    if (searchQuery.value && !item.lr_number?.toLowerCase().includes(searchQuery.value.toLowerCase())) return false
    return true
  })
})

function viewItem(id: number) {
  router.push(buildCustomerPortalPath(route.params.company as string, `warehouse-items/${id}/view`))
}

async function fetchItems() {

  try {
    const companySlug = route.params.company as string
    const { data } = await client.get(`/api/v1/${companySlug}/customer/warehouse-items`, { params: { limit: 50 } })
    items.value = data.data || []
  } catch (e) {
    console.error('Failed to fetch warehouse items:', e)
  }
}

function formatDate(date: string) {
  if (!date) return '—'
  return new Date(date).toLocaleDateString('en-IN', { day: '2-digit', month: 'short' })
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

onMounted(() => fetchItems())
</script>
