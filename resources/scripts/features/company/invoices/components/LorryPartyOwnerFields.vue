<template>
  <div class="grid grid-cols-5 gap-4 mt-4 mb-2">
    <h6 class="col-span-5 text-base font-semibold text-left lg:col-span-1 text-heading">
      Owner Details
    </h6>

    <BaseInputGrid class="col-span-5 lg:col-span-4">
      <BaseInputGroup label="Owner Code">
        <BaseInput
          :model-value="modelValue.owner_code"
          type="text"
          @update:model-value="emit('update:modelValue', { ...modelValue, owner_code: $event })"
        />
      </BaseInputGroup>

      <BaseInputGroup label="Owner Name">
        <BaseInput
          :model-value="modelValue.owner_name"
          type="text"
          @update:model-value="emit('update:modelValue', { ...modelValue, owner_name: $event })"
        />
      </BaseInputGroup>

      <BaseInputGroup label="Owner Address">
        <BaseTextarea
          :model-value="modelValue.owner_address"
          rows="3"
          @update:model-value="emit('update:modelValue', { ...modelValue, owner_address: $event })"
        />
      </BaseInputGroup>

      <BaseInputGroup label="Owner Phone">
        <BaseInput
          :model-value="modelValue.owner_phone"
          type="text"
          @update:model-value="emit('update:modelValue', { ...modelValue, owner_phone: $event })"
        />
      </BaseInputGroup>

      <BaseInputGroup label="Bank Account Number">
        <BaseInput
          :model-value="modelValue.owner_bank_account_no"
          type="text"
          @update:model-value="emit('update:modelValue', { ...modelValue, owner_bank_account_no: $event })"
        />
      </BaseInputGroup>

      <BaseInputGroup label="PAN Number">
        <BaseInput
          :model-value="modelValue.owner_pan_no"
          type="text"
          @update:model-value="emit('update:modelValue', { ...modelValue, owner_pan_no: $event })"
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