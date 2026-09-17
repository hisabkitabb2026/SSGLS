<!--
  LoadTripFormModal

  Shared modal for creating a new Load Trip from a ready consolidation group.
  Used by: LoadTripIndexView and WarehouseItemIndexView (Dispatched tab).

  This component consolidates the trip creation form that was previously
  duplicated between the two views. It uses the advanced card-based layout
  with TruckSelectPopup and PartyProfileSelectPopup for better UX.

  Props:
    - show: Controls modal visibility
    - readyGroups: Array of consolidation groups with status 'ready'
    - editTrip: Optional trip object for edit mode (null for create mode)

  Emits:
    - close: Emitted when modal should close
    - submit: Emitted with the form data when the user submits
    - truck-select: Emitted with the full truck object when a truck is selected
-->
<template>
  <BaseModal :show="show" @close="$emit('close')">
    <template #header>
      <div class="flex w-full items-center justify-between">
        <span>{{ isEditMode ? 'Edit Load Trip' : 'New Load Trip' }}</span>
        <BaseIcon
          name="XMarkIcon"
          class="h-6 w-6 cursor-pointer text-muted"
          @click="$emit('close')"
        />
      </div>
    </template>
    <form
      class="max-h-[calc(80vh-8rem)] overflow-y-auto px-6 py-5"
      @submit.prevent="submit"
    >
      <!-- Trip Details Card -->
      <div class="rounded-lg border border-line-light p-4">
        <p class="mb-3 text-xs font-bold uppercase tracking-wide text-muted">
          Trip Details
        </p>
        <BaseInputGroup label="Consolidation Group" required>
          <BaseMultiselect
            v-model="form.consolidation_group_id"
            :options="readyGroups"
            value-prop="id"
            label="group_number"
            track-by="id"
            searchable
            placeholder="Select a ready group..."
            @update:model-value="onConsolidationGroupSelect"
          />
          <p v-if="readyGroups.length === 0" class="mt-1 text-xs text-status-red">
            No ready consolidation groups available. Mark a group as ready first.
          </p>
        </BaseInputGroup>

        <BaseInputGrid class="mt-3">
          <BaseInputGroup label="Origin City">
            <BaseInput
              v-model="form.origin_city"
              type="text"
              placeholder="e.g. Bangalore"
            />
          </BaseInputGroup>
          <BaseInputGroup label="Destination City">
            <BaseInput
              v-model="form.destination_city"
              type="text"
              placeholder="Auto-filled from group"
              disabled
            />
          </BaseInputGroup>
        </BaseInputGrid>

        <BaseInputGrid class="mt-3">
          <BaseInputGroup label="Dispatch Date">
            <BaseDatePicker v-model="form.dispatch_date" :calendar-button="true" />
          </BaseInputGroup>
          <BaseInputGroup label="Expected Delivery">
            <BaseDatePicker
              v-model="form.expected_delivery_date"
              :calendar-button="true"
            />
          </BaseInputGroup>
        </BaseInputGrid>
      </div>

      <!-- Vehicle & Crew -->
      <div class="mt-4 mb-3 flex items-center gap-2">
        <p class="text-xs font-bold uppercase tracking-wide text-muted">Vehicle & Crew</p>
        <div class="h-px flex-1 bg-line-light"></div>
      </div>

      <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <!-- Truck Card -->
        <div class="rounded-lg border border-line-light p-3">
          <p class="mb-2 text-xs font-semibold text-muted">Truck</p>
          <TruckSelectPopup
            v-model="form.truck_id"
            label="Truck"
            :filter-status="['available']"
            required
            @select="onTruckSelect"
          />
          <div
            v-if="form.truck_number"
            class="mt-2 flex items-center gap-1.5 text-xs text-muted"
          >
            <BaseIcon name="TruckIcon" class="h-3.5 w-3.5" />
            <span>{{ form.truck_number }}</span>
          </div>
          <p
            v-if="truckWarning"
            class="mt-2 text-xs font-semibold text-status-red"
          >
            ⚠ {{ truckWarning }}
          </p>
        </div>

        <!-- Driver Card -->
        <div class="rounded-lg border border-line-light p-3">
          <p class="mb-2 text-xs font-semibold text-muted">Driver</p>
          <PartyProfileSelectPopup
            v-model="form.driver_profile_id"
            type="DRIVER"
            label="Driver"
            required
            @select="onDriverSelect"
          />
          <div
            v-if="form.driver_phone"
            class="mt-2 flex items-center gap-1.5 text-xs text-muted"
          >
            <BaseIcon name="PhoneIcon" class="h-3.5 w-3.5" />
            <span>{{ form.driver_phone }}</span>
          </div>
        </div>

        <!-- Broker Card -->
        <div class="rounded-lg border border-line-light p-3">
          <p class="mb-2 text-xs font-semibold text-muted">Broker</p>
          <PartyProfileSelectPopup
            v-model="form.broker_profile_id"
            type="BROKER"
            label="Broker"
            @select="onBrokerSelect"
          />
          <div
            v-if="form.broker_phone"
            class="mt-2 flex items-center gap-1.5 text-xs text-muted"
          >
            <BaseIcon name="PhoneIcon" class="h-3.5 w-3.5" />
            <span>{{ form.broker_phone }}</span>
          </div>
        </div>
      </div>

      <BaseInputGroup label="Notes" class="mt-4">
        <BaseTextarea v-model="form.notes" rows="2" />
      </BaseInputGroup>

      <div
        class="z-0 mt-6 flex flex-col-reverse gap-3 border-t border-line-default border-solid pt-4 sm:flex-row sm:justify-end"
      >
        <BaseButton variant="white" type="button" @click="$emit('close')">
          Cancel
        </BaseButton>
        <BaseButton :disabled="!canSubmit" type="submit">
          {{ isEditMode ? 'Update Trip' : 'Create Trip' }}
        </BaseButton>
      </div>
    </form>
  </BaseModal>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import PartyProfileSelectPopup from '@/scripts/features/company/shared/PartyProfileSelectPopup.vue'
