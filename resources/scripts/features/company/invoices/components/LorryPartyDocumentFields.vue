<template>
  <div class="grid grid-cols-5 gap-4 mt-4 mb-2">
    <h6 class="col-span-5 text-base font-semibold text-left lg:col-span-1 text-heading">
      Documents
    </h6>

    <BaseInputGrid class="col-span-5 lg:col-span-4">
      <BaseInputGroup label="License Number">
        <BaseInput
          :model-value="modelValue.licence_number"
          type="text"
          @update:model-value="emit('update:modelValue', { ...modelValue, licence_number: $event })"
        />
      </BaseInputGroup>

      <BaseInputGroup label="License Date">
        <BaseInput
          :model-value="modelValue.licence_date"
          type="date"
          @update:model-value="emit('update:modelValue', { ...modelValue, licence_date: $event })"
        />
      </BaseInputGroup>

      <BaseInputGroup label="License Issued By">
        <BaseInput
          :model-value="modelValue.licence_issued_by"
          type="text"
          @update:model-value="emit('update:modelValue', { ...modelValue, licence_issued_by: $event })"
        />
      </BaseInputGroup>

      <BaseInputGroup label="RTO Address">
        <BaseInput
          :model-value="modelValue.rto_address"
          type="text"
          @update:model-value="emit('update:modelValue', { ...modelValue, rto_address: $event })"
        />
      </BaseInputGroup>

      <BaseInputGroup label="Valid Up To">
        <BaseInput
          :model-value="modelValue.valid_up_to"
          type="date"
          @update:model-value="emit('update:modelValue', { ...modelValue, valid_up_to: $event })"
        />
      </BaseInputGroup>

      <BaseInputGroup label="Pan Number">
        <BaseInput
          :model-value="modelValue.pan_number"
          type="text"
          @update:model-value="emit('update:modelValue', { ...modelValue, pan_number: $event })"
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