<template>
  <BasePage>
    <BasePageHeader title="Warehouse Operations">
      <p class="mt-1 text-sm text-muted">
        Receive material, consolidate part-loads, and dispatch truck trips.
      </p>

      <template #actions>
        <div class="flex items-center justify-end gap-3">
          <BaseButton
            v-if="activeTab === 'warehouse'"
            variant="primary-outline"
            @click="toggleFilter"
          >
            Filter
            <template #right="slotProps">
              <BaseIcon
                v-if="!showFilters"
                name="FunnelIcon"
                :class="slotProps.class"
              />
              <BaseIcon v-else name="XMarkIcon" :class="slotProps.class" />
            </template>
          </BaseButton>
          <BaseButton
            v-if="activeTab === 'dispatched'"
            variant="primary-outline"
            @click="toggleTripFilters"
          >
            Filter
            <template #right="slotProps">
              <BaseIcon
                v-if="!showTripFilters"
                name="FunnelIcon"
                :class="slotProps.class"
              />
              <BaseIcon v-else name="XMarkIcon" :class="slotProps.class" />
            </template>
          </BaseButton>
          <BaseButton
            v-if="activeTab === 'warehouse'"
            variant="primary"
            @click="$router.push({ name: 'warehouse-items.create' })"
          >
            <template #left="slotProps">
              <BaseIcon name="PlusIcon" :class="slotProps.class" />
            </template>
            Receive Material
          </BaseButton>
        </div>
      </template>

    </BasePageHeader>

    <!-- Shared Tab Navigation -->
    <OperationsTabBar
      :active-key="activeTab"
      :warehouse-count="store.dashboardStats?.stored_items ?? null"
      :consolidation-count="consolidationStore.groups.length || null"
      :dispatched-count="loadTripStore.trips.length || null"
      @tab-click="onTabClick"
    />

    <!-- ==================== TAB 1: IN WAREHOUSE ==================== -->
    <div v-if="activeTab === 'warehouse'">
      <!-- KPI Strip — Collapsible -->
      <div v-if="store.dashboardStats" class="mb-4">
        <!-- Full KPI Strip (collapsible) -->
        <div v-if="!kpiCollapsed" class="mb-4">
          <div class="mb-3 flex items-center justify-end">
            <BaseButton size="xs" variant="white" @click="kpiCollapsed = true">
              <template #left="slotProps">
                <BaseIcon name="ChevronUpIcon" :class="slotProps.class" />
              </template>
              Collapse
            </BaseButton>
          </div>
          <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
            <BaseCard container-class="px-4 py-4">
              <p class="text-sm text-muted">Active LRs</p>
              <p class="text-2xl font-bold text-heading">
                {{ groupedByLr.length }}
              </p>
            </BaseCard>
            <BaseCard
              container-class="px-4 py-4"
              :class="{ 'border-2 border-status-red': (store.dashboardStats.overdue_items || 0) > 0 }"
            >
              <p class="text-sm text-muted">Overdue LRs</p>
              <p
                class="text-2xl font-bold"
                :class="overdueLrCount > 0 ? 'text-status-red' : 'text-heading'"
              >
                {{ overdueLrCount }}
              </p>
            </BaseCard>
            <BaseCard container-class="px-4 py-4">
              <p class="text-sm text-muted">Total Weight</p>
              <p class="text-2xl font-bold text-heading">
                {{ formatWeight(store.dashboardStats.total_weight_kg) }} kg
              </p>
            </BaseCard>
            <BaseCard container-class="px-4 py-4">
              <p class="text-sm text-muted">Destinations</p>
              <p class="text-2xl font-bold text-heading">
                {{ store.dashboardStats.unique_destinations || 0 }}
              </p>
            </BaseCard>
          </div>

          <!-- Aging Buckets -->
          <div v-if="store.dashboardStats?.aging_buckets" class="mt-3 flex gap-3">
            <BaseCard
              v-for="(count, bucket) in store.dashboardStats.aging_buckets"
              :key="bucket"
              container-class="px-3 py-3 text-center"
              class="flex-1"
              :class="getAgingClass(bucket)"
            >
              <p class="text-xs text-muted">{{ bucket }} days</p>
              <p class="text-xl font-bold">{{ count }}</p>
            </BaseCard>
          </div>
        </div>

        <!-- Collapsed KPI summary bar -->
        <div
          v-else
          class="mb-4 flex items-center gap-4 rounded-lg bg-surface-secondary px-4 py-2 text-sm"
        >
          <span class="font-semibold text-heading">{{ groupedByLr.length }} Active LRs</span>
          <span v-if="overdueLrCount > 0" class="text-status-red font-semibold">
            ⚠ {{ overdueLrCount }} Overdue
          </span>
          <span class="text-muted">
            {{ formatWeight(store.dashboardStats.total_weight_kg) }} kg ·
            {{ store.dashboardStats.unique_destinations || 0 }} Destinations
          </span>
          <BaseButton size="xs" variant="white" class="ml-auto" @click="kpiCollapsed = false">
            <template #left="slotProps">
              <BaseIcon name="ChevronDownIcon" :class="slotProps.class" />
            </template>
            Expand
          </BaseButton>
        </div>
      </div>

      <!-- Advanced Filters (collapsible) — includes search, sort, and all filter keys -->
      <BaseFilterWrapper
        v-if="activeTab === 'warehouse'"
        :show="showFilters"
        class="mt-2"
        :row-on-xl="true"
        @clear="clearFilters"
      >
        <BaseInputGroup label="Search">
          <BaseInput
            v-model="quickSearch"
            type="text"
            placeholder="Search LR no, customer..."
          />
        </BaseInputGroup>

        <BaseInputGroup label="Sort By">
          <BaseMultiselect
            v-model="sortBy"
            :options="sortOptions"
            value-prop="value"
            label="label"
            track-by="value"
            :allow-empty="false"
            :show-labels="false"
            placeholder="Sort..."
          />
        </BaseInputGroup>

        <BaseInputGroup label="Status">
          <BaseMultiselect
            v-model="selectedStatus"
            :options="statusOptions"
            value-prop="value"
            label="label"
            track-by="value"
            searchable
            placeholder="All Statuses"
          />
        </BaseInputGroup>

        <BaseInputGroup label="Destination">
          <BaseMultiselect
            v-model="selectedDestination"
            :options="allDestinations"
            placeholder="All Destinations"
            searchable
          />
        </BaseInputGroup>

        <BaseInputGroup label="LR Number">
          <BaseInput
            v-model="selectedLrNumber"
            type="text"
            placeholder="Search LR no..."
          />
        </BaseInputGroup>

        <BaseInputGroup label="Load Type">
          <BaseMultiselect
            v-model="selectedLoadType"
            :options="loadTypeOptions"
            value-prop="value"
            label="label"
            track-by="value"
            placeholder="All Load Types"
          />
        </BaseInputGroup>

        <BaseInputGroup label="Consignor">
          <BaseMultiselect
            v-model="selectedConsignor"
            :options="allConsignors"
            placeholder="All Consignors"
            searchable
          />
        </BaseInputGroup>

        <BaseInputGroup label="Consignee">
          <BaseMultiselect
            v-model="selectedConsignee"
            :options="allConsignees"
            placeholder="All Consignees"
            searchable
          />
        </BaseInputGroup>
      </BaseFilterWrapper>


      <!-- Bulk action bar (fixed at bottom when LRs are selected) -->
      <Transition name="slide-up">
        <div
          v-if="selectedLrIds.size > 0"
          class="fixed bottom-0 left-0 right-0 z-50 border-t border-line-default bg-surface px-4 py-3 shadow-lg"
        >
          <div class="mx-auto flex max-w-5xl items-center justify-between gap-3">
            <div class="flex items-center gap-3">
              <span class="text-sm font-semibold text-heading">
                {{ selectedLrIds.size }} LR{{ selectedLrIds.size !== 1 ? 's' : '' }} selected
              </span>
              <BaseButton size="xs" variant="white" @click="clearSelection">Clear</BaseButton>
            </div>
            <div class="flex items-center gap-2">
              <BaseButton size="sm" variant="primary-outline" @click="bulkCreateConsolidation">
                <template #left="slotProps">
                  <BaseIcon name="ArrowsRightLeftIcon" :class="slotProps.class" />
                </template>
                <span class="hidden sm:inline">Create Consolidation</span>
              </BaseButton>
              <BaseButton size="sm" variant="white" @click="clearSelection">Dismiss</BaseButton>
            </div>
          </div>
        </div>
      </Transition>

      <!-- View mode toolbar (Compact/Full + Group By Destination) -->
      <div v-if="!store.loading && groupedByLr.length > 0" class="mb-3 flex items-center gap-2">
        <BaseButton
          size="xs"
          :variant="viewMode === 'compact' ? 'primary' : 'white'"
          @click="viewMode = viewMode === 'compact' ? 'full' : 'compact'"
        >
          <template #left="slotProps">
            <BaseIcon
              :name="viewMode === 'compact' ? 'Bars3Icon' : 'Squares2X2Icon'"
              :class="slotProps.class"
            />
          </template>
          {{ viewMode === 'compact' ? 'Compact' : 'Full' }}
        </BaseButton>
        <BaseButton
          size="xs"
          :variant="groupByDestination ? 'primary' : 'white'"
          @click="groupByDestination = !groupByDestination"
        >
          <template #left="slotProps">
            <BaseIcon name="MapPinIcon" :class="slotProps.class" />
          </template>
          By Destination
        </BaseButton>
      </div>

      <!-- Loading / Empty -->
      <div v-if="store.loading" class="py-8 text-center text-muted">Loading...</div>

      <div
        v-else-if="groupedByLr.length === 0"
        class="rounded-lg bg-surface-secondary py-12 text-center text-muted"
      >
        <p class="text-lg">No warehouse items yet</p>
        <p class="mt-2 text-sm">
          Click "Receive Material" to add items to your warehouse
        </p>
      </div>

      <!-- ====== GROUPED BY DESTINATION MODE ====== -->
      <div v-else-if="groupByDestination" class="space-y-4">
        <div v-for="destGroup in groupedByDestination" :key="destGroup.destination">
          <!-- Destination section header -->
          <div
            class="flex cursor-pointer items-center justify-between rounded-lg bg-surface-secondary px-4 py-3"
            @click="toggleDestinationCollapse(destGroup.destination)"
          >
            <div class="flex items-center gap-2">
              <BaseIcon
                :name="collapsedDestinations.has(destGroup.destination) ? 'ChevronRightIcon' : 'ChevronDownIcon'"
                class="h-5 w-5 text-muted"
              />
              <h3 class="text-base font-bold text-heading">
                📍 {{ destGroup.destination }}
              </h3>
              <span class="rounded-full bg-surface-tertiary px-2 py-0.5 text-xs text-muted">
                {{ destGroup.lrs.length }} LR{{ destGroup.lrs.length !== 1 ? 's' : '' }}
              </span>
            </div>
            <div class="flex items-center gap-3 text-sm text-muted">
              <span>{{ formatWeight(destGroup.totalWeight) }} kg</span>
              <span v-if="destGroup.overdueCount > 0" class="text-status-red font-semibold">
                ⚠ {{ destGroup.overdueCount }} overdue
              </span>
            </div>
          </div>

          <!-- LR cards under this destination -->
          <div v-if="!collapsedDestinations.has(destGroup.destination)" class="mt-2 space-y-3">
            <div v-for="lrGroup in destGroup.lrs" :key="lrGroup.lrId">
              <LrCard
                :lr-group="lrGroup"
                :view-mode="viewMode"
                :is-expanded="expandedLrs.has(lrGroup.lrId)"
                :is-selected="selectedLrIds.has(lrGroup.lrId)"
                :item-status-options="itemStatusOptions"
                @toggle-expand="toggleLrExpand(lrGroup.lrId)"
                @toggle-select="toggleLrSelect(lrGroup.lrId)"
                @receive-more="$router.push({ name: 'warehouse-items.create', query: { lr: lrGroup.lrId } })"
                @create-consolidation="goToConsolidation(lrGroup.destination)"
                @view-lr="$router.push({ name: 'invoices.view', params: { id: lrGroup.lrId } })"
                @update-status="updateStatus"
                @delete-item="deleteItem"
              />
            </div>
          </div>
        </div>
      </div>

      <!-- ====== FLAT LIST MODE (default) ====== -->
      <div v-else class="space-y-3">
        <!-- Expand/Collapse All bar -->
        <div v-if="viewMode === 'compact' && groupedByLr.length > 1" class="flex items-center gap-2">
          <BaseButton size="xs" variant="white" @click="expandAllLrs">
            <template #left="slotProps">
              <BaseIcon name="ChevronDownIcon" :class="slotProps.class" />
            </template>
            Expand All
          </BaseButton>
          <BaseButton size="xs" variant="white" @click="collapseAllLrs">
            <template #left="slotProps">
              <BaseIcon name="ChevronUpIcon" :class="slotProps.class" />
            </template>
            Collapse All
          </BaseButton>
          <span class="ml-auto text-xs text-muted">
            Showing {{ visibleLrGroups.length }} of {{ groupedByLr.length }} LRs
          </span>
        </div>

        <!-- LR Cards -->
        <div v-for="lrGroup in visibleLrGroups" :key="lrGroup.lrId">
          <LrCard
            :lr-group="lrGroup"
            :view-mode="viewMode"
            :is-expanded="expandedLrs.has(lrGroup.lrId)"
            :is-selected="selectedLrIds.has(lrGroup.lrId)"
            :item-status-options="itemStatusOptions"
            @toggle-expand="toggleLrExpand(lrGroup.lrId)"
            @toggle-select="toggleLrSelect(lrGroup.lrId)"
            @receive-more="$router.push({ name: 'warehouse-items.create', query: { lr: lrGroup.lrId } })"
            @create-consolidation="goToConsolidation(lrGroup.destination)"
            @view-lr="$router.push({ name: 'invoices.view', params: { id: lrGroup.lrId } })"
            @update-status="updateStatus"
            @delete-item="deleteItem"
          />
        </div>

        <!-- Load More button -->
        <div v-if="visibleLrCount < groupedByLr.length" class="flex justify-center py-4">
          <BaseButton variant="primary-outline" @click="loadMoreLrs">
            <template #left="slotProps">
              <BaseIcon name="ChevronDownIcon" :class="slotProps.class" />
            </template>
            Load More ({{ groupedByLr.length - visibleLrCount }} remaining)
          </BaseButton>
        </div>
      </div>
    </div>

    <!-- ==================== TAB 2: CONSOLIDATION ==================== -->
    <div v-if="activeTab === 'consolidation'">
      <!-- KPI Strip -->
      <div class="mb-6 grid grid-cols-2 gap-4 md:grid-cols-4">
        <BaseCard container-class="px-4 py-4">
          <p class="text-sm text-muted">Open Groups</p>
          <p class="text-2xl font-bold text-heading">{{ consolidationStats.open }}</p>
        </BaseCard>
        <BaseCard container-class="px-4 py-4">
          <p class="text-sm text-muted">Ready to Dispatch</p>
          <p class="text-2xl font-bold text-status-green">{{ consolidationStats.ready }}</p>
        </BaseCard>
        <BaseCard container-class="px-4 py-4">
          <p class="text-sm text-muted">Dispatched</p>
          <p class="text-2xl font-bold text-status-blue">{{ consolidationStats.dispatched }}</p>
        </BaseCard>
        <BaseCard container-class="px-4 py-4">
          <p class="text-sm text-muted">Total Weight (Open)</p>
          <p class="text-2xl font-bold text-heading">
            {{ formatWeight(consolidationStats.totalWeight) }} kg
          </p>
        </BaseCard>
      </div>

      <!-- Unassigned Material -->
      <div v-if="consolidationStore.candidates.length > 0" class="mb-8">
        <h2 class="mb-3 text-lg font-bold text-heading">
          Unassigned Material (Available for Consolidation)
        </h2>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
          <BaseCard
            v-for="candidate in consolidationStore.candidates"
            :key="candidate.destination"
            container-class="p-4"
            class="border-l-4 border-status-yellow"
          >
            <div class="mb-2 flex items-start justify-between">
              <h3 class="font-bold text-heading">{{ candidate.destination }}</h3>
              <span class="rounded-full bg-status-yellow/10 px-2 py-1 text-xs text-status-yellow">
                {{ candidate.item_count }} item{{ candidate.item_count !== 1 ? 's' : '' }}
              </span>
            </div>
            <div class="mb-3 space-y-1 text-sm">
              <div>
                <p class="text-xs text-muted">Total Weight</p>
                <p class="font-semibold text-body">
                  {{ formatWeight(candidate.total_weight_kg) }} kg
                </p>
              </div>
              <div>
                <p class="text-xs text-muted">Total Packages</p>
                <p class="font-semibold text-body">{{ candidate.total_packages || 0 }}</p>
              </div>
            </div>
            <BaseButton
              variant="primary"
              class="w-full"
              @click="openCreateForDestination(candidate.destination)"
            >
              Create Group for {{ candidate.destination }}
            </BaseButton>
          </BaseCard>
        </div>
      </div>

      <!-- Consolidation Groups -->
      <div class="mb-4 flex items-center justify-between">
        <h2 class="text-lg font-bold text-heading">Consolidation Groups</h2>
        <BaseButton variant="primary-outline" @click="showCreateModal = true">
          <template #left="slotProps">
            <BaseIcon name="PlusIcon" :class="slotProps.class" />
          </template>
          New Group
        </BaseButton>
      </div>

      <div v-if="consolidationStore.loading" class="py-8 text-center text-muted">
        Loading...
      </div>
      <div
        v-else-if="consolidationStore.groups.length === 0"
        class="rounded-lg bg-surface-secondary py-12 text-center text-muted"
      >
        <p class="text-lg">No consolidation groups yet</p>
        <p class="mt-2 text-sm">
          Create a group to start combining part-load material for a destination.
        </p>
      </div>

      <!-- Shared ConsolidationGroupCard -->
      <div v-else class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
        <ConsolidationGroupCard
          v-for="group in consolidationStore.groups"
          :key="group.id"
          :group="group"
          @click="(id) => $router.push({ name: 'consolidation.show', params: { id } })"
          @delete="deleteConsolidationGroup"
        />
      </div>

      <!-- Create Consolidation Modal -->
      <BaseModal :show="showCreateModal" @close="closeCreateModal">
        <template #header>
          <div class="flex w-full items-center justify-between">
            <span>New Consolidation Group</span>
            <BaseIcon
              name="XMarkIcon"
              class="h-6 w-6 cursor-pointer text-muted"
              @click="closeCreateModal"
            />
          </div>
        </template>
        <form class="space-y-5 px-6 py-5" @submit.prevent="submitCreate">
          <BaseInputGrid layout="one-column">
            <BaseInputGroup label="Destination City" required>
              <BaseInput
                v-model="createForm.destination_city"
                type="text"
                placeholder="e.g. Mumbai"
              />
            </BaseInputGroup>
            <BaseInputGroup label="Truck Capacity (kg)">
              <BaseInput
                v-model.number="createForm.truck_capacity_kg"
                type="number"
                placeholder="e.g. 9000"
              />
              <p class="mt-1 text-xs text-muted">Default: 9000 kg (standard truck load)</p>
            </BaseInputGroup>
            <BaseInputGroup label="Notes">
              <BaseTextarea v-model="createForm.notes" rows="2" />
            </BaseInputGroup>
          </BaseInputGrid>
          <div
            class="flex flex-col-reverse gap-3 border-t border-line-light pt-5 sm:flex-row sm:justify-end"
          >
            <BaseButton variant="white" type="button" @click="closeCreateModal">
              Cancel
            </BaseButton>
            <BaseButton :disabled="!createForm.destination_city" type="submit">
              Create Group
            </BaseButton>
          </div>
        </form>
      </BaseModal>
    </div>

    <!-- ==================== TAB 3: DISPATCHED ==================== -->
    <div v-if="activeTab === 'dispatched'">
      <!-- KPI Strip -->
      <div class="mb-6 grid grid-cols-2 gap-4 md:grid-cols-4">
        <BaseCard container-class="px-4 py-4">
          <p class="text-sm text-muted">Ready to Dispatch</p>
          <p class="text-2xl font-bold text-status-blue">{{ tripStats.planned }}</p>
        </BaseCard>
        <BaseCard container-class="px-4 py-4">
          <p class="text-sm text-muted">Dispatched</p>
          <p class="text-2xl font-bold text-status-purple">{{ tripStats.dispatched }}</p>
        </BaseCard>
        <BaseCard container-class="px-4 py-4">
          <p class="text-sm text-muted">Delivered</p>
          <p class="text-2xl font-bold text-status-green">{{ tripStats.delivered }}</p>
        </BaseCard>
        <BaseCard container-class="px-4 py-4">
          <p class="text-sm text-muted">Total Items Moved</p>
          <p class="text-2xl font-bold text-heading">{{ tripStats.totalItems }}</p>
        </BaseCard>
      </div>

      <!-- Trip Filter Panel -->
      <BaseFilterWrapper :show="showTripFilters" :row-on-xl="true" class="mb-4" @clear="clearTripFilters">
        <BaseInputGroup label="Status">
          <BaseMultiselect
            v-model="tripFilters.status"
            :options="tripStatusOptions"
            value-prop="value"
            label="label"
            track-by="value"
            placeholder="All statuses"
            @update:model-value="fetchTripsWithFilters(1)"
          />
        </BaseInputGroup>

        <BaseInputGroup label="LR Number">
          <BaseInput
            v-model="tripFilters.lr"
            type="text"
            placeholder="Search LR no..."
            @input="debouncedFetchTrips(1)"
          />
        </BaseInputGroup>

        <BaseInputGroup label="Truck No">
          <BaseInput
            v-model="tripFilters.truck_number"
            type="text"
            placeholder="Search truck no..."
            @input="debouncedFetchTrips(1)"
          />
        </BaseInputGroup>

        <BaseInputGroup label="Route">
          <BaseInput
            v-model="tripFilters.route"
            type="text"
            placeholder="Search route..."
            @input="debouncedFetchTrips(1)"
          />
        </BaseInputGroup>

        <BaseInputGroup label="Dispatch Date">
          <BaseDatePicker
            v-model="tripFilters.dispatch_date_from"
            :calendar-button="true"
            @update:model-value="fetchTripsWithFilters(1)"
          />
        </BaseInputGroup>

        <BaseInputGroup label="Delivered Date">
          <BaseDatePicker
            v-model="tripFilters.delivered_date_from"
            :calendar-button="true"
            @update:model-value="fetchTripsWithFilters(1)"
          />
        </BaseInputGroup>
      </BaseFilterWrapper>

      <div class="mb-4 flex items-center justify-between">
        <h2 class="text-lg font-bold text-heading">Load Trips</h2>
        <BaseButton variant="primary" @click="openNewTrip">
          <template #left="slotProps">
            <BaseIcon name="PlusIcon" :class="slotProps.class" />
          </template>
          New Load Trip
        </BaseButton>
      </div>

      <!-- Shared LoadTripsTable (without broker/items columns for compact view) -->
      <LoadTripsTable
        :trips="loadTripStore.trips"
        :loading="loadTripStore.loading"
        :show-broker="false"
        :show-items="false"
        @row-click="(id) => $router.push({ name: 'load-trips.show', params: { id } })"
        @dispatch="dispatchTrip"
        @deliver="markDelivered"
        @edit="openEditTrip"
        @delete="deleteLoadTrip"
      />

      <!-- Server-side pagination -->
      <TablePagination
        v-if="!loadTripStore.loading && loadTripStore.trips.length"
        :pagination="{
          currentPage: loadTripStore.pagination.currentPage,
          totalPages: loadTripStore.pagination.lastPage,
          totalCount: loadTripStore.pagination.total,
          count: loadTripStore.trips.length,
          limit: loadTripStore.pagination.perPage,
        }"
        @page-change="onTripsPageChange"
      />

      <!-- Shared LoadTripFormModal -->
      <LoadTripFormModal
        :show="showTripModal"
        :ready-groups="readyGroups"
        :edit-trip="editingTrip"
        @close="closeTripModal"
        @submit="submitTrip"
      />
    </div>

  </BasePage>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useWarehouseItemStore } from '../store'
