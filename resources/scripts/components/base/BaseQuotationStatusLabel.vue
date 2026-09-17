<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

interface Props {
  status?: string
}

const props = withDefaults(defineProps<Props>(), {
  status: '',
})

const { t } = useI18n()

const labelStatus = computed<string>(() => {
  switch (props.status) {
    case 'DRAFT':
      return t('quotations.draft')
    case 'SENT':
      return t('quotations.sent')
    case 'VIEWED':
      return t('quotations.viewed')
    case 'EXPIRED':
      return t('quotations.expired')
    case 'ACCEPTED':
      return t('quotations.accepted')
    case 'REJECTED':
      return t('quotations.rejected')
    default:
      return props.status
  }
})
</script>

<template>
  {{ labelStatus }}
</template>
