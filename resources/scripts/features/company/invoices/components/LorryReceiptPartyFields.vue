<script setup lang="ts">
import { reactive, watch, nextTick, computed } from 'vue'
import { Popover, PopoverButton, PopoverPanel } from '@headlessui/vue'
import { useDebounceFn } from '@vueuse/core'
import { useInvoiceStore } from '../store'
import { useModalStore } from '@/scripts/stores/modal.store'
import { useLorryPartyProfileStore } from '@/scripts/features/company/lorry-party-profiles/store'
import type { LorryPartyProfile, LorryPartyProfileType } from '@/scripts/types/domain/lorry-party-profile'
import type { CustomFieldValue } from '@/scripts/types/domain/custom-field'
import TruckSelectPopup from '@/scripts/features/company/shared/TruckSelectPopup.vue'
import BaseProfileCard from '@/scripts/components/base/BaseProfileCard.vue'


interface ProfileConfig {
  key: 'owner' | 'driver' | 'broker'
  type: LorryPartyProfileType
  label: string
  singular: string
  summaryLabel: string
}

interface FieldMapping {
  0: string // label
  1: string // profile key
}

type FieldMappings = Record<string, FieldMapping[]>

const invoiceStore = useInvoiceStore()
const modalStore = useModalStore()
const profileStore = useLorryPartyProfileStore()

const selectedProfiles = reactive<Record<string, LorryPartyProfile | null>>({
  owner: null,
  driver: null,
  broker: null,
})

const profileOptions = reactive<Record<string, LorryPartyProfile[]>>({
  owner: [],
  driver: [],
  broker: [],
})

const profileSearch = reactive<Record<string, string>>({
  owner: '',
  driver: '',
  broker: '',
})

const profileResolveInProgress = reactive<Record<string, boolean>>({
  owner: false,
  driver: false,
  broker: false,
})

const profileInputs: ProfileConfig[] = [
  { key: 'owner', type: 'OWNER', label: 'Owner Name', singular: 'Owner', summaryLabel: 'Owner Details' },
  { key: 'driver', type: 'DRIVER', label: 'Driver Name', singular: 'Driver', summaryLabel: 'Driver Details' },
  { key: 'broker', type: 'BROKER', label: 'Broker Name', singular: 'Broker', summaryLabel: 'Broker Details' },
]

// ── Truck selection ──────────────────────────────────────────
// When a truck is selected, auto-fill the "Lorry No" field
// (invoiceStore.newInvoice.truck_no) with the truck's truck_number.
interface Truck {
  id: number | string
  truck_number: string
  capacity_kg: number | string
  vehicle_type?: string
  status: string
  owner_profile?: { name: string } | null
}

// In edit mode, hydrate the truck card from the existing truck_no value
const initialTruck = computed<Truck | null>(() => {
  const truckNo = (invoiceStore.newInvoice as Record<string, unknown>).truck_no as string | undefined
  if (truckNo) {
    return {
      id: 0,
      truck_number: truckNo,
      capacity_kg: 0,
      status: 'available',
    } as Truck
  }
  return null
})

function onTruckSelect(truck: Truck | null): void {
  ;(invoiceStore.newInvoice as Record<string, unknown>).truck_no = truck?.truck_number || ''
}

const fieldMappingsByType: FieldMappings = {
  OWNER: [
    ['Owner Name', 'name'],
    ['Owner Address', 'address'],
    ['Owner Phone No', 'phone'],
    ['Owner PAN No', 'financer_name'],
    ['Owner Bank Account No', 'bank_account_no'],
  ],
  DRIVER: [
    ['Driver Name', 'name'],
    ['Driver Address', 'address'],
    ['Driver Licence No', 'licence_no'],
    ['Issued Dt.', 'licence_date'],
    ['Driver RTO', 'rto_address'],
    ['Driver Valid Up To', 'valid_up_to'],
    ['Driver Bank Account No', 'bank_account_no'],
  ],
  BROKER: [
    ['Broker Name', 'name'],
    ['Broker Address', 'address'],
    ['Broker Pan No', 'advice_no'],
    ['Advice Date', 'advice_date'],
    ['Broker Phone No', 'phone'],
    ['Broker Bank Account No', 'bank_account_no'],
  ],
}