import { useConsolidationStore } from '../../consolidation/store'
import { useLoadTripStore } from '../../load-trips/store'
import { useDialogStore } from '@/scripts/stores/dialog.store'
import { useApiResponse } from '@/scripts/composables/useApiResponse'
import { useOperationsHelpers } from '@/scripts/composables/useOperationsHelpers'
import OperationsTabBar from '@/scripts/features/company/shared/OperationsTabBar.vue'
import LoadTripsTable from '@/scripts/features/company/shared/LoadTripsTable.vue'
import LoadTripFormModal from '@/scripts/features/company/shared/LoadTripFormModal.vue'
import ConsolidationGroupCard from '@/scripts/features/company/shared/ConsolidationGroupCard.vue'
import TablePagination from '@/scripts/components/table/TablePagination.vue'
import LrCard from '../components/LrCard.vue'

const store = useWarehouseItemStore()
const consolidationStore = useConsolidationStore()
const loadTripStore = useLoadTripStore()
const dialogStore = useDialogStore()
const router = useRouter()
const { extractErrorMessage } = useApiResponse()
const {
  formatWeight,
  formatDate,
  getAgingClass,
  getDaysClass,
  getPriorityClass,
} = useOperationsHelpers()

const activeTab = ref('warehouse')
const showFilters = ref(false)
const selectedStatus = ref('')
const selectedDestination = ref('')
const selectedLrNumber = ref('')
const selectedLoadType = ref('')
const selectedConsignor = ref('')
const selectedConsignee = ref('')
const showCreateModal = ref(false)
const showTripModal = ref(false)
const showTripFilters = ref(false)
const editingTrip = ref<any>(null)

