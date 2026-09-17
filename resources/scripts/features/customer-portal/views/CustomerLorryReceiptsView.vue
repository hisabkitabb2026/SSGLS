<template>
  <div>
    <div class="cp-topbar">
      <div class="cp-topbar-left">
        <h1>Lorry Receipts</h1>
        <p>Truck payment records where you are the owner, driver, or broker</p>
      </div>
    </div>

    <div class="cp-table-card" style="margin-bottom: 28px;">
      <div class="cp-table-card-header">
        <div class="cp-table-card-title">All Lorry Receipts</div>
      </div>
      <div style="overflow-x: auto;">
        <table class="cp-table">
          <thead>
            <tr>
              <th>Receipt No</th>
              <th>Truck No</th>
              <th>Owner</th>
              <th>Driver</th>
              <th>Broker</th>
              <th>Route</th>
              <th>Date</th>
              <th>Amount</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!receipts.length">
              <td colspan="9" style="text-align: center; color: var(--color-muted);">No lorry receipts found</td>
            </tr>
            <tr v-for="r in receipts" :key="r.id" style="cursor: pointer;" @click="viewReceipt(r.id)">
              <td><span class="cp-table-link">{{ r.invoice_number }}</span></td>
              <td>{{ r.truck_number || '—' }}</td>
              <td>{{ r.owner_name || '—' }}</td>
              <td>{{ r.driver_name || '—' }}</td>
              <td>{{ r.broker_name || '—' }}</td>
              <td>{{ r.from_name || '—' }} → {{ r.to_name || '—' }}</td>
              <td>{{ formatDate(r.invoice_date) }}</td>
              <td>{{ formatCurrency(r.total) }}</td>
              <td><span :class="['cp-badge', statusBadgeClass(r.status)]">{{ statusLabel(r.status) }}</span></td>
            </tr>
          </tbody>
        </table>
      </div>
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

const receipts = ref<any[]>([])

async function fetchReceipts() {
  try {
    const companySlug = route.params.company as string
    const { data } = await client.get(`/api/v1/${companySlug}/customer/lorry-receipts`, { params: { limit: 20 } })
    receipts.value = data.data || []
  } catch (e) {
    console.error('Failed to fetch lorry receipts:', e)
  }
}

function viewReceipt(id: number) {
  router.push(buildCustomerPortalPath(route.params.company as string, `lorry-receipts/${id}/view`))
}

function formatDate(date: string) {
  if (!date) return '—'
  return new Date(date).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' })
}

function formatCurrency(amount: number) {
  return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 0 }).format(amount || 0)
}

function statusLabel(status: string): string {
  const map: Record<string, string> = { sent: 'Sent', viewed: 'Viewed', completed: 'Completed', overdue: 'Overdue', draft: 'Draft' }
  return map[status] || status?.replace(/_/g, ' ') || '—'
}

function statusBadgeClass(status: string): string {
  const map: Record<string, string> = { completed: 'cp-badge-success', sent: 'cp-badge-info', viewed: 'cp-badge-info', overdue: 'cp-badge-danger', draft: 'cp-badge-muted' }
  return map[status] || 'cp-badge-muted'
}

onMounted(() => fetchReceipts())
</script>
