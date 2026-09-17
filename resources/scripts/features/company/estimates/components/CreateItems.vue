<template>
  <!-- Tax Included -->
  <div
    v-if="companyStore.selectedCompanySettings?.tax_included === 'YES'"
    class="
      flex
      items-center
      justify-end
      w-full
      px-6
      text-base
      border border-b-0 border-line-default border-solid
      cursor-pointer
      text-primary-400
      bg-surface
    "
  >
    <BaseSwitchSection
      v-model="taxIncludedField"
      :title="$t('settings.tax_types.tax_included')"
      :store="store"
      :store-prop="storeProp"
    />
  </div>
  <table class="text-center item-table min-w-full">
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
    <thead class="bg-surface border border-line-default border-solid">
      <tr>
        <th
          class="
            px-5
            py-3
            text-sm
            not-italic
            font-medium
            leading-5
            text-left text-heading
            border-t border-b border-line-default border-solid
          "
        >
          <BaseContentPlaceholders v-if="isLoading">
            <BaseContentPlaceholdersText :lines="1" class="w-16 h-5" />
          </BaseContentPlaceholders>
          <span v-else class="pl-7">
            {{ $t('items.item', 2) }}
          </span>
        </th>
        <th
          class="
            px-5
            py-3
            text-sm
            not-italic
            font-medium
            leading-5
            text-right text-heading
            border-t border-b border-line-default border-solid
          "
        >
          <BaseContentPlaceholders v-if="isLoading">
            <BaseContentPlaceholdersText :lines="1" class="w-16 h-5" />
          </BaseContentPlaceholders>
          <span v-else>
            {{ $t('invoices.item.quantity') }}
          </span>
        </th>
        <th
          class="
            px-5
            py-3
            text-sm
            not-italic
            font-medium
            leading-5
            text-left text-heading
            border-t border-b border-line-default border-solid
          "
        >
          <BaseContentPlaceholders v-if="isLoading">
            <BaseContentPlaceholdersText :lines="1" class="w-16 h-5" />
          </BaseContentPlaceholders>
          <span v-else>
            {{ $t('invoices.item.price') }}
          </span>
        </th>
        <th
          v-if="store[storeProp].discount_per_item === 'YES'"
          class="
            px-5
            py-3
            text-sm
            not-italic
            font-medium
            leading-5
            text-left text-heading
            border-t border-b border-line-default border-solid
          "
        >
          <BaseContentPlaceholders v-if="isLoading">
            <BaseContentPlaceholdersText :lines="1" class="w-16 h-5" />
          </BaseContentPlaceholders>
          <span v-else>
            {{ $t('invoices.item.discount') }}
          </span>
        </th>
        <th
          class="
            px-5
            py-3
            text-sm
            not-italic
            font-medium
            leading-5
            text-right text-heading
            border-t border-b border-line-default border-solid
          "
        >
          <BaseContentPlaceholders v-if="isLoading">
            <BaseContentPlaceholdersText :lines="1" class="w-16 h-5" />
          </BaseContentPlaceholders>
          <span v-else class="pr-10 column-heading">
            {{ $t('invoices.item.amount') }}
          </span>
        </th>
      </tr>
    </thead>
    <draggable
      v-model="items"
      item-key="id"
      tag="tbody"
      handle=".handle"
    >
      <template #item="{ element, index }">
        <CreateItemRow
          :key="element.id"
          :index="index"
          :item-data="element"
          :loading="isLoading"
          :currency="defaultCurrency"
          :item-validation-scope="itemValidationScope"
          :invoice-items="store[storeProp].items"
          :store="store"
          :store-prop="storeProp"
        />
      </template>
    </draggable>
  </table>

  <div
    class="
      flex
      items-center
      justify-center
      w-full
      px-6
      py-3
      text-base
      border border-t-0 border-line-default border-solid
      cursor-pointer
      text-primary-400
      hover:bg-primary-100
    "
    @click="store.addItem"
  >
    <BaseIcon name="PlusCircleIcon" class="mr-2" />
    {{ $t('general.add_new_item') }}
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useCompanyStore } from '@/scripts/stores/company.store'
import draggable from 'vuedraggable'
import CreateItemRow from './CreateItemRow.vue'

// Types
interface ItemData {
  id: string
  [key: string]: unknown
}

interface StoreProp {
  items: ItemData[]
  discount_per_item: string
  tax_included: string
  tax_per_item: string
  [key: string]: unknown
}

interface Currency {
  id: number
  code: string
  symbol: string
  [key: string]: unknown
}

interface Store {
  [key: string]: StoreProp
  addItem: () => void
}

interface Props {
  store: Store
  storeProp: string
  currency: Currency | string | null
  isLoading?: boolean
  itemValidationScope?: string
}

// Define Props
const props = withDefaults(defineProps<Props>(), {
  isLoading: false,
  itemValidationScope: '',
})

// Stores
const companyStore = useCompanyStore()

// Computed properties
const defaultCurrency = computed(() => {
  if (props.currency && typeof props.currency === 'object') {
    return props.currency
  }
  return companyStore.selectedCompanyCurrency
})

const items = computed({
  get: () => props.store[props.storeProp].items,
  set: (value: ItemData[]) => {
    props.store[props.storeProp].items = value
  },
})

const taxIncludedField = computed({
  get: () => props.store[props.storeProp].tax_included,
  set: async (value: string) => {
    props.store[props.storeProp].tax_included = value
  },
})
</script>
