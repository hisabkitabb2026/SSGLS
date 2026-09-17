<template>
  <div class="flex items-center justify-between mb-3">
    <div class="flex items-center text-base" style="flex: 4">
      <label class="pr-2 mb-0" align="right">
        {{ $t('invoices.item.tax') }}
      </label>

      <BaseMultiselect
        v-model="selectedTax"
        value-prop="id"
        :options="filteredTypes"
        :placeholder="$t('general.select_a_tax')"
        open-direction="top"
        track-by="name"
        searchable
        object
        label="name"
        @update:model-value="onSelectTax"
      >
        <template #singlelabel="{ value }">
          <div class="absolute left-3.5">
            {{ value.name }} -
            <template v-if="value.calculation_type === 'fixed'">
              <BaseFormatMoney :amount="value.fixed_amount" :currency="currency" />
            </template>
            <template v-else>
              {{ value.percent }} %
            </template>
          </div>
        </template>

        <template #option="{ option }">
          {{ option.name }} -
          <template v-if="option.calculation_type === 'fixed'">
            <BaseFormatMoney :amount="option.fixed_amount" :currency="currency" />
          </template>
          <template v-else>
            {{ option.percent }} %
          </template>
        </template>

        <template v-if="userStore.hasAbilities(ability)" #action>
          <BaseButton
            type="button"
            variant="gray"
            class="flex items-center justify-center w-full px-2 py-2 border-none outline-hidden cursor-pointer"
            @click="openTaxModal"
          >
            <BaseIcon name="CheckCircleIcon" class="h-5 text-primary-500" />

            <label
              class="ml-2 text-sm leading-none cursor-pointer text-primary-500"
            >{{ $t('invoices.add_new_tax') }}</label>
          </BaseButton>
        </template>
      </BaseMultiselect>
      <br />
    </div>

    <div class="text-sm text-right" style="flex: 3">
      <BaseFormatMoney :amount="taxAmount" :currency="currency" />
    </div>

    <div class="flex items-center justify-center w-6 h-10 mx-2 cursor-pointer">
      <BaseIcon
        v-if="taxes.length && index !== taxes.length - 1"
        name="TrashIcon"
        class="h-5 text-muted cursor-pointer"
        @click="removeTax(index)"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, reactive, watch, onMounted } from 'vue'
import { useModalStore } from '@/scripts/stores/modal.store'
import { useUserStore } from '@/scripts/stores/user.store'
import { useI18n } from 'vue-i18n'
import { taxTypeService } from '@/scripts/api/services/tax-type.service'
import type { TaxType as TaxTypeModel } from '@/scripts/types/domain/tax'

interface TaxType extends TaxTypeModel {
  disabled?: boolean
}

interface Tax extends TaxType {
  tax_type_id: number
  amount?: number | null
}

interface Store {
  $patch: (fn: (state: any) => void) => void
  [key: string]: any
}

interface Props {
  ability: string
  store: Store | null
  storeProp: string
  itemIndex: number
  index: number
  taxData: Tax
  taxes: Tax[]
  total: number
  totalTax: number
  discountedTotal: number
  currency: Record<string, any> | string
  updateItems?: (items: any[]) => void
}

const props = withDefaults(defineProps<Props>(), {
  ability: '',
  storeProp: '',
  total: 0,
  totalTax: 0,
  discountedTotal: 0,
  updateItems: () => {},
})

const emit = defineEmits<{
  remove: [index: number]
  update: [data: { index: number; item: Tax }]
}>()

const modalStore = useModalStore()
const userStore = useUserStore()
const { t } = useI18n()

const taxTypes = ref<TaxType[]>([])

onMounted(async () => {
  try {
    const response = await taxTypeService.list()
    taxTypes.value = response.data
  } catch (error) {
    console.error('Failed to load tax types:', error)
  }
})

const selectedTax = ref<TaxType | null>(null)
const localTax = reactive<Tax>({ ...props.taxData })