const debounceSearchProfiles = useDebounceFn((profile: ProfileConfig) => {
  fetchProfileOptions(profile)
}, 500)

function getInvoiceField(label: string): CustomFieldValue | undefined {
  return invoiceStore.newInvoice.customFields?.find(
    (field) => field.custom_field?.label === label,
  )
}

function setField(label: string, value: string | null | undefined): void {
  const field = invoiceStore.newInvoice.customFields?.find(
    (f) => f.custom_field?.label === label,
  )

  if (field) {
    field.string_answer = label === 'Broker Pan No'
      ? String(value || '').toUpperCase()
      : (value as string) || ''
  }
}

function getFieldValue(label: string): string {
  const field = getInvoiceField(label)
  return (field?.string_answer as string) || ''
}

async function fetchProfileOptions(profile: ProfileConfig): Promise<void> {
  try {
    const response = await profileStore.fetchProfiles({
      search: profileSearch[profile.key],
      type: profile.type,
      limit: 'all',
    })
    profileOptions[profile.key] = response.data || []
  } catch {
    profileOptions[profile.key] = []
  }
}

function ensureProfilesLoaded(profile: ProfileConfig): void {
  if (!profileOptions[profile.key].length) {
    fetchProfileOptions(profile)
  }
}

function selectProfile(profile: ProfileConfig, option: LorryPartyProfile, close: () => void): void {
  selectedProfiles[profile.key] = option
  fillProfileFields(profile.type, option)
  close()
  profileSearch[profile.key] = ''
}

function resetProfile(profile: ProfileConfig): void {
  selectedProfiles[profile.key] = null
  fillProfileFields(profile.type, null)
}

async function openProfileCreate(profile: ProfileConfig, close?: () => void): Promise<void> {
  close?.()

  // Wait for the Popover to finish closing and restoring focus before
  // opening the modal.  Without this delay, the Popover's focus restoration
  // fires AFTER the Dialog opens, and the Dialog interprets the focus shift
  // as an outside interaction → immediately emits `close` → modal vanishes.
  await nextTick()
  setTimeout(() => {
    profileStore.setCurrentProfile({
      type: profile.type,
      name: '',
      phone: '',
      address: '',
      bank_account_no: '',
    })
    modalStore.openModal({
      title: `New ${profile.singular}`,
      componentName: 'LorryPartyProfileModal',
      size: 'lg',
    })
  }, 150)
}

async function openProfileEdit(profile: ProfileConfig): Promise<void> {
  if (!selectedProfiles[profile.key]?.id) {
    await resolveSelectedProfile(profile)
  }

  const selectedProfile = selectedProfiles[profile.key]

  if (!selectedProfile?.id) {
    return
  }

  try {
    const response = await profileStore.fetchProfile(selectedProfile.id)
    if (response.data) {
      profileStore.setCurrentProfile(response.data)
    }
  } catch {
    return
  }

  modalStore.openModal({
    title: `Edit ${profile.singular}`,
    componentName: 'LorryPartyProfileModal',
    size: 'lg',
  })
}

// React to profile saves from the shared (page-level) LorryPartyProfileModal.
// The modal calls profileStore.notifyProfileSaved() on successful save, and
// we pick it up here via the store — no @saved emit needed.
watch(
  () => profileStore.lastSavedAt,
  (ts) => {
    if (!ts) return
    const saved = profileStore.lastSavedProfile
    if (!saved || !saved.type) return
    const key = saved.type.toLowerCase()
    if (!(key in selectedProfiles)) return
    selectedProfiles[key] = saved
    fillProfileFields(saved.type, saved)
  },
)


/**
 * Mapping from LorryPartyProfile fields to native transport properties
 * on invoiceStore.newInvoice (used by TransportCustomFields sections).
 * This ensures that selecting a profile from the dropdown also fills in
 * the bottom "Owner Details", "Driver Details", "Broker Details" sections.
 */
