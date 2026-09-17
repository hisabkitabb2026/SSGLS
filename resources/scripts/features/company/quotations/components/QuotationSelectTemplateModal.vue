<template>
  <BaseModal :show="modalActive" @close="closeModal" @open="setData">
    <template #header>
      <div class="flex justify-between w-full">
        {{ modalTitle }}
        <BaseIcon
          name="XMarkIcon"
          class="h-6 w-6 text-muted cursor-pointer"
          @click="closeModal"
        />
      </div>
    </template>
    <div class="px-8 py-8 sm:p-6">
      <div
        v-if="templates && templates.length > 0"
        class="grid grid-cols-3 gap-2 p-1 overflow-x-auto"
      >
        <div
          v-for="(template, index) in templates"
          :key="index"
          :class="{
            'border border-solid border-primary-500':
              selectedTemplate === template.name,
          }"
          class="
            relative
            flex flex-col
            m-2
            border border-line-default border-solid
            cursor-pointer
            hover:border-primary-300
          "
          @click="selectedTemplate = template.name"
        >
          <img
            :src="template.path"
            :alt="template.name"
            class="w-full min-h-[100px]"
          />
          <img
            v-if="selectedTemplate === template.name"
            :alt="template.name"
            class="absolute z-10 w-5 h-5 text-primary-500"
            style="top: -6px; right: -5px"
            :src="getTickImage()"
          />
          <span
            :class="[
              'w-full p-1 bg-surface-muted text-sm text-center absolute bottom-0 left-0',
              {
                'text-primary-500 bg-primary-100':
                  selectedTemplate === template.name,
                'text-muted': selectedTemplate != template.name,
              },
            ]"
          >
            {{ template.name }}
          </span>
        </div>
      </div>

      <div
        v-if="!store?.isEdit"
        class="z-0 flex ml-3 pt-5"
      >
        <BaseCheckbox
          v-model="isMarkAsDefault"
          :set-initial-value="false"
          variant="primary"
          :label="$t('general.mark_as_default')"
          :description="markAsDefaultDescription"
        />
      </div>
    </div>

    <div class="z-0 flex justify-end p-4 border-t border-line-default border-solid">
      <BaseButton class="mr-3" variant="primary-outline" @click="closeModal">
        {{ $t('general.cancel') }}
      </BaseButton>
      <BaseButton variant="primary" @click="chooseTemplate()">
        <template #left="slotProps">
          <BaseIcon name="ArrowDownOnSquareIcon" :class="slotProps.class" />
        </template>
        {{ $t('general.choose') }}
      </BaseButton>
    </div>
  </BaseModal>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useModalStore } from '@/scripts/stores/modal.store'
import { useUserStore } from '@/scripts/stores/user.store'
import { useI18n } from 'vue-i18n'

// Types
interface Template {
  name: string
  path: string
}

interface ModalData {
  templates: Template[]
  store: {
    setTemplate: (templateName: string) => Promise<void>
    isEdit?: boolean
  }
  storeProp: string
  isMarkAsDefault?: boolean
  markAsDefaultDescription?: string
}

// Stores
const modalStore = useModalStore()
const userStore = useUserStore()
const { t } = useI18n()

// State
const selectedTemplate = ref<string>('')
const isMarkAsDefault = ref<boolean>(false)

// Computed properties
const modalActive = computed(() => {
  return (
    modalStore.active && modalStore.componentName === 'SelectTemplate'
  )
})

const modalTitle = computed(() => {
  return modalStore.title || t('general.select_template')
})

const modalData = computed(() => {
  return modalStore.data as ModalData | null
})

const templates = computed(() => {
  return modalData.value?.templates || []
})

const store = computed(() => {
  return modalData.value?.store || null
})

const markAsDefaultDescription = computed(() => {
  return modalData.value?.markAsDefaultDescription || ''
})

// Functions
function setData(): void {
  if (!modalData.value) return

  const currentTemplate = modalData.value.store[modalData.value.storeProp]?.template_name

  if (currentTemplate) {
    selectedTemplate.value = currentTemplate
  } else if (templates.value.length > 0) {
    selectedTemplate.value = templates.value[0].name
  }
}

async function chooseTemplate(): Promise<void> {
  if (!modalData.value || !store.value) return

  try {
    await store.value.setTemplate(selectedTemplate.value)

    // update default estimate or invoice template
    if (!store.value.isEdit && isMarkAsDefault.value) {
      if (modalData.value.storeProp === 'newQuotation') {
        await userStore.updateUserSettings({
          settings: {
            default_estimate_template: selectedTemplate.value,
          },
        })
      } else if (modalData.value.storeProp === 'newInvoice') {
        await userStore.updateUserSettings({
          settings: {
            default_invoice_template: selectedTemplate.value,
          },
        })
      }
    }

    closeModal()
  } catch (error) {
    console.error('Error choosing template:', error)
  }
}

function getTickImage(): string {
  const tickSvg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
    <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/>
  </svg>`

  return `data:image/svg+xml;base64,${btoa(tickSvg)}`
}

function closeModal(): void {
  modalStore.closeModal()

  setTimeout(() => {
    selectedTemplate.value = ''
    isMarkAsDefault.value = false
    modalStore.$reset()
  }, 300)
}
</script>
