<template>
  <tr class="box-border bg-surface border border-line-default border-solid rounded-b">
    <td colspan="5" class="p-0 text-left align-top">
      <table class="w-full">
        <colgroup>
          <col style="width: 40%; min-width: 280px" />
          <col style="width: 10%; min-width: 120px" />
          <col style="width: 15%; min-width: 120px" />
          <col
            v-if="store[storeProp].discount_per_item === 'YES'"
            style="width: 15%; min-width: 160px"
          />
          <col style="width: 15%; min-width: 120px" />
        </colgroup>
        <tbody>
          <tr>
            <td class="px-5 py-4 text-left align-top">
              <div class="flex justify-start">
                <div
                  class="flex items-center justify-center w-5 h-5 mt-2 mr-2 text-subtle cursor-move  handle"
                >
                  <BaseIcon name="Bars3BottomLeftIcon" class="w-5 h-5" />
                </div>
                <BaseItemSelect
                  type="Invoice"
                  :item="itemData"
                  :invalid="v$.name.$error"
                  :invalid-description="v$.description.$error"
                  :taxes="itemData.taxes"
                  :index="index"
                  :store-prop="storeProp"
                  :store="store"
                  @search="searchVal"
                  @select="onSelectItem"
                />
              </div>
            </td>
            <td class="px-5 py-4 text-right align-top">
              <BaseInput
                v-model="quantity"
                :invalid="v$.quantity.$error"
                :content-loading="loading"
                type="number"
                small
                step="any"
                @change="syncItemToStore()"
                @input="v$.quantity.$touch()"
              />
            </td>
            <td class="px-5 py-4 text-left align-top">
              <div class="flex flex-col">
                <div class="flex-auto flex-fill bd-highlight">
                  <div class="relative w-full">
                    <BaseMoney
                      :key="selectedCurrency"
                      v-model="price"
                      :invalid="v$.price.$error"
                      :content-loading="loading"
                      :currency="selectedCurrency"
                    />
                  </div>
                </div>
              </div>
            </td>
            <td
              v-if="store[storeProp].discount_per_item === 'YES'"
              class="px-5 py-4 text-left align-top"
            >
              <div class="flex flex-col">
                <div class="flex" style="width: 120px" role="group">
                  <BaseInput
                    v-model="discount"
                    :invalid="v$.discount_val.$error"
                    :content-loading="loading"
                    class="
                      border-r-0
                      focus:border-r-2
                      rounded-tr-sm rounded-br-sm
                      h-[38px]
                    "
                  />
                  <BaseDropdown position="bottom-end">
                    <template #activator>
                      <BaseButton
                        :content-loading="loading"
                        class="rounded-tr-md rounded-br-md !p-2 rounded-none"
                        type="button"
                        variant="white"
                      >
                        <span class="flex items-center">
                          {{
                            itemData.discount_type == 'fixed'
                              ? currency.symbol
                              : '%'
                          }}

                          <BaseIcon
                            name="ChevronDownIcon"
                            class="w-4 h-4 ml-1 text-muted"
                          />
                        </span>
                      </BaseButton>
                    </template>

                    <BaseDropdownItem @click="selectFixed">
                      {{ $t('general.fixed') }}
                    </BaseDropdownItem>

                    <BaseDropdownItem @click="selectPercentage">
                      {{ $t('general.percentage') }}
                    </BaseDropdownItem>
                  </BaseDropdown>
                </div>
              </div>
            </td>
            <td class="px-5 py-4 text-right align-top">
              <div class="flex items-center justify-end text-sm">
                <span>
                  <BaseContentPlaceholders v-if="loading">
                    <BaseContentPlaceholdersText :lines="1" class="w-16 h-5" />
                  </BaseContentPlaceholders>

                  <BaseFormatMoney
                    v-else
                    :amount="total"
                    :currency="selectedCurrency"
                  />
                </span>
                <div class="flex items-center justify-center w-6 h-10 mx-2">
                  <BaseIcon
                    v-if="showRemoveButton"
                    class="h-5 text-heading cursor-pointer"
                    name="TrashIcon"
                    @click="store.removeItem(index)"
                  />
                </div>
              </div>
            </td>
          </tr>
          <tr v-if="store[storeProp].tax_per_item === 'YES'">
            <td class="px-5 py-4 text-left align-top" />
            <td colspan="4" class="px-5 py-4 text-left align-top">
              <BaseContentPlaceholders v-if="loading">
                <BaseContentPlaceholdersText
                  :lines="1"
                  class="w-24 h-8 border border-line-default rounded-md"
                />
              </BaseContentPlaceholders>

              <QuotationCreateItemRowTax
                v-for="(tax, index1) in itemData.taxes"
                v-else
                :key="tax.id"
                :index="index1"
                :item-index="index"
                :tax-data="tax"
                :taxes="itemData.taxes"
                :discounted-total="total"
                :total-tax="totalSimpleTax"
                :total="subtotal"
                :currency="currency"
                :update-items="syncItemToStore"
                :store="store"
                :store-prop="storeProp"
                :discount="discount"
                @update="updateTax"
              />
            </td>
          </tr>
        </tbody>
      </table>
    </td>
  </tr>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useVuelidate } from '@vuelidate/core'
