<template>
  <div class="bg-surface-secondary p-4 rounded-lg space-y-4 border border-line-default">
    <div class="flex items-center justify-between">
      <h4 class="text-sm font-semibold text-heading">
        {{ isEdit ? $t('invoices.edit_item') : $t('invoices.add_item') }}
      </h4>
      <button
        class="text-muted hover:text-heading"
        @click="$emit('cancel')"
      >
        <BaseIcon name="XMarkIcon" class="h-5 w-5" />
      </button>
    </div>

    <div class="grid grid-cols-12 gap-4">
      <BaseInputGroup class="col-span-12 lg:col-span-6" :label="$t('invoices.item_name')" required>
        <BaseInput
          v-model="form.name"
          type="text"
          :placeholder="$t('invoices.enter_item_name')"
        />
      </BaseInputGroup>

      <BaseInputGroup class="col-span-12 lg:col-span-6" :label="$t('invoices.quantity')">
        <BaseInput
          v-model.number="form.quantity"
          type="number"
          step="0.01"
          :placeholder="$t('invoices.enter_quantity')"
        />
      </BaseInputGroup>

      <BaseInputGroup class="col-span-12 lg:col-span-6" :label="$t('invoices.price')">
        <BaseMoney
          v-model.number="form.price"
          :placeholder="$t('invoices.enter_price')"
        />
      </BaseInputGroup>

      <BaseInputGroup class="col-span-12 lg:col-span-6" :label="$t('invoices.amount')">
        <BaseMoney
          :model-value="calculateAmount()"
          disabled
        />
      </BaseInputGroup>

      <BaseInputGroup class="col-span-12" :label="$t('invoices.description')">
        <BaseTextarea
          v-model="form.description"
          rows="2"
          :placeholder="$t('invoices.enter_description')"
        />
      </BaseInputGroup>
    </div>

    <div class="flex gap-2 justify-end pt-2">
      <BaseButton
        variant="secondary"
        @click="$emit('cancel')"
      >
        {{ $t('invoices.cancel') }}
      </BaseButton>
      <BaseButton
        :disabled="!isValid"
        @click="save"
      >
        {{ $t('invoices.save_item') }}
      </BaseButton>
    </div>
  </div>
</template>

<script setup lang="ts">
import { reactive, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { InvoiceItem } from '@/scripts/types/domain/invoice'

interface Props {
  item?: InvoiceItem
}

const props = defineProps<Props>()

const emit = defineEmits<{
  save: [item: Partial<InvoiceItem>]
  cancel: []
}>()

const { t } = useI18n()

const form = reactive({
  name: props.item?.name || '',
  quantity: props.item?.quantity || 1,
  price: props.item?.price || 0,
  description: props.item?.description || '',
})

const isEdit = computed(() => !!props.item)

const isValid = computed(() => {
  return form.name.trim() && form.quantity > 0 && form.price >= 0
})

const calculateAmount = () => {
  return (form.quantity || 0) * (form.price || 0)
}

const save = () => {
  if (isValid.value) {
    emit('save', {
      ...form,
      amount: calculateAmount(),
    })
  }
}
</script>