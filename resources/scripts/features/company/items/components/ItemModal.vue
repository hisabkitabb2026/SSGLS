<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useModalStore } from '../../../../stores/modal.store'
import { useCompanyStore } from '../../../../stores/company.store'
import { useUserStore } from '../../../../stores/user.store'
import { useItemStore } from '../store'
import { useTaxTypes } from '../use-tax-types'
import { useNotificationStore } from '../../../../stores/notification.store'
import {
  handleApiError,
  getErrorTranslationKey,
} from '../../../../utils/error-handling'
import type { TaxType } from '@/scripts/types/domain/tax'

interface TaxOption {
  id: number
  name: string
  percent: number
  fixed_amount: number
  calculation_type: string | null
  tax_name: string
}

interface ItemFormState {
  name: string
  description: string
  price: number
  unit_id: string | number | null
  truck_type: string
  taxes: TaxOption[]
  rate_card: Record<string, number>  // { "unitId": rateInCents }
}

const ABILITIES = {
  VIEW_TAX_TYPE: 'view-tax-type',
} as const

interface Emits {
  (e: 'newItem', item: unknown): void
}

const emit = defineEmits<Emits>()

const modalStore = useModalStore()
const itemStore = useItemStore()
const companyStore = useCompanyStore()
const userStore = useUserStore()
const notificationStore = useNotificationStore()
const { taxTypes, fetchTaxTypes } = useTaxTypes()

const { t } = useI18n()
const isLoading = ref<boolean>(false)
const isAddingUnit = ref<boolean>(false)
const triedSubmit = ref<boolean>(false)
const taxPerItemSetting = ref<string>(
  companyStore.selectedCompanySettings.tax_per_item || 'NO'
)

const modalActive = computed<boolean>(
  () => modalStore.active && modalStore.componentName === 'ItemModal'
)

// Local form state owned by this modal. ItemModal is permanently mounted (inside
// DocumentItemsTable, via BaseModal's `static` dialog); @vuelidate did not track
// this reactive form's values in that context (it kept validating an empty
// snapshot), so validation here is done with plain reactive computeds instead —
// the same reactivity the price/taxes computeds below already rely on.
const form = reactive<ItemFormState>({
  name: '',
  description: '',
  price: 0,
  unit_id: '',
  truck_type: '',
  taxes: [],
  rate_card: {},
})

// Inline "Add Weight + Rate" form state
const newUnitForm = reactive({
  name: '',
  rate: 0, // in display units (will be converted to cents)
})

const nameError = computed<string>(() => {
  const value = (form.name ?? '').trim()
  if (!value) {
    return t('validation.required')
  }
  if (value.length < 3) {
    return t('validation.name_min_length', { count: 3 })
  }
  return ''
})

const descriptionError = computed<string>(() => {
  if ((form.description ?? '').length > 255) {
    return t('validation.description_maxlength', { count: 255 })
  }
  return ''
})

const isFormValid = computed<boolean>(
  () => !nameError.value && !descriptionError.value
)

const taxes = computed<TaxOption[]>({
  get: () =>
    form.taxes?.map((tax) => {
      const currencySymbol = companyStore.selectedCompanyCurrency?.symbol ?? '$'
      return {
        ...tax,
        tax_type_id: tax.id,
        tax_name: `${tax.name} (${
          tax.calculation_type === 'fixed'
            ? tax.fixed_amount + currencySymbol
            : tax.percent + '%'
        })`,
      }
    }) ?? [],
  set: (value: TaxOption[]) => {
    form.taxes = value
  },
})

const isTaxPerItemEnabled = computed<boolean>(() => {
  return taxPerItemSetting.value === 'YES'
})

