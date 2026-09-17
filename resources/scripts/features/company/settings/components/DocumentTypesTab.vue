<script setup lang="ts">
/**
 * DocumentTypesTab — Feature on/off toggles.
 *
 * Refactored:
 * - Uses documentTypeToggles from the settings registry (no more inline array)
 * - Uses useSettings composable instead of inject/provide
 * - Uses local form state with explicit Save button (no auto-save on mount)
 */
import { ref, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import { documentTypeToggles } from '../config/settings-registry'
import { useSettings } from '../composables/useSettings'

const { t } = useI18n()
const { getSetting, saveSettings } = useSettings()

const isSaving = ref(false)

// Build local form state from current settings — no auto-save
const form = reactive<Record<string, boolean>>({})

documentTypeToggles.forEach((docType) => {
  form[docType.key] = getSetting(docType.key) === 'YES'
})

async function submitForm(): Promise<void> {
  isSaving.value = true

  const data: Record<string, string> = {}
  documentTypeToggles.forEach((docType) => {
    data[docType.key] = form[docType.key] ? 'YES' : 'NO'
  })

  await saveSettings(data, 'general.setting_updated')
  isSaving.value = false
}
</script>

<template>
  <BaseSettingCard
    :title="t('settings.customization.document_types.title')"
    :description="t('settings.customization.document_types.description')"
  >
    <form @submit.prevent="submitForm">
      <ul class="divide-y divide-line-default">
        <BaseSwitchSection
          v-for="docType in documentTypeToggles"
          :key="docType.key"
          v-model="form[docType.key]"
          :title="t(docType.titleKey)"
          :description="t(docType.descriptionKey)"
        />
      </ul>

      <p class="mt-4 text-sm text-muted">
        {{ t('settings.customization.document_types.reload_notice') }}
      </p>

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
  </BaseSettingCard>
</template>
