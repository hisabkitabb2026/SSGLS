<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { required, minLength, helpers } from '@vuelidate/validators'
import useVuelidate from '@vuelidate/core'
import { useModalStore } from '@/scripts/stores/modal.store'
import { useNotificationStore } from '@/scripts/stores/notification.store'
import { useLorryPartyProfileStore } from '@/scripts/features/company/lorry-party-profiles/store'
import type { LorryPartyProfile, LorryPartyProfileType } from '@/scripts/types/domain/lorry-party-profile'

const emit = defineEmits<{
  (e: 'saved', payload: { type: LorryPartyProfileType; profile: LorryPartyProfile }): void
}>()

const { t } = useI18n()
const modalStore = useModalStore()
const profileStore = useLorryPartyProfileStore()
const notificationStore = useNotificationStore()

const isSaving = ref<boolean>(false)

const emptyForm: LorryPartyProfile = {
  id: undefined,
  customer_id: null,
  type: 'OWNER',
  code: '',
  name: '',
  address: '',
  phone: '',
  alternate_phone: '',
  pan_number: '',
  gstin: '',
  bank_name: '',
  bank_account_holder_name: '',
  ifsc_code: '',
  upi_id: '',
  status: 'active',
  notes: '',
  bank_account_no: '',
  licence_no: '',
  licence_date: '',
  rto_address: '',
  valid_up_to: '',
  advice_no: '',
  advice_date: '',
  rc_front_path: '',
  rc_back_path: '',
  pan_front_path: '',
  insurance_path: '',
  license_front_path: '',
  license_back_path: '',
  pan_front_path_broker: '',
}

const form = reactive<LorryPartyProfile>({ ...emptyForm })

const modalActive = computed<boolean>(
  () => modalStore.active && modalStore.componentName === 'LorryPartyProfileModal',
)

const singularTitle = computed<string>(() => {
  if (form.type === 'OWNER') return 'Owner'
  if (form.type === 'DRIVER') return 'Driver'
  if (form.type === 'BROKER') return 'Broker'
  return 'Party'
})

const detailsSectionTitle = computed<string>(() => {
  if (form.type === 'OWNER') return 'Owner Details'
  if (form.type === 'DRIVER') return 'Driver Licence Details'
  return 'Broker Details'
})

const rules = computed(() => ({
  name: {
    required: helpers.withMessage(t('validation.required'), required),
    minLength: helpers.withMessage(
      t('validation.name_min_length', { count: 3 }),
      minLength(3),
    ),
  },
}))

const v$ = useVuelidate(rules, form)

watch(modalActive, (active) => {
  if (active) {
    Object.assign(form, emptyForm, profileStore.currentProfile)
  }
})

function handleFileChange(field: keyof LorryPartyProfile, event: Event): void {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]
  if (!file) return

  const reader = new FileReader()
  reader.onload = () => {
    ;(form[field] as string) = reader.result as string
  }
  reader.readAsDataURL(file)
}

function removeFile(field: keyof LorryPartyProfile): void {
  ;(form[field] as string) = ''
}

function getFileName(path: string | null | undefined): string {
  if (!path) return ''
  if (path.startsWith('data:')) return 'New File Selected'
  return path.substring(path.lastIndexOf('/') + 1)
}

/**
 * Prevent Enter key from auto-submitting the form on text inputs.
 * Textareas are excluded so Enter still creates newlines.
 */
function handleEnterKey(event: KeyboardEvent): void {
  if (!(event.target instanceof HTMLTextAreaElement)) {
    event.preventDefault()
  }
}

function closeModal(): void {
  modalStore.closeModal()
  setTimeout(() => {
    Object.assign(form, emptyForm)
    profileStore.resetCurrentProfile()
    v$.value.$reset()
  }, 300)
}

