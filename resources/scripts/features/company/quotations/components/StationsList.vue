<template>
  <div class="flex flex-col md:flex-row gap-4 md:gap-6 h-full md:h-auto">
    <!-- LEFT PANEL: Station Selector -->
    <div class="w-full md:w-72 bg-surface-secondary rounded-xl border border-line-default p-4 md:p-6 flex flex-col md:max-h-screen md:overflow-y-auto">
      <!-- Header -->
      <div class="mb-6">
        <h3 class="text-sm font-bold text-heading tracking-wide uppercase">Stations</h3>
        <p class="text-xs text-muted mt-1">{{ stations.length }} configured</p>
      </div>

      <!-- Add Station Button -->
      <BaseButton
        variant="primary"
        type="button"
        :disabled="!canAddStation"
        class="w-full mb-3 md:mb-4"
        @click="addStation"
      >
        <template #left="slotProps">
          <BaseIcon name="PlusIcon" :class="slotProps.class" />
        </template>
        Add Station
      </BaseButton>

      <!-- Station List -->
      <div class="flex-1 overflow-y-auto space-y-2">
        <div
          v-for="(station, index) in stations"
          :key="index"
          class="
            relative
            p-3 md:p-4
            rounded-lg
            cursor-pointer
            transition
            border-2
            group
          "
          :class="
            selectedStationIndex === index
              ? 'bg-primary-50 border-primary-300 shadow-md'
              : 'bg-surface border-line-default hover:border-line-strong hover:shadow-sm'
          "
          @click="selectedStationIndex = index"
        >
          <!-- Station Icon -->
          <div class="absolute left-4 top-4 text-xl">📍</div>

          <!-- Station Info -->
          <div class="pl-8">
            <p
              v-if="station.name"
              class="text-sm font-semibold text-heading truncate"
            >
              {{ station.name.toUpperCase() }}
            </p>
            <p v-else class="text-sm text-subtle italic">Unnamed station</p>

            <!-- Rate Badge -->
            <div class="mt-2 inline-flex items-center">
              <span
                class="
                  text-xs
                  font-bold
                  px-2.5
                  py-1
                  rounded-full
                  bg-primary-100
                  text-primary-700
                "
              >
                {{ station.rates?.length || 0 }} rates
              </span>
            </div>
          </div>

          <!-- Delete Button -->
          <BaseButton
            variant="danger"
            type="button"
            size="xs"
            rounded
            class="absolute top-3 right-3"
            :title="$t('general.delete')"
            @click.stop="deleteStation(index)"
          >
            🗑
          </BaseButton>
        </div>
      </div>
    </div>

    <!-- RIGHT PANEL: Rate Manager -->
    <div class="w-full md:flex-1 flex flex-col">
      <div
        v-if="selectedStationIndex !== null && selectedStation"
        class="bg-surface rounded-xl border border-line-default p-4 md:p-8 flex flex-col"
      >
        <!-- Header -->
        <div class="mb-4 md:mb-8 pb-4 md:pb-6 border-b border-line-light">
          <div class="flex flex-col md:flex-row md:items-baseline gap-2 md:gap-3">
            <h2 class="text-2xl md:text-3xl font-bold text-heading">
              {{ (selectedStation.name || 'Unnamed Station').toUpperCase() }}
            </h2>
            <BaseInput
              v-model="selectedStation.name"
              type="text"
              placeholder="Enter station name..."
              class="w-full md:flex-1"
            />
          </div>
          <p class="text-sm text-muted mt-2">
            {{ selectedStation.rates?.length || 0 }} rate(s) configured
          </p>
        </div>

        <!-- Add Rate Form -->
        <div class="bg-primary-50 rounded-lg p-4 md:p-6 mb-4 md:mb-8 border border-primary-200">
          <h3 class="text-xs md:text-sm font-semibold text-heading mb-3 md:mb-4 uppercase tracking-wide">
            Add New Rate
          </h3>

          <div class="flex flex-col md:flex-row gap-3 md:gap-4 md:items-end">
            <!-- Capacity Selector -->
            <div class="flex-1">
              <label class="block text-xs font-medium text-heading mb-2">
                Capacity
              </label>
              <div v-if="!showNewCapacityInput">
                <BaseSelectInput
                  v-model="newRate.capacity"
                  :options="capacitySelectOptions"
                  value-prop="value"
                  label-prop="label"
                  placeholder="Select capacity..."
                />
                <BaseButton
                  variant="primary-outline"
                  type="button"
                  size="sm"
                  class="w-full mt-2"
                  @click="showNewCapacityInput = true"
                >
                  + Custom Capacity
                </BaseButton>
              </div>
              <div v-else class="space-y-2">
                <BaseInput
                  v-model="newCapacity"
                  type="text"
                  placeholder="e.g., 35MT, 40MT"
                />
                <div class="flex gap-2">
                  <BaseButton
                    variant="primary"
                    type="button"
                    size="sm"
                    class="flex-1"
                    @click="newRate.capacity = newCapacity; showNewCapacityInput = false; newCapacity = ''"
                  >
                    Use
                  </BaseButton>
                  <BaseButton
                    variant="white"
                    type="button"
                    size="sm"
                    class="flex-1"
                    @click="showNewCapacityInput = false"
                  >
                    Cancel
                  </BaseButton>
                </div>
              </div>
            </div>

            <!-- Rate Input -->
            <div class="flex-1">
              <label class="block text-xs font-medium text-heading mb-2">
                Rate (₹)
              </label>
              <BaseInput
                v-model.number="newRate.rate"
                type="number"
                placeholder="0"
                min="0"
                step="100"
              />
            </div>

            <!-- Add Button -->
            <div class="flex flex-col gap-1">
              <BaseButton
                variant="primary"
                type="button"
                :disabled="!newRate.capacity || !newRate.rate || capacityExists"
                :title="capacityExists ? `${newRate.capacity.toUpperCase()} already exists` : ''"
                @click="addRate"
              >
                + Add Rate
              </BaseButton>
              <p v-if="capacityExists" class="text-xs text-alert-error-text font-medium">
                ⚠️ Already exists
              </p>
            </div>
          </div>
        </div>

        <!-- Rates Table -->
        <div class="flex-1 overflow-y-auto">
          <div v-if="selectedStation.rates && selectedStation.rates.length" class="space-y-3">
            <div class="text-xs font-semibold text-muted uppercase tracking-wide mb-4">
              Configured Rates
            </div>

            <!-- Table Header -->
            <div class="grid grid-cols-12 gap-2 md:gap-4 px-3 md:px-4 py-2 md:py-3 bg-surface-tertiary rounded-lg font-semibold text-xs text-heading uppercase tracking-wide">
              <div class="col-span-5 md:col-span-4">Capacity</div>
              <div class="col-span-4 md:col-span-5">Rate</div>
              <div class="col-span-3 text-right">Actions</div>
            </div>

            <!-- Table Rows -->
            <div class="space-y-2">
              <div
                v-for="(rate, rateIndex) in selectedStation.rates"
                :key="rateIndex"
                class="
                  grid grid-cols-12 gap-2 md:gap-4
                  px-3 md:px-4 py-3 md:py-4
                  bg-surface
                  border border-line-default
                  rounded-lg
                  hover:shadow-md
                  transition
                  items-center
                "
              >
                <div class="col-span-5 md:col-span-4">
                  <div class="text-sm font-bold text-heading">{{ rate.capacity }}</div>
                </div>
                <div class="col-span-4 md:col-span-5">
                  <div class="text-sm font-bold text-heading">
                    ₹{{ formatNumber(rate.rate) }}
                  </div>
                </div>
                <div class="col-span-3 flex justify-end gap-2">
                  <BaseButton
                    variant="gray"
                    type="button"
                    size="xs"
                    rounded
                    title="Edit"
                    @click="editRate(rateIndex)"
                  >
                    ✎
                  </BaseButton>
                  <BaseButton
                    variant="danger"
                    type="button"
                    size="xs"
                    rounded
                    title="Delete"
                    @click="deleteRate(rateIndex)"
                  >
                    🗑
                  </BaseButton>
                </div>
              </div>
            </div>
          </div>

          <!-- Empty State -->
          <div v-else class="flex flex-col items-center justify-center h-full text-center py-12">
            <div class="text-5xl mb-4">📊</div>
            <p class="text-muted text-sm font-medium">No rates added yet</p>
            <p class="text-subtle text-xs mt-1">Add your first rate using the form above</p>
          </div>
        </div>
      </div>

      <!-- Empty State (No Station Selected) -->
      <div v-else class="flex-1 flex items-center justify-center bg-surface rounded-xl border border-line-default">
        <div class="text-center py-12">
          <div class="text-6xl mb-4">👈</div>
          <p class="text-body font-semibold text-lg">Select a station to manage rates</p>
          <p class="text-subtle text-sm mt-2">Create a new station or click an existing one</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Hidden Rate Modal for Editing -->
  <RateModal
    v-if="showEditModal"
    :rate="editingRate"
    :station="selectedStation"
    @save="saveEditedRate"
    @cancel="showEditModal = false"
  />
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { debouncedWatch } from '@vueuse/core'
import { useI18n } from 'vue-i18n'
import { useDialogStore } from '@/scripts/stores/dialog.store'
import { client } from '@/scripts/api/client'
import RateModal from './RateModal.vue'

