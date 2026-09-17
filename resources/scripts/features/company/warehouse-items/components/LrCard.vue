<!--
  LrCard

  Reusable LR Receipt card for the Warehouse Operations board.
  Supports two view modes:
    - "compact": Single-row summary with chevron to expand full detail
    - "full": Full card layout (header, route, stats, items, actions)

  Mobile-responsive:
    - Stats grid collapses to 2x2 on mobile
    - Items table becomes stacked cards on mobile
    - Action buttons collapse into overflow menu on mobile

  Props:
    - lrGroup: The LR group object (lrId, lrNumber, customerName, consignor, consignee, origin, destination, loadType, priority, items[], totalWeight, totalPackages, overdueCount, maxDaysInWarehouse, earliestReceived, promisedDispatch)
    - viewMode: 'compact' | 'full'
    - isExpanded: boolean — whether this card is expanded (compact mode)
    - isSelected: boolean — whether this card is selected for bulk action
    - itemStatusOptions: options array for the item status multiselect

  Emits:
    - toggle-expand
    - toggle-select
    - receive-more
    - create-consolidation
    - view-lr
    - update-status (id, newStatus)
    - delete-item (id)
-->
<template>
  <BaseCard
    container-class="p-0 overflow-hidden"
    :class="getLrBorderClass(lrGroup)"
  >
    <!-- ============ COMPACT MODE ============ -->
    <template v-if="viewMode === 'compact' && !isExpanded">
      <!-- Single-row summary -->
      <div
        class="flex cursor-pointer items-center gap-3 px-4 py-3"
        @click="$emit('toggle-expand')"
      >
        <!-- Selection checkbox -->
        <input
          type="checkbox"
          :checked="isSelected"
          class="h-4 w-4 cursor-pointer rounded border-line-default text-primary-500 focus:ring-primary-500"
          @click.stop="$emit('toggle-select')"
        />

        <!-- LR number + route (main info) -->
        <div class="min-w-0 flex-1">
          <div class="flex items-center gap-2">
            <span class="truncate font-bold text-heading">{{ lrGroup.lrNumber }}</span>
            <span
              v-if="lrGroup.loadType === 'full_load'"
              class="hidden shrink-0 rounded bg-status-purple/10 px-1.5 py-0.5 text-xs text-status-purple sm:inline"
            >
              Full
            </span>
            <span
              v-else
              class="hidden shrink-0 rounded bg-status-blue/10 px-1.5 py-0.5 text-xs text-status-blue sm:inline"
            >
              Part
            </span>
            <span
              v-if="lrGroup.priority && lrGroup.priority !== 'normal'"
              class="hidden shrink-0 rounded px-1.5 py-0.5 text-xs sm:inline"
              :class="getPriorityClass(lrGroup.priority)"
            >
              {{ lrGroup.priority }}
            </span>
          </div>
          <p class="mt-0.5 truncate text-xs text-muted">
            📍 {{ lrGroup.origin || '—' }} → {{ lrGroup.destination || '—' }}
            <span class="hidden sm:inline">· {{ lrGroup.customerName || '' }}</span>
          </p>
        </div>

        <!-- Quick stats (hidden on very small mobile) -->
        <div class="hidden shrink-0 items-center gap-3 text-xs text-muted md:flex">
          <span class="font-semibold text-body">{{ formatWeight(lrGroup.totalWeight) }} kg</span>
          <span>{{ lrGroup.totalPackages }} pkgs</span>
          <span
            class="font-bold"
            :class="getDaysClass(lrGroup.maxDaysInWarehouse)"
          >
            {{ lrGroup.maxDaysInWarehouse }}d
          </span>
        </div>

        <!-- Status badge + overdue -->
        <div class="flex shrink-0 items-center gap-1.5">
          <span
            v-if="lrGroup.overdueCount > 0"
            class="rounded-full bg-status-red/10 px-2 py-0.5 text-xs font-bold text-status-red"
          >
            ⚠
          </span>
          <span
            class="rounded-full px-2 py-0.5 text-xs font-bold"
            :class="getLrStatusBadge(lrGroup).class"
          >
            {{ getLrStatusBadge(lrGroup).label }}
          </span>
        </div>

        <!-- Expand chevron -->
        <BaseIcon
          name="ChevronDownIcon"
          class="h-5 w-5 shrink-0 text-muted"
        />
      </div>
    </template>

    <!-- ============ FULL MODE or EXPANDED COMPACT ============ -->
    <template v-else>
      <!-- HEADER ROW -->
      <div class="flex items-start justify-between gap-3 px-5 py-4">
        <div class="flex flex-1 items-start gap-3">
          <!-- Selection checkbox -->
          <input
            type="checkbox"
            :checked="isSelected"
            class="mt-1 h-4 w-4 cursor-pointer rounded border-line-default text-primary-500 focus:ring-primary-500"
            @click.stop="$emit('toggle-select')"
          />
          <div class="flex-1">
            <div class="flex flex-wrap items-center gap-2">
              <!-- Collapse chevron (compact mode expanded) -->
              <button
                v-if="viewMode === 'compact'"
                class="text-muted hover:text-body"
                @click.stop="$emit('toggle-expand')"
              >
                <BaseIcon name="ChevronUpIcon" class="h-5 w-5" />
              </button>
              <h2 class="text-lg font-bold text-heading sm:text-xl">{{ lrGroup.lrNumber }}</h2>
              <span
                v-if="lrGroup.loadType === 'full_load'"
                class="rounded bg-status-purple/10 px-2 py-0.5 text-xs text-status-purple"
              >
                Full Load
              </span>
              <span v-else class="rounded bg-status-blue/10 px-2 py-0.5 text-xs text-status-blue">
                Part Load
              </span>
              <span
                v-if="lrGroup.priority && lrGroup.priority !== 'normal'"
                class="rounded px-2 py-0.5 text-xs"
                :class="getPriorityClass(lrGroup.priority)"
              >
                {{ lrGroup.priority }}
              </span>
            </div>
            <p class="mt-1 text-sm text-muted">{{ lrGroup.customerName || '—' }}</p>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <span
            class="rounded-full px-2 py-1 text-xs font-bold"
            :class="getLrStatusBadge(lrGroup).class"
          >
            {{ getLrStatusBadge(lrGroup).label }}
          </span>
          <span
            v-if="lrGroup.overdueCount > 0"
            class="rounded-full bg-status-red/10 px-2 py-1 text-xs font-bold text-status-red"
          >
            ⚠ OVERDUE
          </span>
        </div>
      </div>

      <!-- ROUTE ROW -->
      <div class="grid grid-cols-2 gap-4 border-t border-line-light px-5 py-3">
        <div>
          <p class="text-xs text-muted">Consignor</p>
          <p class="text-sm font-semibold text-body">{{ lrGroup.consignor || '—' }}</p>
        </div>
        <div>
          <p class="text-xs text-muted">Consignee</p>
          <p class="text-sm font-semibold text-body">{{ lrGroup.consignee || '—' }}</p>
        </div>
        <div class="col-span-2">
          <p class="text-sm font-semibold text-body">
            📍 {{ lrGroup.origin || '—' }} → {{ lrGroup.destination || '—' }}
          </p>
        </div>
      </div>

      <!-- STATS ROW — 2x2 on mobile, 5 columns on desktop -->
      <div class="grid grid-cols-2 gap-3 border-t border-line-light px-5 py-3 sm:grid-cols-3 lg:grid-cols-5">
        <div class="rounded-lg bg-surface-secondary p-3 text-center">
          <p class="text-xs text-muted">Weight</p>
          <p class="font-bold text-heading">{{ formatWeight(lrGroup.totalWeight) }} kg</p>
        </div>
        <div class="rounded-lg bg-surface-secondary p-3 text-center">
          <p class="text-xs text-muted">Packages</p>
          <p class="font-bold text-heading">{{ lrGroup.totalPackages }}</p>
        </div>
        <div class="rounded-lg bg-surface-secondary p-3 text-center">
          <p class="text-xs text-muted">Days in WH</p>
          <p class="font-bold" :class="getDaysClass(lrGroup.maxDaysInWarehouse)">
            {{ lrGroup.maxDaysInWarehouse }}
          </p>
        </div>
        <div class="rounded-lg bg-surface-secondary p-3 text-center">
          <p class="text-xs text-muted">Received</p>
          <p class="font-bold text-heading">{{ formatDate(lrGroup.earliestReceived) }}</p>
        </div>
        <div class="rounded-lg bg-surface-secondary p-3 text-center">
          <p class="text-xs text-muted">Promised Dispatch</p>
          <p
            class="font-bold"
            :class="lrGroup.overdueCount > 0 ? 'text-status-red' : 'text-heading'"
          >
            {{ formatDate(lrGroup.promisedDispatch) }}
            <span v-if="lrGroup.overdueCount > 0" class="block text-xs">⚠ OVERDUE</span>
          </p>
        </div>
      </div>

      <!-- WAREHOUSE ITEMS SECTION -->
      <div class="border-t border-line-light px-5 py-3">
        <div class="mb-2 flex items-center justify-between">
          <h3 class="text-sm font-semibold text-heading">
            Warehouse Items ({{ lrGroup.items.length }})
          </h3>
          <BaseButton
            v-if="lrGroup.items.length > 1 && viewMode === 'full'"
            size="xs"
            variant="white"
            @click="$emit('toggle-expand')"
          >
            {{ isExpanded ? '▲ Hide' : '▼ Show (' + lrGroup.items.length + ' items)' }}
          </BaseButton>
        </div>

        <!-- Desktop: Items table -->
        <div
          v-if="lrGroup.items.length === 1 || isExpanded || viewMode === 'compact'"
          class="hidden overflow-x-auto sm:block"
        >
          <table class="w-full text-left text-sm">
            <thead class="text-xs text-muted">
              <tr>
                <th class="pb-2">#</th>
                <th class="pb-2">Weight</th>
                <th class="pb-2">Pkgs</th>
                <th class="pb-2">Location</th>
                <th class="pb-2">Status</th>
                <th class="pb-2 text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(item, idx) in lrGroup.items"
                :key="item.id"
                class="border-t border-line-light"
              >
                <td class="py-2 text-body">{{ idx + 1 }}</td>
                <td class="py-2 text-body">{{ formatWeight(item.weight_kg) }} kg</td>
                <td class="py-2 text-body">{{ item.no_of_packages || 0 }}</td>
                <td class="py-2 text-body">{{ item.warehouse_location || '—' }}</td>
                <td class="py-2">
                  <span
                    class="rounded-full px-2 py-0.5 text-xs font-bold capitalize"
                    :class="getItemStatusClass(item.status)"
                  >
                    {{ item.status.replace(/_/g, ' ') }}
                  </span>
                </td>
                <td class="py-2">
                  <div class="flex items-center justify-end gap-2">
                    <BaseMultiselect
                      :model-value="item.status"
                      :options="itemStatusOptions"
                      value-prop="value"
                      label="label"
                      track-by="value"
                      :allow-empty="false"
                      :show-labels="false"
                      class="min-w-[120px] text-xs"
                      @update:model-value="(val: string) => $emit('update-status', item.id, val)"
                    />
                    <BaseButton
                      size="xs"
                      variant="danger"
                      title="Delete"
                      @click="$emit('delete-item', item.id)"
                    >
                      <BaseIcon name="TrashIcon" class="h-4 w-4" />
                    </BaseButton>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Mobile: Items as stacked cards -->
        <div
          v-if="lrGroup.items.length === 1 || isExpanded || viewMode === 'compact'"
          class="space-y-2 sm:hidden"
        >
          <div
            v-for="(item, idx) in lrGroup.items"
            :key="item.id"
            class="rounded-lg border border-line-light bg-surface-secondary p-3"
          >
            <div class="flex items-start justify-between">
              <div>
                <p class="text-xs text-muted">Item #{{ idx + 1 }}</p>
                <p class="font-semibold text-body">
                  {{ formatWeight(item.weight_kg) }} kg · {{ item.no_of_packages || 0 }} pkgs
                </p>
              </div>
              <span
                class="rounded-full px-2 py-0.5 text-xs font-bold capitalize"
                :class="getItemStatusClass(item.status)"
              >
                {{ item.status.replace(/_/g, ' ') }}
              </span>
            </div>
            <p v-if="item.warehouse_location" class="mt-1 text-xs text-muted">
              📍 {{ item.warehouse_location }}
            </p>
            <div class="mt-2 flex items-center gap-2">
              <BaseMultiselect
                :model-value="item.status"
                :options="itemStatusOptions"
                value-prop="value"
                label="label"
                track-by="value"
                :allow-empty="false"
                :show-labels="false"
                class="min-w-[100px] flex-1 text-xs"
                @update:model-value="(val: string) => $emit('update-status', item.id, val)"
              />
              <BaseButton
                size="xs"
                variant="danger"
                @click="$emit('delete-item', item.id)"
              >
                <BaseIcon name="TrashIcon" class="h-4 w-4" />
              </BaseButton>
            </div>
          </div>
        </div>
      </div>

      <!-- ACTION ROW -->
      <div class="flex flex-wrap items-center gap-2 border-t border-line-light px-5 py-3">
        <BaseButton
          size="sm"
          variant="white"
          @click="$emit('receive-more')"
        >
          <template #left="slotProps">
            <BaseIcon name="PlusIcon" :class="slotProps.class" />
          </template>
          Receive More
        </BaseButton>
        <BaseButton
          size="sm"
          variant="primary-outline"
          @click="$emit('create-consolidation')"
        >
          Create Consolidation →
        </BaseButton>
        <BaseButton
          size="sm"
          variant="white"
          @click="$emit('view-lr')"
        >
          View LR Detail →
        </BaseButton>
      </div>
    </template>
  </BaseCard>
