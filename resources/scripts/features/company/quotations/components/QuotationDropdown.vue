<template>
  <BaseDropdown>
    <template #activator>
      <BaseButton v-if="isDetailView" variant="primary">
        <BaseIcon name="EllipsisHorizontalIcon" class="text-white" />
      </BaseButton>
      <BaseIcon v-else class="text-muted" name="EllipsisHorizontalIcon" />
    </template>

    <!-- Copy PDF url -->
    <BaseDropdownItem v-if="isDetailView" @click="copyPdfUrl">
      <BaseIcon
        name="LinkIcon"
        class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
      />
      {{ $t('general.copy_pdf_url') }}
    </BaseDropdownItem>

    <!-- Edit Quotation -->
    <router-link
      v-if="canEdit"
      :to="`/admin/quotations/${row.id}/edit`"
    >
      <BaseDropdownItem>
        <BaseIcon
          name="PencilIcon"
          class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
        />
        {{ $t('general.edit') }}
      </BaseDropdownItem>
    </router-link>

    <!-- Delete Quotation -->
    <BaseDropdownItem v-if="canDelete" @click="removeQuotation">
      <BaseIcon
        name="TrashIcon"
        class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
      />
      {{ $t('general.delete') }}
    </BaseDropdownItem>

    <!-- View Quotation -->
    <router-link
      v-if="!isDetailView && canView"
      :to="`quotations/${row.id}/view`"
    >
      <BaseDropdownItem>
        <BaseIcon
          name="EyeIcon"
          class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
        />
        {{ $t('general.view') }}
      </BaseDropdownItem>
    </router-link>

    <!-- Clone Quotation -->
    <BaseDropdownItem v-if="canCreate" @click="cloneQuotationData">
      <BaseIcon
        name="DocumentTextIcon"
        class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
      />
      {{ $t('quotations.clone_estimate') }}
    </BaseDropdownItem>

    <!-- Convert into Invoice -->
    <BaseDropdownItem v-if="canCreateInvoice && row.status !== 'REJECTED'" @click="convertToInvoice">
      <BaseIcon
        name="DocumentTextIcon"
        class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
      />
      {{ $t('quotations.convert_to_invoice') }}
    </BaseDropdownItem>

    <!-- Mark as Sent -->
    <BaseDropdownItem
      v-if="row.status !== 'SENT' && !isDetailView && canSend"
      @click="onMarkAsSent"
    >
      <BaseIcon
        name="CheckCircleIcon"
        class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
      />
      {{ $t('quotations.mark_as_sent') }}
    </BaseDropdownItem>

    <!-- Send Quotation -->
    <BaseDropdownItem
      v-if="row.status !== 'SENT' && !isDetailView && canSend"
      @click="sendQuotation"
    >
      <BaseIcon
        name="PaperAirplaneIcon"
        class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
      />
      {{ $t('quotations.send_estimate') }}
    </BaseDropdownItem>

    <!-- Resend Quotation -->
    <BaseDropdownItem v-if="canResendQuotation" @click="sendQuotation">
      <BaseIcon
        name="PaperAirplaneIcon"
        class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
      />
      {{ $t('quotations.resend_estimate') }}
    </BaseDropdownItem>

    <!-- Mark as Accepted -->
    <BaseDropdownItem
      v-if="row.status !== 'ACCEPTED' && row.status !== 'REJECTED' && canEdit"
      @click="onMarkAsAccepted"
    >
      <BaseIcon
        name="CheckCircleIcon"
        class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
      />
      {{ $t('quotations.mark_as_accepted') }}
    </BaseDropdownItem>

    <!-- Mark as Rejected -->
    <BaseDropdownItem
      v-if="row.status !== 'REJECTED' && row.status !== 'ACCEPTED' && canEdit"
      @click="onMarkAsRejected"
    >
      <BaseIcon
        name="XCircleIcon"
        class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
      />
      {{ $t('quotations.mark_as_rejected') }}
    </BaseDropdownItem>
  </BaseDropdown>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useQuotationStore } from '../store'
import { useDialogStore } from '../../../../stores/dialog.store'
import { useModalStore } from '../../../../stores/modal.store'
import { useNotificationStore } from '../../../../stores/notification.store'
import type { Quotation } from '../../../../types/domain/quotation'

interface TableRef {
  refresh: () => void
}

interface Props {
  row: Quotation & Record<string, unknown>
  table?: TableRef | null
  canEdit?: boolean
  canView?: boolean
  canCreate?: boolean
  canDelete?: boolean
  canSend?: boolean
  canCreateInvoice?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  table: null,
  canEdit: false,
  canView: false,
  canCreate: false,
  canDelete: false,
  canSend: false,
  canCreateInvoice: false,
})

