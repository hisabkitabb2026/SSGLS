<script setup lang="ts">
import { reactive, ref, computed, watch } from 'vue'

import { useI18n } from 'vue-i18n'
import { required, minLength, helpers } from '@vuelidate/validators'
import { useVuelidate } from '@vuelidate/core'
import { useGlobalStore } from '../../../../stores/global.store'
import { useCompanyStore } from '../../../../stores/company.store'
import { useModalStore } from '../../../../stores/modal.store'

interface CompanyFormData {
  name: string | null
  logo: string | null
  tax_id: string | null
  vat_id: string | null
  enrollment_no: string | null
  gstin: string | null
  pan_no: string | null
  billing_branch: string | null
  address: {
    address_street_1: string
    address_street_2: string
    country_id: number | null
    state: string
    city: string
    phone: string
    email: string
    zip: string
  }
}

interface FilePreview {
  image: string
}

const companyStore = useCompanyStore()
const globalStore = useGlobalStore()
const modalStore = useModalStore()
const { t } = useI18n()

const isSaving = ref<boolean>(false)

const companyForm = reactive<CompanyFormData>({
  name: companyStore.selectedCompany?.name ?? null,
  logo: companyStore.selectedCompany?.logo ?? null,
  tax_id: companyStore.selectedCompany?.tax_id ?? null,
  vat_id: companyStore.selectedCompany?.vat_id ?? null,
  enrollment_no: companyStore.selectedCompany?.enrollment_no ?? null,
  gstin: companyStore.selectedCompany?.gstin ?? null,
  pan_no: companyStore.selectedCompany?.pan_no ?? null,
  billing_branch: companyStore.selectedCompany?.billing_branch ?? null,

  address: {
    address_street_1: (companyStore.selectedCompany?.address as Record<string, string>)?.address_street_1 ?? '',
    address_street_2: (companyStore.selectedCompany?.address as Record<string, string>)?.address_street_2 ?? '',
    country_id: (companyStore.selectedCompany?.address as Record<string, number | null>)?.country_id ?? null,
    state: (companyStore.selectedCompany?.address as Record<string, string>)?.state ?? '',
    city: (companyStore.selectedCompany?.address as Record<string, string>)?.city ?? '',
    phone: (companyStore.selectedCompany?.address as Record<string, string>)?.phone ?? '',
    email: (companyStore.selectedCompany?.address as Record<string, string>)?.email ?? '',
    zip: (companyStore.selectedCompany?.address as Record<string, string>)?.zip ?? '',
  },
})

const previewLogo = ref<FilePreview[]>([])
const logoFileBlob = ref<string | null>(null)
const logoFileName = ref<string | null>(null)
const isCompanyLogoRemoved = ref<boolean>(false)

// Watch for selectedCompany loading asynchronously after page refresh.
// The form is initialized synchronously from selectedCompany, but on page
// refresh selectedCompany starts as null and loads via the bootstrap API.
// This watch ensures the form populates correctly once the data arrives.
watch(
  () => companyStore.selectedCompany,
  (company) => {
    if (!company) return

    companyForm.name = company.name
    companyForm.logo = company.logo
    companyForm.tax_id = company.tax_id
    companyForm.vat_id = company.vat_id
    companyForm.enrollment_no = company.enrollment_no
    companyForm.gstin = company.gstin
    companyForm.pan_no = company.pan_no
    companyForm.billing_branch = company.billing_branch

    const addr = company.address as Record<string, unknown> | undefined
    if (addr) {
      companyForm.address.address_street_1 = (addr.address_street_1 as string) ?? ''
      companyForm.address.address_street_2 = (addr.address_street_2 as string) ?? ''
      companyForm.address.country_id = (addr.country_id as number | null) ?? null
      companyForm.address.state = (addr.state as string) ?? ''
      companyForm.address.city = (addr.city as string) ?? ''
      companyForm.address.phone = (addr.phone as string) ?? ''
      companyForm.address.email = (addr.email as string) ?? ''
      companyForm.address.zip = (addr.zip as string) ?? ''
    }

    // Update logo preview if logo changed
    if (company.logo && previewLogo.value.length === 0) {
      previewLogo.value.push({ image: company.logo })
    }
  },
  { immediate: true },
)


const rules = computed(() => ({
  name: {
    required: helpers.withMessage(t('validation.required'), required),
    minLength: helpers.withMessage(t('validation.name_min_length'), minLength(3)),
  },
  address: {
    country_id: {
      required: helpers.withMessage(t('validation.required'), required),
    },
  },
}))

const v$ = useVuelidate(
  rules,
  computed(() => companyForm)
)

globalStore.fetchCountries()

function onFileInputChange(
  _fileName: string,
  file: string,
  _fileCount: number,
  fileList: { name: string }
): void {
  logoFileName.value = fileList.name
  logoFileBlob.value = file
}

function onFileInputRemove(): void {
  logoFileBlob.value = null
  isCompanyLogoRemoved.value = true
}

