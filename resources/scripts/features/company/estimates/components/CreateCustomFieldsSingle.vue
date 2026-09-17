<template>
  <BaseInputGroup :label="field.label" :required="field.is_required">
    <!-- Placeholder for custom field types -->
    <BaseInput
      v-if="!isComplexField"
      :model-value="field.value"
      :placeholder="field.placeholder"
      type="text"
      @update:model-value="updateFieldValue"
    />
    <BaseTextarea
      v-else-if="field.type === 'TextArea'"
      :model-value="String(field.value || '')"
      :placeholder="field.placeholder"
      rows="4"
      cols="50"
      @update:model-value="updateFieldValue"
    />
  </BaseInputGroup>
</template>

<script setup lang="ts">
import { computed } from 'vue'

// Types
interface CustomField {
  id: number
  label: string
  type: string
  value?: unknown
  default_answer?: unknown
  options?: unknown
  is_required?: boolean
  placeholder?: string
  order?: number
}

interface StoreProp {
  customFields: CustomField[]
  [key: string]: unknown
}

interface Store {
  [key: string]: StoreProp
}

interface Props {
  store: Store
  storeProp: string
  index: number
  field: CustomField
  customFieldScope: string
}

// Define Props
defineProps<Props>()

// Computed
const isComplexField = computed(() => {
  return ['TextArea', 'DateTime', 'Select'].includes(
    (props.field?.type || '').toString()
  )
})

// Functions
function updateFieldValue(value: unknown): void {
  // Placeholder for field update logic
}
</script>
