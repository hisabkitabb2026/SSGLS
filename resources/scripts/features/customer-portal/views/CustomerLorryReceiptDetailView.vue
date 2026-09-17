<template>
  <div>
    <div class="cp-topbar">
      <div class="cp-topbar-left">
        <h1>Lorry Receipt — {{ receipt?.invoice_number || '...' }}</h1>
        <p>Truck: {{ receipt?.truck_number || '—' }} · {{ receipt?.from_name || '—' }} → {{ receipt?.to_name || '—' }}</p>
      </div>
      <div class="cp-topbar-right">
        <button class="cp-btn cp-btn-outline">📄 Download PDF</button>
      </div>
    </div>

    <div v-if="loading" style="text-align: center; padding: 40px; color: var(--color-muted);">Loading...</div>

    <div v-else-if="receipt">
      <!-- Section A & B -->
      <div class="cp-detail-grid">
        <div class="cp-detail-card">
          <div class="cp-detail-card-title">Section A — Vehicle & Party</div>
          <div class="cp-detail-row"><span class="cp-detail-row-label">Lorry No</span><span class="cp-detail-row-value">{{ receipt.truck_number || '—' }}</span></div>
          <div class="cp-detail-row"><span class="cp-detail-row-label">Contract No</span><span class="cp-detail-row-value">{{ receipt.contract_no || '—' }}</span></div>
          <div class="cp-detail-row"><span class="cp-detail-row-label">Owner</span><span class="cp-detail-row-value">{{ receipt.owner_name || '—' }}</span></div>
          <div class="cp-detail-row"><span class="cp-detail-row-label">Driver</span><span class="cp-detail-row-value">{{ receipt.driver_name || '—' }}</span></div>
          <div class="cp-detail-row"><span class="cp-detail-row-label">Broker</span><span class="cp-detail-row-value">{{ receipt.broker_name || '—' }}</span></div>
          <div class="cp-detail-row"><span class="cp-detail-row-label">From → To</span><span class="cp-detail-row-value">{{ receipt.from_name || '—' }} → {{ receipt.to_name || '—' }}</span></div>
        </div>
        <div class="cp-detail-card">
          <div class="cp-detail-card-title">Section B — Consignment</div>
          <div class="cp-detail-row"><span class="cp-detail-row-label">Received Bilties</span><span class="cp-detail-row-value">{{ receipt.bilties || '—' }}</span></div>
          <div class="cp-detail-row"><span class="cp-detail-row-label">Total Weight</span><span class="cp-detail-row-value">{{ receipt.total_weight ? `${receipt.total_weight} kg` : '—' }}</span></div>
          <div class="cp-detail-row"><span class="cp-detail-row-label">No. of Packages</span><span class="cp-detail-row-value">{{ receipt.no_of_packages || '—' }}</span></div>
          <div class="cp-detail-row"><span class="cp-detail-row-label">Dispatch Date</span><span class="cp-detail-row-value">{{ formatDate(receipt.dispatch_date) }}</span></div>
        </div>
      </div>

      <!-- Section C & E -->
      <div class="cp-detail-grid">
        <div class="cp-detail-card">
          <div class="cp-detail-card-title">Section C — Advance Payment</div>
          <div class="cp-detail-row"><span class="cp-detail-row-label">Advance Amount</span><span class="cp-detail-row-value cp-amount-negative">{{ formatCurrency(receipt.advance_amount) }}</span></div>
          <div class="cp-detail-row"><span class="cp-detail-row-label">Paid To</span><span class="cp-detail-row-value">{{ receipt.advance_paid_to || '—' }}</span></div>
          <div class="cp-detail-row"><span class="cp-detail-row-label">Payment Mode</span><span class="cp-detail-row-value">{{ receipt.advance_payment_mode || '—' }}</span></div>
          <div class="cp-detail-row"><span class="cp-detail-row-label">Date</span><span class="cp-detail-row-value">{{ formatDate(receipt.advance_date) }}</span></div>
        </div>
        <div class="cp-detail-card">
          <div class="cp-detail-card-title">Section E — Final Payment</div>
          <div class="cp-detail-row"><span class="cp-detail-row-label">Net Amount Payable</span><span class="cp-detail-row-value cp-amount-negative">{{ formatCurrency(receipt.net_amount) }}</span></div>
          <div class="cp-detail-row"><span class="cp-detail-row-label">Detention Amount</span><span class="cp-detail-row-value">{{ formatCurrency(receipt.detention_amount) }}</span></div>
          <div class="cp-detail-row"><span class="cp-detail-row-label">Extra Hire</span><span class="cp-detail-row-value">{{ formatCurrency(receipt.extra_hire) }}</span></div>
          <div class="cp-detail-row"><span class="cp-detail-row-label">Less Deductions</span><span class="cp-detail-row-value cp-amount-positive">{{ formatCurrency(receipt.deductions) }}</span></div>
          <div class="cp-detail-row"><span class="cp-detail-row-label">Status</span><span class="cp-detail-row-value"><span :class="['cp-badge', statusBadgeClass(receipt.status)]">{{ statusLabel(receipt.status) }}</span></span></div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { client } from '@/scripts/api/client'

const route = useRoute()

const receipt = ref<any>(null)
const loading = ref(true)

async function fetchReceipt() {
  loading.value = true
  try {
    const companySlug = route.params.company as string
    const { data } = await client.get(`/api/v1/${companySlug}/customer/lorry-receipts/${route.params.id}`)
    receipt.value = data.data || data
  } catch (e) {
    console.error('Failed to fetch lorry receipt:', e)
  } finally {
    loading.value = false
  }
}

function formatDate(date?: string) {
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

onMounted(() => fetchReceipt())
</script>
