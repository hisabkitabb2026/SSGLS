<template>
  <BasePage>
    <BasePageHeader title="Maintenance Planner">
      <p class="mt-1 text-sm text-muted">
        Schedule and complete maintenance for this truck.
      </p>
      <template #actions>
        <BaseButton
          variant="primary-outline"
          @click="router.push({ name: 'trucks.show', params: { id: truckId } })"
        >
          Back to truck
        </BaseButton>
      </template>
    </BasePageHeader>

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
      <!-- Schedule Form -->
      <BaseCard class="lg:col-span-1">
        <template #header>
          <h2 class="font-semibold text-heading">Schedule maintenance</h2>
        </template>
        <form @submit.prevent="save">
          <BaseInputGrid layout="one-column">
            <BaseInputGroup label="Maintenance type" required>
              <BaseInput
                v-model="form.type"
                required
                placeholder="Service, repair, tyre change…"
              />
            </BaseInputGroup>
            <BaseInputGroup label="Scheduled date">
              <BaseDatePicker v-model="form.scheduled_date" :calendar-button="true" />
            </BaseInputGroup>
            <BaseInputGroup label="Estimated cost">
              <BaseInput
                v-model.number="form.cost"
                min="0"
                type="number"
                step="0.01"
              />
            </BaseInputGroup>
          </BaseInputGrid>
          <div class="mt-6">
            <BaseButton class="w-full" type="submit" :loading="saving">
              Save schedule
            </BaseButton>
          </div>
        </form>
      </BaseCard>

      <!-- Scheduled Work List -->
      <BaseCard class="lg:col-span-2">
        <template #header>
          <h2 class="font-semibold text-heading">Scheduled work</h2>
        </template>
        <div
          v-if="records.length === 0"
          class="py-8 text-center text-muted"
        >
          No maintenance records for this truck.
        </div>
        <div v-else class="space-y-3">
          <div
            v-for="record in records"
            :key="record.id"
            class="rounded-lg border border-line-light bg-surface-secondary p-4"
          >
            <div class="flex flex-col justify-between gap-3 sm:flex-row">
              <div>
                <p class="font-medium text-heading">{{ record.type }}</p>
                <p class="mt-1 text-sm text-muted">
                  {{ record.scheduled_date || 'No date set' }} ·
                  <BaseFormatMoney :amount="record.cost || 0" />
                </p>
              </div>
              <span class="capitalize text-body">
                {{ record.status.replace('_', ' ') }}
              </span>
            </div>
            <div
              v-if="record.status === 'scheduled' || record.status === 'in_progress'"
              class="mt-4 flex flex-wrap gap-2"
            >
              <BaseButton
                v-if="record.status === 'scheduled'"
                size="sm"
                variant="primary-outline"
                @click="update(record, 'in_progress')"
              >
                Start
              </BaseButton>
              <BaseButton
                size="sm"
                variant="secondary"
                @click="update(record, 'completed')"
              >
                Complete
              </BaseButton>
              <BaseButton
                size="sm"
                variant="danger"
                @click="update(record, 'cancelled')"
              >
                Cancel
              </BaseButton>
            </div>
          </div>
        </div>
      </BaseCard>
    </div>
  </BasePage>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { client } from '@/scripts/api/client'
import { useDialogStore } from '@/scripts/stores/dialog.store'
import BaseFormatMoney from '@/scripts/components/base/BaseFormatMoney.vue'

const route = useRoute()
const router = useRouter()
const dialogStore = useDialogStore()
const truckId = Number(route.params.id)
const records = ref<any[]>([])
const saving = ref(false)
const form = ref({
  type: '',
  scheduled_date: '',
  cost: 0,
  status: 'scheduled',
})

/**
 * Fetches maintenance records filtered server-side by truck_id.
 * Previously fetched ALL records and filtered client-side, which
 * is inefficient and does not scale.
 */
const load = async () => {
  const response = await client.get('/api/v1/truck-maintenances', {
    params: { truck_id: truckId, limit: 'all' },
  })
  records.value = response.data?.data || response.data || []
}

const save = async () => {
  saving.value = true
  try {
    await client.post('/api/v1/truck-maintenances', {
      ...form.value,
      truck_id: truckId,
    })
    form.value = { type: '', scheduled_date: '', cost: 0, status: 'scheduled' }
    await load()
  } catch (error: any) {
    const serverMessage = error?.response?.data?.message
    await dialogStore.openDialog({
      title: 'Error',
      message: serverMessage || 'Failed to save maintenance record',
      variant: 'danger',
      hideNoButton: true,
    })
  } finally {
    saving.value = false
  }
}

const update = async (record: any, status: string) => {
  try {
    await client.put(`/api/v1/truck-maintenances/${record.id}`, {
      status,
      completed_date:
        status === 'completed' ? new Date().toISOString().slice(0, 10) : null,
    })
    await load()
  } catch (error: any) {
    const serverMessage = error?.response?.data?.message
    await dialogStore.openDialog({
      title: 'Error',
      message: serverMessage || 'Failed to update maintenance record',
      variant: 'danger',
      hideNoButton: true,
    })
  }
}

onMounted(load)
</script>