// --- UI Enhancement State ---
const viewMode = ref<'compact' | 'full'>('compact')
const groupByDestination = ref(false)
const kpiCollapsed = ref(false)
const quickSearch = ref('')
const quickStatusFilter = ref('')
const sortBy = ref('overdue')
const visibleLrCount = ref(20) // Load More pagination
const selectedLrIds = ref<Set<number | string>>(new Set())
const collapsedDestinations = ref<Set<string>>(new Set())

// Sort options for the filter panel
const sortOptions = [
  { value: 'overdue', label: 'Overdue First' },
  { value: 'oldest', label: 'Oldest First' },
  { value: 'newest', label: 'Newest First' },
  { value: 'weight', label: 'By Weight' },
  { value: 'destination', label: 'By Destination' },
]

// Trip filter state (server-side)

const tripFilters = ref({
  status: '' as string,
  lr: '' as string,
  truck_number: '' as string,
  route: '' as string,
  dispatch_date_from: '' as string,
  dispatch_date_to: '' as string,
  delivered_date_from: '' as string,
  delivered_date_to: '' as string,
})

// Debounce helper for text-input filters (LR, Truck No, Route)
let tripFilterTimeout: ReturnType<typeof setTimeout> | null = null
const debouncedFetchTrips = (page = 1) => {
  if (tripFilterTimeout) clearTimeout(tripFilterTimeout)
  tripFilterTimeout = setTimeout(() => {
    fetchTripsWithFilters(page)
  }, 400)
}

