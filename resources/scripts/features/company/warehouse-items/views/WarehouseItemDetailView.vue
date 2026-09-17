<template>
  <BasePage>
    <div v-if="loading" class="mt-6 text-center text-muted">Loading...</div>

    <template v-else-if="item">
      <BasePageHeader :title="item.lr?.invoice_number || '-'">
        <BaseButton variant="primary-outline" @click="$router.back()">
          <template #left="slotProps">
            <BaseIcon name="ArrowLeftIcon" :class="slotProps.class" />
          </template>
          Back
        </BaseButton>
        <p class="mt-1 text-sm text-muted">LR Receipt Number</p>
      </BasePageHeader>

      <div class="mt-6 max-w-4xl space-y-6">
        <!-- LR Receipt Details Card -->
        <BaseCard container-class="px-5 py-5">
          <template #header>
            <h3 class="text-lg font-semibold text-heading">LR Receipt Information</h3>
          </template>
          <div class="grid grid-cols-2 gap-4 md:grid-cols-3">
            <div>
              <p class="text-sm text-muted">Consignor (Company)</p>
              <p class="font-semibold text-body">{{ item.lr?.customer?.name || '-' }}</p>
            </div>
            <div>
              <p class="text-sm text-muted">Description of Goods</p>
              <p class="font-semibold text-body">{{ item.lr?.description_of_goods || '-' }}</p>
            </div>
            <div>
              <p class="text-sm text-muted">E-Way Bill No</p>
              <p class="font-semibold text-body">{{ item.lr?.eway_bill_no || '-' }}</p>
            </div>
            <div>
              <p class="text-sm text-muted">Actual Weight</p>
              <p class="font-semibold text-body">{{ item.lr?.actual_weight || '-' }}</p>
            </div>
            <div>
              <p class="text-sm text-muted">No. of Articles</p>
              <p class="font-semibold text-body">{{ item.lr?.no_of_articles || '-' }}</p>
            </div>
            <div>
              <p class="text-sm text-muted">Packing Type</p>
              <p class="font-semibold text-body">{{ item.lr?.packing || '-' }}</p>
            </div>
          </div>
        </BaseCard>

        <!-- Warehouse Storage Card -->
        <BaseCard container-class="px-5 py-5">
          <template #header>
            <h3 class="text-lg font-semibold text-heading">Warehouse Storage</h3>
          </template>
          <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
            <div>
              <p class="text-sm text-muted">Location</p>
              <p class="font-semibold text-body">{{ item.warehouse_location }}</p>
            </div>
            <div>
              <p class="text-sm text-muted">Section</p>
              <p class="font-semibold text-body">{{ item.section_name || '-' }}</p>
            </div>
            <div>
              <p class="text-sm text-muted">Date Received</p>
              <p class="font-semibold text-body">{{ formatDate(item.date_received) }}</p>
            </div>
            <div>
              <p class="text-sm text-muted">Days in Warehouse</p>
              <p class="font-semibold text-status-blue">{{ item.days_in_warehouse || 0 }}</p>
            </div>
          </div>
        </BaseCard>

        <!-- Status & Destination Card -->
        <BaseCard container-class="px-5 py-5">
          <template #header>
            <h3 class="text-lg font-semibold text-heading">Status & Destination</h3>
          </template>
          <BaseInputGrid>
            <BaseInputGroup label="Current Status">
              <BaseMultiselect
                v-model="item.status"
                :options="statusOptions"
                value-prop="value"
                label="label"
                track-by="value"
                :allow-empty="false"
                placeholder="Select status"
                @update:model-value="updateStatus"
              />
            </BaseInputGroup>
            <BaseInputGroup label="Destination City">
              <BaseInput
                :model-value="item.destination_city"
                type="text"
                readonly
                class="text-lg font-semibold"
              />
            </BaseInputGroup>
          </BaseInputGrid>
        </BaseCard>

        <!-- Notes Card -->
        <BaseCard v-if="item.notes" container-class="px-5 py-5">
          <template #header>
            <h3 class="text-lg font-semibold text-heading">Notes</h3>
          </template>
          <p class="whitespace-pre-wrap text-body">{{ item.notes }}</p>
        </BaseCard>
      </div>
    </template>
  </BasePage>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useWarehouseItemStore } from '../store'
import { useDialogStore } from '@/scripts/stores/dialog.store'

const route = useRoute()
const router = useRouter()
const store = useWarehouseItemStore()
const dialogStore = useDialogStore()

const item = ref<any>(null)
const loading = ref(true)

const statusOptions = [
  { value: 'stored', label: 'Stored' },
  { value: 'picked_for_consolidation', label: 'Picked for Consolidation' },
  { value: 'loaded_on_vehicle', label: 'Loaded on Vehicle' },
  { value: 'in_transit', label: 'In Transit' },
  { value: 'delivered', label: 'Delivered' },
  { value: 'cancelled', label: 'Cancelled' },
]

const formatDate = (date: string) => {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('en-IN', {
    day: '2-digit',
    month: 'short',
    year: '2-digit',
  })
}

const fetchItem = async () => {
  loading.value = true
  try {
    item.value = await store.getItem(parseInt(route.params.id as string))
  } finally {
    loading.value = false
  }
}

const updateStatus = async () => {
  if (item.value) {
    try {
      await store.updateItem(item.value.id, { status: item.value.status })
      await fetchItem()
    } catch (error) {
      console.error('Error updating status:', error)
      await dialogStore.openDialog({
        title: 'Error',
        message: 'Failed to update status',
        variant: 'danger',
        hideNoButton: true,
      })
      await fetchItem()
    }
  }
}

onMounted(() => {
  fetchItem()
})
</script>
