<template>
  <BasePage>
    <BasePageHeader title="Receive Material into Warehouse">
      <BaseButton variant="primary-outline" @click="$router.back()">
        <template #left="slotProps">
          <BaseIcon name="ArrowLeftIcon" :class="slotProps.class" />
        </template>
        Back
      </BaseButton>
      <p class="mt-1 text-sm text-muted">
        Select a customer LR receipt and record warehouse storage details.
      </p>
    </BasePageHeader>

    <form class="mt-6 max-w-3xl" @submit.prevent="handleSubmit">
      <!-- Section 1: Customer & LR Selection -->
      <BaseCard container-class="px-5 py-5">
        <template #header>
          <h3 class="text-lg font-semibold text-heading">
            Select Customer & LR Receipt
          </h3>
        </template>

        <BaseInputGrid layout="one-column">
          <BaseInputGroup label="Customer" required>
            <BaseCustomerSelectInput
              v-model="selectedCustomerId"
              show-action
              @update:model-value="onCustomerChange"
            />
          </BaseInputGroup>

          <BaseInputGroup v-if="selectedCustomerId" label="LR Receipt" required>
            <BaseMultiselect
              v-model="selectedLrId"
              value-prop="id"
              track-by="invoice_number"
              label="invoice_number"
              :options="lrList"
              :loading="isLoadingLrs"
              placeholder="Select an LR receipt..."
              searchable
              @select="onLrSelect"
            />
            <div v-if="!isLoadingLrs && lrList.length === 0 && selectedCustomerId" class="mt-2">
              <p class="text-xs text-status-red">No LR receipts found for this customer.</p>
              <router-link
                :to="`/admin/lr-receipts/create?customer=${selectedCustomerId}&from=warehouse`"
                class="inline-flex items-center gap-1 mt-2 text-sm font-medium text-primary-500 hover:text-primary-600"
              >
                <BaseIcon name="PlusCircleIcon" class="w-4 h-4" />
                Create New LR Receipt
              </router-link>
            </div>
          </BaseInputGroup>
        </BaseInputGrid>

        <!-- LR Details (Auto-populated) -->
        <div
          v-if="selectedLrDetails"
          class="mt-4 rounded-lg border border-line-light bg-surface-secondary p-4"
        >
          <h3 class="mb-3 font-semibold text-heading">LR Details (Auto-populated)</h3>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <p class="text-sm text-muted">LR Number</p>
              <p class="font-semibold text-body">{{ selectedLrDetails.invoice_number }}</p>
            </div>
            <div>
              <p class="text-sm text-muted">Company/Customer</p>
              <p class="font-semibold text-body">{{ selectedLrDetails.customer?.name }}</p>
            </div>
            <div>
              <p class="text-sm text-muted">Goods Description</p>
              <p class="font-semibold text-body">
                {{ selectedLrDetails.description_of_goods || '-' }}
              </p>
            </div>
            <div>
              <p class="text-sm text-muted">E-Way Bill No</p>
              <p class="font-semibold text-body">{{ selectedLrDetails.eway_bill_no || '-' }}</p>
            </div>
            <div>
              <p class="text-sm text-muted">Actual Weight</p>
              <p class="font-semibold text-body">{{ selectedLrDetails.actual_weight || '-' }} kg</p>
            </div>
            <div>
              <p class="text-sm text-muted">No. of Articles</p>
              <p class="font-semibold text-body">{{ selectedLrDetails.no_of_articles || '-' }}</p>
            </div>
            <div>
              <p class="text-sm text-muted">Packing Type</p>
              <p class="font-semibold text-body">{{ selectedLrDetails.packing || '-' }}</p>
            </div>
            <div>
              <p class="text-sm text-muted">Destination</p>
              <p class="font-semibold text-body">{{ selectedLrDetails.to_name || '-' }}</p>
            </div>
          </div>
        </div>
      </BaseCard>

      <!-- Section 2: Service Type -->
      <BaseCard class="mt-6" container-class="px-5 py-5">
        <BaseInputGroup label="Service Type" required />
        <div class="grid grid-cols-2 gap-4">
          <label
            class="flex cursor-pointer items-center gap-3 rounded-lg border p-4 transition"
            :class="
              formData.load_type === 'part_load'
                ? 'border-primary-500 bg-primary-500/5'
                : 'border-line-default bg-surface'
            "
          >
            <input v-model="formData.load_type" type="radio" value="part_load" class="h-4 w-4" />
            <div>
              <p class="font-semibold text-body">Part Load</p>
              <p class="text-xs text-muted">
                Material stored in warehouse, consolidated with other shipments
              </p>
            </div>
          </label>
          <label
            class="flex cursor-pointer items-center gap-3 rounded-lg border p-4 transition"
            :class="
              formData.load_type === 'full_load'
                ? 'border-primary-500 bg-primary-500/5'
                : 'border-line-default bg-surface'
            "
          >
            <input v-model="formData.load_type" type="radio" value="full_load" class="h-4 w-4" />
            <div>
              <p class="font-semibold text-body">Full Load</p>
              <p class="text-xs text-muted">Direct dispatch, no warehouse storage needed</p>
            </div>
          </label>
        </div>
      </BaseCard>

      <!-- Section 3: Warehouse Storage (only for Part Load) -->
      <BaseCard
        v-if="formData.load_type === 'part_load'"
        class="mt-6"
        container-class="px-5 py-5"
      >
        <template #header>
          <h3 class="text-lg font-semibold text-heading">Warehouse Storage</h3>
        </template>
        <BaseInputGrid>
          <BaseInputGroup label="Warehouse Location" required>
            <BaseMultiselect
              v-model="formData.warehouse_location"
              :options="warehouseLocationOptions"
              value-prop="value"
              label="label"
              track-by="value"
              :allow-empty="false"
              placeholder="Select Warehouse Location"
            />
          </BaseInputGroup>
          <BaseInputGroup label="Date Received" required>
            <BaseDatePicker v-model="formData.date_received" :calendar-button="true" />
          </BaseInputGroup>
        </BaseInputGrid>
      </BaseCard>

      <!-- Section 4: Delivery Commitment -->
      <BaseCard class="mt-6" container-class="px-5 py-5">
        <template #header>
          <h3 class="text-lg font-semibold text-heading">Delivery Commitment</h3>
        </template>
        <BaseInputGrid>
          <BaseInputGroup label="Promised Dispatch Date">
            <BaseDatePicker v-model="formData.promised_dispatch_date" :calendar-button="true" />
            <p class="mt-1 text-xs text-muted">Deadline given to customer for dispatch</p>
          </BaseInputGroup>
          <BaseInputGroup label="Priority">
            <BaseMultiselect
              v-model="formData.priority"
              :options="priorityOptions"
              value-prop="value"
              label="label"
              track-by="value"
              :allow-empty="false"
              placeholder="Select priority"
            />
          </BaseInputGroup>
        </BaseInputGrid>
      </BaseCard>

      <!-- Section 5: Material Details -->
      <BaseCard class="mt-6" container-class="px-5 py-5">
        <template #header>
          <h3 class="text-lg font-semibold text-heading">Material Details</h3>
        </template>
        <BaseInputGrid>
          <BaseInputGroup label="Weight (kg)">
            <BaseInput
              v-model="formData.weight_kg"
              type="number"
              step="0.01"
              min="0"
            />
          </BaseInputGroup>
          <BaseInputGroup label="No. of Packages">
            <BaseInput v-model="formData.no_of_packages" type="number" min="0" />
          </BaseInputGroup>
          <BaseInputGroup label="Destination City">
            <BaseInput
              v-model="formData.destination_city"
              type="text"
              readonly
              class="bg-surface-secondary"
            />
          </BaseInputGroup>
        </BaseInputGrid>
      </BaseCard>

      <!-- Section 6: Notes -->
      <BaseCard class="mt-6" container-class="px-5 py-5">
        <BaseInputGroup label="Additional Notes">
          <BaseTextarea
            v-model="formData.notes"
            placeholder="Any special handling instructions..."
            rows="3"
          />
        </BaseInputGroup>
      </BaseCard>

      <!-- Actions -->
      <div class="mt-6 flex gap-3">
        <BaseButton
          variant="primary"
          type="submit"
          :loading="loading"
          :disabled="loading || !selectedLrDetails"
        >
          {{ loading ? 'Saving...' : 'Save Warehouse Item' }}
        </BaseButton>
        <BaseButton variant="white" type="button" @click="$router.back()">
          Cancel
        </BaseButton>
      </div>
    </form>
  </BasePage>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useWarehouseItemStore } from '../store'