const filteredTypes = computed(() => {
  const clonedTypes = taxTypes.value.map((a) => ({ ...a }))

  return clonedTypes.map((taxType) => {
    const found = props.taxes.find((tax) => tax.tax_type_id === taxType.id)

    return {
      ...taxType,
      disabled: !!found,
    }
  })
})

const taxAmount = computed(() => {
  if (localTax.calculation_type === 'fixed') {
    return localTax.fixed_amount || 0
  }

  if (props.discountedTotal) {
    const taxPerItemEnabled = props.store?.[props.storeProp]?.tax_per_item === 'YES'
    const discountPerItemEnabled = props.store?.[props.storeProp]?.discount_per_item === 'YES'
    if (taxPerItemEnabled && !discountPerItemEnabled) {
      return getTaxAmount()
    }
    if (props.store?.[props.storeProp]?.tax_included) {
      return Math.round(props.discountedTotal - (props.discountedTotal / (1 + ((localTax.percent || 0) / 100))))
    }
    return Math.round((props.discountedTotal * (localTax.percent || 0)) / 100)
  }
  return 0
})

watch(
  () => props.discountedTotal,
  () => {
    updateRowTax()
  }
)

watch(
  () => props.totalTax,
  () => {
    updateRowTax()
  }
)

watch(
  () => taxAmount.value,
  () => {
    updateRowTax()
  }
)

// Set SelectedTax on mount
watch(
  () => taxTypes.value,
  () => {
    if (props.taxData.tax_type_id > 0) {
      selectedTax.value = (taxTypes.value as TaxType[]).find(
        (_type) => _type.id === props.taxData.tax_type_id
      ) || null
    }
  },
  { immediate: true }
)

updateRowTax()

function onSelectTax(val: TaxType) {
  localTax.calculation_type = val.calculation_type
  localTax.percent = val.calculation_type === 'percentage' ? val.percent : null
  localTax.fixed_amount = val.calculation_type === 'fixed' ? val.fixed_amount : 0
  localTax.tax_type_id = val.id
  localTax.name = val.name

  updateRowTax()
}

function updateRowTax() {
  if (localTax.tax_type_id === 0) {
    return
  }

  emit('update', {
    index: props.index,
    item: {
      ...localTax,
      amount: taxAmount.value,
    },
  })
}

function openTaxModal() {
  const data = {
    itemIndex: props.itemIndex,
    taxIndex: props.index,
  }

  modalStore.openModal({
    title: t('settings.tax_types.add_tax'),
    componentName: 'TaxTypeModal',
    data: data,
    size: 'sm',
  })
}

function removeTax(taxIndex: number) {
  if (props.store) {
    props.store.$patch((state: any) => {
      state[props.storeProp].items[props.itemIndex].taxes.splice(taxIndex, 1)
      state[props.storeProp].items[props.itemIndex].tax = 0
      state[props.storeProp].items[props.itemIndex].totalTax = 0
    })
  }
}

function getTaxAmount(): number {
  if (localTax.calculation_type === 'fixed') {
    return localTax.fixed_amount || 0
  }

  let total = 0
  let discount = 0
  const itemTotal = props.discountedTotal
  const modelDiscount = props.store?.[props.storeProp]?.discount || 0
  const type = props.store?.[props.storeProp]?.discount_type || 'fixed'
  let discountedTotal = props.discountedTotal

  if (modelDiscount > 0) {
    props.store?.[props.storeProp]?.items?.forEach((_i: any) => {
      total += _i.total
    })
    const proportion = (itemTotal / total).toFixed(2)
    discount = type === 'fixed' ? modelDiscount * 100 : (total * modelDiscount) / 100
    const itemDiscount = Math.round(discount * parseFloat(proportion))
    discountedTotal = itemTotal - itemDiscount
  }

  if (props.store?.[props.storeProp]?.tax_included) {
    return Math.round(discountedTotal - (discountedTotal / (1 + ((localTax.percent || 0) / 100))))
  }

  return Math.round((discountedTotal * (localTax.percent || 0)) / 100)
}
</script>
