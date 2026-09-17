<script setup lang="ts">
import { computed } from 'vue'
import { InvoiceStatus, InvoicePaidStatus } from '@/scripts/types/domain'

type InvoiceBadgeStatus =
  | InvoiceStatus
  | InvoicePaidStatus
  | 'DUE'
  | 'OVERDUE'

interface Props {
  status?: InvoiceBadgeStatus | string
}

const props = withDefaults(defineProps<Props>(), {
  status: '',
})

const baseClasses = 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium'

const badgeColorClasses = computed<string>(() => {
  switch (props.status) {
    case InvoiceStatus.DRAFT:
    case 'DRAFT':
      return `${baseClasses} bg-surface-tertiary text-muted ring-1 ring-inset ring-line-default/50`
    case InvoiceStatus.SENT:
    case 'SENT':
      return `${baseClasses} bg-status-blue/10 text-status-blue ring-1 ring-inset ring-status-blue/20`
    case InvoiceStatus.VIEWED:
    case 'VIEWED':
      return `${baseClasses} bg-status-purple/10 text-status-purple ring-1 ring-inset ring-status-purple/20`
    case InvoiceStatus.COMPLETED:
    case 'COMPLETED':
      return `${baseClasses} bg-status-green/10 text-status-green ring-1 ring-inset ring-status-green/20`
    case 'DUE':
      return `${baseClasses} bg-status-yellow/10 text-status-yellow ring-1 ring-inset ring-status-yellow/20`
    case 'OVERDUE':
      return `${baseClasses} bg-status-red/10 text-status-red ring-1 ring-inset ring-status-red/20`
    case InvoicePaidStatus.UNPAID:
    case 'UNPAID':
      return `${baseClasses} bg-status-yellow/10 text-status-yellow ring-1 ring-inset ring-status-yellow/20`
    case InvoicePaidStatus.PARTIALLY_PAID:
    case 'PARTIALLY_PAID':
      return `${baseClasses} bg-status-blue/10 text-status-blue ring-1 ring-inset ring-status-blue/20`
    case InvoicePaidStatus.PAID:
    case 'PAID':
      return `${baseClasses} bg-status-green/10 text-status-green ring-1 ring-inset ring-status-green/20`
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