const { t } = useI18n()
const dialogStore = useDialogStore()

const props = defineProps({
  modelValue: {
    type: Array,
    default: () => [],
  },
  maxStations: {
    type: Number,
    default: 7,
  },
})

const emit = defineEmits(['update:modelValue'])

const stations = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value),
})

const selectedStationIndex = ref(0)
const showNewCapacityInput = ref(false)
const newCapacity = ref('')
const showEditModal = ref(false)
const editingRateIndex = ref(null)

const defaultCapacities = [
  '5MT',
  '7MT',
  '9MT',
  '10MT',
  '12MT',
  '15MT',
  '20MT',
  '25MT',
  '30MT',
]

const capacitySelectOptions = computed(() => {
  return defaultCapacities.map(c => ({ value: c, label: c }))
})

const newRate = ref({
  capacity: '',
  rate: '',
})

const canAddStation = computed(() => stations.value.length < props.maxStations)

const selectedStation = computed(() => {
  if (selectedStationIndex.value !== null && selectedStationIndex.value < stations.value.length) {
    return stations.value[selectedStationIndex.value]
  }
  return null
})

const editingRate = computed(() => {
  if (editingRateIndex.value !== null && selectedStation.value?.rates[editingRateIndex.value]) {
    return selectedStation.value.rates[editingRateIndex.value]
  }
  return null
})

