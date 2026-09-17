<!--
  OperationsTabBar

  Shared tab navigation bar for the three Warehouse Operations views:
  - In Warehouse (warehouse-items.index)
  - Consolidation (consolidation.index)
  - Dispatched (load-trips.index)

  Used by: WarehouseItemIndexView, LoadTripIndexView, ConsolidationBoardView,
  and ConsolidationDetailView.

  Props:
    - activeKey: The key of the currently active tab ('warehouse' | 'consolidation' | 'dispatched')

  Emits:
    - tab-click: Emitted with the tab key when a tab is clicked.
      If the consumer is using router navigation, they handle routing in the handler.
-->
<template>
  <div class="mb-6 flex gap-1 border-b border-line-default">
    <BaseButton
      v-for="tab in tabs"
      :key="tab.key"
      type="button"
      variant="white"
      class="whitespace-nowrap border-b-2 px-4 py-2 text-sm font-semibold transition !rounded-none !shadow-none"
      :class="
        tab.key === activeKey
          ? 'border-primary-500 text-primary-500'
          : 'border-transparent text-muted hover:text-body'
      "
      @click="$emit('tab-click', tab.key)"
    >
      {{ tab.label }}
      <span
        v-if="tab.count !== null && tab.count !== undefined"
        class="ml-1 rounded-full px-1.5 py-0.5 text-xs"
        :class="
          tab.key === activeKey
            ? 'bg-primary-500/10 text-primary-500'
            : 'bg-surface-tertiary text-muted'
        "
      >
        {{ tab.count }}
      </span>
    </BaseButton>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

interface TabItem {
  key: string
  label: string
  count?: number | null
}

const props = withDefaults(
  defineProps<{
    activeKey: string
    warehouseCount?: number | null
    consolidationCount?: number | null
    dispatchedCount?: number | null
  }>(),
  {
    warehouseCount: null,
    consolidationCount: null,
    dispatchedCount: null,
  }
)

defineEmits<{
  (e: 'tab-click', key: string): void
}>()

const tabs = computed<TabItem[]>(() => [
  {
    key: 'warehouse',
    label: 'In Warehouse',
    count: props.warehouseCount ?? null,
  },
  {
    key: 'consolidation',
    label: 'Consolidation',
    count: props.consolidationCount ?? null,
  },
  {
    key: 'dispatched',
    label: 'Dispatched',
    count: props.dispatchedCount ?? null,
  },
])
</script>
