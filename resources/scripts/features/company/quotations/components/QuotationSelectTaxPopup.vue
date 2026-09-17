<template>
  <div class="w-full mt-4 tax-select">
    <Popover v-slot="{ isOpen }" class="relative">
      <PopoverButton
        :class="isOpen ? '' : ''"
        class="
          flex
          items-center
          text-sm
          font-medium
          text-primary-400
          focus:outline-hidden focus:border-none
        "
      >
        <BaseIcon
          name="PlusIcon"
          class="w-4 h-4 font-medium text-primary-400"
        />
        {{ $t('settings.tax_types.add_tax') }}
      </PopoverButton>

      <!-- Tax Select Popup -->
      <div class="relative w-full max-w-md px-4">
        <transition
          enter-active-class="transition duration-200 ease-out"
          enter-from-class="translate-y-1 opacity-0"
          enter-to-class="translate-y-0 opacity-100"
          leave-active-class="transition duration-150 ease-in"
          leave-from-class="translate-y-0 opacity-100"
          leave-to-class="translate-y-1 opacity-0"
        >
          <PopoverPanel
            v-slot="{ close }"
            style="min-width: 350px; margin-left: 62px; top: -28px"
            class="absolute z-10 px-4 py-2 -translate-x-full sm:px-0"
          >
            <div
              class="
                overflow-hidden
                rounded-md
                shadow-lg
                ring-1 ring-black/5
              "
            >
              <!-- Tax Search Input  -->
              <div class="relative bg-surface">
                <div class="relative p-4">
                  <BaseInput
                    v-model="textSearch"
                    :placeholder="$t('general.search')"
                    type="text"
                    class="text-black"
                  />
                </div>

                <!-- List of Taxes  -->
                <div
                  v-if="filteredTaxTypes.length > 0"
                  class="
                    relative
                    flex flex-col
                    overflow-auto
                    list
                    max-h-36
                    border-t border-line-default
                  "
                >
                  <div
                    v-for="(taxType, index) in filteredTaxTypes"
                    :key="index"
                    :class="{
                      'bg-surface-tertiary cursor-not-allowed opacity-50 pointer-events-none':
                        taxes.find((val) => {
                          return val.tax_type_id === taxType.id
                        }),
                    }"
                    tabindex="2"
                    class="
                      px-6
                      py-4
                      border-b border-line-default border-solid
                      cursor-pointer
                      hover:bg-hover hover:cursor-pointer
                      last:border-b-0
                    "
                    @click="selectTaxType(taxType, close)"
                  >
                    <div class="flex justify-between px-2">
                      <label
                        class="
                          m-0
                          text-base
                          font-semibold
                          leading-tight
                          text-heading
                          cursor-pointer
                        "
                      >
                        {{ taxType.name }}
                      </label>

                      <label
                        class="
                          m-0
                          text-base
                          font-semibold
                          text-heading
                          cursor-pointer
                        "
                      >
                        <template v-if="taxType.calculation_type === 'fixed'">
                          <BaseFormatMoney
                            :amount="taxType.fixed_amount"
                            :currency="companyStore.selectedCompanyCurrency"
                          />
                        </template>
                        <template v-else>
                          {{ taxType.percent }} %
                        </template>
                      </label>
                    </div>
                  </div>
                </div>

                <div v-else class="flex justify-center p-5 text-subtle">
                  <label class="text-base text-muted cursor-pointer">
                    {{ $t('general.no_tax_found') }}
                  </label>
                </div>
              </div>

              <!-- Add new Tax action -->
              <BaseButton
                v-if="userStore.hasAbilities(['create-tax-type'])"
                variant="gray"
                class="w-full h-10"
                @click="openTaxTypeModal"
              >
                <BaseIcon name="CheckCircleIcon" class="text-primary-400" />
                <span class="ml-3 text-sm leading-none font-base text-primary-400">
                  {{ $t('quotations.add_new_tax') }}
                </span>
              </BaseButton>
            </div>
          </PopoverPanel>
        </transition>
      </div>
    </Popover>
  </div>
</template>

<script setup lang="ts">
import { Popover, PopoverButton, PopoverPanel } from '@headlessui/vue'
import { computed, ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useModalStore } from '@/scripts/stores/modal.store'
import { useUserStore } from '@/scripts/stores/user.store'
import { useCompanyStore } from '@/scripts/stores/company.store'
import { taxTypeService } from '@/scripts/api/services/tax-type.service'
import type { TaxType } from '@/scripts/types/domain/tax'

interface Props {
  type?: string | null
  store: Record<string, unknown> | null
  storeProp: string
}

const props = withDefaults(defineProps<Props>(), {
  type: null,
  store: null,
  storeProp: '',
})

const emit = defineEmits<{
  'select:tax-type': [taxType: TaxType]
}>()

const modalStore = useModalStore()
const userStore = useUserStore()
const companyStore = useCompanyStore()
const { t } = useI18n()

const textSearch = ref<string>('')
const taxTypes = ref<TaxType[]>([])

onMounted(async () => {
  try {
    const response = await taxTypeService.list()
    taxTypes.value = response.data
  } catch (error) {
    console.error('Failed to load tax types:', error)
  }
})

const filteredTaxTypes = computed<TaxType[]>(() => {
  if (!textSearch.value) {
    return taxTypes.value
  }
  return taxTypes.value.filter((taxType) =>
    taxType.name.toLowerCase().includes(textSearch.value.toLowerCase())
  )
})

const taxes = computed(() => {
  return (props.store?.[props.storeProp] as Record<string, unknown>)?.taxes || []
})

function selectTaxType(data: TaxType, close: () => void): void {
  emit('select:tax-type', { ...data })
  close()
}

function openTaxTypeModal(): void {
  modalStore.openModal({
    title: t('settings.tax_types.add_tax'),
    componentName: 'TaxTypeModal',
    size: 'sm',
    data: {
      refreshData: (data: TaxType) => {
        emit('select:tax-type', data)
      },
    },
  })
}
</script>