async function updateCompanyData(): Promise<void> {
  v$.value.$touch()
  if (v$.value.$invalid) return

  isSaving.value = true

  const res = await companyStore.updateCompany({
    name: companyForm.name ?? '',
    tax_id: companyForm.tax_id,
    vat_id: companyForm.vat_id,
    enrollment_no: companyForm.enrollment_no,
    gstin: companyForm.gstin,
    pan_no: companyForm.pan_no,
    billing_branch: companyForm.billing_branch,
    address: companyForm.address,
  })

  if (res.data) {
    if (logoFileBlob.value || isCompanyLogoRemoved.value) {
      const logoData = new FormData()

      if (logoFileBlob.value) {
        logoData.append(
          'company_logo',
          JSON.stringify({
            name: logoFileName.value,
            data: logoFileBlob.value,
          })
        )
      }
      logoData.append('is_company_logo_removed', String(isCompanyLogoRemoved.value))

      const logoResponse = await companyStore.updateCompanyLogo(logoData)
      logoFileBlob.value = null
      isCompanyLogoRemoved.value = false

      // Sync the logo preview with the updated company logo URL returned from the API.
      // The store has already updated selectedCompany with the new logo, so we refresh
      // the preview array to reflect the new image in the file uploader component.
      if (logoResponse.data?.logo) {
        previewLogo.value = [{ image: logoResponse.data.logo }]
      } else {
        // Logo was removed
        previewLogo.value = []
      }
    }
  }

  isSaving.value = false
}
</script>

<template>
  <form @submit.prevent="updateCompanyData">
    <BaseSettingCard
      :title="$t('settings.company_info.company_info')"
      :description="$t('settings.company_info.section_description')"
    >
      <BaseInputGrid class="mt-5">
        <BaseInputGroup :label="$t('settings.company_info.company_logo')">
          <BaseFileUploader
            v-model="previewLogo"
            base64
            @change="onFileInputChange"
            @remove="onFileInputRemove"
          />
        </BaseInputGroup>
      </BaseInputGrid>

      <BaseInputGrid class="mt-5">
        <BaseInputGroup
          :label="$t('settings.company_info.company_name')"
          :error="v$.name.$error && v$.name.$errors[0]?.$message"
          required
        >
          <BaseInput
            v-model="companyForm.name"
            :invalid="v$.name.$error"
            @blur="v$.name.$touch()"
          />
        </BaseInputGroup>

        <BaseInputGroup :label="$t('settings.company_info.phone')">
          <BaseInput v-model="companyForm.address.phone" />
        </BaseInputGroup>

        <BaseInputGroup :label="$t('settings.company_info.email')">
          <BaseInput v-model="companyForm.address.email" type="email" />
        </BaseInputGroup>

        <BaseInputGroup
          :label="$t('settings.company_info.country')"
          :error="
            v$.address.country_id.$error &&
            v$.address.country_id.$errors[0]?.$message
          "
          required
        >
          <BaseMultiselect
            v-model="companyForm.address.country_id"
            label="name"
            :invalid="v$.address.country_id.$error"
            :options="globalStore.countries"
            value-prop="id"
            :can-deselect="true"
            :can-clear="false"
            searchable
            track-by="name"
          />
        </BaseInputGroup>

        <BaseInputGroup :label="$t('settings.company_info.state')">
          <BaseInput v-model="companyForm.address.state" name="state" type="text" />
        </BaseInputGroup>

        <BaseInputGroup :label="$t('settings.company_info.city')">
          <BaseInput v-model="companyForm.address.city" type="text" />
        </BaseInputGroup>

        <BaseInputGroup :label="$t('settings.company_info.zip')">
          <BaseInput v-model="companyForm.address.zip" />
        </BaseInputGroup>

        <BaseInputGroup :label="$t('settings.company_info.address')">
          <BaseTextarea v-model="companyForm.address.address_street_1" rows="2" />
        </BaseInputGroup>

        <BaseInputGroup :label="$t('settings.company_info.tax_id')">
          <BaseInput v-model="companyForm.tax_id" type="text" />
        </BaseInputGroup>

        <BaseInputGroup :label="$t('settings.company_info.vat_id')">
          <BaseInput v-model="companyForm.vat_id" type="text" />
        </BaseInputGroup>

        <BaseInputGroup :label="$t('settings.company_info.enrollment_no')">
          <BaseInput v-model="companyForm.enrollment_no" type="text" />
        </BaseInputGroup>

        <BaseInputGroup :label="$t('settings.company_info.gstin')">
          <BaseInput v-model="companyForm.gstin" type="text" />
        </BaseInputGroup>

        <BaseInputGroup :label="$t('settings.company_info.pan_no')">
          <BaseInput v-model="companyForm.pan_no" type="text" />
        </BaseInputGroup>

        <BaseInputGroup label="Billing Branch Name & Address">
          <BaseTextarea v-model="companyForm.billing_branch" rows="2" />
        </BaseInputGroup>
      </BaseInputGrid>

      <BaseButton
        :loading="isSaving"
        :disabled="isSaving"
        type="submit"
        class="mt-6"
      >
        <template #left="slotProps">
          <BaseIcon
            v-if="!isSaving"
            :class="slotProps.class"
            name="ArrowDownOnSquareIcon"
          />
        </template>
        {{ $t('settings.company_info.save') }}
      </BaseButton>

    </BaseSettingCard>
  </form>
</template>