</template>

<script setup lang="ts">
import { useOperationsHelpers } from '@/scripts/composables/useOperationsHelpers'

const {
  formatWeight,
  formatDate,
  getDaysClass,
  getPriorityClass,
} = useOperationsHelpers()

const props = defineProps<{
  lrGroup: any
  viewMode?: 'compact' | 'full'
  isExpanded?: boolean
  isSelected?: boolean
  itemStatusOptions?: any[]
}>()

defineEmits<{
  (e: 'toggle-expand'): void
  (e: 'toggle-select'): void
  (e: 'receive-more'): void
  (e: 'create-consolidation'): void
  (e: 'view-lr'): void
  (e: 'update-status', id: number, newStatus: string): void
  (e: 'delete-item', id: number): void
}>()

// LR left border color based on item statuses
const getLrBorderClass = (lrGroup: any) => {
  if (lrGroup.overdueCount > 0) return 'border-l-4 border-status-red'
  const statuses = lrGroup.items.map((i: any) => i.status)
  const allDelivered = statuses.every((s: string) => s === 'delivered')
  const allInTransit = statuses.every((s: string) => s === 'in_transit')
  const anyStored = statuses.some((s: string) => s === 'stored')
  if (allDelivered) return 'border-l-4 border-status-green'
  if (allInTransit) return 'border-l-4 border-status-purple'
  if (anyStored && statuses.some((s: string) => s !== 'stored')) return 'border-l-4 border-status-blue'
  return 'border-l-4 border-status-yellow'
}