import {
  required,
  between,
  maxLength,
  helpers,
} from '@vuelidate/validators'
import { sumBy } from 'lodash'
import { generateClientId } from '@/scripts/utils'
import { useCompanyStore } from '@/scripts/stores/company.store'
import { useItemStore } from '../../items/store'
import QuotationCreateItemRowTax from './QuotationCreateItemRowTax.vue'

// Types
interface Tax {
  id: string
  tax_type_id: number
  amount?: number
  [key: string]: unknown
}

interface ItemData {
  id: string
  name: string
  description: string
  quantity: number
  price: number
  discount: number
  discount_val: number
  discount_type: 'fixed' | 'percentage'
  taxes: Tax[]
  item_id?: number
  unit_name?: string
  [key: string]: unknown
}

interface Currency {
  id: number
  code: string
  symbol: string
  [key: string]: unknown
}

interface Store {
  [key: string]: Record<string, unknown>
  removeItem: (index: number) => void
  updateItem: (data: Record<string, unknown>) => void
  $patch: (callback: (state: any) => void) => void
}

interface Props {
  store: Store
  storeProp: string
  itemData: ItemData
  index: number
  type?: string
  loading?: boolean
  currency: Currency | string
  invoiceItems: ItemData[]
  itemValidationScope?: string
}

// Define Props
const props = withDefaults(defineProps<Props>(), {
  type: '',
  loading: false,
  itemValidationScope: '',
})

// Define Emits
const emit = defineEmits<{
  update: [data: Record<string, unknown>]
  remove: [index: number]
  itemValidate: [data: Record<string, unknown>]
}>()

// Stores
const companyStore = useCompanyStore()
const itemStore = useItemStore()
const { t } = useI18n()

// Reactive computed properties
const quantity = computed({
  get: () => props.itemData.quantity,
  set: (newValue: number | string) => {
    updateItemAttribute('quantity', parseFloat(String(newValue)))
  },
})

const price = computed({
  get: () => {
    const price = props.itemData.price
    return price / 100
  },
  set: (newValue: number | string) => {
    const price = Math.round(Number(newValue) * 100)
    updateItemAttribute('price', price)
    setDiscount()
  },
})

const subtotal = computed(() =>
  Math.round(props.itemData.price * props.itemData.quantity)
)

const discount = computed({
  get: () => props.itemData.discount,
  set: (newValue: number | string) => {
    updateItemAttribute('discount', Number(newValue))
    setDiscount()
  },
})

const total = computed(() => subtotal.value - props.itemData.discount_val)

const selectedCurrency = computed(() => {
  if (props.currency && typeof props.currency === 'object') {
    return props.currency
  }
  return companyStore.selectedCompanyCurrency
})

const showRemoveButton = computed(() => {
  return props.store[props.storeProp].items.length > 1
})

const totalSimpleTax = computed(() => {
  return Math.round(
    sumBy(props.itemData.taxes, (tax: Tax) => {
      return tax.amount ? tax.amount : 0
    })
  )
})

const totalTax = computed(() => totalSimpleTax.value)

