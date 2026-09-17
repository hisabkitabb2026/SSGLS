<template>
  <BaseSettingCard
    :title="$t('settings.customization.custom_template_title')"
    :description="$t('settings.customization.custom_template_description')"
  >
    <div class="mt-4">
      <!-- Active Template Selector -->
      <div class="mb-6 pb-6 border-b border-line-light flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex-1">
          <h4 class="text-sm font-semibold text-heading">{{ $t('settings.customization.active_pdf_template') }}</h4>
          <p class="text-xs text-muted mt-0.5">{{ $t('settings.customization.active_pdf_template_description') }}</p>
        </div>
        <div class="flex items-center gap-3 w-full md:w-auto">
          <div class="flex-1 md:w-64">
            <BaseSelectInput
              v-model="activeTemplate"
              :options="allTemplatesOptions"
              value-prop="id"
              label-key="label"
              :content-loading="isLoading"
              :placeholder="$t('settings.customization.select_template')"
            />
          </div>
          <BaseButton
            variant="primary"
            :loading="isSavingTemplate"
            :disabled="isSavingTemplate"
            class="flex-shrink-0"
            @click="saveActiveTemplate"
          >
            {{ $t('settings.customization.save') }}
          </BaseButton>
        </div>
      </div>

      <!-- Built-in Templates -->
      <div v-if="builtinTemplates.length" class="mb-6">
        <h4 class="text-sm font-semibold text-heading mb-3">{{ $t('settings.customization.builtin_templates') }}</h4>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
          <div
            v-for="tpl in builtinTemplates"
            :key="tpl.name"
            class="relative border border-line-default rounded-lg p-3 hover:border-primary-400 transition-colors"
          >
            <img
              v-if="tpl.path"
              :src="tpl.path"
              :alt="tpl.name"
              class="w-full h-32 object-contain mb-2"
            />
            <div v-else class="w-full h-32 flex items-center justify-center mb-2 bg-surface-secondary rounded">
              <BaseIcon name="DocumentTextIcon" class="w-8 h-8 text-subtle" />
            </div>
            <p class="text-xs font-medium text-heading truncate">{{ tpl.name }}</p>
            <button
              class="mt-1 text-xs text-primary-500 hover:text-primary-600"
              @click="downloadTemplate(tpl.name)"
            >
              {{ $t('settings.customization.download_code') }}
            </button>

          </div>
        </div>
      </div>

      <!-- Custom Templates -->
      <div v-if="customTemplates.length" class="mb-6">
        <h4 class="text-sm font-semibold text-heading mb-3">{{ $t('settings.customization.custom_templates') }}</h4>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
          <div
            v-for="tpl in customTemplates"
            :key="tpl.name"
            class="relative border border-line-default rounded-lg p-3 hover:border-primary-400 transition-colors"
          >
            <span class="absolute top-2 right-2 px-1.5 py-0.5 text-[10px] font-medium bg-primary-50 text-primary-600 rounded">Custom</span>
            <img
              v-if="tpl.path"
              :src="tpl.path"
              :alt="tpl.name"
              class="w-full h-32 object-contain mb-2"
            />
            <div v-else class="w-full h-32 flex items-center justify-center mb-2 bg-surface-secondary rounded">
              <BaseIcon name="DocumentTextIcon" class="w-8 h-8 text-subtle" />
            </div>
            <p class="text-xs font-medium text-heading truncate">{{ tpl.name }}</p>
            <div class="flex gap-2 mt-1">
              <button
                class="text-xs text-primary-500 hover:text-primary-600"
                @click="downloadTemplate(tpl.name)"
              >
                Download
              </button>
              <button
                class="text-xs text-alert-error-text hover:text-alert-error-text"
                @click="confirmDelete(tpl.name)"
              >
                Delete
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Empty state for custom templates -->
      <div v-if="!customTemplates.length && !isLoading" class="mb-6 text-sm text-muted">
        No custom templates uploaded yet. Download a built-in template's code, modify it, and upload as a new template.
      </div>

      <!-- Actions -->
      <div class="flex flex-wrap gap-3">
        <BaseButton
          variant="primary-outline"
          :loading="isDownloading"
          @click="downloadBuiltin"
        >
          <template #left="slotProps">
            <BaseIcon name="ArrowDownTrayIcon" :class="slotProps.class" />
          </template>
          Download Built-in Code
        </BaseButton>
        <BaseButton
          variant="primary"
          @click="showUploadDialog = true"
        >
          <template #left="slotProps">
            <BaseIcon name="ArrowUpTrayIcon" :class="slotProps.class" />
          </template>
          Upload New Template
        </BaseButton>
      </div>
    </div>

    <!-- Upload Dialog -->
    <BaseModal
      :show="showUploadDialog"
      @close="closeUploadDialog"
    >
      <template #header>
        <p>Upload Custom Template</p>
      </template>

      <div class="px-6 py-4 space-y-4">
        <BaseInputGroup
          label="Template Name"
          :error="uploadError"
        >
          <BaseInput
            v-model="templateName"
            placeholder="e.g. My Custom LR Template"
          />
        </BaseInputGroup>

        <BaseInputGroup label="Select .blade.php file">
          <input
            ref="fileInput"
            type="file"
            accept=".php,.blade.php"
            class="block w-full text-sm text-muted file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-primary-50 file:text-primary-600 hover:file:bg-primary-100"
            @change="onFileSelected"
          />
        </BaseInputGroup>

        <p class="text-xs text-muted">
          Document type: <span class="font-medium text-heading">{{ documentType }}</span>
        </p>
      </div>

      <template #footer>
        <div class="flex justify-end gap-3 px-6 py-4">
          <BaseButton variant="gray" @click="closeUploadDialog">
            {{ $t('general.cancel') }}
          </BaseButton>
          <BaseButton
            variant="primary"
            :loading="isUploading"
            :disabled="!templateName || !selectedFile"
            @click="uploadTemplate"
          >
            Upload
          </BaseButton>
        </div>
      </template>
    </BaseModal>
  </BaseSettingCard>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { customTemplateService } from '@/scripts/api/services/custom-template.service'
