<script setup lang="ts">
/**
 * PartyProfileSelectPopup — Reusable Popover-based selector for LorryPartyProfile.
 *
 * Supports any profile type (OWNER, DRIVER, BROKER).  Includes a built-in
 * "Add New {Type}" button that opens the shared LorryPartyProfileModal so the
 * user can create a new profile without leaving the current page.
 *
 * Usage:
 *   <PartyProfileSelectPopup
 *     type="DRIVER"
 *     label="Driver"
 *     v-model="form.driver_profile_id"
 *     @select="onDriverSelect"
 *   />
 *
 * The parent page must render <LorryPartyProfileModal /> at the page level
 * for the "Add New" button to work.
 */
import { ref, computed, nextTick, watch } from 'vue'
import { Popover, PopoverButton, PopoverPanel } from '@headlessui/vue'
import { useDebounceFn } from '@vueuse/core'
import { useModalStore } from '@/scripts/stores/modal.store'
import { useLorryPartyProfileStore } from '@/scripts/features/company/lorry-party-profiles/store'
import type { LorryPartyProfile, LorryPartyProfileType } from '@/scripts/types/domain/lorry-party-profile'
import BaseProfileCard from '@/scripts/components/base/BaseProfileCard.vue'

const props = withDefaults(
  defineProps<{
    /** Profile type to filter by (OWNER, DRIVER, BROKER) */
    type: LorryPartyProfileType
    /** Label shown on the selector card and the "Add New" button */
    label?: string
    /** Model value (profile ID) for v-model support */
    modelValue?: number | string | null
    /** Pre-selected profile object (for edit-mode hydration) */
    initialProfile?: LorryPartyProfile | null
    /** Placeholder text for the search input */
    placeholder?: string
    /** Whether the field is required */
    required?: boolean
  }>(),
  {
    label: 'Party',
    modelValue: null,
    initialProfile: null,
    placeholder: 'Search...',
    required: false,
  },
)

const emit = defineEmits<{
  (e: 'update:modelValue', value: number | string | null): void
  (e: 'select', profile: LorryPartyProfile | null): void
}>()

const modalStore = useModalStore()
const profileStore = useLorryPartyProfileStore()

const search = ref<string>('')
const profiles = ref<LorryPartyProfile[]>([])
const selectedProfile = ref<LorryPartyProfile | null>(props.initialProfile ?? null)

const singularLabel = computed<string>(() => {
  if (props.type === 'OWNER') return 'Owner'
  if (props.type === 'DRIVER') return 'Driver'
  if (props.type === 'BROKER') return 'Broker'
  return props.label
})

// ──────────────────────────────────────────────────────────────
// Data fetching
// ──────────────────────────────────────────────────────────────

async function fetchProfiles(): Promise<void> {
  try {
    const response = await profileStore.fetchProfiles({
      search: search.value,
      type: props.type,
      limit: 'all',
    })
    profiles.value = response.data || []
  } catch {
    profiles.value = []
  }
}

const debounceSearch = useDebounceFn(() => {
  fetchProfiles()
}, 500)

function ensureProfilesLoaded(): void {
  if (!profiles.value.length) {
    fetchProfiles()
  }
}

// ──────────────────────────────────────────────────────────────
// Selection
// ──────────────────────────────────────────────────────────────

function selectProfile(profile: LorryPartyProfile, close: () => void): void {
  selectedProfile.value = profile
  emit('update:modelValue', profile.id ?? null)
  emit('select', profile)
  close()
  search.value = ''
}

function resetProfile(): void {
  selectedProfile.value = null
  emit('update:modelValue', null)
  emit('select', null)
}

// ──────────────────────────────────────────────────────────────
// "Add New" — opens the shared LorryPartyProfileModal
// ──────────────────────────────────────────────────────────────

async function openProfileCreate(close?: () => void): Promise<void> {
  close?.()

  // Wait for the Popover to finish closing and restoring focus before
  // opening the modal.  Without this delay, the Popover's focus restoration
  // fires AFTER the Dialog opens, and the Dialog interprets the focus shift
  // as an outside interaction → immediately emits `close` → modal vanishes.
  await nextTick()
  setTimeout(() => {
    profileStore.setCurrentProfile({
      type: props.type,
      name: '',
      phone: '',
      address: '',
      bank_account_no: '',
    })
    modalStore.openModal({
      title: `New ${singularLabel.value}`,
      componentName: 'LorryPartyProfileModal',
      size: 'lg',
    })
  }, 150)
}

