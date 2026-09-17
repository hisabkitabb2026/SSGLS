<template>
  <BasePage>
    <div v-if="loading" class="mt-6 text-center text-muted">Loading...</div>

    <div v-else-if="!trip">
      <div class="mt-6 text-center text-muted">
        <p class="text-lg">Load trip not found</p>
        <div class="mt-4">
          <BaseButton variant="primary" @click="$router.push({ name: 'load-trips.index' })">
            Back to Trips
          </BaseButton>
        </div>
      </div>
    </div>

    <template v-else>
      <BasePageHeader :title="trip.trip_number">
        <BaseButton
          variant="primary-outline"
          @click="$router.push({ name: 'load-trips.index' })"
        >
          <template #left="slotProps">
            <BaseIcon name="ArrowLeftIcon" :class="slotProps.class" />
          </template>
          Back to Trips
        </BaseButton>

        <div class="mt-2 flex items-center gap-3">
          <span
            class="rounded px-2 py-1 text-xs font-bold"
            :class="getStatusClass(trip.status)"
          >
            {{ trip.status }}
          </span>
          <p class="text-sm text-muted">
            {{ trip.origin_city || '—' }} → {{ trip.destination_city }}
          </p>
        </div>

        <template #actions>
          <div class="flex flex-wrap gap-3">
            <BaseButton variant="white" @click="addExpense">Add Expense</BaseButton>
            <BaseButton
              v-if="trip.status === 'planned'"
              variant="secondary"
              @click="dispatchTrip"
            >
              Dispatch Trip
            </BaseButton>
            <BaseButton
              v-if="trip.status === 'dispatched'"
              variant="primary"
              @click="markDelivered"
            >
              Mark Delivered
            </BaseButton>
            <BaseButton
              v-if="trip.status === 'planned' || trip.status === 'dispatched'"
              variant="danger"
              @click="cancelTrip"
            >
              Cancel Trip
            </BaseButton>
          </div>
        </template>
      </BasePageHeader>

      <!-- A. Visual Trip Progress Stepper -->
      <div class="mt-6">
        <div v-if="trip.status === 'cancelled'" class="rounded-lg border-2 border-status-red/30 bg-status-red/5 p-4 text-center">
          <span class="text-lg font-bold text-status-red">✕ CANCELLED</span>
        </div>
        <div v-else class="flex items-center">
          <!-- Step 1: Planned -->
          <div class="flex flex-1 flex-col items-center">
            <div
              class="flex h-10 w-10 items-center justify-center rounded-full border-2 text-sm font-bold"
              :class="getStepClass(0)"
            >
              1
            </div>
            <p class="mt-2 text-xs font-semibold" :class="getStepLabelClass(0)">Planned</p>
            <p class="text-xs text-muted">{{ trip.created_at ? formatDate(trip.created_at) : '—' }}</p>
          </div>
          <div class="h-0.5 flex-1" :class="trip.status !== 'planned' ? 'bg-status-purple' : 'bg-line-default'"></div>
          <!-- Step 2: Dispatched -->
          <div class="flex flex-1 flex-col items-center">
            <div
              class="flex h-10 w-10 items-center justify-center rounded-full border-2 text-sm font-bold"
              :class="getStepClass(1)"
            >
              2
            </div>
            <p class="mt-2 text-xs font-semibold" :class="getStepLabelClass(1)">Dispatched</p>
            <p class="text-xs text-muted">{{ trip.dispatch_date ? formatDate(trip.dispatch_date) : 'Pending' }}</p>
          </div>
          <div class="h-0.5 flex-1" :class="trip.status === 'delivered' ? 'bg-status-green' : 'bg-line-default'"></div>
          <!-- Step 3: Delivered -->
          <div class="flex flex-1 flex-col items-center">
            <div
              class="flex h-10 w-10 items-center justify-center rounded-full border-2 text-sm font-bold"
              :class="getStepClass(2)"
            >
              3
            </div>
            <p class="mt-2 text-xs font-semibold" :class="getStepLabelClass(2)">Delivered</p>
            <p class="text-xs text-muted">{{ trip.actual_delivery_date ? formatDate(trip.actual_delivery_date) : 'Pending' }}</p>
          </div>
        </div>
      </div>

      <!-- B. Trip Info Cards -->
      <div class="mt-6 grid grid-cols-1 gap-6 md:grid-cols-2">
        <BaseCard container-class="px-5 py-5">
          <template #header>
            <h2 class="font-semibold text-heading">Truck, Driver & Broker</h2>
          </template>
          <div class="space-y-2 text-sm">
            <div class="flex justify-between">
              <span class="text-muted">Truck Number</span>
              <span class="font-semibold text-body">
                {{ trip.truck_number || '—' }}
              </span>
            </div>
            <div class="flex justify-between">
              <span class="text-muted">Driver Name</span>
              <span class="font-semibold text-body">
                {{ trip.driver_name || '—' }}
              </span>
            </div>
            <div class="flex justify-between">
              <span class="text-muted">Driver Phone</span>
              <span class="font-semibold text-body">
                {{ trip.driver_phone || '—' }}
              </span>
            </div>
            <div class="mt-3 border-t border-line-light pt-3">
              <div class="flex justify-between">
                <span class="text-muted">Broker Name</span>
                <span class="font-semibold text-body">
                  {{ trip.broker_name || '—' }}
                </span>
              </div>
              <div class="flex justify-between">
                <span class="text-muted">Broker Phone</span>
                <span class="font-semibold text-body">
                  {{ trip.broker_phone || '—' }}
                </span>
              </div>
            </div>
          </div>
        </BaseCard>

        <BaseCard container-class="px-5 py-5">
          <template #header>
            <h2 class="font-semibold text-heading">Route & Timeline</h2>
          </template>
          <div class="space-y-2 text-sm">
            <div class="flex justify-between">
              <span class="text-muted">Dispatch Date</span>
              <span class="font-semibold text-body">
                {{ trip.dispatch_date ? formatDate(trip.dispatch_date) : '—' }}
              </span>
            </div>
            <div class="flex justify-between">
              <span class="text-muted">Expected Delivery</span>
              <span class="font-semibold text-body">
                {{ trip.expected_delivery_date ? formatDate(trip.expected_delivery_date) : '—' }}
              </span>
            </div>
            <div class="flex justify-between">
              <span class="text-muted">Actual Delivery</span>
              <span
                class="font-semibold"
                :class="trip.actual_delivery_date ? 'text-status-green' : 'text-muted'"
              >
                {{ trip.actual_delivery_date ? formatDate(trip.actual_delivery_date) : 'Pending' }}
              </span>
            </div>
            <div v-if="trip.status === 'dispatched'" class="mt-3 border-t border-line-light pt-3">
              <div class="flex justify-between">
                <span class="text-muted">Days Elapsed</span>
                <span
                  class="font-bold"
                  :class="getDaysElapsedClass(daysElapsed)"
                >
                  {{ daysElapsed }} days
                </span>
              </div>
              <div v-if="trip.expected_delivery_date" class="flex justify-between">
                <span class="text-muted">Days Remaining</span>
                <span class="font-semibold text-body">
                  {{ daysRemaining > 0 ? daysRemaining + ' days' : 'Overdue' }}
                </span>
              </div>
            </div>
          </div>
        </BaseCard>
      </div>

      <!-- D. Financial Summary Card -->
      <div class="mt-6 grid grid-cols-1 gap-6 md:grid-cols-2">
        <BaseCard container-class="px-5 py-5">
          <template #header>
            <h2 class="font-semibold text-heading">Financial Summary</h2>
          </template>
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="rounded-lg bg-surface-secondary p-3">
              <p class="text-xs text-muted">Trip Revenue</p>
              <p class="text-lg font-semibold text-heading">
                <BaseFormatMoney :amount="tripRevenue" />
              </p>
            </div>
            <div class="rounded-lg bg-surface-secondary p-3">
              <p class="text-xs text-muted">Total Expenses</p>
              <p class="text-lg font-semibold text-heading">
                <BaseFormatMoney :amount="totalExpenses" />
              </p>
            </div>
            <div class="rounded-lg bg-surface-secondary p-3">
              <p class="text-xs text-muted">Net Margin</p>
              <p
                class="text-lg font-semibold"
                :class="tripMargin < 0 ? 'text-status-red' : 'text-status-green'"
              >
                <BaseFormatMoney :amount="tripMargin" />
              </p>
            </div>
          </div>
        </BaseCard>

        <!-- Related Expenses -->
        <BaseCard container-class="px-5 py-5">
          <template #header>
            <h2 class="font-semibold text-heading">Related Expenses</h2>
          </template>
          <p v-if="relatedExpenses.length === 0" class="text-sm text-muted">
            No expenses linked to this trip.
          </p>
          <div v-else class="space-y-2">
            <div
              v-for="expense in relatedExpenses"
              :key="expense.id"
              class="flex justify-between border-b border-line-light pb-2 text-sm"
            >
              <span>
                {{ expense.expense_category?.name || 'Expense' }} ·
                {{ expense.expense_date }}
              </span>
              <span class="font-semibold"><BaseFormatMoney :amount="expense.base_amount" /></span>
            </div>
          </div>
        </BaseCard>
      </div>

      <!-- Consolidation Group Link -->
      <BaseCard
        v-if="trip.consolidation_group"
        class="mt-6 cursor-pointer transition hover:bg-hover"
        container-class="px-5 py-4"
        @click="
          $router.push({
            name: 'consolidation.show',
            params: { id: trip.consolidation_group.id },
          })
        "
      >
        <div class="flex items-center justify-between">
          <div>
            <p class="font-semibold text-primary-500">
              {{ trip.consolidation_group.group_number }}
            </p>
            <p class="text-sm text-muted">
              → {{ trip.consolidation_group.destination_city }}
            </p>
          </div>
          <span class="text-muted">→</span>
        </div>
      </BaseCard>

      <!-- C. Material on Trip -->
      <div class="mt-6">
        <h2 class="mb-3 text-lg font-bold text-heading">Material on This Trip</h2>
        <div
          v-if="!trip.warehouse_items || trip.warehouse_items.length === 0"
          class="rounded-lg bg-surface-secondary py-8 text-center text-muted"
        >
          No items assigned to this trip.
        </div>
        <div v-else class="overflow-x-auto rounded-lg bg-surface shadow-sm">
          <table class="w-full text-sm">
            <thead class="bg-surface-secondary text-xs uppercase text-muted">
              <tr>
                <th class="px-4 py-3 text-left">LR Number</th>
                <th class="px-4 py-3 text-left">Consignor</th>
                <th class="px-4 py-3 text-left">Consignee</th>
                <th class="px-4 py-3 text-right">Weight</th>
                <th class="px-4 py-3 text-right">Packages</th>
                <th class="px-4 py-3 text-center">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-line-light">
              <tr
                v-for="item in trip.warehouse_items"
                :key="item.id"
                class="hover:bg-hover"
              >
                <td class="px-4 py-3 font-semibold text-heading">
                  {{ item.lr?.invoice_number || '-' }}
                </td>
                <td class="px-4 py-3 text-body">
                  {{ item.consignor_name || item.lr?.customer?.name || '-' }}
                </td>
                <td class="px-4 py-3 text-body">{{ item.consignee_name || '-' }}</td>
                <td class="px-4 py-3 text-right text-body">
                  {{ formatWeight(item.weight_kg) }} kg
                </td>
                <td class="px-4 py-3 text-right text-body">
                  {{ item.no_of_packages || 0 }}
                </td>
                <td class="px-4 py-3 text-center">
                  <span
                    class="rounded px-2 py-1 text-xs font-bold"
                    :class="getItemStatusClass(item.status)"
                  >
                    {{ item.status }}
                  </span>
                </td>
              </tr>
            </tbody>
            <tfoot class="bg-surface-secondary font-bold">
              <tr>
                <td class="px-4 py-3 text-heading" colspan="3">Total</td>
                <td class="px-4 py-3 text-right text-heading">
                  {{ formatWeight(totalWeight) }} kg
                </td>
                <td class="px-4 py-3 text-right text-heading">{{ totalPackages }}</td>
                <td></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>

      <!-- Notes -->
      <BaseCard v-if="trip.notes" class="mt-6" container-class="px-5 py-5">
        <template #header>
          <h2 class="font-semibold text-heading">Notes</h2>
        </template>
        <p class="text-sm text-body">{{ trip.notes }}</p>
      </BaseCard>

      <!-- Future Integration Placeholder -->
      <div
        class="mt-6 rounded-lg border border-dashed border-line-default bg-surface-secondary p-4"
      >
        <p class="text-xs text-muted">
          🔌 Future integrations: GPS tracking, Fastag toll data, E-Way Bill
          verification, and route optimization will appear here when production
          APIs are connected.
        </p>
      </div>
    </template>
  </BasePage>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { client } from '@/scripts/api/client'
