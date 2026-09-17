<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  required,
  minLength,
  maxLength,
  email,
  helpers,
  requiredIf,
  sameAs,
} from '@vuelidate/validators'
import useVuelidate from '@vuelidate/core'
import { useModalStore } from '../../../../stores/modal.store'
import { useCustomerStore } from '../store'
import { useCompanyStore } from '../../../../stores/company.store'
import { useNotificationStore } from '../../../../stores/notification.store'
import CopyInputField from '@/scripts/features/company/customers/components/CopyInputField.vue'
import { useEstimateStore } from '@/scripts/features/company/estimates/store'
import { useInvoiceStore } from '@/scripts/features/company/invoices/store'

const modalStore = useModalStore()
const customerStore = useCustomerStore()
const companyStore = useCompanyStore()
const invoiceStore = useInvoiceStore()
const estimateStore = useEstimateStore()
const notificationStore = useNotificationStore()

const { t } = useI18n()
const isLoading = ref<boolean>(false)
const isShowPassword = ref<boolean>(false)
const isShowConfirmPassword = ref<boolean>(false)

const modalActive = computed<boolean>(
  () => modalStore.active && modalStore.componentName === 'PartyInformationModal'
)

const rules = computed(() => ({
  name: {
    required: helpers.withMessage(t('validation.required'), required),
    minLength: helpers.withMessage(
      t('validation.name_min_length', { count: 3 }),
      minLength(3)
    ),
  },
  email: {
    required: helpers.withMessage(
      t('validation.required'),
      requiredIf(customerStore.currentCustomer.enable_portal === true)
    ),
    email: helpers.withMessage(t('validation.email_incorrect'), email),
  },
  password: {
    required: helpers.withMessage(
      t('validation.required'),
      requiredIf(
        customerStore.currentCustomer.enable_portal === true &&
          !customerStore.currentCustomer.password_added
      )
    ),
    minLength: helpers.withMessage(
      t('validation.password_min_length', { count: 8 }),
      minLength(8)
    ),
  },
  confirm_password: {
    sameAsPassword: helpers.withMessage(
      t('validation.password_incorrect'),
      sameAs(customerStore.currentCustomer.password)
    ),
  },
  billing: {
    address_street_1: {
      maxLength: helpers.withMessage(
        t('validation.address_maxlength', { count: 255 }),
        maxLength(255)
      ),
    },
  },
}))

const v$ = useVuelidate(
  rules,
  computed(() => customerStore.currentCustomer)
)

const getCustomerPortalUrl = computed<string>(() => {
  return `${window.location.origin}/${companyStore.selectedCompany?.slug}/customer/login`
})

async function submitPartyData(): Promise<void> {
  v$.value.$touch()

  if (v$.value.$invalid) {
    return
  }

  isLoading.value = true

  const data = {
    ...customerStore.currentCustomer,
  }

  try {
    const isEdit = customerStore.isEdit
    const response = isEdit
      ? await customerStore.updateCustomer(data)
      : await customerStore.addCustomer(data)

    notificationStore.showNotification({
      type: 'success',
      message: isEdit ? 'customers.updated_message' : 'customers.created_message',
    })

    // For create mode: auto-select the newly created party if needed
    if (!isEdit && customerStore.openContext === 'consignee') {
      window.dispatchEvent(
        new CustomEvent('customer-created-for-consignee', {
          detail: { customer: response.data },
        }),
      )
    } else if (!isEdit && invoiceStore.newInvoice.customer === null) {
      invoiceStore.selectCustomer(response.data.id)
    } else if (!isEdit && estimateStore.newEstimate.customer === null) {
      estimateStore.selectCustomer(response.data.id)
    }

    // Always dispatch a general event so BaseCustomerSelectInput (and any
    // other listener) can auto-select the newly created party. This runs
    // independently of the specific-store auto-select logic above.
    if (!isEdit) {
      window.dispatchEvent(
        new CustomEvent('customer-created-for-select-input', {
          detail: { customer: response.data },
        }),
      )
    }

    // Reset the context after handling
    customerStore.openContext = null

    modalStore.closeModal()
    isLoading.value = false
  } catch {
    isLoading.value = false
  }

}

function closePartyModal(): void {
  // Reset the open context so a cancelled modal doesn't leave stale state
  customerStore.openContext = null
  modalStore.closeModal()
}
</script>