import { useDialogStore } from '@/scripts/stores/dialog.store'
import { invoiceService } from '@/scripts/api/services/invoice.service'

const route = useRoute()
const router = useRouter()
const store = useWarehouseItemStore()
const dialogStore = useDialogStore()

const loading = ref(false)
const selectedCustomerId = ref<number | string>('')
const selectedLrId = ref<number | string>('')
const selectedLrDetails = ref<any>(null)
const lrList = ref<any[]>([])
const isLoadingLrs = ref(false)

const warehouseLocationOptions = [
  { value: 'VAPI', label: 'VAPI' },
  { value: 'UMB', label: 'UMB' },
]

const priorityOptions = [
  { value: 'normal', label: 'Normal' },
  { value: 'urgent', label: 'Urgent' },
  { value: 'critical', label: 'Critical' },
]

const formData = reactive({
  lr_id: '',
  load_type: 'part_load',
  warehouse_location: '',
  date_received: new Date().toISOString().split('T')[0],
  destination_city: '',
  promised_dispatch_date: '',
  priority: 'normal',
  weight_kg: 0,
  no_of_packages: 0,
  notes: '',
})

/**
 * When customer changes, fetch LR receipts for that customer.
 * Uses the same invoiceService.list() as the Payment page, but
 * filters by template_name=lr_receipt to get only LR Receipts.
 */