// Validation rules
const rules = computed(() => ({
  name: {
    required: helpers.withMessage(t('validation.required'), required),
  },
  quantity: {
    required: helpers.withMessage(t('validation.required'), required),
    maxLength: helpers.withMessage(
      t('validation.amount_maxlength'),
      maxLength(20)
    ),
  },
  price: {
    required: helpers.withMessage(t('validation.required'), required),
    maxLength: helpers.withMessage(
      t('validation.price_maxlength'),
      maxLength(20)
    ),
  },
  discount_val: {
    between: helpers.withMessage(
      t('validation.discount_maxlength'),
      between(0, Math.abs(subtotal.value))
    ),
  },
  description: {
    maxLength: helpers.withMessage(
      t('validation.notes_maxlength'),
      maxLength(65000)
    ),
  },
}))

const v$ = useVuelidate(
  rules,
  computed(
    () => props.store[props.storeProp].items[props.index]
  ),
  { $scope: props.itemValidationScope }
)

// Functions
function updateTax(data: { index: number; item: Tax }): void {
  props.store.$patch((state: any) => {
    state[props.storeProp].items[props.index].taxes[data.index] = data.item
  })

  const lastTax = props.itemData.taxes[props.itemData.taxes.length - 1]

  if (lastTax?.tax_type_id !== 0) {
    props.store.$patch((state: any) => {
      state[props.storeProp].items[props.index].taxes.push({
        id: generateClientId(),
        tax_type_id: 0,
        amount: 0,
      })
    })
  }

  syncItemToStore()
}

function setDiscount(): void {
  const newValue = props.store[props.storeProp].items[props.index].discount
  const absoluteSubtotal = Math.abs(subtotal.value)

  if (props.itemData.discount_type === 'percentage') {
    updateItemAttribute(
      'discount_val',
      Math.round((absoluteSubtotal * newValue) / 100)
    )
  } else {
    updateItemAttribute(
      'discount_val',
      Math.min(Math.round(newValue * 100), absoluteSubtotal)
    )
  }
}

function searchVal(val: string): void {
  updateItemAttribute('name', val)
}

function onSelectItem(itm: Record<string, unknown>): void {
  props.store.$patch((state: any) => {
    state[props.storeProp].items[props.index].name = itm.name
    state[props.storeProp].items[props.index].price = itm.price
    state[props.storeProp].items[props.index].item_id = itm.id
    state[props.storeProp].items[props.index].description = itm.description

    if ((itm as any).unit) {
      state[props.storeProp].items[props.index].unit_name = (itm as any).unit.name
    }

    if (props.store[props.storeProp].tax_per_item === 'YES' && (itm as any).taxes) {
      let taxIndex = 0
      ;(itm as any).taxes.forEach((tax: Tax) => {
        updateTax({ index: taxIndex, item: { ...tax } })
        taxIndex++
      })
    }

    if (state[props.storeProp].exchange_rate) {
      state[props.storeProp].items[props.index].price /=
        state[props.storeProp].exchange_rate
    }
  })

  itemStore.fetchItems()
  syncItemToStore()
}

function selectFixed(): void {
  if (props.itemData.discount_type === 'fixed') {
    return
  }

  updateItemAttribute('discount_val', Math.round(props.itemData.discount * 100))
  updateItemAttribute('discount_type', 'fixed')
}

function selectPercentage(): void {
  if (props.itemData.discount_type === 'percentage') {
    return
  }

  updateItemAttribute(
    'discount_val',
    (subtotal.value * props.itemData.discount) / 100
  )

  updateItemAttribute('discount_type', 'percentage')
}

function syncItemToStore(): void {
  const itemTaxes = props.store[props.storeProp]?.items[props.index]?.taxes || []

  const data = {
    ...props.store[props.storeProp].items[props.index],
    index: props.index,
    total: total.value,
    sub_total: subtotal.value,
    totalSimpleTax: totalSimpleTax.value,
    totalTax: totalTax.value,
    tax: totalTax.value,
    taxes: [...itemTaxes],
    tax_type_ids: itemTaxes.flatMap((t: Tax) =>
      t.tax_type_id ? t.tax_type_id : []
    ),
  }

  props.store.updateItem(data)
}

function updateItemAttribute(attribute: string, value: unknown): void {
  props.store.$patch((state: any) => {
    state[props.storeProp].items[props.index][attribute] = value
  })

  syncItemToStore()
}
</script>
