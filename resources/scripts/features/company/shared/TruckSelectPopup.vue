<script setup lang="ts">
/**
 * TruckSelectPopup — Reusable Popover-based selector for Trucks.
 *
 * Includes a built-in "Add New Truck" button that opens the shared
 * TruckFormModal so the user can create a new truck without leaving
 * the current page.
 *
 * Usage:
 *   <TruckSelectPopup
 *     v-model="form.truck_id"
 *     label="Truck"
 *     :filter-status="['available']"
 *     @select="onTruckSelect"
 *   />
 *
 * The parent must render <TruckFormModal /> at the page level
 * for the "Add New Truck" button to work.
 */
import { ref, computed, nextTick, watch } from 'vue'
import { Popover, PopoverButton, PopoverPanel } from '@headlessui/vue'
import { useDebounceFn } from '@vueuse/core'
import { client } from '@/scripts/api/client'
import TruckFormModal from './TruckFormModal.vue'

interface Truck {
  id: number
  truck_number: string
  capacity_kg: number | string
  vehicle_type?: string
  status: string
  owner_profile?: { name: string } | null
  owner_profile_id?: number | string
  has_expired_documents?: boolean
  has_documents_due_soon?: boolean
}

const props = withDefaults(
  defineProps<{
    /** Model value (truck ID) for v-model support */
    modelValue?: number | string | null
    /** Label shown on the selector card */
    label?: string
    /** Pre-selected truck object (for edit-mode hydration) */
    initialTruck?: Truck | null
    /** Filter trucks by status (e.g. ['available']). Empty = all. */
    filterStatus?: string[]
    /** Whether the field is required */
    required?: boolean
    /** Placeholder text for the search input */
    placeholder?: string
  }>(),
  {
    modelValue: null,
    label: 'Truck',
    initialTruck: null,
    filterStatus: () => [],
    required: false,
    placeholder: 'Search trucks...',
  },
)

const emit = defineEmits<{
  (e: 'update:modelValue', value: number | string | null): void
  (e: 'select', truck: Truck | null): void
  (e: 'add-new'): void
}>()

const search = ref<string>('')
const trucks = ref<Truck[]>([])
const selectedTruck = ref<Truck | null>(props.initialTruck ?? null)
const showTruckForm = ref(false)

// ──────────────────────────────────────────────────────────────
// Data fetching
// ──────────────────────────────────────────────────────────────

async function fetchTrucks(): Promise<void> {
  try {
    const response = await client.get('/api/v1/trucks')
    let allTrucks: Truck[] = response.data?.data || response.data || []

    // Filter by status if requested
    if (props.filterStatus.length > 0) {
      allTrucks = allTrucks.filter((t) => props.filterStatus.includes(t.status))
    }

    // Filter by search term
    if (search.value) {
      const term = search.value.toLowerCase()
      allTrucks = allTrucks.filter(
        (t) =>
          t.truck_number?.toLowerCase().includes(term) ||
          t.vehicle_type?.toLowerCase().includes(term) ||
          t.owner_profile?.name?.toLowerCase().includes(term),
      )
    }

    trucks.value = allTrucks
  } catch {
    trucks.value = []
  }
}

const debounceSearch = useDebounceFn(() => {
  fetchTrucks()
}, 500)

function ensureTrucksLoaded(): void {
  if (!trucks.value.length) {
    fetchTrucks()
  }
}

// ──────────────────────────────────────────────────────────────
// Selection
// ──────────────────────────────────────────────────────────────

function selectTruck(truck: Truck, close: () => void): void {
  selectedTruck.value = truck
  emit('update:modelValue', truck.id)
  emit('select', truck)
  close()
  search.value = ''
}

function resetTruck(): void {
  selectedTruck.value = null
  emit('update:modelValue', null)
  emit('select', null)
}

// ──────────────────────────────────────────────────────────────
// "Add New Truck" — opens the shared TruckFormModal
// ──────────────────────────────────────────────────────────────

async function openTruckCreate(close?: () => void): Promise<void> {
  close?.()
  await nextTick()
  setTimeout(() => {
    showTruckForm.value = true
    emit('add-new')
  }, 150)
}

function onTruckSaved(truck: Truck): void {
  // Auto-select the newly created truck
  selectedTruck.value = truck
  emit('update:modelValue', truck.id)
  emit('select', truck)
  // Refresh the list in the background
  fetchTrucks()
}

