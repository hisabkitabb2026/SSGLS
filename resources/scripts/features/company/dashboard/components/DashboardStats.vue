<script setup lang="ts">
import DollarIcon from '@/scripts/components/icons/dashboard/DollarIcon.vue'
import CustomerIcon from '@/scripts/components/icons/dashboard/CustomerIcon.vue'
import InvoiceIcon from '@/scripts/components/icons/dashboard/InvoiceIcon.vue'
import EstimateIcon from '@/scripts/components/icons/dashboard/EstimateIcon.vue'
import LrReceiptIcon from '@/scripts/components/icons/dashboard/LrReceiptIcon.vue'
import LorryReceiptIcon from '@/scripts/components/icons/dashboard/LorryReceiptIcon.vue'
import DashboardStatsItem from './DashboardStatsItem.vue'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

import { useDashboardStore } from '../store'
import { useCompanyStore } from '../../../../stores/company.store'
import { useUserStore } from '../../../../stores/user.store'

const ABILITIES = {
  VIEW_INVOICE: 'view-invoice',
  VIEW_INVOICE_RECEIPT: 'view-invoice-receipt',
  VIEW_CUSTOMER: 'view-customer',
  VIEW_ESTIMATE: 'view-estimate',
  VIEW_LR_RECEIPT: 'view-lr-receipt',
  VIEW_LORRY_RECEIPT: 'view-lorry-receipt',
} as const

const { t } = useI18n()
const dashboardStore = useDashboardStore()
const companyStore = useCompanyStore()
const userStore = useUserStore()

// ── Document type settings (from Settings → Customization → Document Types) ──
const isStandardInvoiceEnabled = computed<boolean>(() => {
  return companyStore.selectedCompanySettings?.enable_standard_invoices === 'YES'
})

const isInvoiceReceiptEnabled = computed<boolean>(() => {
  return companyStore.selectedCompanySettings?.enable_invoice_receipts === 'YES'
})

const isEstimateEnabled = computed<boolean>(() => {
  return companyStore.selectedCompanySettings?.enable_estimate === 'YES'
})

const isQuotationEnabled = computed<boolean>(() => {
  return companyStore.selectedCompanySettings?.enable_quotation === 'YES'
})

const isLrReceiptEnabled = computed<boolean>(() => {
  return companyStore.selectedCompanySettings?.enable_lr_receipts === 'YES'
})

const isLorryReceiptEnabled = computed<boolean>(() => {
  return companyStore.selectedCompanySettings?.enable_lorry_receipts === 'YES'
})

const isCustomerEnabled = computed<boolean>(() => {
  return companyStore.selectedCompanySettings?.enable_customers === 'YES'
})

// ── Owner bypass helper (mirrors backend BaseCompanyPolicy::hasFullCompanyAccess) ──
function can(ability: string): boolean {
  return userStore.isOwner || userStore.hasAbilities(ability)
}

// ── Party mode: when standard invoices are disabled, "Customer" becomes "Party" ──
const isPartyMode = computed<boolean>(() => {
  return !isStandardInvoiceEnabled.value
})

const customerLabel = computed<string>(() => {
  if (isPartyMode.value) {
    return dashboardStore.stats.totalCustomerCount <= 1 ? 'Party' : 'Parties'
  }
  return dashboardStore.stats.totalCustomerCount <= 1
    ? t('dashboard.cards.customers', 1)
    : t('dashboard.cards.customers', 2)
})

// ── Amount Due card: route depends on which invoice type is enabled ──
const amountDueRoute = computed<string>(() => {
  if (isStandardInvoiceEnabled.value) {
    return '/admin/standard-invoices'
  }
  if (isInvoiceReceiptEnabled.value) {
    return '/admin/invoices'
  }
  return '/admin/invoices'
})

// ── Amount Due card visibility: show if either invoice type is enabled ──
const showAmountDue = computed<boolean>(() => {
  return (isStandardInvoiceEnabled.value || isInvoiceReceiptEnabled.value) &&
    (can(ABILITIES.VIEW_INVOICE) || can(ABILITIES.VIEW_INVOICE_RECEIPT))
})
</script>