const nativePropertyMappings: Record<LorryPartyProfileType, Array<[string, keyof LorryPartyProfile]>> = {
  OWNER: [
    ['owner_name', 'name'],
    ['owner_address', 'address'],
    ['owner_phone', 'phone'],
    ['owner_bank_account_no', 'bank_account_no'],
    ['owner_pan_no', 'financer_name'],
  ],
  DRIVER: [
    ['driver_name', 'name'],
    ['driver_address', 'address'],
    ['driver_licence_no', 'licence_no'],
    ['driver_licence_date', 'licence_date'],
    ['driver_rto_address', 'rto_address'],
    ['driver_valid_up_to', 'valid_up_to'],
    ['driver_bank_account_no', 'bank_account_no'],
  ],
  BROKER: [
    ['broker_name', 'name'],
    ['broker_address', 'address'],
    ['broker_pan_no', 'advice_no'],
    ['broker_phone_no', 'phone'],
    ['broker_bank_account_no', 'bank_account_no'],
  ],
}

function fillProfileFields(type: LorryPartyProfileType, profile: LorryPartyProfile | null): void {
  // 1. Fill custom fields by label (for backward compat / PDF templates)
  const mappings = fieldMappingsByType[type]
  if (mappings) {
    mappings.forEach((mapping) => {
      const [label, key] = mapping
      setField(label, profile?.[key as keyof LorryPartyProfile] as string)
    })
  }

  // 2. Fill native transport properties (for TransportCustomFields sections)
  const nativeMappings = nativePropertyMappings[type]
  if (nativeMappings) {
    nativeMappings.forEach(([prop, key]) => {
      ;(invoiceStore.newInvoice as Record<string, unknown>)[prop] =
        (profile?.[key as keyof LorryPartyProfile] as string) || ''
    })
  }

  if (type === 'OWNER') {
    setField('Paid To', profile?.name)
    setField('Final Paid To', profile?.name)
    // Also set native properties for Paid To / Final Paid To
    invoiceStore.newInvoice.paid_to = profile?.name || ''
    invoiceStore.newInvoice.final_paid_to = profile?.name || ''
  }
}

function compact(value: string | null | undefined): string {
  return value ? String(value).trim() : ''
}

function normalize(value: string | null | undefined): string {
  return compact(value).toLowerCase().replace(/[^a-z0-9]+/g, '')
}

async function resolveSelectedProfile(profile: ProfileConfig): Promise<void> {
  const selectedProfile = selectedProfiles[profile.key]
  const profileName = compact(selectedProfile?.name)

  if (
    !profileName
    || selectedProfile?.id
    || profileResolveInProgress[profile.key]
  ) {
    return
  }

  profileResolveInProgress[profile.key] = true

  try {
    await fetchProfileOptions(profile)
    const matchedProfile = profileOptions[profile.key].find(
      (option) => normalize(option.name) === normalize(profileName),
    )

    if (matchedProfile && !selectedProfiles[profile.key]?.id) {
      selectedProfiles[profile.key] = matchedProfile
      fillProfileFields(profile.type, matchedProfile)
    }
  } finally {
    profileResolveInProgress[profile.key] = false
  }
}

function hydrateSelectedProfilesFromFields(): void {
  profileInputs.forEach((profile) => {
    if (selectedProfiles[profile.key]?.id) {
      return
    }

    if (selectedProfiles[profile.key]) {
      resolveSelectedProfile(profile)
      return
    }

    // Hydrate from native transport columns on the invoice (not custom fields).
    // Transport fields (owner_name, driver_name, broker_name, etc.) are now
    // native columns on the invoices table, bound directly to invoiceStore.newInvoice.
    const nativeMappings = nativePropertyMappings[profile.type]
    if (!nativeMappings) return

    const hydratedProfile: LorryPartyProfile = { type: profile.type }

    nativeMappings.forEach(([prop, key]) => {
      const val = (invoiceStore.newInvoice as Record<string, unknown>)[prop] as string
      if (val) {
        hydratedProfile[key as keyof LorryPartyProfile] = val as never
      }
    })

    if (
      compact(hydratedProfile.name)
      || compact(hydratedProfile.address)
      || compact(hydratedProfile.phone)
    ) {
      selectedProfiles[profile.key] = hydratedProfile
      resolveSelectedProfile(profile)
    }
  })
}


