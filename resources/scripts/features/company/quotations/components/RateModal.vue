<template>
  <BaseModal
    :show="true"
    :title="isEdit ? $t('general.edit') : $t('general.add_new', 1) + ' ' + $t('quotations.capacity_rate')"
    size="sm"
    @close="$emit('cancel')"
  >
    <div class="space-y-4">
      <div>
        <label class="block text-sm font-medium text-heading mb-2">
          {{ $t('quotations.station') }}: {{ station?.name }}
        </label>
        <p class="text-sm text-muted">{{ station?.name }}</p>
      </div>

      <div>
        <label class="block text-sm font-medium text-heading mb-2">
          {{ $t('quotations.capacity') }} <span class="text-alert-error-text">*</span>
        </label>

        <div v-if="!showAddCapacity" class="space-y-2">
          <BaseSelectInput
            v-model="formData.capacity"
            :options="capacityOptions"
            value-prop="value"
            label-prop="label"
            :placeholder="$t('general.select')"
          />

          <BaseButton
            variant="primary-outline"
            size="sm"
            class="w-full"
            @click="showAddCapacity = true"
          >
            + Add new capacity
          </BaseButton>
        </div>

        <div v-else class="space-y-2">
          <BaseInput
            v-model="newCapacity"
            type="text"
            placeholder="e.g., 35MT, 40MT"
          />

          <div class="flex gap-2">
            <BaseButton
              variant="primary"
              size="sm"
              class="flex-1"
              @click="() => { formData.capacity = newCapacity; showAddCapacity = false; newCapacity = ''; }"
            >
              Add
            </BaseButton>
            <BaseButton
              variant="white"
              size="sm"
              class="flex-1"
              @click="showAddCapacity = false"
            >
              {{ $t('general.cancel') }}
            </BaseButton>
          </div>
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-heading mb-2">
          {{ $t('quotations.rate') }} (₹) <span class="text-alert-error-text">*</span>
        </label>
        <BaseInput
          v-model.number="formData.rate"
          type="number"
          placeholder="e.g., 5000"
          min="0"
          step="100"
        />
      </div>
    </div>

    <template #footer>
      <div class="flex gap-3">
        <BaseButton variant="white" @click="$emit('cancel')">
          {{ $t('general.cancel') }}
        </BaseButton>
        <BaseButton variant="primary" @click="saveRate">
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
  rate: {
    type: Object,
    default: null,
  },
  station: {
    type: Object,
    default: null,
  },
})

const emit = defineEmits(['save', 'cancel'])

const formData = ref({ capacity: '', rate: '' })
const showAddCapacity = ref(false)
const newCapacity = ref('')

// Default capacity options
const defaultCapacities = [
  '5MT',
  '7MT',
  '9MT',
  '10MT',
  '12MT',
  '15MT',
  '20MT',
  '25MT',
  '30MT',
]

const capacityOptions = computed(() => {
  return defaultCapacities.map(c => ({ value: c, label: c }))
})

const isEdit = computed(() => !!props.rate?.id)

watch(
  () => props.rate,
  (rate) => {
    showAddCapacity.value = false
    newCapacity.value = ''
    if (rate) {
      formData.value = {
        capacity: rate.capacity,
        rate: typeof rate.rate === 'string' ? parseInt(rate.rate) : rate.rate,
      }
    }
  },
  { immediate: true }
)

function saveRate() {
  if (!formData.value.capacity.trim()) {
    dialogStore.openDialog({
      title: t('general.are_you_sure'),
      message: t('quotations.capacity') + ' is required',
      yesLabel: t('general.ok'),
      hideNoButton: true,
      variant: 'danger',
      size: 'sm',
    })
    return
  }

  if (!formData.value.rate || formData.value.rate <= 0) {
    dialogStore.openDialog({
      title: t('general.are_you_sure'),
      message: t('quotations.rate') + ' must be greater than 0',
      yesLabel: t('general.ok'),
      hideNoButton: true,
      variant: 'danger',
      size: 'sm',
    })
    return
  }

  emit('save', {
    capacity: formData.value.capacity,
    rate: Math.round(formData.value.rate * 100),
  })
}
</script>
