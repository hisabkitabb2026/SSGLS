<template>
  <BaseModal :show="modalActive" @close="closeItemModal">
    <template #header>
      <div class="flex justify-between w-full">
        {{ modalStore.title }}
        <BaseIcon
          name="XMarkIcon"
          class="h-6 w-6 text-muted cursor-pointer"
          @click="closeItemModal"
        />
      </div>
    </template>
    <div class="item-modal">
      <form action="" @submit.prevent="submitItemData">
        <div class="px-8 py-8 sm:p-6">
          <BaseInputGrid layout="one-column">
            <BaseInputGroup
              :label="$t('items.name')"
              required
              :error="v$.name.$error && v$.name.$errors[0]?.$message"
            >
              <BaseInput
                v-model="itemName"
                type="text"
                :invalid="v$.name.$error"
                @input="v$.name.$touch()"
              />
            </BaseInputGroup>

            <BaseInputGroup :label="$t('items.price')">
              <BaseMoney
                :key="companyStore.selectedCompanyCurrency?.code"
                v-model="price"
                :currency="companyStore.selectedCompanyCurrency"
                class="
                  relative
                  w-full
                  focus:border focus:border-solid focus:border-primary
                "
              />
            </BaseInputGroup>

            <BaseInputGroup :label="$t('items.unit')">
              <BaseMultiselect
                v-model="itemUnitId"
                label="name"
                :options="itemStore.itemUnits"
                value-prop="id"
                :can-deselect="false"
                :can-clear="false"
                :placeholder="$t('items.select_a_unit')"
                searchable
                track-by="name"
              />
            </BaseInputGroup>

            <BaseInputGroup
              v-if="isTaxPerItemEnabled"
              :label="$t('items.taxes')"
            >
              <BaseMultiselect
                v-model="taxes"
                :options="getTaxTypes"
                mode="tags"
                label="tax_name"
                value-prop="id"
                class="w-full"
                :can-deselect="false"
                :can-clear="false"
                searchable
                track-by="tax_name"
                object
              />
            </BaseInputGroup>

            <BaseInputGroup
              :label="$t('items.description')"
              :error="
                v$.description.$error && v$.description.$errors[0]?.$message
              "
            >
              <BaseTextarea
                v-model="itemDescription"
                rows="4"
                cols="50"
                :invalid="v$.description.$error"
                @input="v$.description.$touch()"
              />
            </BaseInputGroup>
          </BaseInputGrid>
        </div>
        <div
          class="z-0 flex justify-end p-4 border-t border-line-default border-solid"
        >
          <BaseButton
            class="mr-3"
            variant="primary-outline"
            type="button"
            @click="closeItemModal"
          >
            {{ $t('general.cancel') }}
          </BaseButton>
          <BaseButton
            :loading="isLoading"
            :disabled="isLoading"
            variant="primary"
            type="submit"
          >
            <template #left="slotProps">
              <BaseIcon
                name="ArrowDownOnSquareIcon"
                :class="slotProps.class"
              />
            </template>
            {{
              itemStore.isEdit ? $t('general.update') : $t('general.save')
            }}
          </BaseButton>
        </div>
      </form>
    </div>
  </BaseModal>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useVuelidate } from '@vuelidate/core'
import {
  required,
  minLength,
  maxLength,
  helpers,
} from '@vuelidate/validators'
import { useModalStore } from '@/scripts/stores/modal.store'
import { useCompanyStore } from '@/scripts/stores/company.store'
import { useItemStore } from '../../items/store'
import { useNotificationStore } from '@/scripts/stores/notification.store'

// Types
interface Tax {
  id: number
  name: string
  tax_name?: string
  calculation_type: 'fixed' | 'percentage'
  percent?: number
  fixed_amount?: number
  [key: string]: unknown
}

interface Item {
  id?: number
  name: string
  price: number
  unit_id?: number
  description: string
  taxes: Tax[]
  [key: string]: unknown
}

interface ItemUnit {
  id: number
  name: string
}

// Stores
const modalStore = useModalStore()
const companyStore = useCompanyStore()
const itemStore = useItemStore()
const notificationStore = useNotificationStore()
const { t } = useI18n()

