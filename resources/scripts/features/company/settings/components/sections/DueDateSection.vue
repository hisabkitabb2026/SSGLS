<script setup lang="ts">
/**
 * DueDateSection — Generic due date settings component.
 *
 * Replaces InvoicesTabDueDate.vue.
 * Config-driven: receives autoKey and daysKey from the registry.
 */
import { ref, computed, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import { numeric, helpers, requiredIf } from '@vuelidate/validators'
import useVuelidate from '@vuelidate/core'
import { useSettings } from '../../composables/useSettings'

interface Props {
  autoKey: string
  daysKey: string
  successMessageKey?: string
}

const props = withDefaults(defineProps<Props>(), {
  successMessageKey: 'general.setting_updated',
})

const { t } = useI18n()
const { getSetting, getSettingOrDefault, saveSettings } = useSettings()

const isSaving = ref(false)

const form = reactive<{
  auto: string
  days: string
}>({
  auto: getSettingOrDefault(props.autoKey, 'NO'),
  days: getSetting(props.daysKey) ?? '',
})

const autoField = computed<boolean>({
  get: () => form.auto === 'YES',
  set: (newValue: boolean) => {
    form.auto = newValue ? 'YES' : 'NO'
  },
})

const rules = computed(() => ({
  form: {
    days: {
      required: helpers.withMessage(
        t('validation.required'),
        requiredIf(autoField.value),
      ),
      numeric: helpers.withMessage(t('validation.numbers_only'), numeric),
    },
  },
}))

const v$ = useVuelidate(rules, { form })

async function submitForm(): Promise<void> {
  v$.value.form.$touch()

  if (v$.value.form.$invalid) return

  isSaving.value = true

  const data: Record<string, string> = {
    [props.autoKey]: form.auto,
  }

  if (autoField.value) {
    data[props.daysKey] = form.days
  }

  await saveSettings(data, props.successMessageKey)
  isSaving.value = false
}
</script>

<template>
  <form @submit.prevent="submitForm">
    <BaseSwitchSection
      v-model="autoField"
      :title="t('settings.customization.set_due_date_automatically')"
      :description="t('settings.customization.set_due_date_automatically_description')"
    />

    <BaseInputGroup
      v-if="autoField"
      :label="t('settings.customization.due_date_days')"
      :error="v$.form.days.$error && v$.form.days.$errors[0].$message"
      class="mt-2 mb-4"
    >
      <div class="w-full sm:w-1/2 md:w-1/4 lg:w-1/5">
        <BaseInput
          v-model="form.days"
          :invalid="v$.form.days.$error"
          type="number"
          @input="v$.form.days.$touch()"
        />
      </div>
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
