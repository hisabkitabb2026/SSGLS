<template>
  <BaseModal
    :show="modalStore.active && modalStore.componentName === 'TaxTypeModal'"
    @close="closeTaxTypeModal"
  >
    <template #header>
      <div class="flex justify-between w-full">
        {{ modalStore.title }}
        <BaseIcon
          name="XMarkIcon"
          class="h-6 w-6 text-muted cursor-pointer"
          @click="closeTaxTypeModal"
        />
      </div>
    </template>
    <form action="" @submit.prevent="submitTaxTypeData">
      <div class="p-4 sm:p-6">
        <BaseInputGrid layout="one-column">
          <BaseInputGroup
            :label="$t('tax_types.name')"
            variant="horizontal"
            :error="
              v$.currentTaxType.name.$error &&
              v$.currentTaxType.name.$errors[0]?.$message
            "
            required
          >
            <BaseInput
              v-model="taxTypeName"
              :invalid="v$.currentTaxType.name.$error"
              type="text"
              @input="v$.currentTaxType.name.$touch()"
            />
          </BaseInputGroup>

          <BaseInputGroup
            :label="$t('tax_types.tax_type')"
            variant="horizontal"
            required
          >
            <BaseSelectInput
              v-model="calculationType"
              :options="[
                {
                  id: 'percentage',
                  label: $t('tax_types.percentage'),
                },
                { id: 'fixed', label: $t('tax_types.fixed_amount') },
              ]"
              :allow-empty="false"
              value-prop="id"
              label-prop="label"
              track-by="label"
              :searchable="false"
            />
          </BaseInputGroup>

          <BaseInputGroup
            v-if="calculationType === 'percentage'"
            :label="$t('tax_types.percent')"
            variant="horizontal"
            required
          >
            <BaseInput
              :model-value="taxTypePercent"
              type="number"
              step="0.001"
              min="-100"
              max="100"
              inline-addon="%"
              :invalid="v$.currentTaxType.percent.$error"
              @update:model-value="onTaxPercentInput"
              @blur="onTaxPercentBlur"
            />
          </BaseInputGroup>

          <BaseInputGroup
            v-else
            :label="$t('tax_types.fixed_amount')"
            variant="horizontal"
            required
          >
            <BaseMoney
              v-model="fixedAmount"
              :currency="companyStore.selectedCompanyCurrency"
            />
          </BaseInputGroup>

          <BaseInputGroup
            :label="$t('tax_types.description')"
            :error="
              v$.currentTaxType.description.$error &&
              v$.currentTaxType.description.$errors[0]?.$message
            "
            variant="horizontal"
          >
            <BaseTextarea
              v-model="taxTypeDescription"
              :invalid="v$.currentTaxType.description.$error"
              rows="4"
              cols="50"
              @input="v$.currentTaxType.description.$touch()"
            />
          </BaseInputGroup>
        </BaseInputGrid>
      </div>
      <div
        class="
          z-0
          flex
          justify-end
          p-4
          border-t border-solid border-line-default
        "
      >
        <BaseButton
          class="mr-3 text-sm"
          variant="primary-outline"
          type="button"
          @click="closeTaxTypeModal"
        >
          {{ $t('general.cancel') }}
        </BaseButton>
        <BaseButton
          :loading="isSaving"
          :disabled="isSaving"
          variant="primary"
          type="submit"
        >
          <template #left="slotProps">
            <BaseIcon
              v-if="!isSaving"
              name="ArrowDownOnSquareIcon"
              :class="slotProps.class"
            />
          </template>
          {{ isEdit ? $t('general.update') : $t('general.save') }}
        </BaseButton>
      </div>
    </form>
  </BaseModal>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useVuelidate } from '@vuelidate/core'
import {
  required,
  minLength,
  maxLength,
  between,
  helpers,
} from '@vuelidate/validators'
import { useModalStore } from '@/scripts/stores/modal.store'
import { useCompanyStore } from '@/scripts/stores/company.store'
import { useNotificationStore } from '@/scripts/stores/notification.store'

// Types
interface TaxType {
  id?: number
  name: string
  calculation_type: 'percentage' | 'fixed'
  percent?: number
  fixed_amount?: number
  description: string
  [key: string]: unknown
}

interface CurrentTaxType extends TaxType {}

interface Store {
  currentTaxType: CurrentTaxType
  isEdit: boolean
  updateTaxType?: (data: TaxType) => Promise<{ data: { data: TaxType } }>
  addTaxType?: (data: TaxType) => Promise<{ data: { data: TaxType } }>
  resetCurrentTaxType?: () => void
}

