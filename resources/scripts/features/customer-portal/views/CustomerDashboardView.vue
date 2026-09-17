<template>
  <BasePage>
    <!-- ── Dashboard Header ──────────────────────────────────────── -->
    <div class="mb-6">
      <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="text-2xl font-bold text-heading">
            {{ greeting }}<span v-if="userName">, {{ userName }}</span> 👋
          </h1>
          <p class="mt-1 text-sm text-muted">{{ todayDate }}</p>
        </div>

        <div class="flex items-center gap-3">
          <BaseButton
            size="sm"
            variant="primary-outline"
            @click="refreshDashboard"
          >
            <BaseIcon name="ArrowPathIcon" class="w-4 h-4 mr-1.5" />
            Refresh
          </BaseButton>
        </div>
      </div>
    </div>

    <!-- ── KPI Cards ─────────────────────────────────────────────── -->
    <div class="grid gap-4 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 mb-6">
      <router-link
        v-for="kpi in kpiCards"
        :key="kpi.label"
        :to="kpi.path"
        class="block rounded-xl border border-line-default bg-surface p-5 transition-shadow hover:shadow-md"
      >
        <div class="flex items-center justify-between mb-3">
          <div
            :class="[
              'flex items-center justify-center w-10 h-10 rounded-lg',
              kpi.iconBg,
            ]"
          >
            <BaseIcon :name="kpi.icon" :class="['w-5 h-5', kpi.iconColor]" />
          </div>
        </div>
        <div class="text-sm text-muted">{{ kpi.label }}</div>
        <div class="mt-1 text-2xl font-bold text-heading">{{ kpi.value }}</div>
        <div class="mt-1 text-xs text-subtle">{{ kpi.sub }}</div>
      </router-link>
    </div>

    <!-- ── Track Consignment Quick Access ─────────────────────────── -->
    <div class="mb-6 rounded-xl border border-line-default bg-surface p-5">
      <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
        <div>
          <div class="text-base font-semibold text-heading">🔍 Track Your Consignment</div>
          <div class="text-sm text-muted mt-0.5">Enter your docket number to track your shipment in real-time</div>
        </div>
      </div>
      <div class="flex gap-2 flex-wrap">
        <input
          v-model="quickTrackDocket"
          type="text"
          class="flex-1 min-w-[200px] rounded-lg border border-line-default bg-surface-secondary px-3 py-2 text-sm text-body placeholder:text-subtle focus:border-primary-500 focus:ring-1 focus:ring-primary-500 outline-none"
          placeholder="Enter Docket Number (e.g. LR-2026-0148)"
          @keyup.enter="quickTrack"
        />
        <BaseButton
          variant="primary"
          :disabled="!quickTrackDocket.trim()"
          @click="quickTrack"
        >
          <BaseIcon name="MagnifyingGlassIcon" class="w-4 h-4 mr-1.5" />
          Track Now
        </BaseButton>
      </div>
    </div>
  </BasePage>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useCustomerPortalStore } from '../store'
import { client } from '@/scripts/api/client'
import { buildCustomerPortalPath } from '../utils/routes'

const store = useCustomerPortalStore()
const router = useRouter()

const dashboardData = ref<any>(null)
const quickTrackDocket = ref('')

// ── Greeting based on time of day ──────────
const greeting = computed(() => {
  const hour = new Date().getHours()
  if (hour < 12) return 'Good Morning'
  if (hour < 17) return 'Good Afternoon'
  return 'Good Evening'
})

const userName = computed(() => {
  return store.currentUser?.name?.split(' ')[0] || ''
})

const todayDate = computed(() => {
  return new Date().toLocaleDateString('en-IN', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  })
})

// ── KPI Cards ──────────────
const kpiCards = computed(() => [
  {
    label: 'Active Consignments',
    value: dashboardData.value?.active_consignments ?? 0,
    sub: 'In progress',
    icon: 'DocumentTextIcon',
    iconBg: 'bg-status-blue/10',
    iconColor: 'text-status-blue',
    path: lrReceiptsPath.value,
  },
  {
    label: 'In Transit',
    value: dashboardData.value?.in_transit ?? 0,
    sub: 'On the move',
    icon: 'MapIcon',
    iconBg: 'bg-status-purple/10',
    iconColor: 'text-status-purple',
    path: lrReceiptsPath.value,
  },
  {
    label: 'Delivered',
    value: dashboardData.value?.delivered ?? 0,
    sub: 'Completed',
    icon: 'CheckCircleIcon',
    iconBg: 'bg-status-green/10',
    iconColor: 'text-status-green',
    path: lrReceiptsPath.value,
  },
  {
    label: 'Amount Due',
    value: formatCurrency(dashboardData.value?.due_amount ?? 0),
    sub: `Across ${dashboardData.value?.invoice_count ?? 0} invoices`,
    icon: 'BanknotesIcon',
    iconBg: 'bg-status-red/10',
    iconColor: 'text-status-red',
    path: invoicesPath.value,
  },
])

function quickTrack() {
  if (!quickTrackDocket.value.trim()) return
  router.push({
    name: 'customer-portal.track',
    params: { company: store.companySlug },
    query: { docket: quickTrackDocket.value.trim() },
  })
}

function refreshDashboard() {
  loadDashboardData()
}

const lrReceiptsPath = computed(() => buildCustomerPortalPath(store.companySlug, 'lr-receipts'))
const invoicesPath = computed(() => buildCustomerPortalPath(store.companySlug, 'invoices'))

function formatCurrency(amount: number) {
  return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 0 }).format(amount || 0)
}

async function loadDashboardData() {
  await store.loadDashboard()

  try {
    const { data } = await client.get(
      `/api/v1/${store.companySlug}/customer/dashboard`,
    )
    dashboardData.value = data
  } catch (e) {
    console.error('Failed to load dashboard data:', e)
  }
}

onMounted(() => loadDashboardData())
</script>
