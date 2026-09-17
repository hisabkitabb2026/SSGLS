<script setup lang="ts">
import { computed, ref } from 'vue'
import { useInvoiceStore } from '../store'
import TruckSelectPopup from '@/scripts/features/company/shared/TruckSelectPopup.vue'

/**
 * LRReceiptPartyFields — Renders unified truck selector and details card.
 * Shows truck selector when empty, and truck details when selected.
 */

interface Truck {
  id: number | string
  truck_number: string
  capacity_kg: number | string
  vehicle_type?: string
  status: string
  owner_profile?: { name: string } | null
}

const invoiceStore = useInvoiceStore()
const selectedTruck = ref<Truck | null>(null)

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
  selectedTruck.value = truck
  ;(invoiceStore.newInvoice as Record<string, unknown>).truck_no = truck?.truck_number || ''
}

function resetTruck(): void {
  selectedTruck.value = null
  ;(invoiceStore.newInvoice as Record<string, unknown>).truck_no = ''
}
</script>


<template>
  <div class="relative">
    <!-- Single Card: Shows selector OR details based on selection state -->
    <div
      v-if="!selectedTruck"
      class="flex flex-col p-4 bg-surface border border-line-light border-solid min-h-[170px] rounded-xl shadow"
    >
      <!-- Truck Selector in Card -->
      <TruckSelectPopup
        :initial-truck="initialTruck"
        @select="onTruckSelect"
      />
    </div>

    <!-- Card with Selected Truck Details -->
    <div
      v-else
      class="
        flex flex-col
        p-4
        bg-surface
        border border-line-light border-solid
        min-h-[170px]
        rounded-xl
        shadow
      "
      @click.stop
    >
      <!-- Header: Truck Number + Deselect Action -->
      <div class="flex relative justify-between mb-3">
        <BaseText
          :text="selectedTruck.truck_number"
          class="flex-1 text-base font-medium text-left text-heading"
        />
        <a
          class="
            relative
            my-0
            ml-6
            text-sm
            flex
            items-center
            font-medium
            cursor-pointer
            text-primary-500
          "
          @click="resetTruck"
        >
          <BaseIcon name="XCircleIcon" class="text-muted h-4 w-4 mr-1" />
          {{ $t('general.deselect') }}
        </a>
      </div>

      <!-- Truck Details Grid -->
      <div class="grid grid-cols-2 gap-6 mt-2 flex-1">
        <!-- Owner -->
        <div class="flex flex-col">
          <label class="mb-1 text-xs font-medium text-subtle uppercase">Owner</label>
          <label class="text-sm text-heading">
            {{ selectedTruck.owner_profile?.name || 'N/A' }}
          </label>
        </div>

        <!-- Capacity -->
        <div class="flex flex-col">
          <label class="mb-1 text-xs font-medium text-subtle uppercase">Capacity (KG)</label>
          <label class="text-sm text-heading">
            {{ selectedTruck.capacity_kg || 'N/A' }}
          </label>
        </div>

        <!-- Vehicle Type -->
        <div class="flex flex-col">
          <label class="mb-1 text-xs font-medium text-subtle uppercase">Vehicle Type</label>
          <label class="text-sm text-heading">
            {{ selectedTruck.vehicle_type || 'N/A' }}
          </label>
        </div>

        <!-- Status -->
        <div class="flex flex-col">
          <label class="mb-1 text-xs font-medium text-subtle uppercase">Status</label>
          <label class="text-sm text-heading capitalize">
            {{ selectedTruck.status }}
          </label>
        </div>
      </div>
    </div>
  </div>
</template>