const tripStatusOptions = [
  { value: 'planned', label: 'Planned' },
  { value: 'dispatched', label: 'Dispatched' },
  { value: 'delivered', label: 'Delivered' },
  { value: 'cancelled', label: 'Cancelled' },
]

const toggleFilter = () => {
  showFilters.value = !showFilters.value
}

const clearFilters = () => {
  selectedStatus.value = ''
  selectedDestination.value = ''
  selectedLrNumber.value = ''
  selectedLoadType.value = ''
  selectedConsignor.value = ''
  selectedConsignee.value = ''
  quickSearch.value = ''
  quickStatusFilter.value = ''
}

const loadTypeOptions = [
  { value: 'part_load', label: 'Part Load' },
  { value: 'full_load', label: 'Full Load' },
]


const statusOptions = [
  { value: '', label: 'All Statuses' },
  { value: 'stored', label: 'Stored' },
  { value: 'picked_for_consolidation', label: 'Picked for Consolidation' },
  { value: 'loaded_on_vehicle', label: 'Loaded on Vehicle' },
  { value: 'in_transit', label: 'In Transit' },
  { value: 'delivered', label: 'Delivered' },
  { value: 'cancelled', label: 'Cancelled' },
]

const itemStatusOptions = [
  { value: 'stored', label: 'Stored' },
  { value: 'picked_for_consolidation', label: 'Picked' },
  { value: 'loaded_on_vehicle', label: 'Loaded' },
  { value: 'in_transit', label: 'In Transit' },
  { value: 'delivered', label: 'Delivered' },
  { value: 'cancelled', label: 'Cancelled' },
]