import { useLoadTripStore } from '../store'
import { useDialogStore } from '@/scripts/stores/dialog.store'
import BaseFormatMoney from '@/scripts/components/base/BaseFormatMoney.vue'

const route = useRoute()
const router = useRouter()
const store = useLoadTripStore()
const dialogStore = useDialogStore()
const loading = ref(true)
const relatedExpenses = ref<any[]>([])

const trip = computed(() => store.currentTrip)

const totalWeight = computed(() => {
  if (!trip.value?.warehouse_items) return 0
  return trip.value.warehouse_items.reduce(
    (sum: number, item: any) => sum + (parseFloat(item.weight_kg) || 0),
    0
  )
})

const totalPackages = computed(() => {
  if (!trip.value?.warehouse_items) return 0
  return trip.value.warehouse_items.reduce(
    (sum: number, item: any) => sum + (item.no_of_packages || 0),
    0
  )
})

const totalExpenses = computed(() =>
  relatedExpenses.value.reduce((sum: number, e: any) => sum + (e.base_amount || 0), 0)
)

const tripRevenue = computed(() => {
  if (!trip.value?.warehouse_items) return 0
  // Revenue is allocated by LR weight — same logic as backend FleetReportService
  const itemsByLr = trip.value.warehouse_items
    .filter((i: any) => i.lr_id)
    .reduce((acc: Record<number, any[]>, item: any) => {
      if (!acc[item.lr_id]) acc[item.lr_id] = []
      acc[item.lr_id].push(item)
      return acc
    }, {})

  return Object.entries(itemsByLr).reduce((sum: number, [lrId, items]: [string, any[]]) => {
    const lr = items[0]?.lr
    if (!lr) return sum
    return sum + (lr.total || 0)
  }, 0)
})

