<template>
  <div class="grid grid-cols-5 gap-4 mt-4 mb-2">
    <h6 class="col-span-5 text-base font-semibold text-left lg:col-span-1 text-heading">
      Broker Details
    </h6>

    <BaseInputGrid class="col-span-5 lg:col-span-4">
      <BaseInputGroup label="Broker Name">
        <BaseInput
          :model-value="modelValue.broker_name"
          type="text"
          @update:model-value="emit('update:modelValue', { ...modelValue, broker_name: $event })"
        />
      </BaseInputGroup>

      <BaseInputGroup label="Broker Address">
        <BaseTextarea
          :model-value="modelValue.broker_address"
          rows="3"
          @update:model-value="emit('update:modelValue', { ...modelValue, broker_address: $event })"
        />
      </BaseInputGroup>

      <BaseInputGroup label="PAN Number">
        <BaseInput
          :model-value="modelValue.broker_pan_no"
          type="text"
          @update:model-value="emit('update:modelValue', { ...modelValue, broker_pan_no: $event })"
        />
      </BaseInputGroup>

      <BaseInputGroup label="Phone Number">
        <BaseInput
          :model-value="modelValue.broker_phone_no"
          type="text"
          @update:model-value="emit('update:modelValue', { ...modelValue, broker_phone_no: $event })"
        />
      </BaseInputGroup>

      <BaseInputGroup label="Bank Account Number">
        <BaseInput
          :model-value="modelValue.broker_bank_account_no"
          type="text"
          @update:model-value="emit('update:modelValue', { ...modelValue, broker_bank_account_no: $event })"
        />
      </BaseInputGroup>
    </BaseInputGrid>
  </div>
</template>

<script setup lang="ts">
import type { LorryPartyProfile } from '@/scripts/types/domain/lorry-party-profile'

interface Props {
  modelValue: LorryPartyProfile
}

const props = defineProps<Props>()

const emit = defineEmits<{
  'update:modelValue': [value: Partial<LorryPartyProfile>]
}>()
</script>