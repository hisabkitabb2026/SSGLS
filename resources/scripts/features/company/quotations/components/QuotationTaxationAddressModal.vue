<template>
  <BaseModal :show="modalActive" @close="closeModal">
    <template #header>
      <div class="flex justify-between w-full">
        {{ title }}
        <BaseIcon
          name="XMarkIcon"
          class="h-6 w-6 text-muted cursor-pointer"
          @click="closeModal"
        />
      </div>
    </template>

    <div class="p-6">
      <p class="text-body mb-4">{{ description }}</p>
      <!-- Address form would go here -->
    </div>

    <div class="flex justify-end p-4 border-t border-line-default border-solid">
      <BaseButton variant="primary-outline" @click="closeModal">
        {{ $t('general.cancel') }}
      </BaseButton>
      <BaseButton class="ml-3" variant="primary" @click="submitAddress">
        {{ $t('general.save') }}
      </BaseButton>
    </div>
  </BaseModal>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useModalStore } from '@/scripts/stores/modal.store'
import { useI18n } from 'vue-i18n'

const modalStore = useModalStore()
const { t } = useI18n()

const emit = defineEmits<{
  addTax: [tax: Record<string, unknown>]
}>()

const modalActive = computed(
  () => modalStore.active && modalStore.componentName === 'TaxationAddressModal'
)

const title = computed(() => modalStore.title || t('settings.taxations.address'))

const description = computed(
  () => modalStore.content || t('settings.taxations.modal_description')
)

function submitAddress(): void {
  // Placeholder for address submission
  closeModal()
}

function closeModal(): void {
  modalStore.closeModal()
}
</script>
