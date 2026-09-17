<template>
  <BasePage>
    <BasePageHeader :title="truck?.truck_number || 'Truck details'">
      <BaseButton variant="primary-outline" @click="router.push({ name: 'trucks.index' })">
        <template #left="slotProps">
          <BaseIcon name="ArrowLeftIcon" :class="slotProps.class" />
        </template>
        Back to Fleet
      </BaseButton>

      <p class="mt-1 text-sm text-muted">
        Vehicle profile, trip history and operational controls.
      </p>

      <template #actions>
        <div v-if="truck" class="flex flex-wrap gap-3">
          <BaseButton variant="primary-outline" @click="showExpenseModal = true">
            Add Expense
          </BaseButton>
          <BaseButton variant="primary" @click="openEditModal">
            <template #left="slotProps">
              <BaseIcon name="PencilSquareIcon" :class="slotProps.class" />
            </template>
            Edit Truck
          </BaseButton>
        </div>
      </template>
    </BasePageHeader>

    <div v-if="loading" class="mt-6 text-center text-muted">
      Loading truck details…
    </div>

    <template v-else-if="truck">
      <!-- B. KPI Cards -->
      <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <BaseCard container-class="px-4 py-4">
          <p class="text-xs text-muted">Owner</p>
          <p class="mt-1 font-semibold text-heading">
            {{ truck.owner_profile?.name || 'Not linked' }}
          </p>
        </BaseCard>
        <BaseCard container-class="px-4 py-4">
          <p class="text-xs text-muted">Capacity</p>
          <p class="mt-1 font-semibold text-heading">{{ truck.capacity_kg }} kg</p>
        </BaseCard>
        <BaseCard container-class="px-4 py-4">
          <p class="text-xs text-muted">Current odometer</p>
          <p class="mt-1 font-semibold text-heading">
            {{ truck.current_odometer_km ?? '—' }} km
          </p>
        </BaseCard>
        <BaseCard container-class="px-4 py-4">
          <p class="text-xs text-muted">Status</p>
          <p class="mt-1 font-semibold capitalize text-heading">
            {{ truck.status.replace('_', ' ') }}
          </p>
        </BaseCard>
      </div>

      <!-- C. Trip History Table -->
      <BaseCard class="mt-6" container-class="px-5 py-5">
        <template #header>
          <h2 class="font-semibold text-heading">Trip History</h2>
        </template>

        <div v-if="!truck.load_trips || truck.load_trips.length === 0" class="py-8 text-center text-muted">
          No trips recorded for this truck yet.
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-left text-sm">
            <thead class="text-muted">
              <tr>
                <th class="pb-2">Trip #</th>
                <th class="pb-2">Route</th>
                <th class="pb-2">Driver</th>
                <th class="pb-2">Dispatch Date</th>
                <th class="pb-2">Delivery Date</th>
                <th class="pb-2">Status</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="trip in truck.load_trips"
                :key="trip.id"
                class="cursor-pointer border-t border-line-light hover:bg-hover"
                @click="router.push({ name: 'load-trips.show', params: { id: trip.id } })"
              >
                <td class="py-2 font-semibold text-heading">{{ trip.trip_number }}</td>
                <td class="py-2 text-body">
                  {{ (trip.origin_city ? trip.origin_city + ' → ' : '') + (trip.destination_city || '—') }}
                </td>
                <td class="py-2 text-body">{{ trip.driver_name || '—' }}</td>
                <td class="py-2 text-body">{{ formatDate(trip.dispatch_date) }}</td>
                <td class="py-2 text-body">{{ formatDate(trip.actual_delivery_date) }}</td>
                <td class="py-2">
                  <span
                    class="rounded-full px-2 py-0.5 text-xs font-bold capitalize"
                    :class="getTripStatusClass(trip.status)"
                  >
                    {{ trip.status }}
                  </span>
                </td>
              </tr>
            </tbody>
            <tfoot class="border-t-2 border-line-default bg-surface-secondary font-bold">
              <tr>
                <td class="py-2 text-heading">Total: {{ truck.load_trips.length }} trips</td>
                <td colspan="5"></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </BaseCard>

      <!-- D. Maintenance Quick Access -->
      <BaseCard class="mt-6" container-class="px-5 py-5">
        <template #header>
          <h2 class="font-semibold text-heading">Maintenance</h2>
        </template>
        <p class="text-sm text-muted">
          Manage scheduled work and service intervals from this truck's
          operational record.
        </p>
        <div class="mt-4">
          <BaseButton
            variant="primary-outline"
            @click="router.push({ name: 'trucks.maintenance', params: { id: truck.id } })"
          >
            Open maintenance planner
          </BaseButton>
        </div>
      </BaseCard>
    </template>

    <!-- Edit Truck Modal -->
    <BaseModal :show="showEditForm" @close="closeEditModal">
      <template #header>
        <div class="flex w-full items-center justify-between">
          <span>Edit truck</span>
          <BaseIcon
            name="XMarkIcon"
            class="h-6 w-6 cursor-pointer text-muted"
            @click="closeEditModal"
          />
        </div>
      </template>
      <form class="px-6 py-5" @submit.prevent="save">
        <BaseInputGrid>
          <BaseInputGroup label="Truck number" required>
            <BaseInput
              v-model="form.truck_number"
              type="text"
              name="truck_number"
            />
          </BaseInputGroup>

          <BaseInputGroup label="Capacity (kg)" required>
            <BaseInput
              v-model.number="form.capacity_kg"
              type="number"
              min="1"
              name="capacity_kg"
            />
          </BaseInputGroup>

          <BaseInputGroup label="Vehicle type">
            <BaseInput
              v-model="form.vehicle_type"
              type="text"
              name="vehicle_type"
            />
          </BaseInputGroup>

          <BaseInputGroup label="Status">
            <BaseMultiselect
              v-model="form.status"
              :options="statusOptions"
              value-prop="value"
              label="label"
              track-by="value"
              :allow-empty="false"
              placeholder="Select status"
            />
          </BaseInputGroup>
        </BaseInputGrid>

        <div
          class="z-0 mt-6 flex flex-col-reverse gap-3 border-t border-line-default border-solid pt-4 sm:flex-row sm:justify-end"
        >
          <BaseButton variant="white" type="button" @click="closeEditModal">
            Cancel
          </BaseButton>
          <BaseButton :loading="saving" :disabled="saving" type="submit">
            Save changes
          </BaseButton>
        </div>
      </form>
    </BaseModal>

    <FleetExpenseModal
      v-if="truck"
      :show="showExpenseModal"
      :truck-id="truck.id"
      @close="showExpenseModal = false"
      @saved="load"
    />
  </BasePage>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { client } from '@/scripts/api/client'
