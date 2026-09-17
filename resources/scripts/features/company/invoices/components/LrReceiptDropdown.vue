<template>
  <BaseDropdown>
    <template #activator>
      <BaseIcon name="EllipsisHorizontalIcon" class="h-5 text-muted" />
    </template>

    <!-- Edit LR Receipt -->
    <router-link
      v-if="canEdit"
      :to="`/admin/lr-receipts/${row.id}/edit`"
    >
      <BaseDropdownItem>
        <BaseIcon
          name="PencilIcon"
          class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
        />
        Edit
      </BaseDropdownItem>
    </router-link>

    <!-- Create Invoice Receipt — navigates to office invoice create page
         with the LR docket number pre-filled as Consignment No -->
    <router-link :to="createInvoiceReceiptRoute">
      <BaseDropdownItem>
        <BaseIcon
          name="DocumentTextIcon"
          class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
        />
        Create Invoice Receipt
      </BaseDropdownItem>
    </router-link>

    <!-- Create Lorry Receipt — pre-fills the Lorry Receipt with this LR's
         docket number and transport fields -->
    <router-link :to="createLorryReceiptRoute">
      <BaseDropdownItem>
        <BaseIcon
          name="DocumentPlusIcon"
          class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
        />
        Create Lorry Receipt
      </BaseDropdownItem>
    </router-link>


    <!-- Receive Material — uses router-link to avoid <a href="#"> default
         interfering with Vue Router navigation (first-click issue) -->
    <router-link :to="receiveMaterialRoute">
      <BaseDropdownItem>
        <BaseIcon
          name="ArchiveBoxIcon"
          class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
        />
        Receive Material
      </BaseDropdownItem>
    </router-link>


    <!-- Send LR -->
    <BaseDropdownItem v-if="canSendInvoice" @click="sendInvoice">
      <BaseIcon
        name="PaperAirplaneIcon"
        class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
      />
      Send LR
    </BaseDropdownItem>

    <!-- Resend LR -->
    <BaseDropdownItem v-if="canReSendInvoice" @click="sendInvoice">
      <BaseIcon
        name="PaperAirplaneIcon"
        class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
      />
      Resend LR
    </BaseDropdownItem>

    <!-- Download LR Receipt (single PDF) -->
    <BaseDropdownItem @click="downloadLrReceipt">
      <BaseIcon
        name="ArrowDownTrayIcon"
        class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
      />
      Download LR Receipt
    </BaseDropdownItem>

    <!-- Download Multi LR (4 copies in one PDF) -->
    <BaseDropdownItem @click="downloadMultiLr">
      <BaseIcon
        name="DocumentDuplicateIcon"
        class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
      />
      Download Multi LR
    </BaseDropdownItem>

    <!-- Delete LR Receipt -->
    <BaseDropdownItem v-if="canDelete" @click="removeInvoice">
      <BaseIcon
        name="TrashIcon"
        class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
      />
      Delete
    </BaseDropdownItem>
  </BaseDropdown>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useInvoiceStore } from '../store'
import { useDialogStore } from '@/scripts/stores/dialog.store'
import { useModalStore } from '@/scripts/stores/modal.store'
import { useNotificationStore } from '@/scripts/stores/notification.store'
import type { Invoice } from '@/scripts/types/domain/invoice'

interface TableRef {
  refresh: () => void
}

interface Props {
  row: Invoice & Record<string, unknown>
  table?: TableRef | null
  canEdit?: boolean
  canView?: boolean
  canCreate?: boolean
  canDelete?: boolean
  canSend?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  table: null,
  canEdit: false,
  canView: false,
  canCreate: false,
  canDelete: false,
  canSend: false,
})

const invoiceStore = useInvoiceStore()
const dialogStore = useDialogStore()
const modalStore = useModalStore()
const notificationStore = useNotificationStore()
const router = useRouter()

const canReSendInvoice = computed<boolean>(() => {
  return (
    (props.row.status === 'SENT' || props.row.status === 'VIEWED') &&
    props.canSend
  )
})

const canSendInvoice = computed<boolean>(() => {
  return (
    props.row.status === 'DRAFT' &&
    props.canSend
  )
})

const receiveMaterialRoute = computed(() => {
  const customerId = props.row.customer_id || props.row.customer?.id
  return {
    path: '/admin/warehouse-items/create',
    query: {
      customer: customerId ? String(customerId) : undefined,
      lr: String(props.row.id),
    },
  }
})

// Navigate to Office Invoice (Invoice Receipt) create page with this LR's ID
// as a query param. InvoiceCreateView reads `lr_id` and auto-fills the
// Consignment No with the LR docket number, then triggers the existing
// consignment auto-fill logic in OfficeInvoiceItemsTable.
const createInvoiceReceiptRoute = computed(() => {
  return {
    path: '/admin/invoices/create',
    query: {
      lr_id: String(props.row.id),
    },
  }
})

// Navigate to Lorry Receipt create page with this LR's ID as a query param.
// InvoiceCreateView reads `lr_id` and auto-fills received_no_bilties +
// transport fields from the referenced LR Receipt.
const createLorryReceiptRoute = computed(() => {

  return {
    path: '/admin/lorry-receipts/create',
    query: {
      lr_id: String(props.row.id),
    },
  }
})


function removeInvoice(): void {
  dialogStore.openDialog({
    title: 'Are you sure?',
    message: 'Are you sure you want to delete this LR Receipt?',
    yesLabel: 'OK',
    noLabel: 'Cancel',
    variant: 'danger',
    hideNoButton: false,
    size: 'lg',
  }).then(async (res: boolean) => {
    if (res) {
      const response = await invoiceStore.deleteInvoice({ ids: [props.row.id] })
      if (response.data.success) {
        router.push('/admin/lr-receipts')
        props.table?.refresh()
        invoiceStore.$patch((state) => {
          state.selectedInvoices = []
          state.selectAllField = false
        })
      }
    }
  })
}

function sendInvoice(): void {
  modalStore.openModal({
    title: 'Send LR',
    componentName: 'SendInvoiceModal',
    id: props.row.id,
    data: props.row,
    variant: 'sm',
  })
}

function downloadLrReceipt(): void {
  const pdfUrl = `${window.location.origin}/invoices/pdf/${props.row.unique_hash}`
  window.open(pdfUrl, '_blank')
}

function downloadMultiLr(): void {
  const pdfUrl = `${window.location.origin}/invoices/pdf/${props.row.unique_hash}?copy=multi`
  window.open(pdfUrl, '_blank')
}

</script>
