<template>
  <div
    v-if="
      store[storeProp] && store[storeProp].customFields.length > 0 && !isLoading
    "
  >
    <BaseInputGrid :layout="gridLayout">
      <QuotationCreateCustomFieldsSingle
        v-for="(field, index) in store[storeProp].customFields"
        :key="field.id"
        :custom-field-scope="customFieldScope"
        :store="store"
        :store-prop="storeProp"
        :index="index"
        :field="field"
      />
    </BaseInputGrid>
  </div>
</template>

<script setup lang="ts">
import { watch } from 'vue'
import QuotationCreateCustomFieldsSingle from './QuotationCreateCustomFieldsSingle.vue'

// Types
interface CustomField {
  id: number
  label: string
  type: string
  value?: string | unknown
  default_answer?: string | unknown
  options?: unknown
  is_required?: boolean
  placeholder?: string
  order?: number
  custom_field?: Record<string, unknown>
  custom_field_id?: number
  [key: string]: unknown
}

interface FieldData {
  custom_field_id: number
  default_answer: string
  custom_field: {
    label: string
    type: string
    options: unknown
    is_required: boolean
    placeholder: string
    order: number
  }
}

interface StoreProp {
  customFields: CustomField[]
  fields: FieldData[]
  [key: string]: unknown
}

interface Store {
  [key: string]: StoreProp
}

interface Props {
  store: Store
  storeProp: string
  isEdit?: boolean
  type?: string | null
  gridLayout?: string
  isLoading?: boolean
  customFieldScope: string
}

// Define Props
const props = withDefaults(defineProps<Props>(), {
  isEdit: false,
  type: null,
  gridLayout: 'two-column',
  isLoading: false,
})

// Functions
function mergeExistingValues(): void {
  if (props.isEdit && props.store[props.storeProp].fields) {
    props.store[props.storeProp].fields.forEach((field: FieldData) => {
      const existingIndex = props.store[props.storeProp].customFields.findIndex(
        (f: CustomField) => f.id === field.custom_field_id
      )

      if (existingIndex > -1) {
        let value = field.default_answer

        if (value && field.custom_field.type === 'DateTime') {
          // Format datetime value
          const dateObj = new Date(value as string)
          value = dateObj
            .toISOString()
            .slice(0, 16) // YYYY-MM-DD HH:mm format
        }

        props.store[props.storeProp].customFields[existingIndex] = {
          ...field,
          id: field.custom_field_id,
          value: value,
          label: field.custom_field.label,
          options: field.custom_field.options,
          is_required: field.custom_field.is_required,
          placeholder: field.custom_field.placeholder,
          order: field.custom_field.order,
        }
      }
    })
  }
}

async function getInitialCustomFields(): Promise<void> {
  // Note: This would need to be called from a service or store
  // Placeholder implementation
  const data: CustomField[] = []

  data.forEach((d: CustomField) => {
    d.value = d.default_answer
  })

  // Sort by order
  props.store[props.storeProp].customFields = data.sort(
    (a: CustomField, b: CustomField) => (a.order || 0) - (b.order || 0)
  )

  mergeExistingValues()
}

// Initialize on component load
getInitialCustomFields()

// Watchers
watch(
  () => props.store[props.storeProp]?.fields,
  () => {
    mergeExistingValues()
  }
)
</script>
