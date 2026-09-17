<template>
  <div class="col-span-12 space-y-6">
    <!-- Transport Template Type Badge -->
    <div class="flex items-center gap-2 p-3 bg-surface-secondary rounded-md">
      <BaseIcon :name="getTemplateIcon()" class="h-5 w-5 text-primary-500" />
      <span class="text-sm font-medium text-heading">
        {{ getTemplateLabel() }}
      </span>
    </div>

    <!-- Basic Transport Info -->
    <div class="grid grid-cols-12 gap-4">
      <BaseInput
        v-if="isLorryReceipt || isOfficeInvoice"
        v-model="form.docket_no"
        :label="isOfficeInvoice ? $t('invoices.invoice_number') : $t('invoices.docket_no')"
        type="text"
        class="col-span-12 lg:col-span-4"
      />

      <BaseInput
        v-if="isLorryReceipt"
        v-model="form.vehicle_number"
        :label="$t('invoices.vehicle_number')"
        type="text"
        class="col-span-12 lg:col-span-4"
      />

      <BaseInput
        v-if="isLorryReceipt"
        v-model="form.from_city"
        :label="$t('invoices.from_city')"
        type="text"
        class="col-span-12 lg:col-span-4"
      />

      <BaseInput
        v-if="isLorryReceipt"
        v-model="form.to_city"
        :label="$t('invoices.to_city')"
        type="text"
        class="col-span-12 lg:col-span-4"
      />

      <BaseInput
        v-if="isLorryReceipt"
        v-model="form.driver_name"
        :label="$t('invoices.driver_name')"
        type="text"
        class="col-span-12 lg:col-span-4"
      />

      <BaseInput
        v-if="isLorryReceipt"
        v-model="form.driver_contact"
        :label="$t('invoices.driver_contact')"
        type="text"
        class="col-span-12 lg:col-span-4"
      />
    </div>

    <!-- Transport Charges (if applicable) -->
    <div v-if="isLorryReceipt" class="grid grid-cols-12 gap-4">
      <BaseMoney
        v-model="form.freight_charges"
        :label="$t('invoices.freight_charges')"
        class="col-span-12 lg:col-span-4"
      />

      <BaseMoney
        v-model="form.advance_amount"
        :label="$t('invoices.advance_amount')"
        class="col-span-12 lg:col-span-4"
      />

      <BaseMoney
        v-model="form.balance_amount"
        :label="$t('invoices.balance_amount')"
        class="col-span-12 lg:col-span-4"
      />
    </div>

    <!-- Additional Transport Info (collapsible) -->
    <Disclosure v-if="isLorryReceipt" as="div" class="border border-line-default rounded-md">
      <DisclosureButton class="flex w-full items-center justify-between px-4 py-3 hover:bg-hover">
        <span class="text-sm font-medium text-heading">{{ $t('invoices.additional_details') }}</span>
        <BaseIcon name="ChevronDownIcon" class="h-5 w-5" />
      </DisclosureButton>
      <DisclosurePanel class="px-4 py-3 border-t border-line-default space-y-4">
        <!-- Add more transport fields here as needed -->
        <BaseInput
          v-model="form.lorry_registration_no"
          :label="$t('invoices.lorry_registration_no')"
          type="text"
        />
      </DisclosurePanel>
    </Disclosure>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { Disclosure, DisclosureButton, DisclosurePanel } from '@headlessui/vue'
import type { TransportTemplateType } from '@/types/domain/transport'
import { getTransportTemplateLabel } from '@/types/domain/transport'

interface Props {
  templateType?: TransportTemplateType
  modelValue?: Record<string, any>
}

const props = withDefaults(defineProps<Props>(), {
  templateType: undefined,
  modelValue: () => ({})
})

const emit = defineEmits<{
  'update:modelValue': [value: Record<string, any>]
}>()

const { t } = useI18n()

const form = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
})

const isLorryReceipt = computed(() => props.templateType === 'lorry_receipt')
const isOfficeInvoice = computed(() => props.templateType === 'office_invoice')
const isLRReceipt = computed(() => props.templateType === 'lr_receipt')

const getTemplateIcon = () => {
  switch (props.templateType) {
    case 'lorry_receipt':
      return 'TruckIcon'
    case 'office_invoice':
      return 'DocumentIcon'
    case 'lr_receipt':
      return 'ClipboardListIcon'
    default:
      return 'DocumentIcon'
  }
}

const getTemplateLabel = () => {
  return getTransportTemplateLabel(props.templateType!)
}
</script>