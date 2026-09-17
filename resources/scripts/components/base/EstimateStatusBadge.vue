<script setup lang="ts">
import { computed } from 'vue'
import { EstimateStatus } from '@/scripts/types/domain'

interface Props {
  status?: EstimateStatus | string
}

const props = withDefaults(defineProps<Props>(), {
  status: '',
})

const baseClasses = 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium'

const badgeColorClasses = computed<string>(() => {
  switch (props.status) {
    case EstimateStatus.DRAFT:
    case 'DRAFT':
      return `${baseClasses} bg-surface-tertiary text-muted ring-1 ring-inset ring-line-default/50`
    case EstimateStatus.SENT:
    case 'SENT':
      return `${baseClasses} bg-status-blue/10 text-status-blue ring-1 ring-inset ring-status-blue/20`
    case EstimateStatus.VIEWED:
    case 'VIEWED':
      return `${baseClasses} bg-status-purple/10 text-status-purple ring-1 ring-inset ring-status-purple/20`
    case EstimateStatus.EXPIRED:
    case 'EXPIRED':
      return `${baseClasses} bg-status-red/10 text-status-red ring-1 ring-inset ring-status-red/20`
    case EstimateStatus.ACCEPTED:
    case 'ACCEPTED':
      return `${baseClasses} bg-status-green/10 text-status-green ring-1 ring-inset ring-status-green/20`
    case EstimateStatus.REJECTED:
    case 'REJECTED':
      return `${baseClasses} bg-status-red/10 text-status-red ring-1 ring-inset ring-status-red/20`
    default:
      return `${baseClasses} bg-surface-secondary text-muted ring-1 ring-inset ring-line-default`
  }
})
</script>

<template>
  <span :class="badgeColorClasses">
    <slot />
  </span>
</template>