async function openProfileEdit(): Promise<void> {
  // Use selectedProfile.id if available; fall back to props.modelValue
  // (the v-model bound ID) — this handles the case where initialProfile
  // was a partial object (e.g. truck.owner_profile only has { name })
  // and doesn't contain the full profile with id.
  const profileId = selectedProfile.value?.id ?? props.modelValue
  if (!profileId) return

  try {
    const response = await profileStore.fetchProfile(profileId)
    if (response.data) {
      profileStore.setCurrentProfile(response.data)
      // Update selectedProfile with the full profile data so that
      // the BaseProfileCard shows correct info after edit
      selectedProfile.value = response.data
    }
  } catch {
    return
  }

  modalStore.openModal({
    title: `Edit ${singularLabel.value}`,
    componentName: 'LorryPartyProfileModal',
    size: 'lg',
  })
}


// ──────────────────────────────────────────────────────────────
// React to profile saves from the shared (page-level) LorryPartyProfileModal.
// The modal calls profileStore.notifyProfileSaved() on successful save, and
// we pick it up here via the store.
// ──────────────────────────────────────────────────────────────

watch(
  () => profileStore.lastSavedAt,
  (ts) => {
    if (!ts) return
    const saved = profileStore.lastSavedProfile
    if (!saved) return
    // Only react to profiles matching our type
    if (saved.type !== props.type) return
    selectedProfile.value = saved
    emit('update:modelValue', saved.id ?? null)
    emit('select', saved)
  },
)

// ──────────────────────────────────────────────────────────────
// Sync external modelValue changes (e.g. form resets)
// ──────────────────────────────────────────────────────────────

watch(
  () => props.modelValue,
  (newVal) => {
    if (!newVal && selectedProfile.value?.id) {
      selectedProfile.value = null
    }
  },
)

watch(
  () => props.initialProfile,
  (newVal) => {
    if (newVal) {
      selectedProfile.value = newVal
    }
  },
)

function initGenerator(name?: string): string {
  if (name) {
    return name.charAt(0).toUpperCase()
  }
  return ''
}
</script>

<template>
  <div>
    <!-- Selected profile card (unified BaseProfileCard) -->
    <BaseProfileCard
      v-if="selectedProfile"
      :profile="selectedProfile"
      :show-documents="false"
      :label="`${singularLabel} Details`"
      @edit="openProfileEdit"
      @deselect="resetProfile"
    />

    <!-- Unselected: Popover search selector -->
    <Popover v-else v-slot="{ open }" class="relative flex flex-col rounded-md">
      <PopoverButton
        :class="{
          'focus:ring-2 focus:ring-primary-400': !open,
        }"
        class="w-full outline-hidden rounded-md"
        @click="ensureProfilesLoaded"
      >
        <div class="relative flex justify-center px-0 p-0 py-12 bg-surface border border-line-default border-solid rounded-md min-h-[120px]">
          <BaseIcon
            name="UserIcon"
            class="flex justify-center !w-8 !h-8 p-2 mr-4 text-sm text-white bg-surface-muted rounded-full font-base"
          />
          <div class="mt-1">
            <label class="text-sm font-medium text-heading">
              {{ label }}
              <span v-if="required" class="text-status-red">*</span>
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
                v-model="search"
                container-class="m-4"
                :placeholder="placeholder"
                type="text"
                icon="search"
                @update:model-value="debounceSearch"
              />

              <ul class="max-h-80 flex flex-col overflow-auto list border-t border-line-light">
                <li
                  v-for="option in profiles"
                  :key="option.id"
                  class="flex px-6 py-2 border-b border-line-light border-solid cursor-pointer hover:cursor-pointer hover:bg-hover focus:outline-hidden focus:bg-hover"
                  @click="selectProfile(option, close)"
                >
                  <div class="flex items-center justify-center h-10 w-10 mr-4 rounded-full bg-surface-muted uppercase text-primary-500">
                    {{ initGenerator(option.name) }}
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
                <div
                  v-if="profiles.length === 0"
                  class="flex justify-center p-5 text-subtle"
                >
                  <label class="text-base text-muted cursor-pointer">
                    No {{ singularLabel.toLowerCase() }} profiles found
                  </label>
                </div>
              </ul>

              <button
                type="button"
                class="flex items-center justify-center w-full px-6 py-3 bg-hover cursor-pointer"
                @click="openProfileCreate(close)"
              >
                <BaseIcon name="PlusIcon" class="h-5 text-primary-400" />
                <label class="m-0 ml-3 text-sm leading-none cursor-pointer font-base text-primary-400">
                  Add New {{ singularLabel }}
                </label>
              </button>
            </div>
          </PopoverPanel>
        </div>
      </transition>
    </Popover>
  </div>
</template>
