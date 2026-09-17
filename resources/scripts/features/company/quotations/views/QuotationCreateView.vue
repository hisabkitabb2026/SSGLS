<template>
  <QuotationSelectTemplateModal />

  <BasePage class="relative quotation-create-page">
    <form @submit.prevent="submitForm">
      <BasePageHeader :title="pageTitle">
        <BaseBreadcrumb>
          <BaseBreadcrumbItem :title="$t('general.home')" to="/admin/dashboard" />
          <BaseBreadcrumbItem
            :title="$t('quotations.estimate', 2)"
            to="/admin/quotations"
          />
          <BaseBreadcrumbItem
            v-if="isEdit"
            :title="$t('quotations.edit_estimate')"
            to="#"
            active
          />
          <BaseBreadcrumbItem v-else :title="$t('quotations.new_estimate')" to="#" active />
        </BaseBreadcrumb>

        <template #actions>
          <router-link
            v-if="isEdit"
            :to="`/quotations/pdf/${quotationStore.newQuotation.unique_hash}`"
            target="_blank"
          >
            <BaseButton class="mr-3" variant="primary-outline" type="button">
              <span class="flex">
                {{ $t('general.view_pdf') }}
              </span>
            </BaseButton>
          </router-link>

          <BaseButton
            :loading="isSaving"
            :disabled="isSaving"
            :content-loading="isLoadingContent"
            variant="primary"
            type="submit"
          >
            <template #left="slotProps">
              <BaseIcon
                v-if="!isSaving"
                :class="slotProps.class"
                name="ArrowDownOnSquareIcon"
              />
            </template>
            {{ $t('quotations.save_estimate') }}
          </BaseButton>
        </template>
      </BasePageHeader>

      <!-- Select Customer & Basic Fields -->
      <QuotationBasicFields
        :v="v$"
        :is-loading="isLoadingContent"
        :is-edit="isEdit"
      />

      <BaseScrollPane>
        <!-- Quotation Stations & Rates -->
        <div class="mt-10 mb-10">
          <h2 class="text-2xl font-bold text-heading mb-6">{{ $t('quotations.stations') }}</h2>
          <StationsList
            v-model="quotationStore.newQuotation.quotation_stations"
            :max-stations="7"
            @open-rate-modal="handleOpenRateModal"
          />
        </div>

        <!-- Quotation Footer Section -->
        <div
          class="
            block
            mt-10
            quotation-foot
            lg:flex lg:justify-between lg:items-start
          "
        >
          <!-- Quotation Custom Notes -->
          <QuotationCreateNotesField
            :store="quotationStore"
            store-prop="newQuotation"
            :fields="quotationNoteFieldList"
            type="Quotation"
          />

          <!-- Quotation Custom Fields -->
          <QuotationCreateCustomFields
            type="Quotation"
            :is-edit="isEdit"
            :is-loading="isLoadingContent"
            :store="quotationStore"
            store-prop="newQuotation"
            :custom-field-scope="quotationValidationScope"
            class="mb-6"
          />
        </div>
      </BaseScrollPane>
    </form>
  </BasePage>

  <!-- Rate Modal (outside form to prevent submission) -->
  <RateModal
    v-if="showRateModal"
    :rate="editingRate"
    :station="editingStation"
    @save="saveRate"
    @cancel="closeRateModal"
  />
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import cloneDeep from 'lodash/cloneDeep'
import {
  required,
  maxLength,
  helpers,
  requiredIf,
  decimal,
} from '@vuelidate/validators'
import useVuelidate from '@vuelidate/core'
import { useQuotationStore } from '../store'
import { useCompanyStore } from '@/scripts/stores/company.store'
import { useNotificationStore } from '@/scripts/stores/notification.store'
import {
  handleApiError,
  getErrorTranslationKey,
} from '@/scripts/utils/error-handling'
import QuotationBasicFields from '../components/QuotationBasicFields.vue'
import StationsList from '../components/StationsList.vue'
import QuotationCreateNotesField from '../components/QuotationCreateNotesField.vue'
import QuotationCreateCustomFields from '../components/QuotationCreateCustomFields.vue'
import QuotationSelectTemplateModal from '../components/QuotationSelectTemplateModal.vue'
import RateModal from '../components/RateModal.vue'

const quotationStore = useQuotationStore()
const companyStore = useCompanyStore()
const notificationStore = useNotificationStore()
const { t } = useI18n()
const route = useRoute()
const router = useRouter()

const quotationValidationScope = 'newQuotation'
const isSaving = ref<boolean>(false)

// Rate Modal state
const showRateModal = ref<boolean>(false)
const editingStation = ref<any>(null)
const editingRate = ref<any>(null)
const editingStationIndex = ref<number | null>(null)
const editingRateIndex = ref<number | null>(null)

const estimateNoteFieldList = ref<string[]>([
  'customer',
  'company',
  'customerCustom',
  'quotation',
  'quotationCustom',
])

const isLoadingContent = computed<boolean>(
  () => quotationStore.isFetchingInitialSettings,
)

const pageTitle = computed<string>(() =>
  isEdit.value ? t('quotations.edit_estimate') : t('quotations.new_estimate'),
)

