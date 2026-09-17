<template>
  <BasePage>
    <BasePageHeader title="Load Trips">
      <p class="mt-1 text-sm text-muted">
        Dispatch consolidated groups as truck trips and track delivery status.
      </p>

      <template #actions>
        <div class="flex flex-wrap items-center justify-end gap-3">
          <BaseButton variant="white" @click="$router.push({ name: 'trucks.index' })">
            Truck Master
          </BaseButton>
          <BaseButton variant="white" @click="$router.push({ name: 'consolidation.index' })">
            Consolidation Board
          </BaseButton>
          <BaseButton variant="primary" @click="showCreateModal = true">
            <template #left="slotProps">
              <BaseIcon name="PlusIcon" :class="slotProps.class" />
            </template>
            New Load Trip
          </BaseButton>
        </div>
      </template>
    </BasePageHeader>

    <!-- Shared Tab Navigation -->
    <OperationsTabBar
      active-key="dispatched"
      :consolidation-count="consolidationStore.groups.length || null"
      :dispatched-count="store.trips.length || null"
      @tab-click="onTabClick"
    />

    <!-- KPI Strip -->
    <div class="mt-6 grid grid-cols-2 gap-4 md:grid-cols-4">
      <BaseCard container-class="px-4 py-4">
        <p class="text-sm text-muted">Ready to Dispatch</p>
        <p class="text-2xl font-bold text-status-blue">{{ readyGroups.length }}</p>
      </BaseCard>

      <BaseCard container-class="px-4 py-4">
        <p class="text-sm text-muted">Dispatched</p>
        <p class="text-2xl font-bold text-status-purple">{{ stats.dispatched }}</p>
      </BaseCard>
      <BaseCard container-class="px-4 py-4">
        <p class="text-sm text-muted">Delivered</p>
        <p class="text-2xl font-bold text-status-green">{{ stats.delivered }}</p>
      </BaseCard>
      <BaseCard container-class="px-4 py-4">
        <p class="text-sm text-muted">Total Items Moved</p>
        <p class="text-2xl font-bold text-heading">{{ stats.totalItems }}</p>
      </BaseCard>
    </div>

    <!-- Filters -->
    <div class="mt-6 flex flex-wrap gap-4">
      <BaseInputGroup label="Status" class="min-w-[200px]">
        <BaseMultiselect
          v-model="selectedStatus"
          :options="statusFilterOptions"
          value-prop="value"
          label="label"
          track-by="value"
          searchable
          placeholder="All Statuses"
          @update:model-value="loadTrips"
        />
      </BaseInputGroup>
    </div>

    <!-- Shared Load Trips Table -->
    <div class="mt-6">
      <LoadTripsTable
        :trips="store.trips"
        :loading="store.loading"
        @row-click="(id) => $router.push({ name: 'load-trips.show', params: { id } })"
        @dispatch="dispatchTrip"
        @deliver="markDelivered"
      />
    </div>

    <!-- Shared LorryPartyProfileModal for "Add New Driver" support -->
    <LorryPartyProfileModal />

    <!-- Shared Create Modal -->
    <LoadTripFormModal
      :show="showCreateModal"
      :ready-groups="readyGroups"
      @close="showCreateModal = false"
      @submit="submitCreate"
    />
  </BasePage>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useLoadTripStore } from '../store'
import { useConsolidationStore } from '../../consolidation/store'
import { useDialogStore } from '@/scripts/stores/dialog.store'
import { useApiResponse } from '@/scripts/composables/useApiResponse'
import OperationsTabBar from '@/scripts/features/company/shared/OperationsTabBar.vue'
import LoadTripsTable from '@/scripts/features/company/shared/LoadTripsTable.vue'
import LoadTripFormModal from '@/scripts/features/company/shared/LoadTripFormModal.vue'
import LorryPartyProfileModal from '@/scripts/features/company/invoices/components/LorryPartyProfileModal.vue'

const router = useRouter()
const store = useLoadTripStore()
const consolidationStore = useConsolidationStore()
const dialogStore = useDialogStore()
const { extractErrorMessage } = useApiResponse()
const selectedStatus = ref('')
const showCreateModal = ref(false)

const statusFilterOptions = [
  { value: '', label: 'All Statuses' },
  { value: 'planned', label: 'Planned' },
  { value: 'dispatched', label: 'Dispatched' },
  { value: 'delivered', label: 'Delivered' },
  { value: 'cancelled', label: 'Cancelled' },
]

const readyGroups = computed(() => {
  return consolidationStore.groups.filter((g: any) => g.status === 'ready')
})

const stats = computed(() => {
  const s = { planned: 0, dispatched: 0, delivered: 0, totalItems: 0 }
  store.trips.forEach((t: any) => {
    if (t.status === 'planned') s.planned++
    else if (t.status === 'dispatched') s.dispatched++
    else if (t.status === 'delivered') s.delivered++
    s.totalItems += t.warehouse_items?.length || 0
  })
  return s
})

const loadTrips = () => {
  store.fetchTrips({ status: selectedStatus.value || undefined })
}

const onTabClick = (key: string) => {
  if (key === 'warehouse') {
    router.push({ name: 'warehouse-items.index' })
  } else if (key === 'consolidation') {
    router.push({ name: 'consolidation.index' })
  }
}

const submitCreate = async (formData: any) => {
  try {
    await store.createTrip(formData)
    showCreateModal.value = false
  } catch (error: any) {
    const message = extractErrorMessage(error, 'Failed to create load trip')
    await dialogStore.openDialog({
      title: 'Error',
      message,
      variant: 'danger',
      hideNoButton: true,
    })
  }
}

const dispatchTrip = async (id: number) => {
  const confirmed = await dialogStore.openDialog({
    title: 'Dispatch Trip',
    message: 'Dispatch this trip? Items will be marked as in transit.',
    variant: 'primary',
    yesLabel: 'Dispatch',
  })
  if (confirmed) {
    try {
      await store.dispatchTrip(id)
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

const markDelivered = async (id: number) => {
  const confirmed = await dialogStore.openDialog({
    title: 'Mark Delivered',
    message: 'Mark this trip as delivered?',
    variant: 'primary',
    yesLabel: 'Mark Delivered',
  })
  if (confirmed) {
    try {
      await store.markDelivered(id)
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

onMounted(() => {
  loadTrips()
  consolidationStore.fetchGroups({ status: 'ready' })
})
</script>