const createForm = ref({
  destination_city: '',
  truck_capacity_kg: 9000,
  notes: '',
})

// --- Computed: Warehouse Tab ---
const allDestinations = computed(() => {
  const destinations = new Set<string>()
  store.items.forEach((item: any) => {
    if (item.destination_city) {
      destinations.add(item.destination_city)
    }
  })
  return Array.from(destinations).sort()
})

const allConsignors = computed(() => {
  const names = new Set<string>()
  store.items.forEach((item: any) => {
    const name = item.consignor_name || item.lr?.customer?.name
    if (name) names.add(name)
  })
  return Array.from(names).sort()
})

const allConsignees = computed(() => {
  const names = new Set<string>()
  store.items.forEach((item: any) => {
    if (item.consignee_name) names.add(item.consignee_name)
  })
  return Array.from(names).sort()
})

// Helper: get LR status badge label for filtering
const getLrStatusBadgeLabel = (lrGroup: any) => {
  const statuses = lrGroup.items.map((i: any) => i.status)
  const allDelivered = statuses.every((s: string) => s === 'delivered')
  const allInTransit = statuses.every((s: string) => s === 'in_transit')
  const anyStored = statuses.some((s: string) => s === 'stored')
  if (allDelivered) return 'DELIVERED'
  if (allInTransit) return 'IN TRANSIT'
  if (anyStored && statuses.some((s: string) => s !== 'stored')) return 'PARTIAL'
  return 'IN STORAGE'
}

