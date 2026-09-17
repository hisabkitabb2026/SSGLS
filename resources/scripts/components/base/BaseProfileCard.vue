<script setup lang="ts">
/**
 * BaseProfileCard — Unified display card for a selected LorryPartyProfile.
 *
 * Used across the app wherever a LorryPartyProfile (OWNER/DRIVER/BROKER) is
 * selected and needs to be displayed in a card layout. Replaces the
 * per-component inline card markup that was duplicated in:
 *   - LorryPartySelectPopup.vue
 *   - PartyProfileSelectPopup.vue
 *   - LorryReceiptPartyFields.vue
 *
 * Props:
 *   profile     — The LorryPartyProfile object to display
 *   showDocuments — Whether to show the Attached Documents column (default: true)
 *   label       — Optional label for the details column (default: derived from type)
 *
 * Emits:
 *   edit        — When the Edit button is clicked
 *   deselect    — When the Deselect button is clicked
 */
import { computed } from 'vue'
import type { LorryPartyProfile, LorryPartyProfileType } from '@/scripts/types/domain/lorry-party-profile'

const props = withDefaults(
  defineProps<{
    profile: LorryPartyProfile | null
    showDocuments?: boolean
    label?: string
  }>(),
  {
    showDocuments: true,
    label: '',
  },
)

const emit = defineEmits<{
  (e: 'edit'): void
  (e: 'deselect'): void
}>()

const type = computed<LorryPartyProfileType | undefined>(() => props.profile?.type)

const displayLabel = computed<string>(() => {
  if (props.label) return props.label
  switch (type.value) {
    case 'OWNER':
      return 'Owner Details'
    case 'DRIVER':
      return 'Driver Details'
    case 'BROKER':
      return 'Broker Details'
    default:
      return 'Profile Details'
  }
})

function compact(value: string | null | undefined): string {
  return value ? String(value).trim() : ''
}

const detailLines = computed<string[]>(() => {
  if (!props.profile) return []
  return [
    compact(props.profile.address),
    compact(props.profile.phone),
    compact(props.profile.code),
  ].filter(Boolean)
})

interface ProfileDocument {
  label: string
  path: string | null
}

const documents = computed<ProfileDocument[]>(() => {
  if (!props.profile) return []

  switch (type.value) {
    case 'OWNER':
      return [
        { label: 'RC Front', path: props.profile.rc_front_path ?? null },
        { label: 'RC Back', path: props.profile.rc_back_path ?? null },
        { label: 'PAN Front', path: props.profile.pan_front_path ?? null },
        { label: 'Insurance Copy', path: props.profile.insurance_path ?? null },
      ]
    case 'DRIVER':
      return [
        { label: 'License Front', path: props.profile.license_front_path ?? null },
        { label: 'License Back', path: props.profile.license_back_path ?? null },
      ]
    case 'BROKER':
      return [
        { label: 'PAN Front', path: props.profile.pan_front_path_broker ?? null },
      ]
    default:
      return []
  }
})
</script>

<template>
  <div
    v-if="profile"
    class="flex flex-col p-4 bg-surface border border-line-default border-solid min-h-[170px] rounded-md"
    @click.stop
  >
    <!-- Header: Name + actions -->
    <div class="flex relative justify-between gap-3 mb-2">
      <BaseText
        :text="profile.name ?? displayLabel"
        class="flex-1 text-base font-medium text-left text-heading"
      />
      <div class="flex flex-wrap justify-end gap-x-4 gap-y-2">
        <a
          v-if="profile.id"
          class="relative my-0 text-sm flex items-center font-medium cursor-pointer text-primary-500"
          @click="emit('edit')"
        >
          <BaseIcon name="PencilIcon" class="text-muted h-4 w-4 mr-1" />
          {{ $t('general.edit') }}
        </a>
        <a
          class="relative my-0 text-sm flex items-center font-medium cursor-pointer text-primary-500"
          @click="emit('deselect')"
        >
          <BaseIcon name="XCircleIcon" class="text-muted h-4 w-4 mr-1" />
          {{ $t('general.deselect') }}
        </a>
      </div>
    </div>

    <!-- Body: Details + Documents -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-8 mt-2">
      <!-- Left column: Address / Phone / Code -->
      <div class="flex flex-col">
        <label
          class="mb-1 text-sm font-medium text-left text-muted uppercase whitespace-nowrap"
        >
          {{ displayLabel }}
        </label>
        <div class="flex flex-col flex-1 p-0 text-left">
          <label
            v-for="line in detailLines"
            :key="line"
            class="relative w-11/12 text-sm truncate"
          >
            {{ line }}
          </label>
          <label
            v-if="profile.type"
            class="relative w-11/12 text-xs text-muted mt-1"
          >
            <span
              class="inline-block px-2 py-0.5 rounded-full bg-primary-100 text-primary-700 font-medium"
            >
              {{ profile.type }}
            </span>
          </label>
        </div>
      </div>

      <!-- Right column: Attached Documents (optional) -->
      <div v-if="showDocuments" class="flex flex-col">
        <label
          class="mb-1 text-sm font-medium text-left text-muted uppercase whitespace-nowrap"
        >
          Attached Documents
        </label>
        <div class="flex flex-col flex-1 p-0 text-left">
          <template v-for="doc in documents" :key="doc.label">
            <a
              v-if="doc.path"
              :href="doc.path"
              target="_blank"
              class="relative w-11/12 text-sm truncate text-primary-500 hover:text-primary-600 flex items-center gap-1 mb-1"
            >
              <BaseIcon name="DocumentIcon" class="h-4 w-4" />
              {{ doc.label }}
            </a>
            <span
              v-else
              class="relative w-11/12 text-sm truncate text-muted mb-1 block"
            >
              {{ doc.label }} (Not attached)
            </span>
          </template>
        </div>
      </div>
    </div>
  </div>
</template>
