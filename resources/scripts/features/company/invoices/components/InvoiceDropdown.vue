<template>
  <BaseDropdown>
    <template #activator>
      <BaseButton v-if="isDetailView" variant="primary">
        <BaseIcon name="EllipsisHorizontalIcon" class="h-5 text-white" />
      </BaseButton>
      <BaseIcon v-else name="EllipsisHorizontalIcon" class="h-5 text-muted" />
    </template>

    <!-- Edit Invoice / LR Receipt / Lorry Receipt -->
    <router-link
      v-if="canEdit"
      :to="`${basePath}/${row.id}/edit`"
    >
      <BaseDropdownItem>
        <BaseIcon
          name="PencilIcon"
          class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
        />
        {{ $t('general.edit') }}
      </BaseDropdownItem>
    </router-link>

    <!-- Receive Material (only for LR Receipts) -->
    <BaseDropdownItem v-if="isLrReceipt" @click="receiveMaterial">
      <BaseIcon
        name="ArchiveBoxIcon"
        class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
      />
      Receive Material
    </BaseDropdownItem>

    <!-- Copy PDF url (hidden for Lorry Receipts) -->
    <BaseDropdownItem v-if="isDetailView && !isLorryReceipt" @click="copyPdfUrl">
      <BaseIcon
        name="LinkIcon"
        class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
      />
      {{ $t('general.copy_pdf_url') }}
    </BaseDropdownItem>

    <!-- View Invoice (hidden for LR Receipts — Edit replaces it) -->
    <router-link
      v-if="!isDetailView && canView && !isLrReceipt"
      :to="`/admin/invoices/${row.id}/view`"
    >
      <BaseDropdownItem>
        <BaseIcon
          name="EyeIcon"
          class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
        />
        {{ $t('general.view') }}
      </BaseDropdownItem>
    </router-link>

    <!-- Send Invoice / Lorry Receipt Mail -->
    <BaseDropdownItem v-if="canSendInvoice" @click="sendInvoice">
      <BaseIcon
        name="PaperAirplaneIcon"
        class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
      />
      {{ isLorryReceipt ? 'Send Lorry Receipt' : $t('invoices.send_invoice') }}
    </BaseDropdownItem>

    <!-- Resend Invoice / Lorry Receipt -->
    <BaseDropdownItem v-if="canReSendInvoice && !isDetailView" @click="sendInvoice">
      <BaseIcon
        name="PaperAirplaneIcon"
        class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
      />
      {{ isLorryReceipt ? 'Resend Lorry Receipt' : $t('invoices.resend_invoice') }}
    </BaseDropdownItem>

    <!-- Record Payment (hidden for LR Receipts) -->
    <router-link v-if="!isLrReceipt" :to="recordPaymentLink">
      <BaseDropdownItem
        v-if="row.paid_status !== 'PAID' && !isDetailView && canCreatePayment"
      >
        <BaseIcon
          name="CreditCardIcon"
          class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
        />
        {{ $t('invoices.record_payment') }}
      </BaseDropdownItem>
    </router-link>


    <!-- Mark as Sent -->
    <BaseDropdownItem v-if="row.status === 'DRAFT' && !isDetailView && canSend" @click="onMarkAsSent">
      <BaseIcon
        name="CheckCircleIcon"
        class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
      />
      {{ $t('invoices.mark_as_sent') }}
    </BaseDropdownItem>

    <!-- Clone Invoice / LR / Lorry Receipt -->
    <BaseDropdownItem v-if="canCreate" @click="cloneInvoiceData">
      <BaseIcon
        name="DocumentTextIcon"
        class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
      />
      {{ isLorryReceipt ? 'Clone Lorry Receipt' : (isLrReceipt ? 'Clone LR' : $t('invoices.clone_invoice')) }}
    </BaseDropdownItem>

    <!-- Download Invoice / LR / Lorry Receipt PDF -->
    <BaseDropdownItem @click="downloadInvoicePdf">
      <BaseIcon
        name="ArrowDownTrayIcon"
        class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
      />
      {{ isLorryReceipt ? 'Download Lorry Receipt' : (isLrReceipt ? 'Download LR' : 'Download Invoice') }}
    </BaseDropdownItem>

    <!-- Download Multi LR (only for LR Receipts) -->
    <BaseDropdownItem v-if="isLrReceipt" @click="downloadMultiLr">
      <BaseIcon
        name="DocumentDuplicateIcon"
        class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
      />
      Download Multi LR
    </BaseDropdownItem>

    <!-- Delete Invoice -->
    <BaseDropdownItem v-if="canDelete" @click="removeInvoice">
      <BaseIcon
        name="TrashIcon"
        class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
      />
      {{ $t('general.delete') }}
    </BaseDropdownItem>
  </BaseDropdown>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useInvoiceStore } from '../store'
import { useDialogStore } from '../../../../stores/dialog.store'
import { useModalStore } from '../../../../stores/modal.store'
import { useNotificationStore } from '../../../../stores/notification.store'
import type { Invoice } from '../../../../types/domain/invoice'

interface TableRef {
  refresh: () => void
}