// LR-centric grouping: items grouped by their LR Receipt (lr_id)
// Now with quick search, quick status filter, and sort options
const groupedByLr = computed(() => {
  const groups: Record<string, any> = {}

  const lrSearch = (selectedLrNumber.value || quickSearch.value).trim().toLowerCase()
  const generalSearch = quickSearch.value.trim().toLowerCase()

  store.items.forEach((item: any) => {
    if (selectedStatus.value && item.status !== selectedStatus.value) return
    if (selectedDestination.value && item.destination_city !== selectedDestination.value) return
    if (selectedLoadType.value && item.load_type !== selectedLoadType.value) return
    if (selectedConsignor.value) {
      const itemConsignor = item.consignor_name || item.lr?.customer?.name || ''
      if (itemConsignor !== selectedConsignor.value) return
    }
    if (selectedConsignee.value && item.consignee_name !== selectedConsignee.value) return
    if (lrSearch) {
      const lrNumber = (item.lr?.invoice_number || '').toLowerCase()
      const customerName = (item.lr?.customer?.name || '').toLowerCase()
      if (!lrNumber.includes(lrSearch) && !customerName.includes(generalSearch)) return
    }

    const lrId = item.lr_id || 'no-lr'
    const lrNumber = item.lr?.invoice_number || 'Unlinked Item'
    const consignor = item.consignor_name || item.lr?.customer?.name || ''
    const destination = item.destination_city || '—'

    if (!groups[lrId]) {
      groups[lrId] = {
        lrId,
        lrNumber,
        customerName: item.lr?.customer?.name || '',
        consignor,
        consignee: item.consignee_name || '',
        origin: item.origin_city || '',
        destination,
        loadType: item.load_type || 'part_load',
        priority: item.priority || 'normal',
        items: [],
        totalWeight: 0,
        totalPackages: 0,
        overdueCount: 0,
        maxDaysInWarehouse: 0,
        earliestReceived: item.date_received || '',
        latestReceived: item.date_received || '',
        promisedDispatch: item.promised_dispatch_date || '',
      }
    }
    groups[lrId].items.push(item)
    groups[lrId].totalWeight += parseFloat(item.weight_kg) || 0
    groups[lrId].totalPackages += item.no_of_packages || 0
    if (item.is_overdue) groups[lrId].overdueCount++
    const days = item.days_in_warehouse || 0
    if (days > groups[lrId].maxDaysInWarehouse) {
      groups[lrId].maxDaysInWarehouse = days
    }
    const received = item.date_received || ''
    if (received && received < groups[lrId].earliestReceived) {
      groups[lrId].earliestReceived = received
    }
    if (received && received > groups[lrId].latestReceived) {
      groups[lrId].latestReceived = received
    }
    const promised = item.promised_dispatch_date || ''
    if (promised && (!groups[lrId].promisedDispatch || promised < groups[lrId].promisedDispatch)) {
      groups[lrId].promisedDispatch = promised
    }
  })

  let result = Object.values(groups)

  // Apply quick status filter
  if (quickStatusFilter.value === 'overdue') {
    result = result.filter((g: any) => g.overdueCount > 0)
  } else if (quickStatusFilter.value) {
    result = result.filter((g: any) => getLrStatusBadgeLabel(g) === quickStatusFilter.value)
  }

  // Sort based on sortBy selection
  result.sort((a: any, b: any) => {
    switch (sortBy.value) {
      case 'oldest':
        return b.maxDaysInWarehouse - a.maxDaysInWarehouse
      case 'newest':
        return a.maxDaysInWarehouse - b.maxDaysInWarehouse
      case 'weight':
        return b.totalWeight - a.totalWeight
      case 'destination':
        return (a.destination || '').localeCompare(b.destination || '')
      case 'overdue':
      default:
        if (a.overdueCount > 0 && b.overdueCount === 0) return -1
        if (a.overdueCount === 0 && b.overdueCount > 0) return 1
        return b.maxDaysInWarehouse - a.maxDaysInWarehouse
    }
  })

  return result.map((group: any) => ({
    ...group,
    items: group.items.sort(
      (a: any, b: any) =>
        new Date(b.date_received).getTime() - new Date(a.date_received).getTime()
    ),
  }))
})