const quotationStore = useQuotationStore()
const dialogStore = useDialogStore()
const modalStore = useModalStore()
const notificationStore = useNotificationStore()
const { t } = useI18n()
const route = useRoute()
const router = useRouter()

const isDetailView = computed<boolean>(() => route.name === 'quotations.view')

const canResendQuotation = computed<boolean>(() => {
  return (
    (props.row.status === 'SENT' || props.row.status === 'VIEWED') &&
    !isDetailView.value &&
    props.canSend
  )
})

function removeQuotation(): void {
  dialogStore.openDialog({
    title: t('general.are_you_sure'),
    message: t('quotations.confirm_delete'),
    yesLabel: t('general.ok'),
    noLabel: t('general.cancel'),
    variant: 'danger',
    hideNoButton: false,
    size: 'lg',
  }).then(async (res: boolean) => {
    if (res) {
      const response = await quotationStore.deleteQuotation({ ids: [props.row.id] })
      if (response.data) {
        props.table?.refresh()
        if (response.data.success) {
          router.push('/admin/quotations')
        }
        quotationStore.$patch((state) => {
          state.selectedQuotations = []
          state.selectAllField = false
        })
      }
    }
  })
}

function convertToInvoice(): void {
  dialogStore.openDialog({
    title: t('general.are_you_sure'),
    message: t('quotations.confirm_conversion'),
    yesLabel: t('general.ok'),
    noLabel: t('general.cancel'),
    variant: 'primary',
    hideNoButton: false,
    size: 'lg',
  }).then(async (res: boolean) => {
    if (res) {
      const response = await quotationStore.convertToInvoice(props.row.id)
      if (response.data) {
        router.push(`/admin/invoices/${response.data.data.id}/edit`)
      }
    }
  })
}

function onMarkAsSent(): void {
  dialogStore.openDialog({
    title: t('general.are_you_sure'),
    message: t('quotations.confirm_mark_as_sent'),
    yesLabel: t('general.ok'),
    noLabel: t('general.cancel'),
    variant: 'primary',
    hideNoButton: false,
    size: 'lg',
  }).then(async (res: boolean) => {
    if (res) {
      await quotationStore.markAsSent({ id: props.row.id, status: 'SENT' })
      props.table?.refresh()
    }
  })
}

function sendQuotation(): void {
  modalStore.openModal({
    title: t('quotations.send_estimate'),
    componentName: 'SendQuotationModal',
    id: props.row.id,
    data: props.row,
    variant: 'lg',
  })
}

function onMarkAsAccepted(): void {
  dialogStore.openDialog({
    title: t('general.are_you_sure'),
    message: t('quotations.confirm_mark_as_accepted'),
    yesLabel: t('general.ok'),
    noLabel: t('general.cancel'),
    variant: 'primary',
    hideNoButton: false,
    size: 'lg',
  }).then(async (res: boolean) => {
    if (res) {
      await quotationStore.markAsAccepted({ id: props.row.id, status: 'ACCEPTED' })
      props.table?.refresh()
    }
  })
}

function onMarkAsRejected(): void {
  dialogStore.openDialog({
    title: t('general.are_you_sure'),
    message: t('quotations.confirm_mark_as_rejected'),
    yesLabel: t('general.ok'),
    noLabel: t('general.cancel'),
    variant: 'danger',
    hideNoButton: false,
    size: 'lg',
  }).then(async (res: boolean) => {
    if (res) {
      await quotationStore.markAsRejected({ id: props.row.id, status: 'REJECTED' })
      props.table?.refresh()
    }
  })
}

function copyPdfUrl(): void {
  const pdfUrl = `${window.location.origin}/quotations/pdf/${props.row.unique_hash}`
  copyToClipboard(pdfUrl)
  notificationStore.showNotification({
    type: 'success',
    message: t('general.copied_pdf_url_clipboard'),
  })
}

function copyToClipboard(text: string): void {
  if (navigator.clipboard && window.isSecureContext) {
    navigator.clipboard.writeText(text)
    return
  }
  const textarea = document.createElement('textarea')
  textarea.value = text
  textarea.style.position = 'fixed'
  textarea.style.opacity = '0'
  document.body.appendChild(textarea)
  textarea.focus()
  textarea.select()
  document.execCommand('copy')
  document.body.removeChild(textarea)
}

function cloneQuotationData(): void {
  dialogStore.openDialog({
    title: t('general.are_you_sure'),
    message: t('quotations.confirm_clone'),
    yesLabel: t('general.ok'),
    noLabel: t('general.cancel'),
    variant: 'primary',
    hideNoButton: false,
    size: 'lg',
  }).then(async (res: boolean) => {
    if (res) {
      const response = await quotationStore.cloneQuotation({ id: props.row.id })
      router.push(`/admin/quotations/${response.data.data.id}/edit`)
    }
  })
}
</script>
