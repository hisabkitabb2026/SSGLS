<script setup lang="ts">
/**
 * CollapsibleSection — Wrapper for settings sections.
 *
 * Provides:
 * - Collapsible header with title/description
 * - Dirty state indicator (● Dirty / Saved ✓ / Saving... / Error)
 * - Smooth expand/collapse animation
 * - Default slot for section content
 */
import { ref } from 'vue'

interface Props {
  title: string
  description?: string
  /** 'saved' | 'dirty' | 'saving' | 'error' */
  status?: 'saved' | 'dirty' | 'saving' | 'error'
  /** Start expanded? */
  defaultExpanded?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  description: '',
  status: 'saved',
  defaultExpanded: false,
})


const isExpanded = ref(props.defaultExpanded)

function toggle(): void {
  isExpanded.value = !isExpanded.value
}

const statusConfig: Record<string, { label: string; class: string }> = {
  saved: { label: '✓', class: 'text-status-green' },
  dirty: { label: '●', class: 'text-status-yellow' },
  saving: { label: '⟳', class: 'text-muted animate-spin' },
  error: { label: '⚠', class: 'text-alert-error-text' },
}
</script>

<template>
  <div class="border border-line-default rounded-lg overflow-hidden">
    <!-- Header -->
    <button
      type="button"
      class="w-full flex items-center justify-between px-5 py-4 hover:bg-hover transition-colors text-left"
      @click="toggle"
    >
      <div class="flex-1 min-w-0">
        <div class="flex items-center gap-2">
          <BaseIcon
            name="ChevronDownIcon"
            class="w-4 h-4 text-muted transition-transform duration-200"
            :class="{ '-rotate-90': !isExpanded }"
          />
          <h4 class="text-sm font-semibold text-heading truncate">
            {{ title }}
          </h4>
          <span
            v-if="status !== 'saved'"
            :class="['text-xs', statusConfig[status]?.class]"
            :title="status"
          >
            {{ statusConfig[status]?.label }}
          </span>
        </div>
        <p v-if="description && isExpanded" class="text-xs text-muted mt-1 ml-6">
          {{ description }}
        </p>
      </div>
    </button>

    <!-- Content -->
    <div
      v-show="isExpanded"
      class="px-5 pb-5 pt-2 border-t border-line-light"
    >
      <slot />
    </div>
  </div>
</template>