const capacityExists = computed(() => {
  if (!newRate.value.capacity) return false
  const capacityUpper = newRate.value.capacity.toUpperCase()
  return selectedStation.value?.rates?.some(
    r => r.capacity.toUpperCase() === capacityUpper
  ) ?? false
})

// Auto-load rates for station
debouncedWatch(
  () => selectedStation.value?.name,
  (newName) => {
    if (newName && newName.trim() && (!selectedStation.value.rates || selectedStation.value.rates.length === 0)) {
      loadStationRates(newName)
    }
  },
  { debounce: 800 }
)

async function loadStationRates(stationName) {
  try {
    const response = await client.get('/api/v1/estimates/station-rates', {
      params: { station_name: stationName.toUpperCase() }
    })

    const fetchedRates = response.data.rates || []
    if (fetchedRates.length > 0) {
      const updated = [...stations.value]
      updated[selectedStationIndex.value].rates = fetchedRates
      stations.value = updated
    }
  } catch (error) {
    console.error('Error loading station rates:', error)
  }
}

function formatNumber(num) {
  return (num / 100).toLocaleString('en-IN', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })
}

function addStation() {
  if (canAddStation.value) {
    stations.value = [...stations.value, { name: '', rates: [] }]
    selectedStationIndex.value = stations.value.length - 1
  }
}

function deleteStation(index) {
  dialogStore.openDialog({
    title: t('general.are_you_sure'),
    message: t('general.delete_confirmation'),
    yesLabel: t('general.ok'),
    noLabel: t('general.cancel'),
    variant: 'danger',
    hideNoButton: false,
    size: 'sm',
  }).then((res) => {
    if (res) {
      if (stations.value.length === 1) {
        stations.value = [{ name: '', rates: [] }]
        selectedStationIndex.value = 0
      } else {
        stations.value = stations.value.filter((_, i) => i !== index)
        if (selectedStationIndex.value === index) {
          selectedStationIndex.value = Math.max(0, index - 1)
        }
      }
    }
  })
}

function addRate() {
  if (newRate.value.capacity && newRate.value.rate) {
    const capacityUpper = newRate.value.capacity.toUpperCase()

    // Check if capacity already exists (case-insensitive)
    const existingRate = selectedStation.value.rates?.find(
      r => r.capacity.toUpperCase() === capacityUpper
    )

    if (existingRate) {
      dialogStore.openDialog({
        title: t('general.are_you_sure'),
        message: `${capacityUpper} already exists for this station!\nEdit the existing rate instead.`,
        yesLabel: t('general.ok'),
        hideNoButton: true,
        variant: 'danger',
        size: 'sm',
      })
      return
    }

    const updated = [...stations.value]
    if (!updated[selectedStationIndex.value].rates) {
      updated[selectedStationIndex.value].rates = []
    }
    updated[selectedStationIndex.value].rates.push({
      capacity: capacityUpper,
      rate: Math.round(newRate.value.rate * 100),
    })
    stations.value = updated
    newRate.value = { capacity: '', rate: '' }
    showNewCapacityInput.value = false
  }
}

function editRate(rateIndex) {
  editingRateIndex.value = rateIndex
  showEditModal.value = true
}

function saveEditedRate(rate) {
  const updated = [...stations.value]
  updated[selectedStationIndex.value].rates[editingRateIndex.value] = rate
  stations.value = updated
  showEditModal.value = false
  editingRateIndex.value = null
}

function deleteRate(rateIndex) {
  dialogStore.openDialog({
    title: t('general.are_you_sure'),
    message: t('general.delete_confirmation'),
    yesLabel: t('general.ok'),
    noLabel: t('general.cancel'),
    variant: 'danger',
    hideNoButton: false,
    size: 'sm',
  }).then((res) => {
    if (res) {
      const updated = [...stations.value]
      updated[selectedStationIndex.value].rates = updated[selectedStationIndex.value].rates.filter(
        (_, i) => i !== rateIndex
      )
      stations.value = updated
    }
  })
}
</script>
