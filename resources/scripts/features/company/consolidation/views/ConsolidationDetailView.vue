<template>
  <BasePage>
    <div v-if="loading" class="mt-6 text-center text-muted">Loading...</div>

    <div v-else-if="!group">
      <div class="mt-6 text-center text-muted">
        <p class="text-lg">Consolidation group not found</p>
        <div class="mt-4">
          <BaseButton variant="primary" @click="$router.push({ name: 'consolidation.index' })">
            Back to Board
          </BaseButton>
        </div>
      </div>
    </div>

    <template v-else>
      <BasePageHeader :title="group.group_number">
        <div class="mt-2 flex items-center gap-3">
          <span
            class="rounded px-2 py-1 text-xs font-bold"
            :class="getConsolidationStatusClass(group.status)"
          >
            {{ group.status }}
          </span>
          <p class="text-sm text-muted">
            Destination:
            <span class="font-semibold text-body">{{ group.destination_city }}</span>
          </p>
        </div>

        <template #actions>
          <div class="flex flex-wrap gap-3">
            <BaseButton
              v-if="group.status === 'open'"
              variant="primary"
              :disabled="!group.is_ready_to_dispatch"
              @click="markReady"
            >
              Mark Ready
            </BaseButton>
            <BaseButton
              v-if="group.status === 'ready'"
              variant="primary"
              @click="$router.push({ name: 'load-trips.index' })"
            >
              Create Load Trip
            </BaseButton>
          </div>
        </template>
      </BasePageHeader>

      <!-- Shared Tab Navigation -->
      <OperationsTabBar
        active-key="consolidation"
        @tab-click="onTabClick"
      />

      <!-- Fill Progress -->
      <BaseCard class="mt-6" container-class="px-5 py-5">
        <template #header>
          <div class="flex items-center justify-between">
            <h2 class="font-bold text-heading">Truck Fill Status</h2>
            <span
              v-if="group.is_ready_to_dispatch"
              class="rounded-full bg-status-green/10 px-3 py-1 text-sm font-semibold text-status-green"
            >
              ✓ Ready to Dispatch ({{ (group.fill_percentage || 0).toFixed(1) }}%)
            </span>
            <span v-else class="text-sm text-muted">
              {{ (group.fill_percentage || 0).toFixed(1) }}% filled — need 80% to dispatch
            </span>
          </div>
        </template>
        <div class="h-4 w-full overflow-hidden rounded-full bg-surface-tertiary">
          <div
            class="h-full rounded-full transition-all"
            :class="getFillBarClass(group.fill_percentage)"
            :style="{ width: Math.min(group.fill_percentage || 0, 100) + '%' }"
          ></div>
        </div>
        <div class="mt-2 flex justify-between text-sm text-muted">
          <span>{{ formatWeight(group.total_weight_kg) }} kg loaded</span>
          <span>{{ formatWeight(group.truck_capacity_kg) }} kg capacity</span>
        </div>
        <p class="mt-1 text-sm text-muted">
          Remaining capacity: {{ formatWeight(remainingCapacityKg) }} kg
        </p>
      </BaseCard>

      <!-- Stats Grid -->
      <div class="mt-6 grid grid-cols-2 gap-4 md:grid-cols-4">
        <BaseCard container-class="px-4 py-4">
          <p class="text-sm text-muted">Items in Group</p>
          <p class="text-2xl font-bold text-heading">{{ group.total_items || 0 }}</p>
        </BaseCard>
        <BaseCard container-class="px-4 py-4">
          <p class="text-sm text-muted">Total Packages</p>
          <p class="text-2xl font-bold text-heading">{{ group.total_packages || 0 }}</p>
        </BaseCard>
        <BaseCard container-class="px-4 py-4">
          <p class="text-sm text-muted">Total Weight</p>
          <p class="text-2xl font-bold text-heading">{{ formatWeight(group.total_weight_kg) }} kg</p>
        </BaseCard>
        <BaseCard container-class="px-4 py-4">
          <p class="text-sm text-muted">Overdue Items</p>
          <p
            class="text-2xl font-bold"
            :class="overdueCount > 0 ? 'text-status-red' : 'text-heading'"
          >
            {{ overdueCount }}
          </p>
        </BaseCard>
      </div>

      <!-- Items in Group -->
      <div class="mt-6">
        <h2 class="mb-3 text-lg font-bold text-heading">Material in This Group</h2>
        <div
          v-if="!group.items || group.items.length === 0"
          class="rounded-lg bg-surface-secondary py-8 text-center text-muted"
        >
          No items assigned to this group yet.
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
                <th class="px-4 py-3 text-center">Days</th>
                <th class="px-4 py-3 text-center">Deadline</th>
                <th v-if="group.status === 'open'" class="px-4 py-3 text-center"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-line-light">
              <tr v-for="item in group.items" :key="item.id" class="hover:bg-hover">
                <td class="px-4 py-3 font-semibold text-heading">
                  {{ item.lr?.invoice_number || '-' }}
                </td>
                <td class="px-4 py-3 text-body">
                  {{ item.consignor_name || item.lr?.customer?.name || '-' }}
                </td>
                <td class="px-4 py-3 text-body">{{ item.consignee_name || '-' }}</td>
                <td class="px-4 py-3 text-right text-body">{{ formatWeight(item.weight_kg) }} kg</td>
                <td class="px-4 py-3 text-right text-body">{{ item.no_of_packages || 0 }}</td>
                <td class="px-4 py-3 text-center">
                  <span class="font-semibold" :class="getDaysClass(item.days_in_warehouse)">
                    {{ item.days_in_warehouse || 0 }}
                  </span>
                </td>
                <td class="px-4 py-3 text-center">
                  <span
                    v-if="item.promised_dispatch_date"
                    :class="item.is_overdue ? 'font-bold text-status-red' : 'text-body'"
                  >
                    {{ formatDate(item.promised_dispatch_date) }}
                    <span v-if="item.is_overdue" class="block text-xs">⚠ OVERDUE</span>
                  </span>
                  <span v-else class="text-muted">—</span>
                </td>
                <td v-if="group.status === 'open'" class="px-4 py-3 text-center">
                  <BaseButton
                    size="xs"
                    variant="danger"
                    @click="removeItem(item.id)"
                  >
                    Remove
                  </BaseButton>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Add Items Section (only when open) -->
      <div v-if="group.status === 'open'" class="mt-6">
        <h2 class="mb-3 text-lg font-bold text-heading">Add Material to This Group</h2>
        <p class="mb-3 text-sm text-muted">
          Only unassigned stored items for
          <span class="font-semibold">{{ group.destination_city }}</span> can be added.
        </p>
        <div
          v-if="availableItems.length === 0"
          class="rounded-lg bg-surface-secondary py-6 text-center text-muted"
        >
          No unassigned items available for this destination.
        </div>
        <div v-else class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
          <BaseCard
            v-for="item in availableItems"
            :key="item.id"
            container-class="p-4"
          >
            <div class="mb-2 flex items-start justify-between">
              <div>
                <p class="font-bold text-heading">{{ item.lr?.invoice_number || '-' }}</p>
                <p class="text-xs text-muted">
                  {{ item.consignor_name || item.lr?.customer?.name || '-' }}
                </p>
              </div>
              <div class="flex flex-col gap-1">
                <BaseButton
                  size="xs"
                  variant="primary"
                  :disabled="wouldExceedCapacity(item.weight_kg)"
                  :title="
                    wouldExceedCapacity(item.weight_kg)
                      ? `Cannot add: only ${formatWeight(remainingCapacityKg)} kg capacity remains`
                      : 'Add to truck load'
                  "
                  @click="addItem(item.id)"
                >
                  + Add
                </BaseButton>
                <BaseButton
                  v-if="wouldExceedCapacity(item.weight_kg) && remainingCapacityKg > 0"
                  size="xs"
                  variant="secondary"
                  @click="openSplitModal(item)"
                >
                  Split load
                </BaseButton>
              </div>
            </div>
            <div class="grid grid-cols-3 gap-2 text-xs">
              <div>
                <p class="text-muted">Weight</p>
                <p class="font-semibold text-body">{{ formatWeight(item.weight_kg) }} kg</p>
              </div>
              <div>
                <p class="text-muted">Packages</p>
                <p class="font-semibold text-body">{{ item.no_of_packages || 0 }}</p>
              </div>
              <div>
                <p class="text-muted">Days</p>
                <p class="font-semibold" :class="getDaysClass(item.days_in_warehouse)">
                  {{ item.days_in_warehouse || 0 }}
                </p>
              </div>
            </div>
            <p v-if="wouldExceedCapacity(item.weight_kg)" class="mt-3 text-xs text-status-red">
              Exceeds remaining capacity by
              {{ formatWeight(Number(item.weight_kg) - remainingCapacityKg) }} kg
            </p>
          </BaseCard>
        </div>
      </div>

      <!-- Split LR Load Modal -->
      <BaseModal :show="!!selectedItem" @close="closeSplitModal">
        <template #header>
          <div class="flex w-full items-center justify-between">
            <span>Split LR Load</span>
            <BaseIcon
              name="XMarkIcon"
              class="h-6 w-6 cursor-pointer text-muted"
              @click="closeSplitModal"
            />
          </div>
        </template>
        <form class="px-6 py-5" @submit.prevent="splitItem">
          <p class="text-sm text-muted">
            Add part of LR {{ selectedItem?.lr?.invoice_number || '-' }} to this truck; the
            balance remains in the warehouse.
          </p>
          <div class="mt-4 rounded-lg bg-surface-secondary p-3 text-sm text-body">
            LR load: {{ formatWeight(selectedItem?.weight_kg) }} kg · Remaining truck capacity:
            {{ formatWeight(remainingCapacityKg) }} kg
          </div>
          <BaseInputGrid layout="one-column" class="mt-4">
            <BaseInputGroup label="Weight for this truck (kg)" required>
              <BaseInput
                v-model.number="splitForm.weight_kg"
                type="number"
                min="0.01"
                :max="
                  selectedItem
                    ? Math.min(Number(selectedItem.weight_kg) - 0.01, remainingCapacityKg)
                    : 0
                "
                step="0.01"
              />
            </BaseInputGroup>
            <BaseInputGroup
              v-if="selectedItem && selectedItem.no_of_packages > 0"
              label="Whole packages for this truck"
            >
              <BaseInput
                v-model.number="splitForm.no_of_packages"
                type="number"
                min="1"
                :max="selectedItem ? selectedItem.no_of_packages - 1 : 0"
              />
              <p class="mt-1 text-xs text-muted">
                The remaining packages stay with the balance load.
              </p>
            </BaseInputGroup>
          </BaseInputGrid>
          <p v-if="splitForm.weight_kg > 0 && selectedItem" class="mt-3 text-sm text-muted">
            Warehouse balance:
            {{ formatWeight(Number(selectedItem.weight_kg) - splitForm.weight_kg) }} kg
          </p>
          <div
            class="z-0 mt-6 flex flex-col-reverse gap-3 border-t border-line-default border-solid pt-4 sm:flex-row sm:justify-end"
          >
            <BaseButton variant="white" type="button" @click="closeSplitModal">
              Cancel
            </BaseButton>
            <BaseButton :disabled="!canSubmitSplit" type="submit">Split & Add</BaseButton>
          </div>
        </form>
      </BaseModal>

      <!-- Load Trips -->
      <div v-if="group.load_trips && group.load_trips.length > 0" class="mt-6">
        <h2 class="mb-3 text-lg font-bold text-heading">Load Trips from This Group</h2>
        <div class="space-y-3">
          <BaseCard
            v-for="trip in group.load_trips"
            :key="trip.id"
            container-class="px-4 py-4"
            class="flex cursor-pointer items-center justify-between transition hover:shadow-md"
            @click="$router.push({ name: 'load-trips.show', params: { id: trip.id } })"
          >
            <div>
              <p class="font-bold text-heading">{{ trip.trip_number }}</p>
              <p class="text-sm text-muted">{{ trip.truck_number }} · {{ trip.driver_name }}</p>
            </div>
            <div class="flex items-center gap-4">
              <span
                class="rounded px-2 py-1 text-xs font-bold"
                :class="getTripStatusClass(trip.status)"
              >
                {{ trip.status }}
              </span>
              <span class="text-muted">→</span>
            </div>
          </BaseCard>
        </div>
      </div>
    </template>
  </BasePage>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useConsolidationStore } from '../store'
