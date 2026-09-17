<template>
  <div>
    <label class="flex text-heading font-medium text-sm mb-2">
      {{ $t('general.select_template') }}
      <span class="text-sm text-alert-error-text"> *</span>
    </label>
    <BaseButton
      type="button"
      class="flex justify-center w-full text-sm lg:w-auto hover:bg-surface-muted"
      variant="gray"
      @click="openTemplateModal"
    >
      <template #right="slotProps">
        <BaseIcon name="PencilIcon" :class="slotProps.class" />
      </template>
      {{ store[storeProp].template_name }}
    </BaseButton>
  </div>
</template>

<script setup lang="ts">
import { useModalStore } from '@/scripts/stores/modal.store'
import { useI18n } from 'vue-i18n'

interface Props {
  store: Record<string, unknown> | null
  storeProp: string
  componentName?: string
  isMarkAsDefault?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  store: null,
  storeProp: '',
  componentName: 'SelectTemplateModal',
  isMarkAsDefault: false,
})

const modalStore = useModalStore()
const { t } = useI18n()

function openTemplateModal(): void {
  let markAsDefaultDescription = ''
  if (props.storeProp === 'newEstimate') {
    markAsDefaultDescription = t(
      'estimates.mark_as_default_estimate_template_description'
    )
  } else if (props.storeProp === 'newInvoice') {
    markAsDefaultDescription = t(
      'invoices.mark_as_default_invoice_template_description'
    )
  } else if (props.storeProp === 'newQuotation') {
    markAsDefaultDescription = t(
      'quotations.mark_as_default_quotation_template_description'
    )
  }

  modalStore.openModal({
    title: t('general.choose_template'),
    componentName: props.componentName,
    data: {
      templates: props.store?.templates,
      store: props.store,
      storeProp: props.storeProp,
      isMarkAsDefault: props.isMarkAsDefault,
      markAsDefaultDescription,
    },
  })
}
</script>
