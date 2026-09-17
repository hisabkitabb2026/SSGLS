<template>
  <div class="space-y-4">
    <!-- Table Container -->
    <div class="border border-line-default rounded-lg overflow-hidden">
      <table class="w-full">
        <OfficeItemTableHeader />
        <tbody v-if="items.length > 0 && !showForm">
          <OfficeItemRow
            v-for="(item, index) in items"
            :key="item.id"
            :item="item"
            :index="index"
            @edit="editItem"
            @delete="deleteItem"
          />
        </tbody>
        <tbody v-else-if="items.length === 0 && !showForm">
          <tr>
            <td :colspan="6" class="px-4 py-8 text-center text-muted">
              {{ $t('invoices.no_items') }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Add/Edit Form -->
    <OfficeItemForm
      v-if="showForm"
      :item="editingItem"
      @save="saveItem"
      @cancel="closeForm"
    />

    <!-- Add Button -->
    <BaseButton
      v-if="!showForm"
      variant="secondary"
      @click="openForm"
    >
      <BaseIcon name="PlusIcon" class="h-4 w-4 mr-2" />
      {{ $t('invoices.add_item') }}
    </BaseButton>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { InvoiceItem } from '@/scripts/types/domain/invoice'
import OfficeItemTableHeader from './OfficeItemTableHeader.vue'
import OfficeItemRow from './OfficeItemRow.vue'
import OfficeItemForm from './OfficeItemForm.vue'

interface Props {
  modelValue: InvoiceItem[]
}

const props = defineProps<Props>()

const emit = defineEmits<{
  'update:modelValue': [items: InvoiceItem[]]
}>()

const { t } = useI18n()

const showForm = ref(false)
const editingItem = ref<InvoiceItem | undefined>()

const items = computed({
  get: () => props.modelValue || [],
  set: (value) => emit('update:modelValue', value),
})

const openForm = () => {
  editingItem.value = undefined
  showForm.value = true
}

const editItem = (item: InvoiceItem) => {
  editingItem.value = item
  showForm.value = true
}

const closeForm = () => {
  showForm.value = false
  editingItem.value = undefined
}

const saveItem = (itemData: Partial<InvoiceItem>) => {
  if (editingItem.value) {
    const index = items.value.findIndex(i => i.id === editingItem.value!.id)
    if (index !== -1) {
      const newItems = [...items.value]
      newItems[index] = { ...newItems[index], ...itemData }
      items.value = newItems
    }
  } else {
    const newItem: InvoiceItem = {
      id: `temp-${Date.now()}`,
      ...itemData,
    } as InvoiceItem
    items.value = [...items.value, newItem]
  }
  closeForm()
}

const deleteItem = (itemId: number | string) => {
  items.value = items.value.filter(i => i.id !== itemId)
}
</script>