import { useWarehouseItemStore } from '../../warehouse-items/store'
import { useDialogStore } from '@/scripts/stores/dialog.store'
import { useOperationsHelpers } from '@/scripts/composables/useOperationsHelpers'
import OperationsTabBar from '@/scripts/features/company/shared/OperationsTabBar.vue'

const route = useRoute()
const router = useRouter()
const store = useConsolidationStore()
const warehouseStore = useWarehouseItemStore()
const dialogStore = useDialogStore()
const {
  formatWeight,
  formatDate,
  getConsolidationStatusClass,
  getTripStatusClass,
  getFillBarClass,
  getDaysClass,
} = useOperationsHelpers()
const loading = ref(true)
const selectedItem = ref<any>(null)
const splitForm = ref({ weight_kg: 0, no_of_packages: 0 })

const onTabClick = (key: string) => {
  if (key === 'warehouse') {
    router.push({ name: 'warehouse-items.index' })
  } else if (key === 'dispatched') {
    router.push({ name: 'load-trips.index' })
  }
}


const group = computed(() => store.currentGroup)

const overdueCount = computed(() => {
  if (!group.value?.items) return 0
  return group.value.items.filter((i: any) => i.is_overdue).length
})

const availableItems = computed(() => {
  if (!group.value) return []
  return warehouseStore.items.filter((item: any) => {
    return (
      item.status === 'stored' &&
      !item.consolidation_id &&
      item.destination_city === group.value.destination_city
    )
  })
})