import { useNotificationStore } from '@/scripts/stores/notification.store'
import { useDialogStore } from '@/scripts/stores/dialog.store'
import { useCompanyStore } from '@/scripts/stores/company.store'
import type { CustomTemplate } from '@/scripts/api/services/custom-template.service'

interface Props {
  documentType: string
}

const props = defineProps<Props>()

const { t } = useI18n()
const notificationStore = useNotificationStore()
const dialogStore = useDialogStore()
const companyStore = useCompanyStore()


const settingKey = computed(() => {
  switch (props.documentType) {
    case 'invoice1':
      return 'default_invoice_template'
    case 'estimate1':
      return 'default_estimate_template'
    case 'office_invoice':
      return 'default_office_invoice_template'
    case 'lr_receipt':
      return 'default_lr_receipt_template'
    case 'lorry_receipt':
      return 'default_lorry_receipt_template'
    case 'payment':
      return 'default_payment_template'
    default:
      return `default_${props.documentType}_template`
  }
})

const allTemplatesOptions = computed(() => {
  const options: Array<{ id: string; label: string }> = []
  
  builtinTemplates.value.forEach((tpl) => {
    options.push({ id: tpl.name, label: tpl.name })
  })
  
  customTemplates.value.forEach((tpl) => {
    options.push({ id: tpl.name, label: `${tpl.name} (Custom)` })
  })
  
  return options
})

// Local form state — initialized from settings, saved on explicit Save button click.
// No auto-save on mount (prevents "Setting updated successfully" on tab switch).
const activeTemplate = ref<string>(
  (companyStore.selectedCompanySettings?.[settingKey.value] as string | undefined) || props.documentType,
)

