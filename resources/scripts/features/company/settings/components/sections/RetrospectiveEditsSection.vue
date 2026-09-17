<script setup lang="ts">
/**
 * RetrospectiveEditsSection — Generic retrospective edits component.
 *
 * Replaces 6 near-identical files:
 * - InvoicesTabRetrospective.vue (radio group variant)
 * - LrReceiptsTabRetrospective.vue (toggle variant)
 * - LorryReceiptsTabRetrospective.vue (toggle variant)
 * - InvoiceReceiptsTabRetrospective.vue (toggle variant)
 * - QuotationsTabRetrospective.vue (toggle variant)
 * - PaymentsTabRetrospective.vue (toggle variant)
 *
 * Two variants driven by config:
 * - 'invoice' = radio group with enum options
 * - 'toggle'  = simple YES/NO switch
 */
import { ref, reactive, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useSettings } from '../../composables/useSettings'
import type { RetrospectiveConfig } from '../../config/settings-registry'

interface Props {
  config: RetrospectiveConfig
  successMessageKey?: string
}

const props = withDefaults(defineProps<Props>(), {
  successMessageKey: 'general.setting_updated',
})

const { t } = useI18n()
const { getSetting, getSettingOrDefault, saveSettings } = useSettings()

const isSaving = ref(false)

// ── Toggle variant ──
const allowEditField = computed<boolean>({
  get: () => getSetting(props.config.settingKey) === 'YES',
  set: (newValue: boolean) => {
    form[props.config.settingKey] = newValue ? 'YES' : 'NO'
  },
})

// ── Radio variant ──
const form = reactive<Record<string, string>>({
  [props.config.settingKey]: getSettingOrDefault(props.config.settingKey, 'allow'),
})

async function submitForm(): Promise<void> {
  isSaving.value = true
  await saveSettings(
    { [props.config.settingKey]: form[props.config.settingKey] },
    props.successMessageKey,
  )
  isSaving.value = false
}

// For toggle variant, save immediately on change
async function submitToggle(): Promise<void> {
  isSaving.value = true
  await saveSettings(
    { [props.config.settingKey]: form[props.config.settingKey] },
    props.successMessageKey,
  )
  isSaving.value = false
}
</script>

<template>
  <!-- Toggle variant -->
  <form v-if="config.variant === 'toggle'" @submit.prevent="submitToggle">
    <BaseSwitchSection
      v-model="allowEditField"
      :title="t('settings.customization.allow_edit_after_send')"
      :description="t('settings.customization.allow_edit_after_send_description')"
    />

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

  <!-- Radio variant (invoice) -->
  <form v-else @submit.prevent="submitForm">
    <BaseInputGroup required>
      <BaseRadio
        v-for="option in config.enumOptions"
        :id="option.value"
        :key="option.value"
        v-model="form[config.settingKey]"
        :label="t(option.labelKey)"
        size="sm"
        :name="config.settingKey"
        :value="option.value"
        class="mt-2"
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