// Stores
const modalStore = useModalStore()
const companyStore = useCompanyStore()
const notificationStore = useNotificationStore()
const { t } = useI18n()

// State
const isSaving = ref(false)
const currentTaxTypeStore = ref<Store>({
  currentTaxType: {
    name: '',
    calculation_type: 'percentage',
    percent: 0,
    fixed_amount: 0,
    description: '',
  },
  isEdit: false,
})

// Computed properties
const isEdit = computed(() => currentTaxTypeStore.value.isEdit)

const taxTypeName = computed({
  get: () => currentTaxTypeStore.value.currentTaxType.name,
  set: (value: string) => {
    currentTaxTypeStore.value.currentTaxType.name = value
  },
})

const calculationType = computed({
  get: () => currentTaxTypeStore.value.currentTaxType.calculation_type,
  set: (value: 'percentage' | 'fixed') => {
    currentTaxTypeStore.value.currentTaxType.calculation_type = value
  },
})

const taxTypePercent = computed({
  get: () => currentTaxTypeStore.value.currentTaxType.percent || 0,
  set: (value: number) => {
    currentTaxTypeStore.value.currentTaxType.percent = value
  },
})

const fixedAmount = computed({
  get: () => ((currentTaxTypeStore.value.currentTaxType.fixed_amount || 0) / 100),
  set: (value: number) => {
    currentTaxTypeStore.value.currentTaxType.fixed_amount = Math.round(
      value * 100
    )
  },
})

const taxTypeDescription = computed({
  get: () => currentTaxTypeStore.value.currentTaxType.description || '',
  set: (value: string) => {
    currentTaxTypeStore.value.currentTaxType.description = value
  },
})

// Validation rules
const rules = computed(() => ({
  currentTaxType: {
    name: {
      required: helpers.withMessage(t('validation.required'), required),
      minLength: helpers.withMessage(
        t('validation.name_min_length', { count: 3 }),
        minLength(3)
      ),
    },
    calculation_type: {
      required: helpers.withMessage(t('validation.required'), required),
    },
    percent: {
      required: helpers.withMessage(t('validation.required'), required),
      between: helpers.withMessage(
        t('validation.enter_valid_tax_rate'),
        between(-100, 100)
      ),
    },
    fixed_amount: {
      required: helpers.withMessage(t('validation.required'), required),
    },
    description: {
      maxLength: helpers.withMessage(
        t('validation.description_maxlength', { count: 255 }),
        maxLength(255)
      ),
    },
  },
}))

const v$ = useVuelidate(rules, currentTaxTypeStore)

// Functions
function onTaxPercentInput(val: number | string | null): void {
  v$.value.currentTaxType.percent.$touch()

  if (val === '' || val === null) {
    currentTaxTypeStore.value.currentTaxType.percent = null
    return
  }

  const n = typeof val === 'number' ? val : parseFloat(String(val))
  currentTaxTypeStore.value.currentTaxType.percent = Number.isNaN(n) ? null : n
}

function onTaxPercentBlur(): void {
  const p = currentTaxTypeStore.value.currentTaxType.percent
  if (p === null || p === undefined || p === '') {
    return
  }

  const n = typeof p === 'number' ? p : parseFloat(String(p))
  if (Number.isNaN(n)) {
    return
  }

  currentTaxTypeStore.value.currentTaxType.percent = Math.round(n * 1000) / 1000
}

async function submitTaxTypeData(): Promise<void> {
  if (calculationType.value === 'percentage') {
    onTaxPercentBlur()
  }

  v$.value.currentTaxType.$touch()
  if (v$.value.currentTaxType.$invalid) {
    return
  }

  try {
    isSaving.value = true
    const action = isEdit.value
      ? currentTaxTypeStore.value.updateTaxType
      : currentTaxTypeStore.value.addTaxType

    if (!action) {
      throw new Error('Action not available')
    }

    const res = await action(currentTaxTypeStore.value.currentTaxType as TaxType)

    isSaving.value = false

    if (modalStore.refreshData) {
      modalStore.refreshData(res.data.data)
    }

    notificationStore.showNotification({
      type: 'success',
      message: isEdit.value
        ? 'tax_types.updated_successfully'
        : 'tax_types.created_successfully',
    })

    closeTaxTypeModal()
  } catch (err) {
    console.error('Error saving tax type:', err)
    isSaving.value = false
    notificationStore.showNotification({
      type: 'error',
      message: 'tax_types.error_saving',
    })
  }
}

function closeTaxTypeModal(): void {
  modalStore.closeModal()
  setTimeout(() => {
    currentTaxTypeStore.value.resetCurrentTaxType?.()
    v$.value.$reset()
  }, 300)
}
</script>
