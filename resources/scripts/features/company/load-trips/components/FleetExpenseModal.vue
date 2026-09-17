<template>
  <BaseModal :show="show" @close="emit('close')">
    <template #header>
      <div class="flex w-full items-center justify-between">
        <span>Add truck expense</span>
        <BaseIcon
          name="XMarkIcon"
          class="h-6 w-6 cursor-pointer text-muted"
          @click="emit('close')"
        />
      </div>
    </template>
    <form
      class="max-h-[70vh] space-y-6 overflow-y-auto px-6 py-5"
      @submit.prevent="save"
    >
      <BaseInputGrid>
        <BaseInputGroup label="Expense category" required>
          <BaseMultiselect
            v-model="form.expense_category_id"
            :options="categories"
            value-prop="id"
            label="name"
            track-by="id"
            searchable
            placeholder="Select category"
          >
            <template #action>
              <BaseSelectAction @click="openCategory">
                <BaseIcon
                  name="PlusIcon"
                  class="mr-2 h-4 w-4 text-primary-400"
                />
                Add new category
              </BaseSelectAction>
            </template>
          </BaseMultiselect>
        </BaseInputGroup>

        <BaseInputGroup label="Expense date" required>
          <BaseDatePicker v-model="form.expense_date" :calendar-button="true" />
        </BaseInputGroup>

        <BaseInputGroup label="Amount" required>
          <BaseInput
            v-model.number="form.amount"
            min="0.01"
            step="0.01"
            type="number"
          />
        </BaseInputGroup>

        <BaseInputGroup label="Load trip">
          <BaseMultiselect
            v-model="form.load_trip_id"
            :options="trips"
            value-prop="id"
            label="trip_number"
            track-by="id"
            searchable
            placeholder="Select a warehouse load trip"
          />
        </BaseInputGroup>

        <BaseInputGroup label="Driver">
          <BaseMultiselect
            v-model="form.driver_profile_id"
            :options="drivers"
            value-prop="id"
            label="name"
            track-by="id"
            searchable
            placeholder="Select driver (optional)"
          />
        </BaseInputGroup>

        <BaseInputGroup label="Odometer reading (km)">
          <BaseInput
            v-model.number="form.odometer_reading_km"
            min="0"
            type="number"
            placeholder="Optional"
          />
        </BaseInputGroup>
      </BaseInputGrid>

      <BaseInputGroup label="Notes">
        <BaseTextarea v-model="form.notes" rows="3" />
      </BaseInputGroup>

      <div
        class="flex flex-col-reverse gap-3 border-t border-line-light pt-5 sm:flex-row sm:justify-end"
      >
        <BaseButton variant="white" type="button" @click="emit('close')">
          Cancel
        </BaseButton>
        <BaseButton :loading="saving" :disabled="saving" type="submit">
          Save expense
        </BaseButton>
      </div>
    </form>
  </BaseModal>
  <CategoryModal />
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { expenseService } from '@/scripts/api/services/expense.service'
import { client } from '@/scripts/api/client'
import { useCompanyStore } from '@/scripts/stores/company.store'
import { useGlobalStore } from '@/scripts/stores/global.store'
import { useModalStore } from '@/scripts/stores/modal.store'
import { useDialogStore } from '@/scripts/stores/dialog.store'
import CategoryModal from '@/scripts/features/company/settings/components/CategoryModal.vue'

const props = defineProps<{ show: boolean; truckId: number }>()
const emit = defineEmits<{ close: []; saved: [] }>()

const companyStore = useCompanyStore()
const globalStore = useGlobalStore()
const modalStore = useModalStore()
const dialogStore = useDialogStore()
const saving = ref(false)
const categories = ref<{ id: number; name: string }[]>([])
const trips = ref<{ id: number; trip_number: string; status: string }[]>([])
const drivers = ref<{ id: number; name: string }[]>([])

const form = ref({
  expense_category_id: null as number | null,
  expense_date: '',
  amount: 0,
  load_trip_id: null as number | null,
  driver_profile_id: null as number | null,
  odometer_reading_km: null as number | null,
  notes: '',
})

const load = async () => {
  await globalStore.fetchCurrencies()
  const [categoryResponse, tripResponse, driverResponse] = await Promise.all([
    expenseService.listCategories({ limit: 'all' }),
    client.get('/api/v1/load-trips', { params: { truck_id: props.truckId } }),
    client.get('/api/v1/lorry-party-profiles?type=DRIVER&limit=all'),
  ])
  categories.value = categoryResponse.data || []
  trips.value = tripResponse.data?.data || tripResponse.data || []
  drivers.value = driverResponse.data?.data || driverResponse.data || []
  form.value = {
    expense_category_id: null,
    expense_date: new Date().toISOString().slice(0, 10),
    amount: 0,
    load_trip_id: null,
    driver_profile_id: null,
    odometer_reading_km: null,
    notes: '',
  }
}

const openCategory = () =>
  modalStore.openModal({
    title: 'Add new category',
    componentName: 'CategoryModal',
    refreshData: load,
    size: 'sm',
  })

const save = async () => {
  const currencyId = companyStore.selectedCompanyCurrency?.id
  if (!currencyId) {
    await dialogStore.openDialog({
      title: 'Error',
      message: 'No company currency configured. Please set a currency in company settings.',
      variant: 'danger',
      hideNoButton: true,
    })
    return
  }
  saving.value = true
  try {
    const data = new FormData()
    data.append('truck_id', String(props.truckId))
    data.append('expense_category_id', String(form.value.expense_category_id))
    data.append('expense_date', form.value.expense_date)
    data.append('amount', String(Math.round(form.value.amount * 100)))
    data.append('currency_id', String(currencyId))
    if (form.value.load_trip_id) {
      data.append('load_trip_id', String(form.value.load_trip_id))
    }
    if (form.value.driver_profile_id) {
      data.append('driver_profile_id', String(form.value.driver_profile_id))
    }
    if (form.value.odometer_reading_km !== null) {
      data.append('odometer_reading_km', String(form.value.odometer_reading_km))
    }
    if (form.value.notes) {
      data.append('notes', form.value.notes)
    }
    await expenseService.create(data)
    emit('saved')
    emit('close')
  } catch (error: any) {
    const serverErrors = error?.response?.data?.errors
    const serverMessage = error?.response?.data?.message
    let message = 'Failed to save expense'
    if (serverErrors) {
      const fieldMessages = Object.values(serverErrors).flat() as string[]
      message = fieldMessages.join('\n') || serverMessage || message
    } else if (serverMessage) {
      message = serverMessage
    }
    await dialogStore.openDialog({
      title: 'Error',
      message,
      variant: 'danger',
      hideNoButton: true,
    })
  } finally {
    saving.value = false
  }
}

watch(
  () => props.show,
  (show) => {
    if (show) load()
  }
)
</script>
