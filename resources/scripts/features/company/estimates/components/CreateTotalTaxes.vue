<template>
  <div class="flex items-center justify-between w-full mt-2 text-sm">
    <label v-if="tax.calculation_type === 'percentage'" class="font-semibold leading-5 text-muted uppercase">
      {{ tax.name }} ({{ tax.percent }} %)
    </label>
    <label v-else class="font-semibold leading-5 text-muted uppercase">
      {{ tax.name }} (<BaseFormatMoney :amount="tax.fixed_amount" :currency="currency" />)
    </label>
    <label class="flex items-center justify-center text-lg text-heading">
      <BaseFormatMoney :amount="tax.amount" :currency="currency" />

      <BaseIcon
        name="TrashIcon"
        class="h-5 ml-2 cursor-pointer"
        @click="emit('remove', tax.id)"
      />
    </label>
  </div>
</template>

<script setup lang="ts">
import { computed, watch, watchEffect } from 'vue'
import type { DocumentTax } from '../../../shared/document-form/use-document-calculations'

interface Tax extends DocumentTax {
  calculation_type?: string | null
  percent?: number | null
  fixed_amount?: number
  compound_tax?: boolean
  amount?: number | null
}

interface Store {
  getSubtotalWithDiscount: number
  getTotalSimpleTax: number
  [key: string]: any
}

interface Props {
  index: number
  tax: Tax
  taxes: Tax[]
  currency: Record<string, any> | string
  store: Store | null
  storeProp: string
  data?: string
}

const props = withDefaults(defineProps<Props>(), {
  storeProp: '',
  data: '',
})

const emit = defineEmits<{
  remove: [taxId: number | string]
  update: [tax: Tax]
}>()

const taxAmount = computed(() => {
  if (props.tax.calculation_type === 'fixed') {
    return props.tax.fixed_amount || 0
  }

  if (props.tax.compound_tax && props.store?.getSubtotalWithDiscount) {
    return Math.round(
      ((props.store.getSubtotalWithDiscount + props.store.getTotalSimpleTax) * (props.tax.percent || 0)) / 100
    )
  }

  if (props.store?.getSubtotalWithDiscount && props.tax.percent && props.store[props.storeProp]?.tax_included) {
    return Math.round(
      props.store.getSubtotalWithDiscount - (props.store.getSubtotalWithDiscount / (1 + (props.tax.percent / 100)))
    )
  }

  if (props.store?.getSubtotalWithDiscount && props.tax.percent) {
    return Math.round((props.store.getSubtotalWithDiscount * props.tax.percent) / 100)
  }

  return 0
})

watchEffect(() => {
  if (props.store?.getSubtotalWithDiscount) {
    updateTax()
  }
  if (props.store?.getTotalSimpleTax) {
    updateTax()
  }
})

watch(
  () => props.store?.[props.storeProp]?.tax_included,
  () => {
    updateTax()
  },
  { deep: true }
)

function updateTax() {
  emit('update', {
    ...props.tax,
    amount: taxAmount.value,
  })
}
</script>
