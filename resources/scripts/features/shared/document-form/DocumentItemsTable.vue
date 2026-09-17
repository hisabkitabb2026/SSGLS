<template>
  <div class="rounded-xl border border-line-light shadow bg-surface">
    <!-- Single shared item-create modal for the whole table (one instance, not one
         per row — stacked HeadlessUI dialogs would otherwise close each other). -->
    <ItemModal />

    <!-- ============================================================ -->
    <!-- ESTIMATE CONTEXT: Rate Card Matrix Table                     -->
    <!--                                                              -->
    <!-- Renders a matrix where each row is a station (item) and      -->
    <!-- columns are dynamically generated from the units table        -->
    <!-- (itemStore.itemUnits — e.g. 9MT, 10MT, 12MT, ...).            -->
    <!-- Each cell is an editable rate input.  Rates are stored in     -->
    <!-- item.rate_card as { "unitId": rateInCents }.                  -->
    <!-- ============================================================ -->
    <template v-if="isEstimateContext && !isTransportEntryTemplate">
      <!-- Tax Included Toggle -->
      <div
        v-if="taxIncludedSetting === 'YES'"
        class="flex items-center justify-end w-full px-6 text-base border-b border-line-light cursor-pointer text-primary-400 bg-surface"
      >
        <BaseSwitchSection
          v-model="taxIncludedField"
          :title="$t('settings.tax_types.tax_included')"
          :store="store"
          :store-prop="storeProp"
        />
      </div>

      <div class="overflow-x-auto">
        <table class="text-center item-table min-w-full">
          <thead class="bg-surface-secondary border-b border-line-light">
            <tr>
              <!-- Station name column -->
              <th
                class="px-5 py-3 text-sm not-italic font-medium leading-5 text-left text-body sticky left-0 bg-surface-secondary z-10"
                style="min-width: 200px"
              >
                <BaseContentPlaceholders v-if="isLoading">
                  <BaseContentPlaceholdersText :lines="1" class="w-16 h-5" />
                </BaseContentPlaceholders>
                <span v-else class="pl-7">
                  {{ $t('items.station_name') }}
                </span>
              </th>
              <!-- Dynamic weight columns from units table -->
              <th
                v-for="unit in itemStore.itemUnits"
                :key="unit.id"
                class="px-3 py-3 text-sm not-italic font-medium leading-5 text-center text-body"
                style="min-width: 120px"
              >
                <BaseContentPlaceholders v-if="isLoading">
                  <BaseContentPlaceholdersText :lines="1" class="w-12 h-5" />
                </BaseContentPlaceholders>
                <span v-else>{{ unit.name }}</span>
              </th>
              <!-- Remove column -->
              <th class="px-3 py-3" style="min-width: 50px" />
            </tr>
          </thead>

          <draggable
            v-model="formData.items"
            item-key="id"
            tag="tbody"
            handle=".handle"
          >
            <template #item="{ element, index }">
              <tr
                :key="element.id"
                class="box-border bg-surface border-b border-line-light"
              >
                <!-- Station name + drag handle + item select -->
                <td class="px-5 py-4 text-left align-top sticky left-0 bg-surface z-10" style="min-width: 200px">
                  <div class="flex justify-start">
                    <div
                      class="flex items-center justify-center w-5 h-5 mt-2 mr-2 text-subtle cursor-move handle"
                    >
                      <DragIcon />
                    </div>
                    <BaseItemSelect
                      type="Estimate"
                      :item="element"
                      :invalid="false"
                      :taxes="element.taxes"
                      :index="index"
                      :store-prop="storeProp"
                      :store="store"
                      @search="(val: string) => updateMatrixItemName(index, val)"
                      @select="(itm: Record<string, unknown>) => onMatrixItemSelect(index, itm)"
                    />
                  </div>
                </td>
                <!-- Dynamic rate cells — one per unit (weight) -->
                <td
                  v-for="unit in itemStore.itemUnits"
                  :key="unit.id"
                  class="px-3 py-4 text-center align-top"
                  style="min-width: 120px"
                >
                  <BaseMoney
                    :model-value="getMatrixRate(element, unit.id) / 100"
                    :currency="defaultCurrency"
                    small
                    @update:model-value="(val: number) => setMatrixRate(index, unit.id, Math.round(val * 100))"
                  />
                </td>
                <!-- Remove button -->
                <td class="px-3 py-4 text-center align-top" style="min-width: 50px">
                  <BaseIcon
                    v-if="formData.items.length > 1"
                    class="h-5 text-body cursor-pointer"
                    name="TrashIcon"
                    @click="store.removeItem(index)"
                  />
                </td>
              </tr>
            </template>
          </draggable>
        </table>
      </div>
    </template>

    <!-- ============================================================ -->
    <!-- INVOICE / TRANSPORT CONTEXT: Standard Row-Based Table         -->
    <!-- ============================================================ -->
    <template v-else>
      <!-- Tax Included Toggle (hidden for transport templates) -->
      <div
        v-if="taxIncludedSetting === 'YES' && !isTransportEntryTemplate"
        class="flex items-center justify-end w-full px-6 text-base border-b border-line-light cursor-pointer text-primary-400 bg-surface"
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
          <col style="width: 15%; min-width: 120px" />
          <col style="width: 15%; min-width: 120px" />
          <col
            v-if="formData.discount_per_item === 'YES'"
            style="width: 15%; min-width: 160px"
          />
          <col v-if="!isEstimateContext" style="width: 15%; min-width: 120px" />
        </colgroup>

        <thead
          v-if="!isTransportEntryTemplate"
          class="bg-surface-secondary border-b border-line-light"
        >
          <tr>
            <th class="px-5 py-3 text-sm not-italic font-medium leading-5 text-left text-body">
              <BaseContentPlaceholders v-if="isLoading">
                <BaseContentPlaceholdersText :lines="1" class="w-16 h-5" />
              </BaseContentPlaceholders>
              <span v-else class="pl-7">
                {{ isEstimateContext ? $t('items.station_name') : $t('items.item', 2) }}
              </span>
            </th>
            <th class="px-5 py-3 text-sm not-italic font-medium leading-5 text-right text-body">
              <BaseContentPlaceholders v-if="isLoading">
                <BaseContentPlaceholdersText :lines="1" class="w-16 h-5" />
              </BaseContentPlaceholders>
              <span v-else>
                {{ isEstimateContext ? $t('items.weight') : $t('invoices.item.quantity') }}
              </span>
            </th>
            <th class="px-5 py-3 text-sm not-italic font-medium leading-5 text-left text-body">
              <BaseContentPlaceholders v-if="isLoading">
                <BaseContentPlaceholdersText :lines="1" class="w-16 h-5" />
              </BaseContentPlaceholders>
              <span v-else>
                {{ isEstimateContext ? $t('items.rate') : $t('invoices.item.price') }}
              </span>
            </th>
            <th
              v-if="formData.discount_per_item === 'YES'"
              class="px-5 py-3 text-sm not-italic font-medium leading-5 text-left text-body"
            >
              <BaseContentPlaceholders v-if="isLoading">
                <BaseContentPlaceholdersText :lines="1" class="w-16 h-5" />
              </BaseContentPlaceholders>
              <span v-else>
                {{ $t('invoices.item.discount') }}
              </span>
            </th>
            <th v-if="!isEstimateContext" class="px-5 py-3 text-sm not-italic font-medium leading-5 text-right text-body">
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
          v-model="formData.items"
          item-key="id"
          tag="tbody"
          handle=".handle"
        >
          <template #item="{ element, index }">
            <DocumentItemRow
              :key="element.id"
              :index="index"
              :item-data="element"
              :loading="isLoading"
              :currency="defaultCurrency"
              :item-validation-scope="itemValidationScope"
              :invoice-items="formData.items"
              :store="store"
              :store-prop="storeProp"
              :is-estimate-context="isEstimateContext"
            />
          </template>
        </draggable>
      </table>
    </template>

    <div
      v-if="!isTransportEntryTemplate"
      class="flex items-center justify-center w-full px-6 py-3 text-base border-t border-line-light cursor-pointer text-primary-400 hover:bg-primary-100"
      @click="store.addItem()"
    >
      <BaseIcon name="PlusCircleIcon" class="mr-2" />
      {{ $t('general.add_new_item') }}
    </div>
  </div>