import { useDialogStore } from '@/scripts/stores/dialog.store'
import FleetExpenseModal from '../components/FleetExpenseModal.vue'

interface Truck {
  id: number
  truck_number: string
  capacity_kg: number | string
  vehicle_type?: string
  current_odometer_km?: number
  status: string
  owner_profile?: { name: string }
  load_trips?: any[]
}

const route = useRoute()
const router = useRouter()
const dialogStore = useDialogStore()
const truck = ref<Truck | null>(null)

const loading = ref(true)
const saving = ref(false)
const showEditForm = ref(false)
const showExpenseModal = ref(false)
const form = ref({
  truck_number: '',
  capacity_kg: 0,
  vehicle_type: '',
  status: 'available',
})

const statusOptions = [
  { value: 'available', label: 'Available' },
  { value: 'reserved', label: 'Reserved' },
  { value: 'on_trip', label: 'On Trip' },
  { value: 'maintenance', label: 'Maintenance' },
  { value: 'inactive', label: 'Inactive' },
]

const load = async () => {
  loading.value = true
  try {
    const response = await client.get(`/api/v1/trucks/${route.params.id}`)
    truck.value = response.data?.data || response.data
    if (truck.value) {
      form.value = {
        truck_number: truck.value.truck_number,
        capacity_kg: Number(truck.value.capacity_kg),
        vehicle_type: truck.value.vehicle_type || '',
        status: truck.value.status,
      }
    }
  } finally {
    loading.value = false
  }
}

const openEditModal = () => {
  showEditForm.value = true
}

const closeEditModal = () => {
  showEditForm.value = false
}

const save = async () => {
  if (!truck.value) return
  saving.value = true
  try {
    await client.put(`/api/v1/trucks/${truck.value.id}`, form.value)
    showEditForm.value = false
    await load()
  } catch (error: any) {
    const serverErrors = error?.response?.data?.errors
    const serverMessage = error?.response?.data?.message
    let message = 'Failed to save truck'
    if (serverErrors) {
      const fieldMessages = Object.values(serverErrors).flat() as string[]
      message = fieldMessages.join('\n') || serverMessage || message
    } else if (serverMessage) {
      message = serverMessage
    }
    await dialogStore.openDialog({
      title: 'Error',
      message,
      variant: 'danger',
      hideNoButton: true,
    })
  } finally {
    saving.value = false
  }
}

const getTripStatusClass = (status: string) => {
  const classes: Record<string, string> = {
    planned: 'bg-status-blue/10 text-status-blue',
    dispatched: 'bg-status-purple/10 text-status-purple',
    delivered: 'bg-status-green/10 text-status-green',
    cancelled: 'bg-status-red/10 text-status-red',
  }
  return classes[status] || ''
}

const formatDate = (date: string) => {
  if (!date) return '—'
  return new Date(date).toLocaleDateString('en-IN', {
    day: '2-digit',
    month: 'short',
    year: '2-digit',
  })
}

onMounted(load)
</script>
