<script setup lang="ts">
/**
 * CustomizationView — Main customization settings page.
 *
 * Redesigned to be fully config-driven from the settings registry.
 * Features:
 * - Dynamic tab rendering from the settings registry
 * - Full-text search across all settings
 * - Collapsible sections with dirty state tracking
 *
 * Uses a simple custom tab system instead of BaseTabGroup to avoid
 * HeadlessUI slot processing issues with v-for.
 */
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { settingsTabs } from '../config/settings-registry'
import type { SettingTab } from '../config/settings-registry'
import DocumentTab from '../components/DocumentTab.vue'
import DocumentTypesTab from '../components/DocumentTypesTab.vue'
import ItemsTab from '../components/ItemsTab.vue'

const { t } = useI18n()

// ── Active Tab ──
const activeTabId = ref<string>(settingsTabs[0].id)

const activeTab = computed<SettingTab>(() => {
  return (
    settingsTabs.find((tab) => tab.id === activeTabId.value) ?? settingsTabs[0]
  )
})

// ── Search ──
const searchQuery = ref('')
const isSearching = computed(() => searchQuery.value.trim().length > 0)

// Search results — match against tab titles and section titles
interface SearchResult {
  tabId: string
  tabTitle: string
  sectionId: string
  sectionTitle: string
  sectionDescription: string
}

const searchResults = computed<SearchResult[]>(() => {
  if (!isSearching.value) return []

  const query = searchQuery.value.toLowerCase()
  const results: SearchResult[] = []

  settingsTabs.forEach((tab) => {
    tab.sections.forEach((section) => {
      const title = t(section.titleKey).toLowerCase()
      const desc = section.descriptionKey
        ? t(section.descriptionKey).toLowerCase()
        : ''
      if (title.includes(query) || desc.includes(query)) {
        results.push({
          tabId: tab.id,
          tabTitle: t(tab.titleKey),
          sectionId: section.id,
          sectionTitle: t(section.titleKey),
          sectionDescription: desc,
        })
      }
    })
  })

  return results
})

// Navigate to a tab from search results
function navigateToResult(result: SearchResult): void {
  activeTabId.value = result.tabId
  searchQuery.value = ''
  // Scroll to section after tab switch
  setTimeout(() => {
    const el = document.getElementById(`section-${result.sectionId}`)
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' })
  }, 100)
}
</script>

<template>
  <div class="relative">
    <BaseCard container-class="px-4 py-5 sm:px-8 sm:py-2">
      <!-- Header with Search -->
      <div
        class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6"
      >
        <div>
          <h3 class="text-lg font-semibold text-heading">
            {{ t('settings.customization.title') }}
          </h3>
          <p class="text-sm text-muted mt-1">
            {{ t('settings.customization.description') }}
          </p>
        </div>

        <!-- Search Bar -->
        <div class="relative w-full sm:w-80">
          <BaseInput
            v-model="searchQuery"
            :placeholder="t('settings.customization.search_settings')"
            type="text"
          />
          <BaseIcon
            name="MagnifyingGlassIcon"
            class="absolute right-3 top-1/2 -translate-y-1/2 w-5 h-5 text-subtle pointer-events-none"
          />
        </div>
      </div>

      <!-- Search Results -->
      <div v-if="isSearching" class="space-y-3">
        <p class="text-sm text-muted">
          {{ searchResults.length }}
          {{ t('settings.customization.results_for') }} "{{ searchQuery }}"
        </p>

        <div
          v-if="searchResults.length === 0"
          class="text-center py-12"
        >
          <BaseIcon
            name="MagnifyingGlassIcon"
            class="w-12 h-12 text-subtle mx-auto mb-3"
          />
          <p class="text-sm text-muted">
            {{ t('settings.customization.no_results') }}
          </p>
        </div>

        <div
          v-for="result in searchResults"
          :key="`${result.tabId}-${result.sectionId}`"
          class="border border-line-default rounded-lg p-4 hover:border-primary-400 hover:bg-hover cursor-pointer transition-colors"
          @click="navigateToResult(result)"
        >
          <div class="flex items-center gap-2 mb-1">
            <BaseIcon name="DocumentTextIcon" class="w-4 h-4 text-subtle" />
            <span class="text-xs font-medium text-primary-500">{{
              result.tabTitle
            }}</span>
            <span class="text-subtle">→</span>
            <span class="text-sm font-medium text-heading">{{
              result.sectionTitle
            }}</span>
          </div>
          <p v-if="result.sectionDescription" class="text-xs text-muted ml-6">
            {{ result.sectionDescription }}
          </p>
        </div>
      </div>

      <!-- Tab Content (when not searching) -->
      <div v-else>
        <!-- Tab Buttons -->
        <div
          class="flex border-b border-line-default relative overflow-x-auto overflow-y-hidden mb-4"
        >
          <button
            v-for="tab in settingsTabs"
            :key="tab.id"
            type="button"
            :class="[
              'px-5 py-2.5 text-sm leading-5 font-medium flex items-center relative -mb-px border-b-2 focus:outline-hidden whitespace-nowrap transition-colors',
              activeTabId === tab.id
                ? 'border-primary-400 text-heading'
                : 'border-transparent text-muted hover:text-body hover:border-line-strong',
            ]"
            @click="activeTabId = tab.id"
          >
            {{ t(tab.titleKey) }}
          </button>
        </div>

        <!-- Tab Panel -->
        <div class="py-4 mt-px">
          <!-- Special: Document Types -->
          <DocumentTypesTab
            v-if="activeTab.special === 'document-types'"
          />

          <!-- Special: Items -->
          <ItemsTab v-else-if="activeTab.special === 'item-units'" />

          <!-- Generic: Config-driven document tab -->
          <DocumentTab v-else :key="activeTab.id" :tab="activeTab" />

        </div>
      </div>
    </BaseCard>
  </div>
</template>
