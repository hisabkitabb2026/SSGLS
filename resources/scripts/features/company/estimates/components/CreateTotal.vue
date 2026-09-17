<template>
  <div
    class="
      px-5
      py-4
      mt-6
      bg-surface
      border border-line-default border-solid
      rounded
      md:min-w-[390px]
      min-w-[300px]
      lg:mt-7
    "
  >
    <div class="flex items-center justify-between w-full">
      <BaseContentPlaceholders v-if="isLoading">
        <BaseContentPlaceholdersText :lines="1" class="w-16 h-5" />
      </BaseContentPlaceholders>
      <label
        v-else
        class="text-sm font-semibold leading-5 text-subtle uppercase"
      >
        {{ $t('estimates.sub_total') }}
      </label>

      <BaseContentPlaceholders v-if="isLoading">
        <BaseContentPlaceholdersText :lines="1" class="w-16 h-5" />
      </BaseContentPlaceholders>

      <label
        v-else
        class="flex items-center justify-center m-0 text-lg text-heading uppercase "
      >
        <BaseFormatMoney
          :amount="store.getSubTotal"
          :currency="defaultCurrency"
        />
      </label>
    </div>

    <div
      v-if="store[storeProp].tax_per_item === 'YES'"
    >
      <NetTotal
        :currency="currency"
        :store="store"
        :store-prop="storeProp"
        :is-loading="isLoading"
      />
    </div>

    <div
      v-for="tax in itemWiseTaxes"
      :key="tax.tax_type_id"
      class="flex items-center justify-between w-full"
    >
      <BaseContentPlaceholders v-if="isLoading">
        <BaseContentPlaceholdersText :lines="1" class="w-16 h-5" />
      </BaseContentPlaceholders>
      <label
        v-else-if="store[storeProp].tax_per_item === 'YES'"
        class="m-0 text-sm font-semibold leading-5 text-muted uppercase"
      >
        <template v-if="tax.calculation_type === 'percentage'">
          {{ tax.name }} - {{ tax.percent }}%
        </template>
        <template v-else>
          {{ tax.name }} - <BaseFormatMoney :amount="tax.fixed_amount" :currency="defaultCurrency" />
        </template>
      </label>

      <BaseContentPlaceholders v-if="isLoading">
        <BaseContentPlaceholdersText :lines="1" class="w-16 h-5" />
      </BaseContentPlaceholders>

      <label
        v-else-if="store[storeProp].tax_per_item === 'YES'"
        class="flex items-center justify-center m-0 text-lg text-heading uppercase "
      >
        <BaseFormatMoney :amount="tax.amount" :currency="defaultCurrency" />
      </label>
    </div>

    <div
      v-if="
        store[storeProp].discount_per_item === 'NO' ||
        store[storeProp].discount_per_item === null
      "
      class="flex items-center justify-between w-full mt-2"
    >
      <BaseContentPlaceholders v-if="isLoading">
        <BaseContentPlaceholdersText :lines="1" class="w-16 h-5" />
      </BaseContentPlaceholders>
      <label
        v-else
        class="text-sm font-semibold leading-5 text-subtle uppercase"
      >
        {{ $t('estimates.discount') }}
      </label>
      <BaseContentPlaceholders v-if="isLoading">
        <BaseContentPlaceholdersText
          :lines="1"
          class="w-24 h-8 border border-line-default rounded-md"
        />
      </BaseContentPlaceholders>
      <div v-else class="flex" style="width: 140px" role="group">
        <BaseInput
          v-model.number="totalDiscount"
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
              class="p-2 rounded-none rounded-tr-md rounded-br-md"
              type="button"
              variant="white"
            >
              <span class="flex items-center">
                {{
                  store[storeProp].discount_type === 'fixed'
                    ? defaultCurrency.symbol
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

    <div
      v-if="
        store[storeProp].tax_per_item === 'NO' ||
        store[storeProp].tax_per_item === null
      "
      class="flex items-center justify-between w-full mt-2"
    >
      <NetTotal
        :currency="currency"
        :store="store"
        :store-prop="storeProp"
        :is-loading="isLoading"
      />
    </div>

    <div
      v-if="
        store[storeProp].tax_per_item === 'NO' ||
        store[storeProp].tax_per_item === null
      "
    >
      <CreateTotalTaxes
        v-for="(tax, index) in taxes"
        :key="tax.id"
        :index="index"
        :tax="tax"
        :taxes="taxes"
        :currency="currency"
        :store="store"
        :store-prop="storeProp"
        @remove="removeTax"
        @update="updateTax"
      />
    </div>

    <div
      v-if="
        store[storeProp].tax_per_item === 'NO' ||
        store[storeProp].tax_per_item === null
      "
      ref="taxModal"
      class="float-right pt-2 pb-4"
    >
      <SelectTaxPopup
        :store-prop="storeProp"
        :store="store"
        :type="taxPopupType"
        @select:tax-type="onSelectTax"
      />
    </div>

    <div
      class="flex items-center justify-between w-full pt-2 mt-5 border-t border-line-default border-solid "
    >
      <BaseContentPlaceholders v-if="isLoading">
        <BaseContentPlaceholdersText :lines="1" class="w-16 h-5" />
      </BaseContentPlaceholders>
      <label
        v-else
        class="m-0 text-sm font-semibold leading-5 text-subtle uppercase"
      >{{ $t('estimates.total') }} {{ $t('estimates.amount') }}:</label>

      <BaseContentPlaceholders v-if="isLoading">
        <BaseContentPlaceholdersText :lines="1" class="w-16 h-5" />
      </BaseContentPlaceholders>
      <label
        v-else
        class="flex items-center justify-center text-lg uppercase  text-primary-400"
      >
        <BaseFormatMoney :amount="store.getTotal" :currency="defaultCurrency" />
      </label>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { generateClientId } from '@/scripts/utils'
import NetTotal from './NetTotal.vue'
import CreateTotalTaxes from './CreateTotalTaxes.vue'
import SelectTaxPopup from './SelectTaxPopup.vue'
import { useCompanyStore } from '@/scripts/stores/company.store'

interface Tax {
  id: string
  name: string
  percent: number
  tax_type_id: number
  amount: number
  calculation_type: string
  fixed_amount: number
}

interface Currency {
  symbol: string
  [key: string]: unknown
}

interface Props {
  store: Record<string, unknown> | null
  storeProp: string
  taxPopupType: string
  currency: Currency | string | Record<string, unknown>
  isLoading?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  store: null,
  storeProp: '',
  taxPopupType: '',
  currency: '',
  isLoading: false,
})

const emit = defineEmits<{
  'select:taxType': [tax: Tax]
}>()

const taxModal = ref<HTMLElement | null>(null)
const companyStore = useCompanyStore()

watch(
  () => (props.store?.[props.storeProp] as Record<string, unknown>)?.items,
  () => {
    setDiscount()
  },
  { deep: true },
)

const totalDiscount = computed({
  get: (): number => {
    return (props.store?.[props.storeProp] as Record<string, unknown>)?.discount as number || 0
  },
  set: (newValue: number) => {
    if (props.store && props.storeProp) {
      (props.store[props.storeProp] as Record<string, unknown>).discount = newValue
      setDiscount()
    }
  },
})

const taxes = computed({
  get: (): Tax[] => {
    return ((props.store?.[props.storeProp] as Record<string, unknown>)?.taxes || []) as Tax[]
  },
  set: (value: Tax[]) => {
    if (props.store && props.storeProp) {
      (props.store[props.storeProp] as Record<string, unknown>).taxes = value
    }
  },
})

const itemWiseTaxes = computed(() => {
  const taxMap: Record<number, Tax & { amount: number }> = {}
  const items = ((props.store?.[props.storeProp] as Record<string, unknown>)?.items || []) as Array<Record<string, unknown>>

  items.forEach((item) => {
    const itemTaxes = (item.taxes || []) as Tax[]
    itemTaxes.forEach((tax) => {
      if (tax.tax_type_id) {
        if (taxMap[tax.tax_type_id]) {
          taxMap[tax.tax_type_id].amount += tax.amount
        } else {
          taxMap[tax.tax_type_id] = {
            tax_type_id: tax.tax_type_id,
            amount: Math.round(tax.amount),
            percent: tax.percent,
            name: tax.name,
            calculation_type: tax.calculation_type,
            fixed_amount: tax.fixed_amount,
            id: '',
          }
        }
      }
    })
  })

  return Object.values(taxMap)
})

const defaultCurrency = computed<Currency>(() => {
  if (props.currency && typeof props.currency === 'object' && 'symbol' in props.currency) {
    return props.currency as Currency
  }
  return companyStore.selectedCompanyCurrency as Currency
})

function setDiscount(): void {
  const storeData = props.store?.[props.storeProp] as Record<string, unknown>
  if (!storeData) return

  const newValue = storeData.discount as number

  if (storeData.discount_type === 'percentage') {
    const subtotal = (props.store as Record<string, unknown>).getSubTotal as number || 0
    storeData.discount_val = Math.round((subtotal * newValue) / 100)
    return
  }

  storeData.discount_val = Math.round(newValue * 100)
}

function selectFixed(): void {
  const storeData = props.store?.[props.storeProp] as Record<string, unknown>
  if (!storeData) return

  if (storeData.discount_type === 'fixed') {
    return
  }
  storeData.discount_val = Math.round((storeData.discount as number) * 100)
  storeData.discount_type = 'fixed'
}

function selectPercentage(): void {
  const storeData = props.store?.[props.storeProp] as Record<string, unknown>
  if (!storeData) return

  if (storeData.discount_type === 'percentage') {
    return
  }

  const val = Math.round((storeData.discount as number) * 100) / 100
  const subtotal = (props.store as Record<string, unknown>).getSubTotal as number || 0

  storeData.discount_val = Math.round((subtotal * val) / 100)
  storeData.discount_type = 'percentage'
}

function onSelectTax(selectedTax: Record<string, unknown>): void {
  let amount = 0
  const subtotalWithDiscount = (props.store as Record<string, unknown>).getSubtotalWithDiscount as number || 0

  if (selectedTax.calculation_type === 'percentage' && subtotalWithDiscount && selectedTax.percent) {
    amount = Math.round(
      (subtotalWithDiscount * (selectedTax.percent as number)) / 100
    )
  } else if (selectedTax.calculation_type === 'fixed') {
    amount = selectedTax.fixed_amount as number
  }

  const newTax: Tax = {
    id: generateClientId(),
    name: selectedTax.name as string,
    percent: selectedTax.percent as number,
    tax_type_id: selectedTax.id as number,
    amount,
    calculation_type: selectedTax.calculation_type as string,
    fixed_amount: selectedTax.fixed_amount as number,
  }

  const storeData = props.store?.[props.storeProp] as Record<string, unknown>
  if (storeData && Array.isArray(storeData.taxes)) {
    (storeData.taxes as Tax[]).push(newTax)
  }
}

function updateTax(data: Record<string, unknown>): void {
  const storeData = props.store?.[props.storeProp] as Record<string, unknown>
  const taxList = (storeData?.taxes || []) as Tax[]
  const tax = taxList.find((t) => t.id === data.id)
  if (tax) {
    Object.assign(tax, data)
  }
}

function removeTax(id: string): void {
  const storeData = props.store?.[props.storeProp] as Record<string, unknown>
  const taxList = (storeData?.taxes || []) as Tax[]
  const index = taxList.findIndex((tax) => tax.id === id)

  if (index !== -1) {
    taxList.splice(index, 1)
  }
}
</script>