</template>


<script setup lang="ts">
import { computed, onMounted } from 'vue'
import draggable from 'vuedraggable'
import DocumentItemRow from './DocumentItemRow.vue'
import ItemModal from '@/scripts/features/company/items/components/ItemModal.vue'
import DragIcon from '@/scripts/components/icons/DragIcon.vue'
import { useItemStore } from '@/scripts/features/company/items/store'
import type { Currency } from '../../../types/domain/currency'
import type { DocumentFormData, DocumentItem } from './use-document-calculations'

interface Props {
  store: Record<string, unknown> & {
    addItem: () => void
    removeItem: (index: number) => void
    updateItem: (data: Record<string, unknown>) => void
    $patch: (fn: (state: Record<string, unknown>) => void) => void
  }
  storeProp: string
  currency: Currency | Record<string, unknown> | string | null
  isLoading?: boolean
  itemValidationScope?: string
  taxIncludedSetting?: string
}

const props = withDefaults(defineProps<Props>(), {
  isLoading: false,
  itemValidationScope: '',
  taxIncludedSetting: 'NO',
})

const itemStore = useItemStore()

const formData = computed<DocumentFormData>(() => {
  return props.store[props.storeProp] as DocumentFormData
})

const isTransportEntryTemplate = computed<boolean>(() =>
  ['office_invoice', 'lr_receipt', 'lorry_receipt'].includes(
    formData.value.template_name ?? '',
  ),
)

