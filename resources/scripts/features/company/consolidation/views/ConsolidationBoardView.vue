<template>
  <BasePage>
    <BasePageHeader title="Consolidation Board">
      <p class="mt-1 text-sm text-muted">
        Group part-load material by destination, fill trucks, and dispatch when ready.
      </p>

      <template #actions>
        <BaseButton variant="primary" @click="showCreateModal = true">
          <template #left="slotProps">
            <BaseIcon name="PlusIcon" :class="slotProps.class" />
          </template>
          New Consolidation Group
        </BaseButton>
      </template>
    </BasePageHeader>

    <!-- Shared Tab Navigation -->
    <OperationsTabBar
      active-key="consolidation"
      :consolidation-count="store.groups.length || null"
      @tab-click="onTabClick"
    />

    <!-- KPI Strip -->
    <div class="mt-6 grid grid-cols-2 gap-4 md:grid-cols-4">
      <BaseCard container-class="px-4 py-4">
        <p class="text-sm text-muted">Open Groups</p>
        <p class="text-2xl font-bold text-heading">{{ stats.open }}</p>
      </BaseCard>
      <BaseCard container-class="px-4 py-4">
        <p class="text-sm text-muted">Ready to Dispatch</p>
        <p class="text-2xl font-bold text-status-green">{{ stats.ready }}</p>
      </BaseCard>
      <BaseCard container-class="px-4 py-4">
        <p class="text-sm text-muted">Dispatched</p>
        <p class="text-2xl font-bold text-status-blue">{{ stats.dispatched }}</p>
      </BaseCard>
      <BaseCard container-class="px-4 py-4">
        <p class="text-sm text-muted">Total Weight (Open)</p>
        <p class="text-2xl font-bold text-heading">{{ formatWeight(stats.totalWeight) }} kg</p>
      </BaseCard>
    </div>

    <!-- Candidates Section -->
    <div v-if="store.candidates.length > 0" class="mt-8">
      <h2 class="mb-3 text-lg font-bold text-heading">
        Unassigned Material (Available for Consolidation)
      </h2>
      <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
        <BaseCard
          v-for="candidate in store.candidates"
          :key="candidate.destination"
          container-class="p-4"
          class="border-l-4 border-status-yellow"
        >
          <div class="mb-2 flex items-start justify-between">
            <h3 class="font-bold text-heading">{{ candidate.destination }}</h3>
            <span class="rounded-full bg-status-yellow/10 px-2 py-1 text-xs text-status-yellow">
              {{ candidate.item_count }} item{{ candidate.item_count !== 1 ? 's' : '' }}
            </span>
          </div>
          <div class="mb-3 space-y-1 text-sm">
            <div>
              <p class="text-xs text-muted">Total Weight</p>
              <p class="font-semibold text-body">{{ formatWeight(candidate.total_weight_kg) }} kg</p>
            </div>
            <div>
              <p class="text-xs text-muted">Total Packages</p>
              <p class="font-semibold text-body">{{ candidate.total_packages || 0 }}</p>
            </div>
          </div>
          <BaseButton
            variant="primary"
            class="w-full"
            @click="openCreateForDestination(candidate.destination)"
          >
            Create Group for {{ candidate.destination }}
          </BaseButton>
        </BaseCard>
      </div>
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
          @update:model-value="loadGroups"
        />
      </BaseInputGroup>
    </div>

    <!-- Loading / Empty -->
    <div v-if="store.loading" class="mt-6 py-8 text-center text-muted">Loading...</div>
    <div
      v-else-if="store.groups.length === 0"
      class="mt-6 rounded-lg bg-surface-secondary py-12 text-center text-muted"
    >
      <p class="text-lg">No consolidation groups yet</p>
      <p class="mt-2 text-sm">
        Create a group to start combining part-load material for a destination.
      </p>
    </div>

    <!-- Groups Grid — using shared ConsolidationGroupCard -->
    <div v-else class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
      <ConsolidationGroupCard
        v-for="group in store.groups"
        :key="group.id"
        :group="group"
        @click="goToDetail"
        @delete="deleteGroup"
      />
    </div>

    <!-- Create Modal -->
    <BaseModal :show="showCreateModal" @close="closeCreateModal">
      <template #header>
        <div class="flex w-full items-center justify-between">
          <span>New Consolidation Group</span>
          <BaseIcon
            name="XMarkIcon"
            class="h-6 w-6 cursor-pointer text-muted"
            @click="closeCreateModal"
          />
        </div>
      </template>
      <form class="space-y-5 px-6 py-5" @submit.prevent="submitCreate">
        <BaseInputGrid layout="one-column">
          <BaseInputGroup label="Destination City" required>
            <BaseInput
              v-model="createForm.destination_city"
              type="text"
              placeholder="e.g. Mumbai"
            />
          </BaseInputGroup>
          <BaseInputGroup label="Truck Capacity (kg)">
            <BaseInput
              v-model.number="createForm.truck_capacity_kg"
              type="number"
              placeholder="e.g. 9000"
            />
            <p class="mt-1 text-xs text-muted">Default: 9000 kg (standard truck load)</p>
          </BaseInputGroup>
          <BaseInputGroup label="Notes">
            <BaseTextarea v-model="createForm.notes" rows="2" />
          </BaseInputGroup>
        </BaseInputGrid>
        <div
          class="flex flex-col-reverse gap-3 border-t border-line-light pt-5 sm:flex-row sm:justify-end"
        >
          <BaseButton variant="white" type="button" @click="closeCreateModal">
            Cancel
          </BaseButton>
          <BaseButton :disabled="!createForm.destination_city" type="submit">
            Create Group
          </BaseButton>
        </div>
      </form>
    </BaseModal>
  </BasePage>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useConsolidationStore } from '../store'
