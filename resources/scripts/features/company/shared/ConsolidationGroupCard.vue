<!--
  ConsolidationGroupCard

  Shared card component for displaying a consolidation group with fill progress,
  stats, and status badge.
  Used by: ConsolidationBoardView and WarehouseItemIndexView (Consolidation tab).

  Props:
    - group: The consolidation group object

  Emits:
    - click: Emitted when the card is clicked (for navigation to detail)
    - delete: Emitted when the delete button is clicked
-->
<template>
  <BaseCard
    container-class="p-4"
    class="cursor-pointer transition hover:shadow-lg"
    @click="$emit('click', group.id)"
  >
    <div class="mb-3 flex items-start justify-between">
      <div>
        <p class="font-bold text-heading">{{ group.group_number }}</p>
        <p class="text-sm text-muted">→ {{ group.destination_city }}</p>
      </div>
      <div class="flex items-center gap-2">
        <span
          class="rounded px-2 py-1 text-xs font-bold"
          :class="getConsolidationStatusClass(group.status)"
        >
          {{ group.status }}
        </span>
        <BaseButton
          size="xs"
          variant="danger"
          title="Delete Group"
          @click.stop="$emit('delete', group.id)"
        >
          <BaseIcon name="TrashIcon" class="h-4 w-4" />
        </BaseButton>
      </div>
    </div>

    <!-- Fill Progress Bar -->
    <div class="mb-3">
      <div class="mb-1 flex justify-between text-xs text-muted">
        <span>Truck Fill</span>
        <span class="font-semibold text-body">
          {{ formatWeight(group.total_weight_kg) }} /
          {{ formatWeight(group.truck_capacity_kg) }} kg
        </span>
      </div>
      <div class="h-2.5 w-full overflow-hidden rounded-full bg-surface-tertiary">
        <div
          class="h-full rounded-full transition-all"
          :class="getFillBarClass(group.fill_percentage)"
          :style="{ width: Math.min(group.fill_percentage || 0, 100) + '%' }"
        ></div>
      </div>
      <p
        class="mt-1 text-right text-xs"
        :class="
          group.is_ready_to_dispatch
            ? 'font-semibold text-status-green'
            : 'text-muted'
        "
      >
        {{ (group.fill_percentage || 0).toFixed(1) }}% filled
        <span v-if="group.is_ready_to_dispatch"> ✓ Ready!</span>
      </p>
    </div>

    <div class="grid grid-cols-3 gap-2 border-t border-line-light pt-3 text-sm">
      <div>
        <p class="text-xs text-muted">Items</p>
        <p class="font-semibold text-body">{{ group.total_items || 0 }}</p>
      </div>
      <div>
        <p class="text-xs text-muted">Packages</p>
        <p class="font-semibold text-body">{{ group.total_packages || 0 }}</p>
      </div>
      <div>
        <p class="text-xs text-muted">Weight</p>
        <p class="font-semibold text-body">{{ formatWeight(group.total_weight_kg) }} kg</p>
      </div>
    </div>
  </BaseCard>
</template>

<script setup lang="ts">
import { useOperationsHelpers } from '@/scripts/composables/useOperationsHelpers'

defineProps<{
  group: any
}>()

defineEmits<{
  (e: 'click', id: number): void
  (e: 'delete', id: number): void
}>()

const { formatWeight, getConsolidationStatusClass, getFillBarClass } = useOperationsHelpers()
</script>
