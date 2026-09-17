<script setup lang="ts">
/**
 * ConvertEstimateSection — Generic convert estimate settings component.
 *
 * Replaces EstimatesTabConvertEstimate.vue.
 * Config-driven: receives settingKey and enumOptions from the registry.
 */
import { reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import { useSettings } from '../../composables/useSettings'
import type { EnumOption } from '../../config/settings-registry'

interface Props {
  settingKey: string
  enumOptions: EnumOption[]
  successMessageKey?: string
}

const props = withDefaults(defineProps<Props>(), {
  successMessageKey: 'general.setting_updated',
})

const { t } = useI18n()
const { getSettingOrDefault, saveSettings } = useSettings()

const form = reactive<Record<string, string>>({
  [props.settingKey]: getSettingOrDefault(props.settingKey, 'no_action'),
})

async function submitForm(): Promise<void> {
  await saveSettings(
    { [props.settingKey]: form[props.settingKey] },
    props.successMessageKey,
  )
}
</script>

<template>
  <form @submit.prevent="submitForm">
    <BaseInputGroup required>
      <BaseRadio
        v-for="option in props.enumOptions"
        :id="option.value"
        :key="option.value"
        v-model="form[props.settingKey]"
        :label="t(option.labelKey)"
        size="sm"
        :name="props.settingKey"
        :value="option.value"
        class="mt-2"
      />
    </BaseInputGroup>

    <BaseButton
      variant="primary"
      type="submit"
      class="mt-4"
    >
      <template #left="slotProps">
        <BaseIcon
          :class="slotProps.class"
          name="ArrowDownOnSquareIcon"
        />
      </template>
      {{ t('settings.customization.save') }}
    </BaseButton>
  </form>

</template>