import { useDialogStore } from '@/scripts/stores/dialog.store'
import { useApiResponse } from '@/scripts/composables/useApiResponse'
import { useOperationsHelpers } from '@/scripts/composables/useOperationsHelpers'
import OperationsTabBar from '@/scripts/features/company/shared/OperationsTabBar.vue'
import ConsolidationGroupCard from '@/scripts/features/company/shared/ConsolidationGroupCard.vue'

const store = useConsolidationStore()
const dialogStore = useDialogStore()
const route = useRoute()
const router = useRouter()
const { extractErrorMessage } = useApiResponse()
const { formatWeight } = useOperationsHelpers()
const selectedStatus = ref('')
const showCreateModal = ref(false)

const statusFilterOptions = [
  { value: '', label: 'All Statuses' },
  { value: 'open', label: 'Open' },
  { value: 'ready', label: 'Ready' },
  { value: 'dispatched', label: 'Dispatched' },
  { value: 'completed', label: 'Completed' },
  { value: 'cancelled', label: 'Cancelled' },
]

const createForm = ref({
  destination_city: '',
  truck_capacity_kg: 9000,
  notes: '',
})

const stats = computed(() => {
  const s = { open: 0, ready: 0, dispatched: 0, totalWeight: 0 }
  store.groups.forEach((g: any) => {
    if (g.status === 'open') {
      s.open++
      s.totalWeight += parseFloat(g.total_weight_kg) || 0
    } else if (g.status === 'ready') {
      s.ready++
    } else if (g.status === 'dispatched') {
      s.dispatched++
    }
  })
  return s
})

const loadGroups = () => {
  store.fetchGroups({
    status: selectedStatus.value || undefined,
    destination: (route.query.destination as string) || undefined,
  })
}

const onTabClick = (key: string) => {
  if (key === 'warehouse') {
    router.push({ name: 'warehouse-items.index' })
  } else if (key === 'dispatched') {
    router.push({ name: 'load-trips.index' })
  }
}

const openCreateForDestination = (destination: string) => {
  createForm.value.destination_city = destination
  showCreateModal.value = true
}

const closeCreateModal = () => {
  showCreateModal.value = false
  createForm.value = { destination_city: '', truck_capacity_kg: 9000, notes: '' }
}

const submitCreate = async () => {
  try {
    await store.createGroup(createForm.value)
    closeCreateModal()
    await store.fetchCandidates()
  } catch (error: any) {
    const message = extractErrorMessage(error, 'Failed to create consolidation group')
    await dialogStore.openDialog({
      title: 'Error',
      message,
      variant: 'danger',
      hideNoButton: true,
    })
  }
}

const goToDetail = (id: number) => {
  router.push({ name: 'consolidation.show', params: { id } })
}

const deleteGroup = async (id: number) => {
  const confirmed = await dialogStore.openDialog({
    title: 'Delete Group',
    message: 'Are you sure you want to delete this consolidation group? Items will be unassigned.',
    variant: 'danger',
    yesLabel: 'Delete',
  })
  if (confirmed) {
    try {
      await store.deleteGroup(id)
      await store.fetchCandidates()
    } catch (error: any) {
      const message = extractErrorMessage(error, 'Failed to delete consolidation group')
      await dialogStore.openDialog({
        title: 'Error',
        message,
        variant: 'danger',
        hideNoButton: true,
      })
    }
  }
}

onMounted(() => {
  loadGroups()
  store.fetchCandidates()
})
</script>
