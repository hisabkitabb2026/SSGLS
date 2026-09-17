<template>
  <div class="md:grid-cols-12 grid-cols-1 md:gap-x-6 mt-6 mb-8 grid gap-y-5">
    <BaseCustomerSelectPopup
      :valid="v.customer_id"
      :content-loading="isLoading"
      type="quotation"
      label="New Party"
      class="col-span-6 pr-0"
    />

    <BaseInputGrid
      class="col-span-6 rounded-xl shadow border border-line-light bg-surface p-5"
    >
      <BaseInputGroup
        :label="$t('reports.estimates.estimate_date')"
        :content-loading="isLoading"
        required
        :error="v.estimate_date.$error && v.estimate_date.$errors[0].$message"
      >
        <BaseDatePicker
          v-model="quotationStore.newQuotation.estimate_date"
          :content-loading="isLoading"
          :calendar-button="true"
          calendar-button-icon="calendar"
        />
      </BaseInputGroup>

      <BaseInputGroup
        :label="$t('quotations.expiry_date')"
        :content-loading="isLoading"
      >
        <BaseDatePicker
          v-model="quotationStore.newQuotation.expiry_date"
          :content-loading="isLoading"
          :calendar-button="true"
          calendar-button-icon="calendar"
        />
      </BaseInputGroup>

      <BaseInputGroup
        :label="$t('quotations.estimate_number')"
        :content-loading="isLoading"
        required
        :error="
          v.estimate_number.$error && v.estimate_number.$errors[0].$message
        "
      >
        <BaseInput
          v-model="quotationStore.newQuotation.estimate_number"
          :content-loading="isLoading"
        />
      </BaseInputGroup>

    </BaseInputGrid>
  </div>
</template>

<script setup lang="ts">
import { useQuotationStore } from '../store'

interface ValidationField {
  $error: boolean
  $errors: Array<{ $message: string }>
  $touch: () => void
}

interface Props {
  v: Record<string, ValidationField>
  isLoading?: boolean
  isEdit?: boolean
}

withDefaults(defineProps<Props>(), {
  isLoading: false,
  isEdit: false,
})

const quotationStore = useQuotationStore()
</script>