const getTaxTypes = computed<TaxOption[]>(() => {
  return taxTypes.value.map((tax: TaxType) => {
    const currencyCode = companyStore.selectedCompanyCurrency?.code ?? 'USD'
    const amount =
      tax.calculation_type === 'fixed'
        ? new Intl.NumberFormat(undefined, {
            style: 'currency',
            currency: currencyCode,
          }).format(tax.fixed_amount / 100)
        : `${tax.percent}%`

    return {
      ...tax,
      tax_name: `${tax.name} (${amount})`,
    }
  }) as TaxOption[]
})

// Reset + prefill the form every time the modal opens. The typed item-search
// text is handed in via modalStore.data.name by BaseItemSelect.
watch(modalActive, (active) => {
  if (!active) {
    return
  }
  const data = modalStore.data as { name?: string } | null
  Object.assign(form, {
    name: data?.name ?? '',
    description: '',
    price: 0,
    unit_id: '',
    truck_type: '',
    taxes: [],
    rate_card: {},
  })
  // Reset inline form
  newUnitForm.name = ''
  newUnitForm.rate = 0
  triedSubmit.value = false
})

onMounted(async () => {
  await itemStore.fetchItemUnits({ limit: 'all' })

  if (userStore.hasAbilities(ABILITIES.VIEW_TAX_TYPE)) {
    await fetchTaxTypes()
  }
})

async function submitItemData(): Promise<void> {
  triedSubmit.value = true

  if (!isFormValid.value) {
    notificationStore.showNotification({
      type: 'error',
      message: nameError.value || descriptionError.value,
    })
    return
  }

  // Use the first rate from rate_card as the base price for tax calc,
  // or fall back to 0 if no rates are set.
  const rateCard = form.rate_card ?? {}
  const firstRate = Object.values(rateCard)[0] ?? 0

  const data: Record<string, unknown> = {
    name: form.name,
    description: form.description,
    price: firstRate,
    unit_id: form.unit_id || null,
    truck_type: form.truck_type || null,
    rate_card: { ...form.rate_card },
    taxes: (form.taxes ?? []).map((tax) => ({
      tax_type_id: tax.id,
      amount:
        tax.calculation_type === 'fixed'
          ? tax.fixed_amount
          : Math.round((firstRate / 100) * tax.percent),
      percent: tax.percent,
      fixed_amount: tax.fixed_amount,
      calculation_type: tax.calculation_type,
      name: tax.name,
      collective_tax: 0,
    })),
  }

  isLoading.value = true

  try {
    const res = await itemStore.addItem(data)
    isLoading.value = false
    if (res.data && modalStore.refreshData) {
      modalStore.refreshData(res.data)
    }
    closeItemModal()
  } catch (err: unknown) {
    isLoading.value = false
    const normalized = handleApiError(err)
    const translationKey = getErrorTranslationKey(normalized.message)
    notificationStore.showNotification({
      type: 'error',
      message: translationKey ? t(translationKey) : normalized.message,
    })
  }
}

// --- Rate Card helpers ---
// rate_card is { "unitId": rateInCents }.  These helpers let the UI
// add/remove weight→rate rows dynamically.  Columns come from the units table
// (itemStore.itemUnits).  The inline "Add Weight + Rate" form lets users
// create a new unit and set its rate without leaving this modal.

function getRateCardRate(unitId: number | string): number {
  return form.rate_card[String(unitId)] ?? 0
}

function setRateCardRate(unitId: number | string, valueCents: number): void {
  form.rate_card[String(unitId)] = valueCents
}

function removeRateCardEntry(unitId: number | string): void {
  delete form.rate_card[String(unitId)]
}

// Add a new weight type + rate inline.  Creates the unit via the API,
// which adds it to itemStore.itemUnits (so it appears as a new row
// in the Rate Card editor), then sets the rate in the rate_card JSON.
async function addInlineWeightRate(): Promise<void> {
  const name = newUnitForm.name.trim()
  if (!name) {
    notificationStore.showNotification({
      type: 'error',
      message: t('validation.required'),
    })
    return
  }

  isAddingUnit.value = true
  try {
    const res = await itemStore.addItemUnit({ name })
    const newUnitId = res.data.id

    // Set the rate for the newly created unit
    if (newUnitForm.rate > 0) {
      setRateCardRate(newUnitId, Math.round(newUnitForm.rate * 100))
    }

    // Reset the inline form
    newUnitForm.name = ''
    newUnitForm.rate = 0
  } catch {
    // Error handled by store
  } finally {
    isAddingUnit.value = false
  }
}