const tripMargin = computed(() => tripRevenue.value - totalExpenses.value)

const daysElapsed = computed(() => {
  if (!trip.value?.dispatch_date) return 0
  const diff = new Date().getTime() - new Date(trip.value.dispatch_date).getTime()
  return Math.floor(diff / (1000 * 60 * 60 * 24))
})

const daysRemaining = computed(() => {
  if (!trip.value?.expected_delivery_date) return 0
  const diff = new Date(trip.value.expected_delivery_date).getTime() - new Date().getTime()
  return Math.ceil(diff / (1000 * 60 * 60 * 24))
})

const loadTrip = async () => {
  loading.value = true
  const id = parseInt(route.params.id as string)
  await store.getTrip(id)
  const response = await client.get(`/api/v1/expenses?load_trip_id=${id}&limit=all`)
  relatedExpenses.value = response.data?.data || response.data || []
  loading.value = false
}

const addExpense = () =>
  router.push({
    path: '/admin/expenses/create',
    query: {
      trip: String(trip.value?.id),
      truck: String(trip.value?.truck_id || ''),
      driver: String(trip.value?.driver_profile_id || ''),
    },
  })

const dispatchTrip = async () => {
  const confirmed = await dialogStore.openDialog({
    title: 'Dispatch Trip',
    message: 'Dispatch this trip? All items will be marked as in transit.',
    variant: 'primary',
    yesLabel: 'Dispatch',
  })
  if (confirmed) {
    try {
      await store.dispatchTrip(parseInt(route.params.id as string))
      await store.getTrip(parseInt(route.params.id as string))
    } catch {
      await dialogStore.openDialog({
        title: 'Error',
        message: 'Failed to dispatch trip',
        variant: 'danger',
        hideNoButton: true,
      })
    }
  }
}