// ──────────────────────────────────────────────────────────────
// Sync external modelValue changes (e.g. form resets)
// ──────────────────────────────────────────────────────────────

watch(
  () => props.modelValue,
  (newVal) => {
    if (!newVal && selectedTruck.value?.id) {
      selectedTruck.value = null
    }
  },
)

watch(
  () => props.initialTruck,
  (newVal) => {
    if (newVal) {
      selectedTruck.value = newVal
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
    <!-- Selected truck card -->
    <div
      v-if="selectedTruck"
      class="flex flex-col p-4 bg-surface border border-line-default border-solid min-h-[120px] rounded-md"
      @click.stop
    >
      <div class="flex relative justify-between gap-3 mb-2">
        <BaseText
          :text="selectedTruck.truck_number ?? label"
          class="flex-1 text-base font-medium text-left text-heading"
        />
        <div class="flex flex-wrap justify-end gap-x-4 gap-y-2">
          <a
            class="relative my-0 text-sm flex items-center font-medium cursor-pointer text-primary-500"
            @click="resetTruck"
          >
            <BaseIcon name="XCircleIcon" class="text-muted h-4 w-4 mr-1" />
            {{ $t('general.deselect') }}
          </a>
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-8 mt-2">
        <div class="flex flex-col">
          <label class="mb-1 text-sm font-medium text-left text-muted uppercase whitespace-nowrap">
            Truck Details
          </label>
          <div class="flex flex-col flex-1 p-0 text-left">
            <label class="relative w-11/12 text-sm truncate">
              {{ selectedTruck.truck_number }}
            </label>
            <label v-if="selectedTruck.vehicle_type" class="relative w-11/12 text-xs text-muted mt-1">
              {{ selectedTruck.vehicle_type }} · {{ selectedTruck.capacity_kg }} kg
            </label>
            <label v-if="selectedTruck.owner_profile?.name" class="relative w-11/12 text-xs text-muted mt-1">
              Owner: {{ selectedTruck.owner_profile.name }}
            </label>
          </div>
        </div>
      </div>
    </div>

    <!-- Unselected: Popover search selector -->
    <Popover v-else v-slot="{ open }" class="relative flex flex-col rounded-md">
      <PopoverButton
        :class="{
          'focus:ring-2 focus:ring-primary-400': !open,
        }"
        class="w-full outline-hidden rounded-md"
        @click="ensureTrucksLoaded"
      >
        <div class="relative flex justify-center px-0 p-0 py-12 bg-surface border border-line-default border-solid rounded-md min-h-[120px]">
          <BaseIcon
            name="TruckIcon"
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
                  v-for="option in trucks"
                  :key="option.id"
                  class="flex px-6 py-2 border-b border-line-light border-solid cursor-pointer hover:cursor-pointer hover:bg-hover focus:outline-hidden focus:bg-hover"
                  @click="selectTruck(option, close)"
                >
                  <div class="flex items-center justify-center h-10 w-10 mr-4 rounded-full bg-surface-muted uppercase text-primary-500">
                    {{ initGenerator(option.truck_number) }}
                  </div>
                  <div class="flex-1 flex flex-col text-left">
                    <span class="text-sm font-medium text-heading">
                      {{ option.truck_number }}
                    </span>
                    <span class="text-xs text-muted">
                      {{ option.vehicle_type || 'N/A' }} · {{ option.capacity_kg }} kg
                      <span v-if="option.owner_profile?.name"> · {{ option.owner_profile.name }}</span>
                    </span>
                  </div>
                </li>
                <div
                  v-if="trucks.length === 0"
                  class="flex justify-center p-5 text-subtle"
                >
                  <label class="text-base text-muted cursor-pointer">
                    No trucks found
                  </label>
                </div>
              </ul>

              <button
                type="button"
                class="flex items-center justify-center w-full px-6 py-3 bg-hover cursor-pointer"
                @click="openTruckCreate(close)"
              >
                <BaseIcon name="PlusIcon" class="h-5 text-primary-400" />
                <label class="m-0 ml-3 text-sm leading-none cursor-pointer font-base text-primary-400">
                  Add New Truck
                </label>
              </button>
            </div>
          </PopoverPanel>
        </div>
      </transition>
    </Popover>

    <!-- Shared TruckFormModal for "Add New Truck" support -->
    <TruckFormModal
      :show="showTruckForm"
      @close="showTruckForm = false"
      @saved="onTruckSaved"
    />
  </div>
</template>