const onCustomerChange = async () => {
  selectedLrId.value = ''
  selectedLrDetails.value = null
  formData.lr_id = ''
  formData.destination_city = ''
  formData.weight_kg = 0
  formData.no_of_packages = 0
  lrList.value = []

  if (!selectedCustomerId.value) return

  isLoadingLrs.value = true
  try {
    const response = await invoiceService.list({
      customer_id: Number(selectedCustomerId.value),
      template_name: 'lr_receipt',
      limit: 'all',
    } as never)
    lrList.value = (response.data as unknown as any[]) ?? []
  } catch (error) {
    console.error('Error fetching LR receipts:', error)
    lrList.value = []
  } finally {
    isLoadingLrs.value = false
  }
}

/**
 * When an LR is selected from the dropdown, fetch full details via
 * the lookup endpoint and auto-fill the form fields.
 */
const onLrSelect = async (lrId: number) => {
  if (!lrId) {
    selectedLrDetails.value = null
    formData.lr_id = ''
    formData.destination_city = ''
    return
  }

  const lrOption = lrList.value.find((lr: any) => lr.id === lrId)
  if (!lrOption) return

  const lrData = await store.lookupLr(lrOption.invoice_number)

  if (lrData) {
    selectedLrDetails.value = lrData
    formData.lr_id = lrData.id
    formData.destination_city = (lrData.to_name || '')
      .trim()
      .toLowerCase()
      .replace(/\b\w/g, (char: string) => char.toUpperCase())
    if (lrData.actual_weight) {
      formData.weight_kg = parseFloat(lrData.actual_weight) || 0
    }
    if (lrData.no_of_articles) {
      formData.no_of_packages = parseInt(lrData.no_of_articles) || 0
    }
  } else {
    selectedLrDetails.value = null
    formData.lr_id = ''
    formData.destination_city = ''
    await dialogStore.openDialog({
      title: 'Error',
      message: 'Could not load LR details. Please try again.',
      variant: 'danger',
      hideNoButton: true,
    })
  }
}

const handleSubmit = async () => {
  if (!selectedLrDetails.value) {
    await dialogStore.openDialog({
      title: 'Validation',
      message: 'Please select a valid LR Receipt',
      variant: 'danger',
      hideNoButton: true,
    })
    return
  }

  loading.value = true
  try {
    const data: any = {
      lr_id: parseInt(formData.lr_id),
      load_type: formData.load_type,
      warehouse_location:
        formData.load_type === 'part_load' ? formData.warehouse_location : null,
      date_received: formData.date_received,
      destination_city: formData.destination_city,
      promised_dispatch_date: formData.promised_dispatch_date || null,
      priority: formData.priority,
      weight_kg: parseFloat(formData.weight_kg) || 0,
      no_of_packages: parseInt(formData.no_of_packages) || 0,
      notes: formData.notes,
    }

    await store.createItem(data)
    router.push({ name: 'warehouse-items.index' })
  } catch (error) {
    console.error('Error creating item:', error)
    await dialogStore.openDialog({
      title: 'Error',
      message: 'Failed to save warehouse item',
      variant: 'danger',
      hideNoButton: true,
    })
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  formData.date_received = new Date().toISOString().split('T')[0]

  // Auto-fill from query params (e.g., ?customer=123&lr=456)
  // Used when navigating from LR Receipt list "Receive Material" action
  const queryCustomer = route.query.customer as string
  const queryLr = route.query.lr as string

  if (queryCustomer) {
    selectedCustomerId.value = queryCustomer
    await onCustomerChange()

    if (queryLr && lrList.value.length > 0) {
      const lrId = parseInt(queryLr)
      selectedLrId.value = lrId
      await onLrSelect(lrId)
    }
  }
})
</script>