const markDelivered = async () => {
  const confirmed = await dialogStore.openDialog({
    title: 'Mark Delivered',
    message: 'Mark this trip as delivered? All items will be marked as delivered.',
    variant: 'primary',
    yesLabel: 'Mark Delivered',
  })
  if (confirmed) {
    try {
      await store.markDelivered(parseInt(route.params.id as string))
      await store.getTrip(parseInt(route.params.id as string))
    } catch {
      await dialogStore.openDialog({
        title: 'Error',
        message: 'Failed to mark trip as delivered',
        variant: 'danger',
        hideNoButton: true,
      })
    }
  }
}

const cancelTrip = async () => {
  const confirmed = await dialogStore.openDialog({
    title: 'Cancel Trip',
    message:
      'Cancel this trip? The truck will be released, items will be reverted to stored, and the consolidation group will be reopened.',
    variant: 'danger',
    yesLabel: 'Cancel Trip',
  })
  if (confirmed) {
    try {
      await store.cancelTrip(parseInt(route.params.id as string))
      await store.getTrip(parseInt(route.params.id as string))
    } catch {
      await dialogStore.openDialog({
        title: 'Error',
        message: 'Failed to cancel trip',
        variant: 'danger',
        hideNoButton: true,
      })
    }
  }
}

// ─── Stepper helpers ───