// LR status badge
const getLrStatusBadge = (lrGroup: any) => {
  const statuses = lrGroup.items.map((i: any) => i.status)
  const allDelivered = statuses.every((s: string) => s === 'delivered')
  const allInTransit = statuses.every((s: string) => s === 'in_transit')
  const anyStored = statuses.some((s: string) => s === 'stored')
  if (allDelivered) return { label: 'DELIVERED', class: 'bg-status-green/10 text-status-green' }
  if (allInTransit) return { label: 'IN TRANSIT', class: 'bg-status-purple/10 text-status-purple' }
  if (anyStored && statuses.some((s: string) => s !== 'stored')) return { label: 'PARTIAL', class: 'bg-status-blue/10 text-status-blue' }
  return { label: 'IN STORAGE', class: 'bg-status-yellow/10 text-status-yellow' }
}

// Item status pill class
const getItemStatusClass = (status: string) => {
  const classes: Record<string, string> = {
    stored: 'bg-status-yellow/10 text-status-yellow',
    picked_for_consolidation: 'bg-status-blue/10 text-status-blue',
    loaded_on_vehicle: 'bg-status-blue/10 text-status-blue',
    in_transit: 'bg-status-purple/10 text-status-purple',
    delivered: 'bg-status-green/10 text-status-green',
    cancelled: 'bg-status-red/10 text-status-red',
  }
  return classes[status] || 'bg-surface-tertiary text-body'
}
</script>
