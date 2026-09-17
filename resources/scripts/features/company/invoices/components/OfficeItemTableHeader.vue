<template>
  <thead class="bg-surface-secondary border-b border-line-default">
    <tr>
      <th class="px-4 py-3 text-left text-xs font-semibold text-heading w-12">
        #
      </th>
      <th class="px-4 py-3 text-left text-xs font-semibold text-heading flex-1">
        {{ $t('invoices.item_name') }}
      </th>
      <th class="px-4 py-3 text-right text-xs font-semibold text-heading w-24">
        {{ $t('invoices.quantity') }}
      </th>
      <th class="px-4 py-3 text-right text-xs font-semibold text-heading w-28">
        {{ $t('invoices.price') }}
      </th>
      <th class="px-4 py-3 text-right text-xs font-semibold text-heading w-28">
        {{ $t('invoices.amount') }}
      </th>
      <th class="px-4 py-3 text-center text-xs font-semibold text-heading w-16">
        {{ $t('invoices.actions') }}
      </th>
    </tr>
  </thead>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n'

defineProps<{
  // No props needed for header
}>()

const { t } = useI18n()
</script>