const currentStep = computed(() => {
  if (!trip.value) return 0
  if (trip.value.status === 'delivered') return 2
  if (trip.value.status === 'dispatched') return 1
  return 0
})

const getStepClass = (step: number) => {
  if (step < currentStep.value) {
    // Completed steps
    const classes = ['bg-status-blue text-white border-status-blue', 'bg-status-purple text-white border-status-purple', 'bg-status-green text-white border-status-green']
    return classes[step]
  }
  if (step === currentStep.value) {
    // Current step
    const classes = ['bg-status-blue text-white border-status-blue', 'bg-status-purple text-white border-status-purple', 'bg-status-green text-white border-status-green']
    return classes[step]
  }
  return 'bg-surface text-muted border-line-default'
}

const getStepLabelClass = (step: number) => {
  if (step <= currentStep.value) {
    const classes = ['text-status-blue', 'text-status-purple', 'text-status-green']
    return classes[step]
  }
  return 'text-muted'
}

// ─── Status helpers ───

const getStatusClass = (status: string) => {
  const classes: Record<string, string> = {
    planned: 'bg-status-blue/10 text-status-blue',
    dispatched: 'bg-status-purple/10 text-status-purple',
    delivered: 'bg-status-green/10 text-status-green',
    cancelled: 'bg-status-red/10 text-status-red',
  }
  return classes[status] || ''
}

const getItemStatusClass = (status: string) => {
  const classes: Record<string, string> = {
    stored: 'bg-status-blue/10 text-status-blue',
    picked_for_consolidation: 'bg-status-yellow/10 text-status-yellow',
    loaded_on_vehicle: 'bg-status-purple/10 text-status-purple',
    in_transit: 'bg-status-purple/10 text-status-purple',
    delivered: 'bg-status-green/10 text-status-green',
    cancelled: 'bg-status-red/10 text-status-red',
  }
  return classes[status] || ''
}

const getDaysElapsedClass = (days: number) => {
  if (days > 5) return 'text-status-red'
  if (days >= 3) return 'text-status-yellow'
  return 'text-status-green'
}

const formatWeight = (weight: any) => {
  const w = parseFloat(weight) || 0
  return w.toLocaleString('en-IN', { maximumFractionDigits: 2 })
}

const formatDate = (date: string) => {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('en-IN', {
    day: '2-digit',
    month: 'short',
    year: '2-digit',
  })
}

onMounted(() => {
  loadTrip()
})
</script>