async function submit(): Promise<void> {
  v$.value.$touch()
  if (v$.value.$invalid) {
    return
  }

  isSaving.value = true
  try {
    const response = await profileStore.saveProfile({ ...form }, form.id)
    notificationStore.showNotification({
      type: 'success',
      message: 'general.party_profile_saved',
    })
    // Notify via store so that any sibling component (LorryPartySelectPopup,
    // LorryReceiptPartyFields) can react — even if they don't listen to the
    // @saved emit (e.g. when the modal is rendered at the page level).
    profileStore.notifyProfileSaved(response.data)
    emit('saved', { type: form.type, profile: response.data })
    closeModal()

  } catch {
    // Error is handled by the API interceptor (toast notification)
  } finally {
    isSaving.value = false
  }
}
</script>

<template>
  <BaseModal :show="modalActive" @close="closeModal">
    <template #header>
      <div class="flex justify-between w-full">
        {{ modalStore.title }}
        <BaseIcon
          name="XMarkIcon"
          class="h-6 w-6 text-muted cursor-pointer"
          @click="closeModal"
        />
      </div>
    </template>

    <form @submit.prevent="submit" @keydown.enter="handleEnterKey">
      <div class="px-6 pb-6 max-h-[70vh] overflow-y-auto space-y-6">
        <!-- Owner / basic details -->
        <div class="mt-4 mb-2">
          <h6 class="block text-base font-semibold text-left text-heading mb-4">
            {{ form.type === 'OWNER' ? 'Owner Details' : 'Basic Info' }}
          </h6>

          <BaseInputGrid>
            <BaseInputGroup
              :label="`${singularTitle} Name`"
              required
              :error="v$.name.$error && v$.name.$errors[0].$message"
            >
              <BaseInput
                v-model.trim="form.name"
                type="text"
                :invalid="v$.name.$error"
                @input="v$.name.$touch()"
              />
            </BaseInputGroup>

            <BaseInputGroup label="Phone No.">
              <BaseInput v-model.trim="form.phone" type="text" />
            </BaseInputGroup>

            <BaseInputGroup v-if="form.type === 'OWNER'" label="Alternate Phone No.">
              <BaseInput v-model.trim="form.alternate_phone" type="text" />
            </BaseInputGroup>

            <BaseInputGroup label="Full Address">
              <BaseTextarea v-model.trim="form.address" rows="3" />
            </BaseInputGroup>

            <BaseInputGroup v-if="form.type === 'OWNER'" label="PAN Number">
              <BaseInput v-model.trim="form.pan_number" type="text" />
            </BaseInputGroup>

            <BaseInputGroup v-if="form.type === 'OWNER'" label="GSTIN">
              <BaseInput v-model.trim="form.gstin" type="text" />
            </BaseInputGroup>
          </BaseInputGrid>
        </div>

        <BaseDivider />

        <!-- Details Section -->
        <div class="mb-2">
          <h6 class="block text-base font-semibold text-left text-heading mb-4">
            {{ form.type === 'OWNER' ? 'Owner Account Details' : detailsSectionTitle }}
          </h6>

          <!-- OWNER fields (separate layout to avoid overlap on mobile) -->
          <div
            v-if="form.type === 'OWNER'"
            class="space-y-6"
          >
            <BaseInputGrid>
              <BaseInputGroup label="Bank Name">
                <BaseInput v-model.trim="form.bank_name" type="text" />
              </BaseInputGroup>

              <BaseInputGroup label="Account Holder Name">
                <BaseInput v-model.trim="form.bank_account_holder_name" type="text" />
              </BaseInputGroup>

              <BaseInputGroup label="Owner Bank Account No.">
                <BaseInput v-model.trim="form.bank_account_no" type="text" />
              </BaseInputGroup>

              <BaseInputGroup label="IFSC Code">
                <BaseInput v-model.trim="form.ifsc_code" type="text" />
              </BaseInputGroup>

              <BaseInputGroup label="UPI ID">
                <BaseInput v-model.trim="form.upi_id" type="text" />
              </BaseInputGroup>
            </BaseInputGrid>

            <BaseInputGroup label="Notes">
              <BaseTextarea v-model.trim="form.notes" rows="3" />
            </BaseInputGroup>

            <div>
              <h6 class="text-base font-semibold text-muted text-left mb-2">
                Mandatory Required Documents
              </h6>
            </div>

            <BaseInputGrid>
              <BaseInputGroup label="Attach RC Front">
                <div v-if="form.rc_front_path" class="flex flex-col p-3 border border-line-default rounded-md bg-surface-secondary">
                  <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-heading truncate max-w-[200px]" :title="getFileName(form.rc_front_path)">
                      {{ getFileName(form.rc_front_path) }}
                    </span>
                    <div class="flex items-center space-x-2">
                      <a
                        v-if="!form.rc_front_path.startsWith('data:')"
                        :href="form.rc_front_path"
                        target="_blank"
                        class="text-xs text-primary-500 hover:text-primary-600 hover:underline"
                      >
                        View
                      </a>
                      <button
                        type="button"
                        class="text-xs text-status-red hover:underline"
                        @click="removeFile('rc_front_path')"
                      >
                        Remove
                      </button>
                    </div>
                  </div>
                </div>
                <div
                  v-else
                  class="relative border-2 border-dashed border-line-default rounded-md bg-surface-secondary hover:bg-hover transition p-4 text-center cursor-pointer"
                >
                  <input
                    type="file"
                    accept="image/*,application/pdf"
                    class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10"
                    @change="handleFileChange('rc_front_path', $event)"
                  />
                  <div class="flex flex-col items-center justify-center space-y-1">
                    <BaseIcon name="CloudArrowUpIcon" class="h-6 w-6 text-muted" />
                    <span class="text-xs text-muted">Click to upload document</span>
                  </div>
                </div>
              </BaseInputGroup>

              <BaseInputGroup label="Attach RC Back">
                <div v-if="form.rc_back_path" class="flex flex-col p-3 border border-line-default rounded-md bg-surface-secondary">
                  <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-heading truncate max-w-[200px]" :title="getFileName(form.rc_back_path)">
                      {{ getFileName(form.rc_back_path) }}
                    </span>
                    <div class="flex items-center space-x-2">
                      <a
                        v-if="!form.rc_back_path.startsWith('data:')"
                        :href="form.rc_back_path"
                        target="_blank"
                        class="text-xs text-primary-500 hover:text-primary-600 hover:underline"
                      >
                        View
                      </a>
                      <button
                        type="button"
                        class="text-xs text-status-red hover:underline"
                        @click="removeFile('rc_back_path')"
                      >
                        Remove
                      </button>
                    </div>
                  </div>
                </div>
                <div
                  v-else
                  class="relative border-2 border-dashed border-line-default rounded-md bg-surface-secondary hover:bg-hover transition p-4 text-center cursor-pointer"
                >
                  <input
                    type="file"
                    accept="image/*,application/pdf"
                    class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10"
                    @change="handleFileChange('rc_back_path', $event)"
                  />
                  <div class="flex flex-col items-center justify-center space-y-1">
                    <BaseIcon name="CloudArrowUpIcon" class="h-6 w-6 text-muted" />
                    <span class="text-xs text-muted">Click to upload document</span>
                  </div>
                </div>
              </BaseInputGroup>

              <BaseInputGroup label="Attach Insurance Copy">
                <div v-if="form.insurance_path" class="flex flex-col p-3 border border-line-default rounded-md bg-surface-secondary">
                  <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-heading truncate max-w-[200px]" :title="getFileName(form.insurance_path)">
                      {{ getFileName(form.insurance_path) }}
                    </span>
                    <div class="flex items-center space-x-2">
                      <a
                        v-if="!form.insurance_path.startsWith('data:')"
                        :href="form.insurance_path"
                        target="_blank"
                        class="text-xs text-primary-500 hover:text-primary-600 hover:underline"
                      >
                        View
                      </a>
                      <button
                        type="button"
                        class="text-xs text-status-red hover:underline"
                        @click="removeFile('insurance_path')"
                      >
                        Remove
                      </button>
                    </div>
                  </div>
                </div>
                <div
                  v-else
                  class="relative border-2 border-dashed border-line-default rounded-md bg-surface-secondary hover:bg-hover transition p-4 text-center cursor-pointer"
                >
                  <input
                    type="file"
                    accept="image/*,application/pdf"
                    class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10"
                    @change="handleFileChange('insurance_path', $event)"
                  />
                  <div class="flex flex-col items-center justify-center space-y-1">
                    <BaseIcon name="CloudArrowUpIcon" class="h-6 w-6 text-muted" />
                    <span class="text-xs text-muted">Click to upload document</span>
                  </div>
                </div>
              </BaseInputGroup>
            </BaseInputGrid>
          </div>

          <!-- BROKER fields (separate layout to avoid overlap on mobile) -->
          <div
            v-else-if="form.type === 'BROKER'"
            class="space-y-6"
          >
            <BaseInputGrid>
              <BaseInputGroup label="Broker Bank Account No.">
                <BaseInput v-model.trim="form.bank_account_no" type="text" />
              </BaseInputGroup>

              <BaseInputGroup label="Broker Pan No.">
                <BaseInput v-model.trim="form.advice_no" type="text" />
              </BaseInputGroup>
            </BaseInputGrid>

            <div class="mt-2 mb-2">
              <h6 class="text-base font-semibold text-muted text-left">
                Mandatory Required Documents
              </h6>
            </div>

            <BaseInputGrid>
              <BaseInputGroup label="Attach PAN Front">
                <div v-if="form.pan_front_path_broker" class="flex flex-col p-3 border border-line-default rounded-md bg-surface-secondary">
                  <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-heading truncate max-w-[200px]" :title="getFileName(form.pan_front_path_broker)">
                      {{ getFileName(form.pan_front_path_broker) }}
                    </span>
                    <div class="flex items-center space-x-2">
                      <a
                        v-if="!form.pan_front_path_broker.startsWith('data:')"
                        :href="form.pan_front_path_broker"
                        target="_blank"
                        class="text-xs text-primary-500 hover:text-primary-600 hover:underline"
                      >
                        View
                      </a>
                      <button
                        type="button"
                        class="text-xs text-status-red hover:underline"
                        @click="removeFile('pan_front_path_broker')"
                      >
                        Remove
                      </button>
                    </div>
                  </div>
                </div>
                <div
                  v-else
                  class="relative border-2 border-dashed border-line-default rounded-md bg-surface-secondary hover:bg-hover transition p-4 text-center cursor-pointer"
                >
                  <input
                    type="file"
                    accept="image/*,application/pdf"
                    class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10"
                    @change="handleFileChange('pan_front_path_broker', $event)"
                  />
                  <div class="flex flex-col items-center justify-center space-y-1">
                    <BaseIcon name="CloudArrowUpIcon" class="h-6 w-6 text-muted" />
                    <span class="text-xs text-muted">Click to upload document</span>
                  </div>
                </div>
              </BaseInputGroup>
            </BaseInputGrid>
          </div>

          <!-- DRIVER fields (separate layout to avoid overlap) -->
          <div
            v-else-if="form.type === 'DRIVER'"
            class="space-y-6"
          >
            <BaseInputGrid>
              <BaseInputGroup label="Driver Bank Account No.">
                <BaseInput v-model.trim="form.bank_account_no" type="text" />
              </BaseInputGroup>

              <BaseInputGroup label="Licence No.">
                <BaseInput v-model.trim="form.licence_no" type="text" />
              </BaseInputGroup>

              <BaseInputGroup label="RTO">
                <BaseInput v-model.trim="form.rto_address" type="text" />
              </BaseInputGroup>
            </BaseInputGrid>

            <BaseInputGrid>
              <BaseInputGroup label="Issued Date">
                <BaseInput v-model.trim="form.licence_date" type="date" />
              </BaseInputGroup>

              <BaseInputGroup label="Valid up Dt.">
                <BaseInput v-model.trim="form.valid_up_to" type="date" />
              </BaseInputGroup>
            </BaseInputGrid>

            <div class="mt-2 mb-2">
              <h6 class="text-base font-semibold text-muted text-left">
                Mandatory Required Documents
              </h6>
            </div>

            <BaseInputGrid>
              <BaseInputGroup label="Attach Driving License Front">
                <div v-if="form.license_front_path" class="flex flex-col p-3 border border-line-default rounded-md bg-surface-secondary">
                  <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-heading truncate max-w-[200px]" :title="getFileName(form.license_front_path)">
                      {{ getFileName(form.license_front_path) }}
                    </span>
                    <div class="flex items-center space-x-2">
                      <a
                        v-if="!form.license_front_path.startsWith('data:')"
                        :href="form.license_front_path"
                        target="_blank"
                        class="text-xs text-primary-500 hover:text-primary-600 hover:underline"
                      >
                        View
                      </a>
                      <button
                        type="button"
                        class="text-xs text-status-red hover:underline"
                        @click="removeFile('license_front_path')"
                      >
                        Remove
                      </button>
                    </div>
                  </div>
                </div>
                <div
                  v-else
                  class="relative border-2 border-dashed border-line-default rounded-md bg-surface-secondary hover:bg-hover transition p-4 text-center cursor-pointer"
                >
                  <input
                    type="file"
                    accept="image/*,application/pdf"
                    class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10"
                    @change="handleFileChange('license_front_path', $event)"
                  />
                  <div class="flex flex-col items-center justify-center space-y-1">
                    <BaseIcon name="CloudArrowUpIcon" class="h-6 w-6 text-muted" />
                    <span class="text-xs text-muted">Click to upload document</span>
                  </div>
                </div>
              </BaseInputGroup>

              <BaseInputGroup label="Attach Driving License Back">
                <div v-if="form.license_back_path" class="flex flex-col p-3 border border-line-default rounded-md bg-surface-secondary">
                  <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-heading truncate max-w-[200px]" :title="getFileName(form.license_back_path)">
                      {{ getFileName(form.license_back_path) }}
                    </span>
                    <div class="flex items-center space-x-2">
                      <a
                        v-if="!form.license_back_path.startsWith('data:')"
                        :href="form.license_back_path"
                        target="_blank"
                        class="text-xs text-primary-500 hover:text-primary-600 hover:underline"
                      >
                        View
                      </a>
                      <button
                        type="button"
                        class="text-xs text-status-red hover:underline"
                        @click="removeFile('license_back_path')"
                      >
                        Remove
                      </button>
                    </div>
                  </div>
                </div>
                <div
                  v-else
                  class="relative border-2 border-dashed border-line-default rounded-md bg-surface-secondary hover:bg-hover transition p-4 text-center cursor-pointer"
                >
                  <input
                    type="file"
                    accept="image/*,application/pdf"
                    class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10"
                    @change="handleFileChange('license_back_path', $event)"
                  />
                  <div class="flex flex-col items-center justify-center space-y-1">
                    <BaseIcon name="CloudArrowUpIcon" class="h-6 w-6 text-muted" />
                    <span class="text-xs text-muted">Click to upload document</span>
                  </div>
                </div>
              </BaseInputGroup>
            </BaseInputGrid>
          </div>
        </div>
      </div>

      <div class="z-0 flex justify-end p-4 border-t border-line-default border-solid">
        <BaseButton
          class="mr-3 text-sm"
          type="button"
          variant="primary-outline"
          @click="closeModal"
        >
          {{ $t('general.cancel') }}
        </BaseButton>

        <BaseButton :loading="isSaving" variant="primary" type="button" @click="submit">
          <template #left="slotProps">
            <BaseIcon
              v-if="!isSaving"
              name="ArrowDownOnSquareIcon"
              :class="slotProps.class"
            />
          </template>
          {{ $t('general.save') }}
        </BaseButton>
      </div>
    </form>
  </BaseModal>
</template>
