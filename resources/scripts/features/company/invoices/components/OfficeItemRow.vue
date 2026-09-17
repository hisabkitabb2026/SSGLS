<template>
  <tr class="border-b border-line-light hover:bg-hover transition-colors">
    <td class="px-4 py-3 text-sm text-muted w-12">
      {{ index + 1 }}
    </td>
    <td class="px-4 py-3 text-sm text-heading flex-1">
      <div class="font-medium">{{ item.name }}</div>
      <div v-if="item.description" class="text-xs text-muted mt-1">{{ item.description }}</div>
    </td>
    <td class="px-4 py-3 text-sm text-right w-24">
      {{ formatQuantity(item.quantity) }}
    </td>
    <td class="px-4 py-3 text-sm text-right w-28">
      {{ formatMoney(item.price) }}
    </td>
    <td class="px-4 py-3 text-sm font-medium text-right w-28 text-heading">
      {{ formatMoney(item.amount) }}
    </td>
    <td class="px-4 py-3 text-center w-16 space-x-2">
      <button
        class="text-primary-500 hover:text-primary-700 p-1 rounded hover:bg-surface"
        :title="$t('invoices.edit')"
        @click="$emit('edit', item)"
      >
        <BaseIcon name="PencilIcon" class="h-4 w-4" />
      </button>
      <button
        class="text-alert-error-text hover:text-alert-error-text p-1 rounded hover:bg-surface"
        :title="$t('invoices.delete')"
        @click="$emit('delete', item.id)"
      >
        <BaseIcon name="TrashIcon" class="h-4 w-4" />
      </button>
    </td>
  </tr>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { InvoiceItem } from '@/scripts/types/domain/invoice'

interface Props {
  item: InvoiceItem
  index: number
}

defineProps<Props>()

const emit = defineEmits<{
  edit: [item: InvoiceItem]
  delete: [itemId: number | string]
}>()

const { t } = useI18n()

const formatMoney = (value: number | string) => {
  const num = typeof value === 'string' ? parseFloat(value) : value
  return new Intl.NumberFormat('en-IN', {
    style: 'currency',
    currency: 'INR',
    minimumFractionDigits: 2,
  }).format(num)
}

const formatQuantity = (value: number | string) => {
  const num = typeof value === 'string' ? parseFloat(value) : value
  return num.toFixed(2)
}
</script>