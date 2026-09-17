<script setup lang="ts">
/**
 * DefaultFormatsSection — Generic default formats component.
 *
 * Replaces 7 near-identical files:
 * - InvoicesTabDefaultFormats.vue
 * - LrReceiptsTabDefaultFormats.vue
 * - LorryReceiptsTabDefaultFormats.vue
 * - OfficeInvoiceTabDefaultFormats.vue
 * - EstimatesTabDefaultFormats.vue
 * - QuotationsTabDefaultFormats.vue
 * - PaymentsTabDefaultFormats.vue
 *
 * Driven entirely by the DefaultFormatsConfig from the settings registry.
 */
import { ref, reactive, watch } from 'vue'

import { useI18n } from 'vue-i18n'
import { useSettings } from '../../composables/useSettings'
import type { DefaultFormatsConfig } from '../../config/settings-registry'


interface Props {
  config: DefaultFormatsConfig
  /** i18n key for the success message */
  successMessageKey?: string
}

const props = withDefaults(defineProps<Props>(), {
  successMessageKey: 'general.setting_updated',
})

const { t } = useI18n()
const { getSetting, saveSettings, selectedCompanySettings } = useSettings()

const isSaving = ref(false)

// Build a reactive form from the config
const form = reactive<Record<string, string>>({})

// Initialize form values from company settings
function initForm(): void {
  props.config.addressFormats.forEach((fmt) => {
    form[fmt.key] = getSetting(fmt.key) ?? ''
  })
  form[props.config.mailBodyKey] = getSetting(props.config.mailBodyKey) ?? ''
}


initForm()


// Re-initialize when company settings are loaded/updated (e.g. after
// bootstrap completes or settings are backfilled via migration)
watch(selectedCompanySettings, () => {
  initForm()
})



async function submitForm(): Promise<void> {
  isSaving.value = true

  const data: Record<string, string> = {}
  data[props.config.mailBodyKey] = form[props.config.mailBodyKey]
  props.config.addressFormats.forEach((fmt) => {
    data[fmt.key] = form[fmt.key]
  })

  await saveSettings(data, props.successMessageKey)
  isSaving.value = false
}
</script>

<template>
  <form @submit.prevent="submitForm">
    <!-- Email Body -->
    <BaseInputGroup
      :label="t(props.config.mailBodyLabelKey)"
      class="mt-6 mb-4"
    >
      <BaseCustomInput
        v-model="form[props.config.mailBodyKey]"
        :fields="props.config.mailBodyFields"
      />
    </BaseInputGroup>

    <!-- Address Formats -->
    <BaseInputGroup
      v-for="addrFormat in props.config.addressFormats"
      :key="addrFormat.key"
      :label="t(addrFormat.labelKey)"
      class="mt-6 mb-4"
    >
      <BaseCustomInput
        v-model="form[addrFormat.key]"
        :fields="addrFormat.customInputFields"
      />
    </BaseInputGroup>

    <BaseButton
      :loading="isSaving"
      :disabled="isSaving"
      variant="primary"
      type="submit"
      class="mt-4"
    >
      <template #left="slotProps">
        <BaseIcon
          v-if="!isSaving"
          :class="slotProps.class"
          name="ArrowDownOnSquareIcon"
        />
      </template>
      {{ t('settings.customization.save') }}
    </BaseButton>
  </form>
</template>
