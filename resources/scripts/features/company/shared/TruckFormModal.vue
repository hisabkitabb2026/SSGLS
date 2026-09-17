<script setup lang="ts">
/**
 * TruckFormModal — Reusable Add/Edit Truck modal.
 *
 * Extracted from TruckIndexView.vue so it can be opened from anywhere
 * (e.g. LoadTrip create modal, TruckIndexView, etc.) without duplicating
 * the truck form logic.
 *
 * Usage:
 *   <TruckFormModal
 *     :show="showTruckForm"
 *     :truck="editingTruck"
 *     @close="showTruckForm = false"
 *     @saved="onTruckSaved"
 *   />
 *
 * The parent must provide the owner profiles list (or the modal will
 * fetch them itself).
 */
import { ref, watch, computed } from 'vue'
import { client } from '@/scripts/api/client'
import PartyProfileSelectPopup from './PartyProfileSelectPopup.vue'
import { useLorryPartyProfileStore } from '@/scripts/features/company/lorry-party-profiles/store'
import { useModalStore } from '@/scripts/stores/modal.store'


interface Truck {
  id?: number | string
  owner_profile_id?: number | string
  truck_number: string
  capacity_kg: number | string
  vehicle_type?: string
  body_type?: string
  make?: string
  vehicle_model?: string
  registered_at?: string
  colour?: string
  chassis_number?: string
  engine_number?: string
}


const props = withDefaults(
  defineProps<{
    show: boolean
    /** Pass an existing truck to edit; leave null/undefined for "Add" mode */
    truck?: Truck | null
  }>(),
  {
    truck: null,
  },
)

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'saved', truck: Truck): void
}>()

const profileStore = useLorryPartyProfileStore()
const modalStore = useModalStore()

const saving = ref(false)

const emptyTruckForm = (): Record<string, string | number> => ({
  id: '',
  owner_profile_id: '',
  truck_number: '',
  capacity_kg: 0,
  vehicle_type: '',
  body_type: '',
  make: '',
  vehicle_model: '',
  registered_at: '',
  colour: '',
  chassis_number: '',
  engine_number: '',
})


const truckForm = ref<Record<string, string | number>>(emptyTruckForm())

const isEdit = computed(() => !!truckForm.value.id)

// ──────────────────────────────────────────────────────────────
// Sync form when modal opens
// ──────────────────────────────────────────────────────────────

watch(
  () => props.show,
  (visible) => {
    if (visible) {
      if (props.truck) {
        truckForm.value = { ...emptyTruckForm(), ...props.truck }
      } else {
        truckForm.value = emptyTruckForm()
      }
    }
  },
)

// ──────────────────────────────────────────────────────────────
// Owner profile selection (via PartyProfileSelectPopup)
// ──────────────────────────────────────────────────────────────

function onOwnerSelect(profile: any): void {
  if (profile) {
    truckForm.value.owner_profile_id = profile.id ?? ''
  } else {
    truckForm.value.owner_profile_id = ''
  }
}

// React to owner profile saves from the shared LorryPartyProfileModal
watch(
  () => profileStore.lastSavedAt,
  (ts) => {
    if (!ts) return
    const saved = profileStore.lastSavedProfile
    if (!saved || saved.type !== 'OWNER') return
    truckForm.value.owner_profile_id = saved.id ?? ''
  },
)

// ──────────────────────────────────────────────────────────────
// Save
// ──────────────────────────────────────────────────────────────

async function saveTruck(): Promise<void> {
  saving.value = true
  try {
    let response
    if (truckForm.value.id) {
      response = await client.put(`/api/v1/trucks/${truckForm.value.id}`, truckForm.value)
    } else {
      response = await client.post('/api/v1/trucks', truckForm.value)
    }

    const savedTruck = response.data?.data || response.data
    emit('saved', savedTruck)
    emit('close')
  } finally {
    saving.value = false
  }
}

function closeForm(): void {
  emit('close')
}

// ──────────────────────────────────────────────────────────────
// "Add New Owner" — opens the shared LorryPartyProfileModal
// ──────────────────────────────────────────────────────────────

