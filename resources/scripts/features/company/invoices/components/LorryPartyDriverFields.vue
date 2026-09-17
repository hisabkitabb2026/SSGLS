<template>
  <div class="grid grid-cols-5 gap-4 mt-4 mb-2">
    <h6 class="col-span-5 text-base font-semibold text-left lg:col-span-1 text-heading">
      Driver Details
    </h6>

    <BaseInputGrid class="col-span-5 lg:col-span-4">
      <BaseInputGroup label="Driver Name">
        <BaseInput
          :model-value="modelValue.driver_name"
          type="text"
          @update:model-value="emit('update:modelValue', { ...modelValue, driver_name: $event })"
        />
      </BaseInputGroup>

      <BaseInputGroup label="Driver Address">
        <BaseTextarea
          :model-value="modelValue.driver_address"
          rows="3"
          @update:model-value="emit('update:modelValue', { ...modelValue, driver_address: $event })"
        />
      </BaseInputGroup>

      <BaseInputGroup label="License Number">
        <BaseInput
          :model-value="modelValue.driver_licence_no"
          type="text"
          @update:model-value="emit('update:modelValue', { ...modelValue, driver_licence_no: $event })"
        />
      </BaseInputGroup>

      <BaseInputGroup label="License Date">
        <BaseInput
          :model-value="modelValue.driver_licence_date"
          type="date"
          @update:model-value="emit('update:modelValue', { ...modelValue, driver_licence_date: $event })"
        />
      </BaseInputGroup>

      <BaseInputGroup label="RTO Address">
        <BaseInput
          :model-value="modelValue.driver_rto_address"
          type="text"
          @update:model-value="emit('update:modelValue', { ...modelValue, driver_rto_address: $event })"
        />
      </BaseInputGroup>

      <BaseInputGroup label="Valid Up To">
        <BaseInput
          :model-value="modelValue.driver_valid_up_to"
          type="date"
          @update:model-value="emit('update:modelValue', { ...modelValue, driver_valid_up_to: $event })"
        />
      </BaseInputGroup>

      <BaseInputGroup label="Bank Account Number">
        <BaseInput
          :model-value="modelValue.driver_bank_account_no"
          type="text"
          @update:model-value="emit('update:modelValue', { ...modelValue, driver_bank_account_no: $event })"
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