const remainingCapacityKg = computed(() => {
  if (!group.value) return 0
  return Math.max(0, Number(group.value.truck_capacity_kg) - Number(group.value.total_weight_kg))
})

const wouldExceedCapacity = (weightKg: number | string) =>
  Number(weightKg) > remainingCapacityKg.value

const canSubmitSplit = computed(() => {
  if (!selectedItem.value) return false

  const validWeight =
    splitForm.value.weight_kg > 0 &&
    splitForm.value.weight_kg < Number(selectedItem.value.weight_kg) &&
    splitForm.value.weight_kg <= remainingCapacityKg.value
  const validPackages =
    selectedItem.value.no_of_packages === 0 ||
    (splitForm.value.no_of_packages > 0 &&
      splitForm.value.no_of_packages < selectedItem.value.no_of_packages)

  return validWeight && validPackages
})

const loadGroup = async () => {
  loading.value = true
  const id = parseInt(route.params.id as string)
  await store.getGroup(id)
  await warehouseStore.fetchItems()
  loading.value = false
}

const markReady = async () => {
  try {
    await store.markReady(parseInt(route.params.id as string))
    await store.getGroup(parseInt(route.params.id as string))
  } catch {
    await dialogStore.openDialog({
      title: 'Error',
      message: 'Failed to mark group as ready',
      variant: 'danger',
      hideNoButton: true,
    })
  }
}