const isEdit = computed<boolean>(() => route.name === 'quotations.edit')

const rules = {
  estimate_date: {
    required: helpers.withMessage(t('validation.required'), required),
  },
  estimate_number: {
    required: helpers.withMessage(t('validation.required'), required),
  },
  reference_number: {
    maxLength: helpers.withMessage(t('validation.price_maxlength'), maxLength(255)),
  },
  customer_id: {
    required: helpers.withMessage(t('validation.required'), required),
  },
  exchange_rate: {
    required: requiredIf(() => quotationStore.showExchangeRate),
  },
}

const v$ = useVuelidate(
  rules,
  computed(() => quotationStore.newQuotation),
  { $scope: quotationValidationScope },
)

// Initialization
quotationStore.resetCurrentQuotation()
v$.value.$reset
quotationStore.fetchQuotationInitialSettings(
  isEdit.value,
  { id: route.params.id as string, query: route.query as Record<string, string> },
)

watch(
  () => quotationStore.newQuotation.customer,
  (newVal) => {
    if (newVal && (newVal as Record<string, unknown>).currency) {
      quotationStore.newQuotation.selectedCurrency = (
        newVal as Record<string, unknown>
      ).currency as Record<string, unknown>
    }
  },
)

async function submitForm(): Promise<void> {
  // Block form submission if modal is open
  if (showRateModal.value) {
    return
  }

  v$.value.$touch()

  if (v$.value.$invalid) {
    console.log('Quotation form invalid. Errors:', JSON.stringify(
      v$.value.$errors.map((e: { $property: string; $message: string }) => `${e.$property}: ${e.$message}`)
    ))
    return
  }

  isSaving.value = true

  try {
    const data: Record<string, unknown> = {
      ...cloneDeep(quotationStore.newQuotation),
      sub_total: Math.round(quotationStore.getSubTotal),
      total: Math.round(quotationStore.getTotal),
      tax: Math.round(quotationStore.getTotalTax),
    }

    // Transform quotation_stations to items with rate_card for PDF pivot table
    let items = data.items as Array<Record<string, unknown>>
    const quotationStations = data.quotation_stations as Array<Record<string, unknown>> | undefined

    if ((!items || items.length === 0) && quotationStations && quotationStations.length > 0) {
      // Transform stations to items for PDF rendering
      items = quotationStations.map((station) => ({
        name: station.name || 'Station',
        description: null,
        quantity: 1,
        price: 0,
        discount_type: 'fixed',
        discount: 0,
        discount_val: 0,
        tax: 0,
        taxes: [],
        // Store rates data for PDF generation
        rates: station.rates || [],
      }))
      data.items = items
    } else if (!items || items.length === 0) {
      // Fallback to dummy item if no stations or items
      items = [{
        name: 'Station Rates',
        description: null,
        quantity: 1,
        price: 0,
        discount_type: 'fixed',
        discount: 0,
        discount_val: 0,
        tax: 0,
        taxes: [],
      }]
      data.items = items
    }
    if (data.discount_per_item === 'YES') {
      items.forEach((item, index) => {
        if (item.discount_type === 'fixed') {
          items[index].discount = Math.round((item.discount as number) * 100)
        }
      })
    } else {
      if (data.discount_type === 'fixed') {
        data.discount = Math.round((data.discount as number) * 100)
      }
    }

    const taxes = data.taxes as Array<Record<string, unknown>>
    if (data.tax_per_item !== 'YES' && taxes.length) {
      data.tax_type_ids = taxes.map((tax) => tax.tax_type_id)
    }

    const action = isEdit.value
      ? quotationStore.updateQuotation
      : quotationStore.addQuotation

    const res = await action(data)
    if (res.data.data) {
      router.push(`/admin/quotations/${res.data.data.id}/view`)
    }
  } catch (err: unknown) {
    const normalized = handleApiError(err)
    const translationKey = getErrorTranslationKey(normalized.message)

    notificationStore.showNotification({
      type: 'error',
      message: translationKey ? t(translationKey) : normalized.message,
    })
  } finally {
    isSaving.value = false
  }
}

function handleOpenRateModal(data: any) {
  console.log('handleOpenRateModal called', data)
  editingStation.value = data.station
  editingStationIndex.value = data.stationIndex
  editingRate.value = data.rate
  editingRateIndex.value = data.rateIndex
  showRateModal.value = true
  console.log('showRateModal set to', showRateModal.value)
}

function saveRate(rate: any) {
  const updated = [...quotationStore.newQuotation.quotation_stations]
  const station = updated[editingStationIndex.value!]

  if (editingRateIndex.value !== null) {
    station.rates[editingRateIndex.value] = rate
  } else {
    if (!station.rates) station.rates = []
    station.rates.push(rate)
  }

  updated[editingStationIndex.value!] = station
  quotationStore.newQuotation.quotation_stations = updated
  closeRateModal()
}

function closeRateModal() {
  showRateModal.value = false
  editingRate.value = null
  editingStation.value = null
  editingStationIndex.value = null
  editingRateIndex.value = null
}
</script>