<template>
  <BaseModal
    :show="modalActive"
    @close="closePartyModal"
  >
    <template #header>
      <div class="flex justify-between w-full">
        {{ $t('customers.new_party') }}
        <BaseIcon
          name="XMarkIcon"
          class="h-6 w-6 text-muted cursor-pointer"
          @click="closePartyModal"
        />
      </div>
    </template>

    <form @submit.prevent="submitPartyData">
      <div class="px-6 pb-3 max-h-[calc(80vh-8rem)] overflow-y-auto">
        <!-- Party Information Section -->
        <div class="mb-8">
          <h6 class="text-lg font-semibold mb-6 text-heading">
            {{ $t('customers.party_information') }}
          </h6>

          <BaseInputGrid layout="one-column">
            <!-- Party Name -->
            <BaseInputGroup
              :label="$t('customers.party_name')"
              required
              :error="
                v$.name.$error && v$.name.$errors[0]?.$message
              "
            >
              <BaseInput
                v-model="customerStore.currentCustomer.name"
                type="text"
                name="name"
                class="mt-1 md:mt-0"
                :invalid="v$.name.$error"
                @input="v$.name.$touch()"
              />
            </BaseInputGroup>

            <!-- Email -->
            <BaseInputGroup
              :label="$t('customers.email')"
              :error="
                v$.email.$error && v$.email.$errors[0]?.$message
              "
            >
              <BaseInput
                v-model.trim="customerStore.currentCustomer.email"
                type="text"
                name="email"
                class="mt-1 md:mt-0"
                :invalid="v$.email.$error"
                @input="v$.email.$touch()"
              />
            </BaseInputGroup>

            <!-- Phone -->
            <BaseInputGroup :label="$t('customers.phone')">
              <BaseInput
                v-model.trim="customerStore.currentCustomer.phone"
                type="text"
                name="phone"
                class="mt-1 md:mt-0"
              />
            </BaseInputGroup>

            <!-- GSTIN -->
            <BaseInputGroup :label="$t('customers.gstin')">
              <BaseInput
                v-model="customerStore.currentCustomer.tax_id"
                type="text"
                name="tax_id"
                class="mt-1 md:mt-0"
              />
            </BaseInputGroup>

            <!-- Full Address -->
            <BaseInputGroup
              :label="$t('customers.full_address')"
              :error="
                v$.billing.address_street_1.$error &&
                v$.billing.address_street_1.$errors[0]?.$message
              "
            >
              <BaseTextarea
                v-model.trim="
                  customerStore.currentCustomer.billing.address_street_1
                "
                type="text"
                name="full_address"
                rows="3"
                class="mt-1 md:mt-0"
                :invalid="v$.billing.address_street_1.$error"
                @input="v$.billing.address_street_1.$touch()"
              />
            </BaseInputGroup>
          </BaseInputGrid>
        </div>

        <!-- Portal Access Section -->
        <div class="border-t border-line-light pt-8">
          <h6 class="text-lg font-semibold mb-6 text-heading">
            {{ $t('customers.portal_access') }}
          </h6>

          <BaseInputGrid layout="one-column">
            <!-- Enable Portal -->
            <div>
              <p class="text-sm text-muted mb-3">
                Would you like to allow this party to login to the Party Portal?
              </p>
              <BaseSwitch
                v-model="customerStore.currentCustomer.enable_portal"
                class="flex"
              />
            </div>

            <!-- Portal URL -->
            <BaseInputGroup
              v-if="customerStore.currentCustomer.enable_portal"
              label="Party Portal Login URL"
              help-text="Please copy & forward the above given URL to your party for providing access."
            >
              <CopyInputField :token="getCustomerPortalUrl" />
            </BaseInputGroup>

            <!-- Password -->
            <BaseInputGroup
              v-if="customerStore.currentCustomer.enable_portal"
              :label="$t('customers.password')"
              :error="
                v$.password.$error && v$.password.$errors[0]?.$message
              "
            >
              <BaseInput
                v-model.trim="customerStore.currentCustomer.password"
                :type="isShowPassword ? 'text' : 'password'"
                name="password"
                class="mt-1 md:mt-0"
                :invalid="v$.password.$error"
                @input="v$.password.$touch()"
              >
                <template #right>
                  <BaseIcon
                    :name="isShowPassword ? 'EyeIcon' : 'EyeSlashIcon'"
                    class="mr-1 text-muted cursor-pointer"
                    @click="isShowPassword = !isShowPassword"
                  />
                </template>
              </BaseInput>
            </BaseInputGroup>

            <!-- Confirm Password -->
            <BaseInputGroup
              v-if="customerStore.currentCustomer.enable_portal"
              :label="$t('customers.confirm_password')"
              :error="
                v$.confirm_password.$error &&
                v$.confirm_password.$errors[0]?.$message
              "
            >
              <BaseInput
                v-model.trim="customerStore.currentCustomer.confirm_password"
                :type="isShowConfirmPassword ? 'text' : 'password'"
                name="confirm_password"
                class="mt-1 md:mt-0"
                :invalid="v$.confirm_password.$error"
                @input="v$.confirm_password.$touch()"
              >
                <template #right>
                  <BaseIcon
                    :name="isShowConfirmPassword ? 'EyeIcon' : 'EyeSlashIcon'"
                    class="mr-1 text-muted cursor-pointer"
                    @click="isShowConfirmPassword = !isShowConfirmPassword"
                  />
                </template>
              </BaseInput>
            </BaseInputGroup>
          </BaseInputGrid>
        </div>
      </div>

      <!-- Modal Footer with Actions -->
      <div class="px-6 py-3 border-t border-line-light flex justify-end gap-3">
        <BaseButton
          type="button"
          variant="secondary"
          @click="closePartyModal"
        >
          {{ $t('general.cancel') }}
        </BaseButton>
        <BaseButton
          type="submit"
          :loading="isLoading"
          :disabled="isLoading"
        >
          {{ customerStore.isEdit ? $t('general.update') : $t('customers.save_party') }}
        </BaseButton>
      </div>
    </form>
  </BaseModal>
</template>