<template>
  <!--
    KPI Grid: 4 columns on large screens.
    The "Amount Due" card spans 2 columns (large), the rest span 1.
    Cards are shown only when the corresponding document type is enabled
    in Settings → Customization → Document Types AND the user has the
    required ability (owners bypass ability checks).
  -->
  <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 xl:gap-5">
    <!-- Amount Due — large card spanning 2 columns -->
    <DashboardStatsItem
      v-if="showAmountDue"
      :icon-component="DollarIcon"
      :loading="!dashboardStore.isDashboardDataLoaded"
      :route="amountDueRoute"
      :large="true"
      accent="primary"
      :label="$t('dashboard.cards.due_amount')"
    >
      <BaseFormatMoney
        :amount="dashboardStore.stats.totalAmountDue"
        :currency="companyStore.selectedCompanyCurrency"
      />
    </DashboardStatsItem>

    <!-- Customers / Parties -->
    <DashboardStatsItem
      v-if="isCustomerEnabled && can(ABILITIES.VIEW_CUSTOMER)"
      :icon-component="CustomerIcon"
      :loading="!dashboardStore.isDashboardDataLoaded"
      route="/admin/customers"
      accent="blue"
      :label="customerLabel"
    >
      {{ dashboardStore.stats.totalCustomerCount }}
    </DashboardStatsItem>

    <!-- Standard Invoices (only when enable_standard_invoices is YES) -->
    <DashboardStatsItem
      v-if="isStandardInvoiceEnabled && can(ABILITIES.VIEW_INVOICE)"
      :icon-component="InvoiceIcon"
      :loading="!dashboardStore.isDashboardDataLoaded"
      route="/admin/standard-invoices"
      accent="green"
      :label="
        dashboardStore.stats.totalInvoiceCount <= 1
          ? $t('dashboard.cards.invoices', 1)
          : $t('dashboard.cards.invoices', 2)
      "
    >
      {{ dashboardStore.stats.totalInvoiceCount }}
    </DashboardStatsItem>

    <!-- Invoice Receipts (only when enable_invoice_receipts is YES) -->
    <DashboardStatsItem
      v-if="isInvoiceReceiptEnabled && can(ABILITIES.VIEW_INVOICE_RECEIPT)"
      :icon-component="InvoiceIcon"
      :loading="!dashboardStore.isDashboardDataLoaded"
      route="/admin/invoices"
      accent="green"
      :label="
        dashboardStore.stats.totalInvoiceReceiptCount <= 1
          ? $t('dashboard.cards.invoice_receipts', 1)
          : $t('dashboard.cards.invoice_receipts', 2)
      "
    >
      {{ dashboardStore.stats.totalInvoiceReceiptCount }}
    </DashboardStatsItem>

    <!-- Estimates (only when enable_estimate is YES) -->
    <DashboardStatsItem
      v-if="isEstimateEnabled && can(ABILITIES.VIEW_ESTIMATE)"
      :icon-component="EstimateIcon"
      :loading="!dashboardStore.isDashboardDataLoaded"
      route="/admin/estimates"
      accent="purple"
      :label="
        dashboardStore.stats.totalEstimateCount <= 1
          ? $t('dashboard.cards.estimates', 1)
          : $t('dashboard.cards.estimates', 2)
      "
    >
      {{ dashboardStore.stats.totalEstimateCount }}
    </DashboardStatsItem>

    <!-- Quotations (only when enable_quotation is YES) -->
    <DashboardStatsItem
      v-if="isQuotationEnabled && can(ABILITIES.VIEW_ESTIMATE)"
      :icon-component="EstimateIcon"
      :loading="!dashboardStore.isDashboardDataLoaded"
      route="/admin/quotations"
      accent="yellow"
      :label="
        dashboardStore.stats.totalQuotationCount <= 1
          ? $t('dashboard.cards.quotations', 1)
          : $t('dashboard.cards.quotations', 2)
      "
    >
      {{ dashboardStore.stats.totalQuotationCount }}
    </DashboardStatsItem>

    <!-- LR Receipts (only when enable_lr_receipts is YES) -->
    <DashboardStatsItem
      v-if="isLrReceiptEnabled && can(ABILITIES.VIEW_LR_RECEIPT)"
      :icon-component="LrReceiptIcon"
      :loading="!dashboardStore.isDashboardDataLoaded"
      route="/admin/lr-receipts"
      accent="blue"
      :label="
        dashboardStore.stats.totalLrReceiptCount <= 1
          ? $t('dashboard.cards.lr_receipts', 1)
          : $t('dashboard.cards.lr_receipts', 2)
      "
    >
      {{ dashboardStore.stats.totalLrReceiptCount }}
    </DashboardStatsItem>

    <!-- Lorry Receipts (only when enable_lorry_receipts is YES) -->
    <DashboardStatsItem
      v-if="isLorryReceiptEnabled && can(ABILITIES.VIEW_LORRY_RECEIPT)"
      :icon-component="LorryReceiptIcon"
      :loading="!dashboardStore.isDashboardDataLoaded"
      route="/admin/lorry-receipts"
      accent="green"
      :label="
        dashboardStore.stats.totalLorryReceiptCount <= 1
          ? $t('dashboard.cards.lorry_receipts', 1)
          : $t('dashboard.cards.lorry_receipts', 2)
      "
    >
      {{ dashboardStore.stats.totalLorryReceiptCount }}
    </DashboardStatsItem>
  </div>
</template>