function openOwnerCreate(): void {
  profileStore.setCurrentProfile({
    type: 'OWNER',
    name: '',
    phone: '',
    address: '',
  })
  modalStore.openModal({
    title: 'New Owner',
    componentName: 'LorryPartyProfileModal',
    size: 'lg',
  })
}
</script>

<template>
  <BaseModal :show="show" @close="closeForm">
    <template #header>
      <div class="flex w-full items-center justify-between">
        <span>{{ isEdit ? 'Edit truck' : 'Add truck' }}</span>
        <BaseIcon
          name="XMarkIcon"
          class="h-6 w-6 cursor-pointer text-muted"
          @click="closeForm"
        />
      </div>
    </template>

    <form
      class="max-h-[calc(80vh-8rem)] overflow-y-auto px-6 py-5"
      @submit.prevent="saveTruck"
    >
      <BaseInputGrid>
        <BaseInputGroup label="Owner profile">
          <PartyProfileSelectPopup
            :model-value="(truckForm.owner_profile_id as number | string | null)"
            :initial-profile="(props.truck as any)?.owner_profile ?? null"
            type="OWNER"
            label="Owner"
            placeholder="Search owner..."
            @update:model-value="truckForm.owner_profile_id = $event ?? ''"
            @select="onOwnerSelect"
          />
        </BaseInputGroup>

        <BaseInputGroup label="Truck number" required>
          <BaseInput
            v-model="truckForm.truck_number"
            type="text"
            name="truck_number"
          />
        </BaseInputGroup>

        <BaseInputGroup label="Capacity (kg)" required>
          <BaseInput
            v-model.number="truckForm.capacity_kg"
            type="number"
            min="1"
            name="capacity_kg"
          />
        </BaseInputGroup>

        <BaseInputGroup label="Vehicle type">
          <BaseInput
            v-model="truckForm.vehicle_type"
            type="text"
            name="vehicle_type"
          />
        </BaseInputGroup>
      </BaseInputGrid>

      <!-- Vehicle Details Section -->
      <h6 class="mt-6 mb-3 text-sm font-semibold text-heading">Vehicle Details</h6>
      <BaseInputGrid>
        <BaseInputGroup label="Registered At">
          <BaseInput
            v-model="truckForm.registered_at"
            type="text"
            name="registered_at"
            placeholder="e.g. RTO Mumbai"
          />
        </BaseInputGroup>

        <BaseInputGroup label="Body Type">
          <BaseInput
            v-model="truckForm.body_type"
            type="text"
            name="body_type"
            placeholder="e.g. Flatbed, Container"
          />
        </BaseInputGroup>

        <BaseInputGroup label="Make">
          <BaseInput
            v-model="truckForm.make"
            type="text"
            name="make"
            placeholder="e.g. Tata, Ashok Leyland"
          />
        </BaseInputGroup>

        <BaseInputGroup label="Model">
          <BaseInput
            v-model="truckForm.vehicle_model"
            type="text"
            name="vehicle_model"
            placeholder="e.g. LPT 1613"
          />
        </BaseInputGroup>

        <BaseInputGroup label="Colour">
          <BaseInput
            v-model="truckForm.colour"
            type="text"
            name="colour"
            placeholder="e.g. Red, White"
          />
        </BaseInputGroup>

        <BaseInputGroup label="Chasis No">
          <BaseInput
            v-model="truckForm.chassis_number"
            type="text"
            name="chassis_number"
          />
        </BaseInputGroup>

        <BaseInputGroup label="Engine No">
          <BaseInput
            v-model="truckForm.engine_number"
            type="text"
            name="engine_number"
          />
        </BaseInputGroup>
      </BaseInputGrid>


      <div
        class="z-0 mt-6 flex flex-col-reverse gap-3 border-t border-line-default border-solid pt-4 sm:flex-row sm:justify-end"
      >
        <BaseButton variant="white" type="button" @click="closeForm">
          Cancel
        </BaseButton>
        <BaseButton :loading="saving" :disabled="saving" type="submit">
          <template #left="slotProps">
            <BaseIcon
              v-if="!saving"
              name="ArrowDownOnSquareIcon"
              :class="slotProps.class"
            />
          </template>
          Save truck
        </BaseButton>
      </div>
    </form>
  </BaseModal>

</template>