// Count of LRs that have at least one overdue item
const overdueLrCount = computed(() =>
  groupedByLr.value.filter((g: any) => g.overdueCount > 0).length
)

// Visible LR groups (Load More pagination)
const visibleLrGroups = computed(() => groupedByLr.value.slice(0, visibleLrCount.value))

// Grouped by destination
const groupedByDestination = computed(() => {
  const destMap: Record<string, any> = {}
  groupedByLr.value.forEach((lr: any) => {
    const dest = lr.destination || '—'
    if (!destMap[dest]) {
      destMap[dest] = {
        destination: dest,
        lrs: [],
        totalWeight: 0,
        overdueCount: 0,
      }
    }
    destMap[dest].lrs.push(lr)
    destMap[dest].totalWeight += lr.totalWeight
    destMap[dest].overdueCount += lr.overdueCount > 0 ? 1 : 0
  })
  return Object.values(destMap).sort((a: any, b: any) =>
    a.destination.localeCompare(b.destination)
  )
})

// --- Expand/Collapse Methods ---
const expandedLrs = ref<Set<number | string>>(new Set())

const toggleLrExpand = (lrId: number | string) => {
  if (expandedLrs.value.has(lrId)) {
    expandedLrs.value.delete(lrId)
  } else {
    expandedLrs.value.add(lrId)
  }
  expandedLrs.value = new Set(expandedLrs.value)
}

const expandAllLrs = () => {
  expandedLrs.value = new Set(groupedByLr.value.map((g: any) => g.lrId))
}

const collapseAllLrs = () => {
  expandedLrs.value = new Set()
}

const loadMoreLrs = () => {
  visibleLrCount.value += 20
}

// --- Destination Collapse ---
const toggleDestinationCollapse = (destination: string) => {
  if (collapsedDestinations.value.has(destination)) {
    collapsedDestinations.value.delete(destination)
  } else {
    collapsedDestinations.value.add(destination)
  }
  collapsedDestinations.value = new Set(collapsedDestinations.value)
}

// --- Bulk Selection ---
const toggleLrSelect = (lrId: number | string) => {
  if (selectedLrIds.value.has(lrId)) {
    selectedLrIds.value.delete(lrId)
  } else {
    selectedLrIds.value.add(lrId)
  }
  selectedLrIds.value = new Set(selectedLrIds.value)
}

const clearSelection = () => {
  selectedLrIds.value = new Set()
}

const bulkCreateConsolidation = () => {
  // Get the first selected LR's destination for consolidation
  const firstLr = groupedByLr.value.find((g: any) => selectedLrIds.value.has(g.lrId))
  if (firstLr) {
    goToConsolidation(firstLr.destination)
  }
  clearSelection()
}

// --- Computed: Consolidation Tab ---
const consolidationStats = computed(() => {
  const s = { open: 0, ready: 0, dispatched: 0, totalWeight: 0 }
  consolidationStore.groups.forEach((g: any) => {
    if (g.status === 'open') {
      s.open++
      s.totalWeight += parseFloat(g.total_weight_kg) || 0
    } else if (g.status === 'ready') {
      s.ready++
    } else if (g.status === 'dispatched') {
      s.dispatched++
    }
  })
  return s
})

// --- Computed: Dispatched Tab ---
const readyGroups = computed(() => {
  return consolidationStore.groups.filter((g: any) => g.status === 'ready')
})

const tripStats = computed(() => {
  const s = { planned: 0, dispatched: 0, delivered: 0, totalItems: 0 }
  loadTripStore.trips.forEach((t: any) => {
    if (t.status === 'planned') s.planned++
    else if (t.status === 'dispatched') s.dispatched++
    else if (t.status === 'delivered') s.delivered++
    s.totalItems += t.warehouse_items?.length || 0
  })
  return s
})

// --- Watchers: Load data when tab changes ---
watch(activeTab, (newTab) => {
  if (newTab === 'consolidation') {
    consolidationStore.fetchGroups()
    consolidationStore.fetchCandidates()
  } else if (newTab === 'dispatched') {
    fetchTripsWithFilters(1)
    consolidationStore.fetchGroups({ status: 'ready' })
  }
})

// Reset visible count when filters change
watch([quickSearch, quickStatusFilter, sortBy, selectedStatus, selectedDestination, selectedLrNumber, selectedLoadType, selectedConsignor, selectedConsignee], () => {
  visibleLrCount.value = 20
})

// --- Methods: Tab Navigation ---
const onTabClick = (key: string) => {
  activeTab.value = key
}

// --- Methods: Warehouse Tab ---
const deleteItem = async (id: number) => {
  const confirmed = await dialogStore.openDialog({
    title: 'Delete Item',
    message: 'Delete this warehouse item?',
    variant: 'danger',
    yesLabel: 'Delete',
  })
  if (confirmed) {
    await store.deleteItem(id)
  }
}

const updateStatus = async (id: number, newStatus: string) => {
  try {
    await store.updateItem(id, { status: newStatus })
  } catch (error) {
    console.error('Error updating status:', error)
    await dialogStore.openDialog({
      title: 'Error',
      message: 'Failed to update status',
      variant: 'danger',
      hideNoButton: true,
    })
    await store.fetchItems()
  }
}

const goToConsolidation = (destination: string) => {
  activeTab.value = 'consolidation'
  consolidationStore.fetchGroups({ destination })
}

// --- Methods: Consolidation Tab ---
const openCreateForDestination = (destination: string) => {
  createForm.value.destination_city = destination
  showCreateModal.value = true
}