import TruckSelectPopup from '@/scripts/features/company/shared/TruckSelectPopup.vue'

const props = defineProps<{
  show: boolean
  readyGroups: any[]
  editTrip?: any
}>()

const isEditMode = computed(() => !!props.editTrip)

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'submit', data: any): void
  (e: 'truck-select', truck: any): void
}>()

const selectedTruck = ref<any>(null)

const defaultForm = () => ({
  consolidation_group_id: '' as string | number,
  truck_id: '' as string | number,
  driver_profile_id: '' as string | number,
  broker_profile_id: '' as string | number,
  truck_number: '',
  driver_name: '',
  driver_phone: '',
  broker_name: '',
  broker_phone: '',
  origin_city: '',
  destination_city: '',
  dispatch_date: '',
  expected_delivery_date: '',
  notes: '',
})

const form = ref(defaultForm())

// Reset form when modal is closed, or pre-fill when editing
watch(
  () => props.show,
  (newVal) => {
    if (!newVal) {
      form.value = defaultForm()
      selectedTruck.value = null
    } else if (props.editTrip) {
      // Pre-fill form with existing trip data for edit mode
      form.value = {
        consolidation_group_id: props.editTrip.consolidation_group_id || '',
        truck_id: props.editTrip.truck_id || '',
        driver_profile_id: props.editTrip.driver_profile_id || '',
        broker_profile_id: props.editTrip.broker_profile_id || '',
        truck_number: props.editTrip.truck_number || '',
        driver_name: props.editTrip.driver_name || '',
        driver_phone: props.editTrip.driver_phone || '',
        broker_name: props.editTrip.broker_name || '',
        broker_phone: props.editTrip.broker_phone || '',
        origin_city: props.editTrip.origin_city || '',
        destination_city: props.editTrip.destination_city || '',
        dispatch_date: props.editTrip.dispatch_date
          ? props.editTrip.dispatch_date.split(' ')[0]
          : '',
        expected_delivery_date: props.editTrip.expected_delivery_date || '',
        notes: props.editTrip.notes || '',
      }
    } else {
      form.value = defaultForm()
      selectedTruck.value = null
    }
  }
)

const canSubmit = computed(() => {
  return (
    form.value.consolidation_group_id &&
    form.value.truck_id &&
    form.value.driver_profile_id
  )
})

/**
 * Warns the user if the selected truck has insufficient capacity
 * for the consolidation group's total weight.
 */
const truckWarning = computed(() => {
  if (!selectedTruck.value) return ''
  const truck = selectedTruck.value
  const group = props.readyGroups.find(
    (g: any) => g.id === Number(form.value.consolidation_group_id)
  )
  if (group && Number(truck.capacity_kg) < Number(group.total_weight_kg)) {
    return `Truck capacity (${truck.capacity_kg}kg) is less than load weight (${group.total_weight_kg}kg).`
  }
  return ''
})

const onConsolidationGroupSelect = (value: any) => {
  if (value) {
    const group = props.readyGroups.find((g: any) => g.id === Number(value))
    if (group) {
      form.value.destination_city = group.destination_city || ''
    }
  } else {
    form.value.destination_city = ''
  }
}

const onTruckSelect = (truck: any) => {
  selectedTruck.value = truck || null
  emit('truck-select', truck || null)
  if (truck) {
    form.value.truck_number = truck.truck_number || ''
  } else {
    form.value.truck_number = ''
  }
}

const onDriverSelect = (profile: any) => {
  if (profile) {
    form.value.driver_name = profile.name || ''
    form.value.driver_phone = profile.phone || ''
  } else {
    form.value.driver_name = ''
    form.value.driver_phone = ''
  }
}

const onBrokerSelect = (profile: any) => {
  if (profile) {
    form.value.broker_name = profile.name || ''
    form.value.broker_phone = profile.phone || ''
  } else {
    form.value.broker_name = ''
    form.value.broker_phone = ''
  }
}

const submit = () => {
  emit('submit', { ...form.value })
}
</script>