const isSavingTemplate = ref(false)

async function saveActiveTemplate(): Promise<void> {
  isSavingTemplate.value = true
  const data = {
    settings: {
      [settingKey.value]: activeTemplate.value,
    },
  }
  try {
    await companyStore.updateCompanySettings({
      data,
      message: 'general.setting_updated',
    })
  } catch {
    notificationStore.showNotification({
      type: 'error',
      message: 'Failed to update active template setting.',
    })
  } finally {
    isSavingTemplate.value = false
  }
}


const builtinTemplates = ref<CustomTemplate[]>([])
const customTemplates = ref<CustomTemplate[]>([])
const isLoading = ref(false)
const isDownloading = ref(false)
const isUploading = ref(false)
const showUploadDialog = ref(false)
const templateName = ref('')
const selectedFile = ref<File | null>(null)
const uploadError = ref('')
const fileInput = ref<HTMLInputElement | null>(null)

async function fetchTemplates() {
  isLoading.value = true
  try {
    const response = await customTemplateService.list(props.documentType)
    builtinTemplates.value = response.builtinTemplates
    customTemplates.value = response.customTemplates
  } catch {
    notificationStore.showNotification({
      type: 'error',
      message: 'Failed to load templates.',
    })
  } finally {
    isLoading.value = false
  }
}

async function downloadTemplate(templateName: string) {
  isDownloading.value = true
  try {
    const blob = await customTemplateService.download(templateName)
    triggerDownload(blob, `${templateName}.blade.php`)
  } catch {
    notificationStore.showNotification({
      type: 'error',
      message: 'Failed to download template.',
    })
  } finally {
    isDownloading.value = false
  }
}

async function downloadBuiltin() {
  isDownloading.value = true
  try {
    const blob = await customTemplateService.downloadBuiltin(props.documentType)
    triggerDownload(blob, `${props.documentType}.blade.php`)
  } catch {
    notificationStore.showNotification({
      type: 'error',
      message: 'Failed to download built-in template.',
    })
  } finally {
    isDownloading.value = false
  }
}

function triggerDownload(blob: Blob, filename: string) {
  const url = window.URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filename
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
  window.URL.revokeObjectURL(url)
}

function onFileSelected(event: Event) {
  const target = event.target as HTMLInputElement
  selectedFile.value = target.files?.[0] ?? null
}

function closeUploadDialog() {
  showUploadDialog.value = false
  templateName.value = ''
  selectedFile.value = null
  uploadError.value = ''
  if (fileInput.value) {
    fileInput.value.value = ''
  }
}

async function uploadTemplate() {
  if (!templateName.value || !selectedFile.value) return

  isUploading.value = true
  uploadError.value = ''

  try {
    await customTemplateService.upload(
      templateName.value,
      props.documentType,
      selectedFile.value,
    )
    notificationStore.showNotification({
      type: 'success',
      message: 'Template uploaded successfully.',
    })
    closeUploadDialog()
    await fetchTemplates()
  } catch (error) {
    const err = error as { response?: { data?: { error?: string } } }
    uploadError.value = err?.response?.data?.error || 'Failed to upload template.'
  } finally {
    isUploading.value = false
  }
}

function confirmDelete(templateName: string) {
  dialogStore.openDialog({
    title: 'Delete Template',
    message: `Delete "${templateName}"? This cannot be undone.`,
    yesLabel: 'Delete',
    noLabel: 'Cancel',
    variant: 'danger',
    hideNoButton: false,
    size: 'lg',
  }).then(async (res: boolean) => {
    if (res) {
      try {
        await customTemplateService.delete(templateName)
        notificationStore.showNotification({
          type: 'success',
          message: 'Template deleted successfully.',
        })
        await fetchTemplates()
      } catch {
        notificationStore.showNotification({
          type: 'error',
          message: 'Failed to delete template.',
        })
      }
    }
  })
}

onMounted(() => {
  fetchTemplates()
})
</script>