// Estimate context: when the form is an estimate (not an invoice), we render
// the Rate Card Matrix table (Station rows × Weight columns) instead of the
// standard row-based table.
const isEstimateContext = computed<boolean>(() =>
  props.storeProp === 'newEstimate',
)

const defaultCurrency = computed(() => {
  if (props.currency) {
    return props.currency
  }
  return null
})

const taxIncludedField = computed<boolean>({
  get: () => {
    return !!formData.value.tax_included
  },
  set: (value: boolean) => {
    formData.value.tax_included = value
  },
})

// --- Rate Card Matrix helpers ---
// These functions power the matrix table in estimate context.  Each item's
// rate_card is { "unitId": rateInCents }.  Columns come from itemStore.itemUnits.

function getMatrixRate(item: DocumentItem, unitId: number | string): number {
  const rateCard = (item.rate_card ?? {}) as Record<string, number>
  return rateCard[String(unitId)] ?? 0
}

function setMatrixRate(itemIndex: number, unitId: number | string, valueCents: number): void {
  props.store.$patch((state: Record<string, unknown>) => {
    const form = state[props.storeProp] as DocumentFormData
    const item = form.items[itemIndex] as DocumentItem
    if (!item.rate_card) {
      item.rate_card = {}
    }
    ;(item.rate_card as Record<string, number>)[String(unitId)] = valueCents
  })
}

function updateMatrixItemName(itemIndex: number, name: string): void {
  props.store.$patch((state: Record<string, unknown>) => {
    const form = state[props.storeProp] as DocumentFormData
    form.items[itemIndex].name = name
  })
}

function onMatrixItemSelect(itemIndex: number, itm: Record<string, unknown>): void {
  props.store.$patch((state: Record<string, unknown>) => {
    const form = state[props.storeProp] as DocumentFormData
    const item = form.items[itemIndex]
    item.name = itm.name as string
    item.item_id = itm.id as number
    item.price = itm.price as number
    item.description = (itm.description as string | null) ?? null

    // Auto-fill rate_card from the selected item's saved rate card
    if (itm.rate_card) {
      item.rate_card = itm.rate_card as Record<string, number>
    }

    // Auto-fill truck_type from the selected item
    if (itm.truck_type) {
      item.truck_type = itm.truck_type as string
    }

    if (form.exchange_rate) {
      item.price = Math.round(item.price / form.exchange_rate)
    }
  })
}

// Fetch item units (truck weights) for the matrix columns
onMounted(() => {
  if (isEstimateContext.value && itemStore.itemUnits.length === 0) {
    itemStore.fetchItemUnits({ limit: 'all' })
  }
})
</script>
