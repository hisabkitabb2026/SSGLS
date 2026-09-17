<script setup lang="ts">
/**
 * DashboardView — Logistics Control Tower Dashboard
 *
 * Redesigned layout (operations-first, finance-second):
 *   1. Dashboard Header (greeting + date + refresh + quick actions)
 *   2. Unified KPI Strip (operations + billing)
 *   3. Alert Banner (conditional — overdue items, emitted from DashboardOperations)
 *   4. Fleet Status + Warehouse & Dispatch panels (side by side)
 *   5. Recent Active Trips table
 *   6. Financial Overview chart
 *   7. Recent Due Invoices + Recent Estimates tables
 */
import { onMounted, ref, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useUserStore } from '../../../../stores/user.store'
import { useDashboardStore } from '../store'
import DashboardStats from '../components/DashboardStats.vue'
import DashboardOperations from '../components/DashboardOperations.vue'
import DashboardChart from '../components/DashboardChart.vue'
import DashboardTable from '../components/DashboardTable.vue'
import DashboardQuickActions from '../components/DashboardQuickActions.vue'
import SendInvoiceModal from '@/scripts/features/company/invoices/components/SendInvoiceModal.vue'
import SendEstimateModal from '@/scripts/features/company/estimates/components/SendEstimateModal.vue'
// Note: client import removed — warehouse API call is now handled by DashboardOperations

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const userStore = useUserStore()
const dashboardStore = useDashboardStore()


// Overdue count is now emitted from DashboardOperations (single API call)
const overdueItemsCount = ref(0)

// Refresh key — incremented to force child components to reload
const refreshKey = ref(0)

// ── Greeting based on time of day ────────────────────────────────
const greeting = computed(() => {
  const hour = new Date().getHours()
  if (hour < 12) return t('dashboard.header.good_morning')
  if (hour < 17) return t('dashboard.header.good_afternoon')
  return t('dashboard.header.good_evening')
})


const userName = computed(() => {
  return userStore.currentUser?.name?.split(' ')[0] || ''
})

const todayDate = computed(() => {
  return new Date().toLocaleDateString('en-IN', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  })
})

function handleOverdueUpdate(count: number) {
  overdueItemsCount.value = count
}

function refreshDashboard() {
  refreshKey.value++
  // Reload dashboard store data
  dashboardStore.loadData()
}

onMounted(() => {
  const meta = route.meta as { ability?: string; isOwner?: boolean }

  if (meta.ability && !userStore.hasAbilities(meta.ability)) {
    router.push({ name: 'settings.account' })
  } else if (meta.isOwner && !userStore.isOwner) {
    router.push({ name: 'settings.account' })
  }
})
</script>

<template>
  <BasePage>
    <!-- ── SECTION 1: Dashboard Header ─────────────────────────── -->
    <div class="mb-6">
      <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <!-- Left: greeting + date -->
        <div>
          <h1 class="text-2xl font-bold text-heading">
            {{ greeting }}<span v-if="userName">, {{ userName }}</span> 👋
          </h1>
          <p class="mt-1 text-sm text-muted">{{ todayDate }}</p>
        </div>

        <!-- Right: refresh button -->
        <div class="flex items-center gap-3">
          <BaseButton
            size="sm"
            variant="primary-outline"
            :disabled="!dashboardStore.isDashboardDataLoaded"
            @click="refreshDashboard"
          >
            <BaseIcon name="ArrowPathIcon" class="w-4 h-4 mr-1.5" />
            {{ $t('dashboard.header.refresh') }}
          </BaseButton>

        </div>
      </div>

      <!-- Quick Actions strip -->
      <div class="mt-4">
        <DashboardQuickActions />
      </div>
    </div>

    <!-- ── SECTION 2: Unified KPI Strip ─────────────────────────── -->
    <DashboardStats :key="`stats-${refreshKey}`" />

    <!-- ── SECTION 3: Alert Banner (conditional) ────────────────── -->
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0 -translate-y-2"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="overdueItemsCount > 0"
        class="mt-6 flex items-center justify-between rounded-xl border border-status-red/30 bg-status-red/5 px-5 py-4"
      >
        <div class="flex items-center gap-3">
          <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-status-red/10">
            <BaseIcon name="ExclamationTriangleIcon" class="h-5 w-5 text-status-red" />
          </div>
          <div>
            <p class="text-sm font-semibold text-status-red">
              {{ $t('dashboard.alert_banner.overdue_items', { count: overdueItemsCount, s: overdueItemsCount !== 1 ? 's' : '' }) }}
            </p>
            <p class="text-xs text-muted mt-0.5">
              {{ $t('dashboard.alert_banner.overdue_desc') }}
            </p>
          </div>
        </div>
        <BaseButton
          size="sm"
          variant="danger"
          @click="$router.push({ name: 'warehouse-items.index' })"
        >
          {{ $t('dashboard.alert_banner.view_details') }}
        </BaseButton>

      </div>
    </Transition>

    <!-- ── SECTION 4: Fleet + Warehouse panels + Recent Trips ──── -->
    <DashboardOperations
      :key="`ops-${refreshKey}`"
      @overdue-updated="handleOverdueUpdate"
    />

    <!-- ── SECTION 5: Financial Overview ────────────────────────── -->
    <DashboardChart :key="`chart-${refreshKey}`" />

    <!-- ── SECTION 6: Recent Due Invoices + Estimates ──────────── -->
    <DashboardTable :key="`table-${refreshKey}`" />
  </BasePage>

  <SendInvoiceModal />
  <SendEstimateModal />
</template>
