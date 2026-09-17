<script setup lang="ts">
/**
 * DashboardQuickActions — horizontal strip of quick-action buttons.
 *
 * Permission-gated via Bouncer abilities. Each action links directly to
 * the corresponding create page. Hidden entirely in admin mode.
 */
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useUserStore } from '../../../../stores/user.store'
import { useCompanyStore } from '../../../../stores/company.store'
import { ABILITIES } from '../../../../config/abilities'

const { t } = useI18n()
const userStore = useUserStore()
const companyStore = useCompanyStore()


const isPartyMode = computed<boolean>(() => {
  return companyStore.selectedCompanySettings?.enable_standard_invoices !== 'YES'
})

interface QuickAction {
  label: string
  icon: string
  route: string
  ability: string
  accent: string
}

const actions = computed<QuickAction[]>(() => {
  const list: QuickAction[] = []

  if (userStore.hasAbilities(ABILITIES.CREATE_INVOICE)) {
    list.push({
      label: t('dashboard.quick_actions.new_invoice'),
      icon: 'DocumentTextIcon',
      route: '/admin/invoices/create',
      ability: ABILITIES.CREATE_INVOICE,
      accent: 'primary',
    })
  }

  if (userStore.hasAbilities(ABILITIES.CREATE_INVOICE)) {
    list.push({
      label: t('dashboard.quick_actions.new_lr_receipt'),
      icon: 'ClipboardDocumentListIcon',
      route: '/admin/lr-receipts/create',
      ability: ABILITIES.CREATE_INVOICE,
      accent: 'blue',
    })
  }

  if (userStore.hasAbilities(ABILITIES.CREATE_CUSTOMER)) {
    list.push({
      label: isPartyMode.value ? t('dashboard.quick_actions.new_party') : t('dashboard.quick_actions.new_customer'),
      icon: 'UserPlusIcon',
      route: '/admin/customers/create',
      ability: ABILITIES.CREATE_CUSTOMER,
      accent: 'green',
    })
  }

  // Receive Material — links to warehouse item creation page
  list.push({
    label: 'Receive Material',
    icon: 'ArchiveBoxIcon',
    route: '/admin/warehouse-items/create',
    ability: '',
    accent: 'yellow',
  })

  return list
})

const ACCENT_CLASSES: Record<string, string> = {
  primary: 'border-primary-500/20 text-primary-500 hover:bg-primary-50',
  blue: 'border-status-blue/20 text-status-blue hover:bg-status-blue/10',
  green: 'border-status-green/20 text-status-green hover:bg-status-green/10',
  purple: 'border-status-purple/20 text-status-purple hover:bg-status-purple/10',
  yellow: 'border-status-yellow/20 text-status-yellow hover:bg-status-yellow/10',
}
</script>

<template>
  <div
    v-if="actions.length > 0"
    class="flex flex-wrap items-center gap-2"
  >
    <router-link
      v-for="action in actions"
      :key="action.route"
      :to="action.route"
      class="inline-flex items-center gap-2 rounded-lg border bg-surface px-3 py-2 text-sm font-medium transition-colors"
      :class="ACCENT_CLASSES[action.accent] || ACCENT_CLASSES.primary"
    >
      <BaseIcon :name="action.icon" class="w-4 h-4" />
      <span class="hidden sm:inline">{{ action.label }}</span>
    </router-link>
  </div>
</template>
