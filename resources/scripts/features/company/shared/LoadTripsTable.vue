<!--
  LoadTripsTable

  Shared table component for displaying load trips.
  Used by: LoadTripIndexView and WarehouseItemIndexView (Dispatched tab).

  Props:
    - trips: Array of trip objects
    - loading: Boolean loading state
    - showBroker: Whether to show the broker column
    - showItems: Whether to show the Items column

  Emits:
    - row-click: Emitted with the trip id when a row is clicked
    - dispatch: Emitted with the trip id when "Dispatch" button is clicked
    - deliver: Emitted with the trip id when "Deliver" button is clicked
    - edit: Emitted with the full trip object when "Edit" button is clicked
    - delete: Emitted with the trip id when "Delete" button is clicked
-->
<template>
  <div v-if="loading" class="py-8 text-center text-muted">Loading...</div>
  <div
    v-else-if="trips.length === 0"
    class="rounded-lg bg-surface-secondary py-12 text-center text-muted"
  >
    <p class="text-lg">No load trips yet</p>
    <p class="mt-2 text-sm">
      Create a load trip from a ready consolidation group to dispatch a truck.
    </p>
  </div>
  <div v-else class="overflow-x-auto rounded-lg bg-surface shadow-sm">
    <table class="w-full text-sm">
      <thead class="bg-surface-secondary text-xs uppercase text-muted">
        <tr>
          <th class="px-4 py-3 text-left">Trip Number</th>
          <th class="px-4 py-3 text-left">Route</th>
          <th class="px-4 py-3 text-left">Truck / Driver</th>
          <th v-if="showBroker" class="px-4 py-3 text-left">Broker</th>
          <th class="px-4 py-3 text-left">LR Details</th>
          <th v-if="showItems" class="px-4 py-3 text-center">Items</th>
          <th class="px-4 py-3 text-center">Dispatch Date</th>
          <th class="px-4 py-3 text-center">Delivered Date</th>
          <th class="px-4 py-3 text-center">Status</th>
          <th class="px-4 py-3 text-center">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-line-light">
        <tr
          v-for="trip in trips"
          :key="trip.id"
          class="cursor-pointer hover:bg-hover"
          @click="$emit('row-click', trip.id)"
        >
          <td class="px-4 py-3 font-semibold text-heading">{{ trip.trip_number }}</td>
          <td class="px-4 py-3 text-body">
            {{ trip.origin_city || '—' }} → {{ trip.destination_city }}
          </td>
          <td class="px-4 py-3 text-body">
            <p class="font-medium">{{ trip.truck_number || '—' }}</p>
            <p class="text-xs text-muted">{{ trip.driver_name || '—' }}</p>
          </td>
          <td v-if="showBroker" class="px-4 py-3 text-body">
            <p class="font-medium">{{ trip.broker_name || '—' }}</p>
            <p class="text-xs text-muted">{{ trip.broker_phone || '' }}</p>
          </td>
          <td class="px-4 py-3 text-body">
            <div v-if="tripLrNumbers(trip).length" class="flex flex-wrap gap-1">
              <span
                v-for="lr in tripLrNumbers(trip).slice(0, 3)"
                :key="lr"
                class="rounded bg-surface-tertiary px-1.5 py-0.5 text-xs text-body"
              >
                {{ lr }}
              </span>
              <span
                v-if="tripLrNumbers(trip).length > 3"
                class="text-xs text-muted"
              >
                +{{ tripLrNumbers(trip).length - 3 }}
              </span>
            </div>
            <span v-else class="text-muted">—</span>
          </td>
          <td v-if="showItems" class="px-4 py-3 text-center text-body">
            {{ trip.warehouse_items?.length || 0 }}
          </td>
          <td class="px-4 py-3 text-center text-body">
            {{ trip.dispatch_date ? formatDate(trip.dispatch_date) : '—' }}
          </td>
          <td class="px-4 py-3 text-center text-body">
            {{ trip.actual_delivery_date ? formatDate(trip.actual_delivery_date) : '—' }}
          </td>
          <td class="px-4 py-3 text-center">
            <span
              class="rounded px-2 py-1 text-xs font-bold"
              :class="getTripStatusClass(trip.status)"
            >
              {{ trip.status }}
            </span>
          </td>
          <td class="px-4 py-3 text-center" @click.stop>
            <div class="flex justify-center gap-1">
              <BaseButton
                v-if="trip.status === 'planned'"
                size="xs"
                variant="secondary"
                @click="$emit('dispatch', trip.id)"
              >
                Dispatch
              </BaseButton>
              <BaseButton
                v-if="trip.status === 'dispatched'"
                size="xs"
                variant="primary"
                @click="$emit('deliver', trip.id)"
              >
                Deliver
              </BaseButton>
              <BaseButton
                size="xs"
                variant="white"
                title="Edit"
                @click="$emit('edit', trip)"
              >
                <template #left="slotProps">
                  <BaseIcon name="PencilSquareIcon" :class="slotProps.class" />
                </template>
              </BaseButton>
              <BaseButton
                size="xs"
                variant="danger"
                title="Delete"
                @click="$emit('delete', trip.id)"
              >
                <template #left="slotProps">
                  <BaseIcon name="TrashIcon" :class="slotProps.class" />
                </template>
              </BaseButton>
            </div>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup lang="ts">
import { useOperationsHelpers } from '@/scripts/composables/useOperationsHelpers'

  withDefaults(
    defineProps<{
      trips: any[]
      loading?: boolean
      showBroker?: boolean
      showItems?: boolean
    }>(),
    {
      loading: false,
      showBroker: true,
      showItems: true,
    }
  )

defineEmits<{
  (e: 'row-click', id: number): void
  (e: 'dispatch', id: number): void
  (e: 'deliver', id: number): void
  (e: 'edit', trip: any): void
  (e: 'delete', id: number): void
}>()

const { formatDate, getTripStatusClass } = useOperationsHelpers()

/**
 * Extracts unique LR numbers from a trip's warehouse items.
 * Each warehouse item may have a linked LR (invoice) with an invoice_number or lr_number.
 */
const tripLrNumbers = (trip: any): string[] => {
  if (!trip.warehouse_items) return []
  return trip.warehouse_items
    .filter((item: any) => item.lr)
    .map((item: any) => item.lr.invoice_number || item.lr.lr_number || '—')
    .filter((v: string, i: number, arr: string[]) => arr.indexOf(v) === i)
}
</script>
