<script setup lang="ts">
/**
 * DocumentTab — Config-driven tab renderer.
 *
 * Replaces 7 individual tab components:
 * - InvoicesTab.vue
 * - InvoiceReceiptsTab.vue
 * - LrReceiptsTab.vue
 * - LorryReceiptsTab.vue
 * - EstimatesTab.vue
 * - QuotationsTab.vue
 * - PaymentsTab.vue
 *
 * Reads the SettingSection[] from the registry and renders each section
 * inside a CollapsibleSection wrapper, dispatching to the correct
 * generic section component based on `section.component`.
 */
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { SettingTab } from '../config/settings-registry'
import CollapsibleSection from './CollapsibleSection.vue'
import NumberCustomizer from './NumberCustomizer.vue'
import CustomTemplateUploader from './CustomTemplateUploader.vue'
import DefaultFormatsSection from './sections/DefaultFormatsSection.vue'
import RetrospectiveEditsSection from './sections/RetrospectiveEditsSection.vue'
import DueDateSection from './sections/DueDateSection.vue'
import ExpiryDateSection from './sections/ExpiryDateSection.vue'
import ConvertEstimateSection from './sections/ConvertEstimateSection.vue'
import EmailAttachmentSection from './sections/EmailAttachmentSection.vue'
import { useInvoiceStore } from '@/scripts/features/company/invoices/store'
import { useEstimateStore } from '@/scripts/features/company/estimates/store'
import { useQuotationStore } from '@/scripts/features/company/quotations/store'
import { usePaymentStore } from '@/scripts/features/company/payments/store'

interface Props {
  tab: SettingTab
}

const props = defineProps<Props>()

const { t } = useI18n()

// ── Store resolution for NumberCustomizer ──
// NumberCustomizer needs a type store with a getNextNumber() method.
// We resolve the correct store based on the `type` field in the config.
const invoiceStore = useInvoiceStore()
const estimateStore = useEstimateStore()
const quotationStore = useQuotationStore()
const paymentStore = usePaymentStore()

const storeMap: Record<string, typeof invoiceStore> = {
  invoice: invoiceStore,
  estimate: estimateStore,
  quotation: quotationStore,
  payment: paymentStore,
}

function getTypeStore(type: string): typeof invoiceStore {
  return storeMap[type] ?? invoiceStore
}


// Track which sections are expanded — first section expanded by default
const expandedSections = ref<Set<string>>(new Set([props.tab.sections[0]?.id]))

function isExpanded(sectionId: string): boolean {
  return expandedSections.value.has(sectionId)
}

function toggleSection(sectionId: string): void {
  if (expandedSections.value.has(sectionId)) {
    expandedSections.value.delete(sectionId)
  } else {
    expandedSections.value.add(sectionId)
  }
}

// Section anchor nav — for the left sidebar
const sectionAnchors = computed(() => {
  return props.tab.sections.map((s) => ({
    id: s.id,
    label: t(s.titleKey),
  }))
})

// Scroll to a section
function scrollToSection(sectionId: string): void {
  const el = document.getElementById(`section-${sectionId}`)
  if (el) {
    el.scrollIntoView({ behavior: 'smooth', block: 'start' })
    expandedSections.value.add(sectionId)
  }
}
</script>

<template>
  <div class="flex gap-6">
    <!-- Left: Section Anchor Nav (sticky, hidden on mobile) -->
    <aside class="hidden lg:block w-48 flex-shrink-0">
      <div class="sticky top-4">
        <p class="text-xs font-semibold text-muted uppercase mb-3">
          {{ t('settings.customization.on_this_page') }}
        </p>
        <ul class="space-y-1">
          <li v-for="anchor in sectionAnchors" :key="anchor.id">
            <button
              type="button"
              class="text-sm text-left w-full px-2 py-1 rounded transition-colors"
              :class="isExpanded(anchor.id)
                ? 'text-primary-500 font-medium bg-primary-50'
                : 'text-muted hover:text-heading hover:bg-hover'"
              @click="scrollToSection(anchor.id)"
            >
              {{ anchor.label }}
            </button>
          </li>
        </ul>
      </div>
    </aside>

    <!-- Right: Section Content -->
    <div class="flex-1 min-w-0 space-y-4">
      <div
        v-for="section in tab.sections"
        :id="`section-${section.id}`"
        :key="section.id"
      >

        <CollapsibleSection
          :title="t(section.titleKey)"
          :description="section.descriptionKey ? t(section.descriptionKey) : undefined"
          :default-expanded="isExpanded(section.id)"
        >
          <!-- Number Customizer -->
          <NumberCustomizer
            v-if="section.component === 'number-customizer' && section.numberCustomizer"
            :type="section.numberCustomizer.type"
            :type-store="getTypeStore(section.numberCustomizer.type)"
            :default-series="section.numberCustomizer.defaultSeries"
            :setting-key="section.numberCustomizer.settingKey"
          />

          <!-- Default Formats -->
          <DefaultFormatsSection
            v-else-if="section.component === 'default-formats' && section.defaultFormats"
            :config="section.defaultFormats"
          />

          <!-- Retrospective Edits -->
          <RetrospectiveEditsSection
            v-else-if="section.component === 'retrospective-edits' && section.retrospective"
            :config="section.retrospective"
          />

          <!-- Due Date -->
          <DueDateSection
            v-else-if="section.component === 'due-date' && section.dueDate"
            :auto-key="section.dueDate.autoKey"
            :days-key="section.dueDate.daysKey"
          />

          <!-- Expiry Date -->
          <ExpiryDateSection
            v-else-if="section.component === 'expiry-date' && section.expiryDate"
            :auto-key="section.expiryDate.autoKey"
            :days-key="section.expiryDate.daysKey"
          />

          <!-- Convert Estimate -->
          <ConvertEstimateSection
            v-else-if="section.component === 'convert-estimate' && section.convertEstimate"
            :setting-key="section.convertEstimate.settingKey"
            :enum-options="section.convertEstimate.enumOptions"
          />

          <!-- Template Uploader -->
          <CustomTemplateUploader
            v-else-if="section.component === 'template-uploader' && section.templateUploader"
            :document-type="section.templateUploader.documentType"
          />

          <!-- Email Attachment -->
          <EmailAttachmentSection
            v-else-if="section.component === 'email-attachment' && section.emailAttachment"
            :setting-key="section.emailAttachment.settingKey"
            :title-key="section.titleKey"
            :description-key="section.descriptionKey ?? ''"
          />
        </CollapsibleSection>
      </div>
    </div>
  </div>
</template>