function closeItemModal(): void {
  modalStore.closeModal()
  setTimeout(() => {
    triedSubmit.value = false
  }, 300)
}
</script>

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
              :label="$t('items.station_name')"
              required
              :error="triedSubmit ? nameError : ''"
            >
              <BaseInput
                v-model="form.name"
                type="text"
                :invalid="Boolean(triedSubmit && nameError)"
              />
            </BaseInputGroup>

            <!-- Rate Card Editor: weight → rate rows.
                 Each row shows a weight type (from the units table) and an
                 editable rate.  The "Add Weight + Rate" button below lets
                 users create a new weight type and set its rate inline,
                 without navigating to Settings. -->
            <BaseInputGroup :label="$t('items.rate_card')">
              <div class="space-y-2">
                <div
                  v-for="unit in itemStore.itemUnits"
                  :key="unit.id"
                  class="flex items-center gap-2"
                >
                  <span class="w-24 text-sm text-body">{{ unit.name }}</span>
                  <BaseMoney
                    :model-value="getRateCardRate(unit.id) / 100"
                    :currency="companyStore.selectedCompanyCurrency"
                    class="relative w-full focus:border focus:border-solid focus:border-primary"
                    @update:model-value="(val: number) => setRateCardRate(unit.id, Math.round(val * 100))"
                  />
                  <BaseIcon
                    name="TrashIcon"
                    class="h-5 w-5 text-muted cursor-pointer hover:text-status-red"
                    @click="removeRateCardEntry(unit.id)"
                  />
                </div>
                <p
                  v-if="itemStore.itemUnits.length === 0"
                  class="text-sm text-muted"
                >
                  {{ $t('items.no_units_available') }}
                </p>

                <!-- Inline Add Weight + Rate form -->
                <div class="pt-2 border-t border-line-light">
                  <div class="flex items-center gap-2">
                    <BaseInput
                      v-model="newUnitForm.name"
                      type="text"
                      :placeholder="$t('items.weight_name_placeholder')"
                      class="w-24"
                      :disabled="isAddingUnit"
                    />
                    <BaseMoney
                      v-model="newUnitForm.rate"
                      :currency="companyStore.selectedCompanyCurrency"
                      class="relative w-full focus:border focus:border-solid focus:border-primary"
                      :disabled="isAddingUnit"
                    />
                    <BaseButton
                      variant="primary-outline"
                      type="button"
                      size="sm"
                      :loading="isAddingUnit"
                      :disabled="isAddingUnit"
                      @click="addInlineWeightRate"
                    >
                      <template #left="slotProps">
                        <BaseIcon
                          name="PlusIcon"
                          :class="slotProps.class"
                        />
                      </template>
                      {{ $t('items.add_weight_rate') }}
                    </BaseButton>
                  </div>
                </div>
              </div>
            </BaseInputGroup>

            <BaseInputGroup :label="$t('items.truck_type')">
              <BaseInput
                v-model="form.truck_type"
                type="text"
                :placeholder="$t('items.select_truck_type')"
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
              :error="triedSubmit ? descriptionError : ''"
            >
              <BaseTextarea
                v-model="form.description"
                rows="4"
                cols="50"
                :invalid="Boolean(triedSubmit && descriptionError)"
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
              <BaseIcon name="ArrowDownOnSquareIcon" :class="slotProps.class" />
            </template>
            {{ $t('general.save') }}
          </BaseButton>
        </div>
      </form>
    </div>
  </BaseModal>
</template>
