<script setup lang="ts">
/**
 * EmailAttachmentSection — Generic email attachment toggle.
 *
 * Replaces the inline email attachment toggle blocks that were duplicated
 * across 7 tab components.
 *
 * Uses a form with explicit Save button — no auto-save on mount.
 */
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useSettings } from '../../composables/useSettings'

interface Props {
  settingKey: string
  titleKey: string
  descriptionKey: string
  successMessageKey?: string
}

const props = withDefaults(defineProps<Props>(), {
  successMessageKey: 'general.setting_updated',
})

const { t } = useI18n()
const { getSetting, saveSettings } = useSettings()

const isSaving = ref(false)

// Local form state — initialized from settings, saved on submit
const sendAsAttachment = ref<boolean>(getSetting(props.settingKey) === 'YES')

async function submitForm(): Promise<void> {
  isSaving.value = true
  await saveSettings(
    { [props.settingKey]: sendAsAttachment.value ? 'YES' : 'NO' },
    props.successMessageKey,
  )
  isSaving.value = false
}
</script>

<template>
  <form @submit.prevent="submitForm">
    <ul class="divide-y divide-line-default">
      <BaseSwitchSection
        v-model="sendAsAttachment"
        :title="t(props.titleKey)"
        :description="t(props.descriptionKey)"
      />
    </ul>

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
