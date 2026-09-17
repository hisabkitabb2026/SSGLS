<template>
  <div class="grid grid-cols-5 gap-4 mt-4 mb-2">
    <h6 class="col-span-5 text-base font-semibold text-left lg:col-span-1 text-heading">
      Basic Info
    </h6>

    <BaseInputGrid class="col-span-5 lg:col-span-4">
      <BaseInputGroup
        :label="`${singularTitle} Name`"
        required
        :error="v?.name?.$error && v?.name?.$errors?.[0]?.$message"
      >
        <BaseInput
          :model-value="modelValue.name"
          type="text"
          :invalid="v?.name?.$error"
          @update:model-value="emit('update:modelValue', { ...modelValue, name: $event })"
          @input="v?.name?.$touch?.()"
        />
      </BaseInputGroup>

      <BaseInputGroup label="Phone No.">
        <BaseInput
          :model-value="modelValue.phone"
          type="text"
          @update:model-value="emit('update:modelValue', { ...modelValue, phone: $event })"
        />
      </BaseInputGroup>

      <BaseInputGroup label="Full Address">
        <BaseTextarea
          :model-value="modelValue.address"
          rows="3"
          @update:model-value="emit('update:modelValue', { ...modelValue, address: $event })"
        />
      </BaseInputGroup>
    </BaseInputGrid>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { LorryPartyProfile } from '@/scripts/types/domain/lorry-party-profile'

interface Props {
  modelValue: LorryPartyProfile
  v?: any
}

const props = defineProps<Props>()

const emit = defineEmits<{
  'update:modelValue': [value: Partial<LorryPartyProfile>]
}>()

const singularTitle = computed<string>(() => {
  if (props.modelValue.type === 'OWNER') return 'Owner'
  if (props.modelValue.type === 'DRIVER') return 'Driver'
  if (props.modelValue.type === 'BROKER') return 'Broker'
  return 'Party'
})
</script>