// Watch native transport fields (owner_name, driver_name, broker_name, etc.)
// for changes — this triggers hydration when invoice data loads in edit mode.
watch(
  () => [
    invoiceStore.newInvoice.owner_name,
    invoiceStore.newInvoice.owner_address,
    invoiceStore.newInvoice.owner_phone,
    invoiceStore.newInvoice.driver_name,
    invoiceStore.newInvoice.driver_address,
    invoiceStore.newInvoice.broker_name,
    invoiceStore.newInvoice.broker_address,
  ],
  () => {
    hydrateSelectedProfilesFromFields()
  },
  { deep: true, immediate: true },
)

</script>

<template>
  <div class="mb-8">
    <div class="grid gap-y-6 gap-x-4 grid-cols-1 md:grid-cols-2">
      <template v-for="profile in profileInputs" :key="profile.key">
      <div
        class="relative flex flex-col rounded-md"
      >
        <!-- Selected profile card (unified BaseProfileCard) -->
        <BaseProfileCard
          v-if="selectedProfiles[profile.key]"
          :profile="selectedProfiles[profile.key]"
          :label="profile.summaryLabel"
          @edit="openProfileEdit(profile)"
          @deselect="resetProfile(profile)"
        />

        <!-- Unselected: Popover search selector -->
        <Popover v-else v-slot="{ open }" class="relative flex flex-col rounded-md">
          <PopoverButton
            :class="{
              'focus:ring-2 focus:ring-primary-400': !open,
            }"
            class="w-full outline-hidden rounded-md"
            @click="ensureProfilesLoaded(profile)"
          >
            <div class="relative flex justify-center px-0 p-0 py-16 bg-surface border border-line-default border-solid rounded-md min-h-[170px]">
              <BaseIcon
                name="UserIcon"
                class="flex justify-center !w-10 !h-10 p-2 mr-5 text-sm text-white bg-surface-muted rounded-full font-base"
              />
              <div class="mt-1">
                <label class="text-lg font-medium text-heading">
                  {{ profile.label }}
                </label>
              </div>
            </div>
          </PopoverButton>

          <transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="translate-y-1 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="translate-y-0 opacity-100"
            leave-to-class="translate-y-1 opacity-0"
          >
            <div v-if="open" class="absolute min-w-full z-10">
              <PopoverPanel
                v-slot="{ close }"
                static
                class="overflow-hidden rounded-md shadow-lg ring-1 ring-black/5 bg-surface"
              >
                <div class="relative">
                  <BaseInput
                    v-model="profileSearch[profile.key]"
                    container-class="m-4"
                    :placeholder="$t('general.search')"
                    type="text"
                    icon="search"
                    @update:model-value="debounceSearchProfiles(profile)"
                  />

                  <ul class="max-h-80 flex flex-col overflow-auto list border-t border-line-light">
                    <li
                      v-for="option in profileOptions[profile.key]"
                      :key="option.id"
                      class="flex px-6 py-2 border-b border-line-light border-solid cursor-pointer hover:cursor-pointer hover:bg-hover focus:outline-hidden focus:bg-hover"
                      @click="selectProfile(profile, option, close)"
                    >
                      <div class="flex items-center justify-center h-10 w-10 mr-4 rounded-full bg-surface-muted uppercase text-primary-500">
                        {{ (option.name || profile.singular).charAt(0) }}
                      </div>
                      <div class="flex-1 flex flex-col text-left">
                        <span class="text-sm font-medium text-heading">
                          {{ option.name }}
                        </span>
                        <span class="text-xs text-muted">
                          {{ option.phone || option.address }}
                        </span>
                      </div>
                    </li>
                  </ul>

                  <button
                    type="button"
                    class="flex items-center justify-center w-full px-6 py-3 bg-hover cursor-pointer"
                    @click="openProfileCreate(profile, close)"
                  >
                    <BaseIcon name="PlusIcon" class="h-5 text-primary-400" />
                    <label class="m-0 ml-3 text-sm leading-none cursor-pointer font-base text-primary-400">
                      Add New {{ profile.singular }}
                    </label>
                  </button>
                </div>
              </PopoverPanel>
            </div>
          </transition>
        </Popover>
      </div>
      <!-- Truck selector card: appears after Driver, before Broker -->
      <div v-if="profile.key === 'driver'" class="relative flex flex-col rounded-md">
        <TruckSelectPopup
          label="Select Truck"
          :filter-status="['available']"
          :initial-truck="initialTruck"
          @select="onTruckSelect"
        />
      </div>
      </template>
    </div>
  </div>
</template>