interface Props {
  row: Invoice & Record<string, unknown>
  table?: TableRef | null
  loadData?: () => void
  canEdit?: boolean
  canView?: boolean
  canCreate?: boolean
  canDelete?: boolean
  canSend?: boolean
  canCreatePayment?: boolean
  canCreateEstimate?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  table: null,
  loadData: () => {},
  canEdit: false,
  canView: false,
  canCreate: false,
  canDelete: false,
  canSend: false,
  canCreatePayment: false,
  canCreateEstimate: false,
})

const invoiceStore = useInvoiceStore()
const dialogStore = useDialogStore()
const modalStore = useModalStore()
const notificationStore = useNotificationStore()
const { t } = useI18n()
const route = useRoute()
const router = useRouter()

const isDetailView = computed<boolean>(() =>
  route.name === 'invoices.view' ||
  route.name === 'standard-invoices.view' ||
  route.name === 'lr-receipts.view' ||
  route.name === 'lorry-receipts.view',
)

// Detect LR Receipt context from the route name
const isLrReceipt = computed<boolean>(() =>
  route.name?.toString().startsWith('lr-receipts') ?? false,
)

// Detect Lorry Receipt context from the route name
const isLorryReceipt = computed<boolean>(() =>
  route.name?.toString().startsWith('lorry-receipts') ?? false,
)

// Base path for edit/view/delete redirects — respects the current document type
const basePath = computed<string>(() => {
  if (isLrReceipt.value) return '/admin/lr-receipts'
  if (route.name?.toString().startsWith('lorry-receipts')) return '/admin/lorry-receipts'
  if (route.name?.toString().startsWith('standard-invoices')) return '/admin/standard-invoices'
  return '/admin/invoices'
})

// Build the "Record Payment" link with a `from` query param so the payment
// page knows which invoice list the user came from (standard-invoices vs
// invoices). This lets us redirect back to the correct list after saving.
const recordPaymentLink = computed(() => {
  const from = route.path.includes('/admin/standard-invoices')
    ? 'standard-invoices'
    : 'invoices'
  return `/admin/payments/${props.row.id}/create?from=${from}`
})


const canReSendInvoice = computed<boolean>(() => {
  return (
    (props.row.status === 'SENT' || props.row.status === 'VIEWED') &&
    props.canSend
  )
})

const canSendInvoice = computed<boolean>(() => {
  return (
    props.row.status === 'DRAFT' &&
    !isDetailView.value &&
    props.canSend
  )
})

function removeInvoice(): void {
  dialogStore.openDialog({
    title: t('general.are_you_sure'),
    message: t('invoices.confirm_delete'),
    yesLabel: t('general.ok'),
    noLabel: t('general.cancel'),
    variant: 'danger',
    hideNoButton: false,
    size: 'lg',
  }).then(async (res: boolean) => {
    if (res) {
      const response = await invoiceStore.deleteInvoice({ ids: [props.row.id] })
      if (response.data.success) {
        router.push(basePath.value)
        props.table?.refresh()
        invoiceStore.$patch((state) => {
          state.selectedInvoices = []
          state.selectAllField = false
        })
      }
    }
  })
}

function cloneInvoiceData(): void {
  dialogStore.openDialog({
    title: t('general.are_you_sure'),
    message: t('invoices.confirm_clone'),
    yesLabel: t('general.ok'),
    noLabel: t('general.cancel'),
    variant: 'primary',
    hideNoButton: false,
    size: 'lg',
  }).then(async (res: boolean) => {
    if (res) {
      const response = await invoiceStore.cloneInvoice({ id: props.row.id })
      router.push(`${basePath.value}/${response.data.data.id}/edit`)
    }
  })
}

function downloadInvoicePdf(): void {
  const pdfUrl = `${window.location.origin}/invoices/pdf/${props.row.unique_hash}`
  window.open(pdfUrl, '_blank')
}

function downloadMultiLr(): void {
  const pdfUrl = `${window.location.origin}/invoices/pdf/${props.row.unique_hash}?copy=multi`
  window.open(pdfUrl, '_blank')
}

function receiveMaterial(): void {
  const customerId = props.row.customer_id || props.row.customer?.id
  router.push({
    path: '/admin/warehouse-items/create',
    query: {
      customer: customerId ? String(customerId) : undefined,
      lr: String(props.row.id),
    },
  })
}

function onMarkAsSent(): void {
  dialogStore.openDialog({
    title: t('general.are_you_sure'),
    message: t('invoices.invoice_mark_as_sent'),
    yesLabel: t('general.ok'),
    noLabel: t('general.cancel'),
    variant: 'primary',
    hideNoButton: false,
    size: 'lg',
  }).then(async (res: boolean) => {
    if (res) {
      await invoiceStore.markAsSent({ id: props.row.id, status: 'SENT' })
      props.table?.refresh()
    }
  })
}

function sendInvoice(): void {
  modalStore.openModal({
    title: t('invoices.send_invoice'),
    componentName: 'SendInvoiceModal',
    id: props.row.id,
    data: props.row,
    variant: 'sm',
  })
}

function copyPdfUrl(): void {
  const pdfUrl = `${window.location.origin}/invoices/pdf/${props.row.unique_hash}`
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
</script>