const closeCreateModal = () => {
  showCreateModal.value = false
  createForm.value = { destination_city: '', truck_capacity_kg: 9000, notes: '' }
}

const submitCreate = async () => {
  try {
    await consolidationStore.createGroup(createForm.value)
    closeCreateModal()
    await consolidationStore.fetchCandidates()
  } catch (error: any) {
    const message = extractErrorMessage(error, 'Failed to create consolidation group')
    await dialogStore.openDialog({
      title: 'Error',
      message,
      variant: 'danger',
      hideNoButton: true,
    })
  }
}

const deleteConsolidationGroup = async (id: number) => {
  const confirmed = await dialogStore.openDialog({
    title: 'Delete Group',
    message: 'Delete this consolidation group? Items will be returned to the warehouse.',
    variant: 'danger',
    yesLabel: 'Delete',
  })
  if (confirmed) {
    try {
      await consolidationStore.deleteGroup(id)
      await consolidationStore.fetchCandidates()
    } catch (error: any) {
      const message = extractErrorMessage(error, 'Failed to delete consolidation group')
      await dialogStore.openDialog({
        title: 'Error',
        message,
        variant: 'danger',
        hideNoButton: true,
      })
    }
  }
}

// --- Methods: Dispatched Tab ---

const toggleTripFilters = () => {
  showTripFilters.value = !showTripFilters.value
}

const clearTripFilters = () => {
  tripFilters.value = {
    status: '',
    lr: '',
    truck_number: '',
    route: '',
    dispatch_date_from: '',
    dispatch_date_to: '',
    delivered_date_from: '',
    delivered_date_to: '',
  }
  fetchTripsWithFilters(1)
}

const fetchTripsWithFilters = (page = 1) => {
  loadTripStore.fetchTrips({
    page,
    per_page: loadTripStore.pagination.perPage,
    status: tripFilters.value.status || undefined,
    lr: tripFilters.value.lr || undefined,
    truck_number: tripFilters.value.truck_number || undefined,
    route: tripFilters.value.route || undefined,
    dispatch_date_from: tripFilters.value.dispatch_date_from || undefined,
    dispatch_date_to: tripFilters.value.dispatch_date_to || undefined,
    delivered_date_from: tripFilters.value.delivered_date_from || undefined,
    delivered_date_to: tripFilters.value.delivered_date_to || undefined,
  })
}

const onTripsPageChange = (page: number) => {
  fetchTripsWithFilters(page)
}

const openNewTrip = () => {
  editingTrip.value = null
  showTripModal.value = true
}

const openEditTrip = (trip: any) => {
  editingTrip.value = trip
  showTripModal.value = true
}

const closeTripModal = () => {
  showTripModal.value = false
  editingTrip.value = null
}

const submitTrip = async (formData: any) => {
  try {
    if (editingTrip.value) {
      // Edit mode — update existing trip
      await loadTripStore.updateTrip(editingTrip.value.id, formData)
    } else {
      // Create mode
      const group = readyGroups.value.find(
        (g: any) => g.id === Number(formData.consolidation_group_id)
      )
      if (group) {
        formData.destination_city = group.destination_city
      }
      await loadTripStore.createTrip(formData)
    }
    showTripModal.value = false
    editingTrip.value = null
  } catch (error: any) {
    const message = extractErrorMessage(error, 'Failed to save load trip')
    await dialogStore.openDialog({
      title: 'Error',
      message,
      variant: 'danger',
      hideNoButton: true,
    })
  }
}

const deleteLoadTrip = async (id: number) => {
  const confirmed = await dialogStore.openDialog({
    title: 'Delete Trip',
    message: 'Delete this load trip? Warehouse items will be unlinked and the truck released.',
    variant: 'danger',
    yesLabel: 'Delete',
  })
  if (confirmed) {
    try {
      await loadTripStore.deleteTrip(id)
    } catch (error: any) {
      const message = extractErrorMessage(error, 'Failed to delete load trip')
      await dialogStore.openDialog({
        title: 'Error',
        message,
        variant: 'danger',
        hideNoButton: true,
      })
    }
  }
}

const dispatchTrip = async (id: number) => {
  const confirmed = await dialogStore.openDialog({
    title: 'Dispatch Trip',
    message: 'Dispatch this trip? Items will be marked as in transit.',
    variant: 'primary',
    yesLabel: 'Dispatch',
  })
  if (confirmed) {
    try {
      await loadTripStore.dispatchTrip(id)
    } catch {
      await dialogStore.openDialog({
        title: 'Error',
        message: 'Failed to dispatch trip',
        variant: 'danger',
        hideNoButton: true,
      })
    }
  }
}

const markDelivered = async (id: number) => {
  const confirmed = await dialogStore.openDialog({
    title: 'Mark Delivered',
    message: 'Mark this trip as delivered?',
    variant: 'primary',
    yesLabel: 'Mark Delivered',
  })
  if (confirmed) {
    try {
      await loadTripStore.markDelivered(id)
    } catch {
      await dialogStore.openDialog({
        title: 'Error',
        message: 'Failed to mark trip as delivered',
        variant: 'danger',
        hideNoButton: true,
      })
    }
  }
}

onMounted(() => {
  store.fetchItems()
  store.fetchDashboard()
  // Pre-fetch consolidation groups and load trips so all tab counts
  // are populated immediately on page load, not just when the tab is clicked.
  consolidationStore.fetchGroups()
  fetchTripsWithFilters(1)
})

</script>

<style scoped>
/* Slide-up transition for bulk action bar */
.slide-up-enter-active,
.slide-up-leave-active {
  transition: transform 0.25s ease, opacity 0.25s ease;
}
.slide-up-enter-from,
.slide-up-leave-to {
  transform: translateY(100%);
  opacity: 0;
}
</style>
