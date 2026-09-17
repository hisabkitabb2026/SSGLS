<template>
  <BaseCard class="p-6 border border-line-default">
    <!-- Station Header -->
    <div class="flex items-center justify-between mb-4 pb-4 border-b border-line-light">
      <h3 class="text-lg font-semibold text-heading">
        🏙️ {{ station.name }}
      </h3>
      <BaseButton
        variant="gray"
        size="sm"
        @click="$emit('edit')"
      >
        ✎ {{ $t('general.edit') }}
      </BaseButton>
    </div>

    <!-- Rates Table -->
    <div v-if="station.rates && station.rates.length" class="mb-4">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-line-light">
            <th class="text-left py-2 font-semibold text-heading">{{ $t('quotations.capacity') }}</th>
            <th class="text-left py-2 font-semibold text-heading">{{ $t('quotations.rate') }}</th>
            <th class="text-right py-2 font-semibold text-heading">{{ $t('general.actions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="(rate, rateIndex) in station.rates"
            :key="rateIndex"
            class="border-b border-line-light hover:bg-hover"
          >
            <td class="py-3 text-body">{{ rate.capacity }}</td>
            <td class="py-3 text-body">₹{{ formatNumber(rate.rate) }}</td>
            <td class="py-3 text-right">
              <div class="flex items-center justify-end gap-2">
                <BaseButton
                  variant="gray"
                  size="xs"
                  :title="$t('general.edit')"
                  @click="$emit('edit-rate', rateIndex)"
                >
                  ✎
                </BaseButton>
                <BaseButton
                  variant="danger"
                  size="xs"
                  :title="$t('general.delete')"
                  @click="$emit('delete-rate', rateIndex)"
                >
                  🗑
                </BaseButton>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Empty State -->
    <div v-else class="mb-4 py-6 text-center">
      <p class="text-muted text-sm">{{ $t('quotations.no_rates') }}</p>
    </div>

    <!-- Add Rate Button -->
    <BaseButton
      variant="gray"
      class="w-full"
      @click="$emit('add-rate')"
    >
      + {{ $t('quotations.add_capacity_rate') }}
    </BaseButton>

    <!-- Delete Station Button -->
    <BaseButton
      variant="danger"
      class="w-full mt-3"
      @click="$emit('delete')"
    >
      {{ $t('general.delete') }} {{ $t('quotations.station') }}
    </BaseButton>
  </BaseCard>
</template>

<script setup>
defineProps({
  station: {
    type: Object,
    required: true,
  },
  index: {
    type: Number,
    required: true,
  },
})

defineEmits(['edit', 'delete', 'add-rate', 'edit-rate', 'delete-rate'])

function formatNumber(num) {
  return (num / 100).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}
</script>