const addItem = async (itemId: number) => {
  try {
    await store.addItemToGroup(parseInt(route.params.id as string), itemId)
    await store.getGroup(parseInt(route.params.id as string))
    await warehouseStore.fetchItems()
  } catch (error: any) {
    await dialogStore.openDialog({
      title: 'Error',
      message:
        error.response?.data?.errors?.item_id?.[0] ||
        error.response?.data?.message ||
        'Failed to add item to group',
      variant: 'danger',
      hideNoButton: true,
    })
  }
}

const openSplitModal = (item: any) => {
  selectedItem.value = item
  splitForm.value = {
    weight_kg: Math.min(Number(item.weight_kg) - 0.01, remainingCapacityKg.value),
    no_of_packages:
      item.no_of_packages > 0
        ? Math.max(
            1,
            Math.min(
              item.no_of_packages - 1,
              Math.floor((item.no_of_packages * remainingCapacityKg.value) / Number(item.weight_kg))
            )
          )
        : 0,
  }
}

const closeSplitModal = () => {
  selectedItem.value = null
  splitForm.value = { weight_kg: 0, no_of_packages: 0 }
}

const splitItem = async () => {
  if (!selectedItem.value) return

  try {
    const data: { weight_kg: number; no_of_packages?: number } = {
      weight_kg: splitForm.value.weight_kg,
    }
    if (selectedItem.value.no_of_packages > 0) {
      data.no_of_packages = splitForm.value.no_of_packages
    }

    await store.splitItemToGroup(
      parseInt(route.params.id as string),
      selectedItem.value.id,
      data
    )
    closeSplitModal()
    await store.getGroup(parseInt(route.params.id as string))
    await warehouseStore.fetchItems()
  } catch (error: any) {
    const errors = error.response?.data?.errors
    await dialogStore.openDialog({
      title: 'Error',
      message:
        errors?.weight_kg?.[0] ||
        errors?.no_of_packages?.[0] ||
        error.response?.data?.message ||
        'Failed to split LR load',
      variant: 'danger',
      hideNoButton: true,
    })
  }
}

const removeItem = async (itemId: number) => {
  try {
    await store.removeItemFromGroup(parseInt(route.params.id as string), itemId)
    await store.getGroup(parseInt(route.params.id as string))
    await warehouseStore.fetchItems()
  } catch {
    await dialogStore.openDialog({
      title: 'Error',
      message: 'Failed to remove item from group',
      variant: 'danger',
      hideNoButton: true,
    })
  }
}

onMounted(() => {
  loadGroup()
})
</script>
