<template>
  <div class="mb-6">
    <div class="z-20 text-sm font-semibold leading-5 text-primary-400 float-right">
      <SelectNotePopup :type="type" @select="onSelectNote" />
    </div>
    <label class="text-heading font-medium mb-4 text-sm">
      {{ $t('invoices.notes') }}
    </label>
    <BaseCustomInput
      v-model="store[storeProp].notes"
      :content-loading="store.isFetchingInitialSettings"
      :fields="fields"
      class="mt-1"
    />
  </div>
</template>

<script setup lang="ts">
import SelectNotePopup from './SelectNotePopup.vue'

interface Props {
  store: Record<string, unknown> | null
  storeProp: string
  fields: Record<string, unknown> | null
  type: string
}

const props = withDefaults(defineProps<Props>(), {
  store: null,
  storeProp: '',
  fields: null,
  type: '',
})

function onSelectNote(data: Record<string, unknown>): void {
  if (props.store && props.storeProp) {
    (props.store[props.storeProp] as Record<string, unknown>).notes = String(data.notes || '')
  }
}
</script>