// State
const isLoading = ref(false)
const taxPerItemSetting = ref(
  companyStore.selectedCompanySettings?.tax_per_item === 'YES'
)

// Emit
const emit = defineEmits<{
  newItem: [item: Record<string, unknown>]
}>()

// Computed properties
const modalActive = computed(
  () => modalStore.active && modalStore.componentName === 'ItemModal'
)

const isTaxPerItemEnabled = computed(() => {
  return taxPerItemSetting.value
})

const itemName = computed({
  get: () => (itemStore.currentItem as Item).name || '',
  set: (value: string) => {
    ;(itemStore.currentItem as Item).name = value
  },
})

const itemUnitId = computed({
  get: () => (itemStore.currentItem as Item).unit_id,
  set: (value: number | null) => {
    ;(itemStore.currentItem as Item).unit_id = value || undefined
  },
})

const itemDescription = computed({
  get: () => (itemStore.currentItem as Item).description || '',
  set: (value: string) => {
    ;(itemStore.currentItem as Item).description = value
  },
})

const price = computed({
  get: () => ((itemStore.currentItem as Item).price || 0) / 100,
  set: (value: number | string) => {
    ;(itemStore.currentItem as Item).price = Math.round(Number(value) * 100)
  },
})

const taxes = computed({
  get: () =>
    ((itemStore.currentItem as Item).taxes || []).map((tax: Tax) => {
      if (tax) {
        const amount =
          tax.calculation_type === 'fixed'
            ? new Intl.NumberFormat(undefined, {
                style: 'currency',
                currency:
                  companyStore.selectedCompanyCurrency?.code || 'USD',
              }).format((tax.fixed_amount || 0) / 100)
            : `${tax.percent}%`

        return {
          ...tax,
          tax_type_id: tax.id,
          tax_name: `${tax.name} (${amount})`,
        }
      }
      return tax
    }),
  set: (value: Tax[]) => {
    ;(itemStore.currentItem as Item).taxes = value
  },
})

const getTaxTypes = computed(() => {
  // This would need to be implemented with a tax type store
  // Placeholder implementation
  return []
})

// Validation rules
const rules = computed(() => ({
  name: {
    required: helpers.withMessage(t('validation.required'), required),
    minLength: helpers.withMessage(
      t('validation.name_min_length', { count: 3 }),
      minLength(3)
    ),
  },
  description: {
    maxLength: helpers.withMessage(
      t('validation.description_maxlength', { count: 255 }),
      maxLength(255)
    ),
  },
}))

const v$ = useVuelidate(
  rules,
  computed(() => itemStore.currentItem as Item)
)

// Lifecycle
onMounted(() => {
  v$.value.$reset()
  itemStore.fetchItemUnits({ limit: 'all' })
})

// Functions
async function submitItemData(): Promise<void> {
  v$.value.$touch()

  if (v$.value.$invalid) {
    return
  }

  try {
    isLoading.value = true
    const currentItem = itemStore.currentItem as Item

    const data = {
      ...currentItem,
      taxes: (currentItem.taxes || []).map((tax: Tax) => {
        return {
          tax_type_id: tax.id,
          amount:
            tax.calculation_type === 'fixed'
              ? tax.fixed_amount
              : Math.round((price.value * (tax.percent || 0)) / 100),
          percent: tax.percent,
          fixed_amount: tax.fixed_amount,
          calculation_type: tax.calculation_type,
          name: tax.name,
          collective_tax: 0,
        }
      }),
    }

    const action = itemStore.isEdit
      ? itemStore.updateItem.bind(itemStore)
      : itemStore.addItem.bind(itemStore)

    const res = await action(data)

    isLoading.value = false

    if (res?.data?.data) {
      if (modalStore.refreshData) {
        modalStore.refreshData(res.data.data)
      }
      emit('newItem', res.data.data)
    }

    closeItemModal()
  } catch (error) {
    console.error('Error submitting item:', error)
    isLoading.value = false
    notificationStore.showNotification({
      type: 'error',
      message: 'items.error_saving',
    })
  }
}

function closeItemModal(): void {
  modalStore.closeModal()
  setTimeout(() => {
    itemStore.resetCurrentItem?.()
    modalStore.$reset()
    v$.value.$reset()
  }, 300)
}
</script>
