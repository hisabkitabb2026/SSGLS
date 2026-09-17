<template>
  <BaseModal
    :title="isEdit ? $t('general.edit') : $t('general.add_new', 1) + ' ' + $t('quotations.station')"
    size="sm"
    @close="$emit('cancel')"
  >
    <div class="space-y-4">
      <div>
        <label class="block text-sm font-medium text-heading mb-2">
          {{ $t('quotations.station_name') }}
        </label>
        <BaseInput
          v-model="formData.name"
          type="text"
          placeholder="e.g., Mumbai, Delhi, Bangalore"
        />
      </div>
    </div>

    <template #footer>
      <div class="flex gap-3">
        <BaseButton variant="white" @click="$emit('cancel')">
          {{ $t('general.cancel') }}
        </BaseButton>
        <BaseButton variant="primary" @click="saveStation">
          {{ $t('general.save') }}
        </BaseButton>
      </div>
    </template>
  </BaseModal>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { useDialogStore } from '@/scripts/stores/dialog.store'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const dialogStore = useDialogStore()

const props = defineProps({
  station: {
    type: Object,
    default: null,
  },
})

const emit = defineEmits(['save', 'cancel'])

const formData = ref({ name: '', rates: [] })

const isEdit = computed(() => !!props.station?.id)

watch(
  () => props.station,
  (station) => {
    if (station) {
      formData.value = JSON.parse(JSON.stringify(station))
    }
  },
  { immediate: true }
)

function saveStation() {
  if (!formData.value.name.trim()) {
    dialogStore.openDialog({
      title: t('general.are_you_sure'),
      message: t('quotations.station_name') + ' is required',
      yesLabel: t('general.ok'),
      hideNoButton: true,
      variant: 'danger',
      size: 'sm',
    })
    return
  }

  emit('save', formData.value)
}
</script>
