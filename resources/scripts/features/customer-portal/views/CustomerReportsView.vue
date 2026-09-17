<script setup lang="ts">
import { ref } from 'vue'
import CustomerLrReceiptsReportView from './reports/CustomerLrReceiptsReportView.vue'
import CustomerPaymentsReportView from './reports/CustomerPaymentsReportView.vue'

// Refs to the active tab's report component so the header
// "Download PDF" button can trigger the correct report download.
const lrReceiptsRef = ref<InstanceType<typeof CustomerLrReceiptsReportView> | null>(null)
const paymentsRef = ref<InstanceType<typeof CustomerPaymentsReportView> | null>(null)

function onDownload(): void {
  // The currently visible tab's component is the one that's mounted;
  // call its exposed downloadReport method.
  lrReceiptsRef.value?.downloadReport()
  paymentsRef.value?.downloadReport()
}
</script>

<template>
  <BasePage>
    <BasePageHeader title="Reports">
      <template #default>
        <BaseBreadcrumb>
          <BaseBreadcrumbItem title="Home" to="customer-portal.dashboard" />
          <BaseBreadcrumbItem title="Reports" to="#" active />
        </BaseBreadcrumb>
      </template>

      <template #actions>
        <BaseButton variant="primary" class="ml-4" @click="onDownload">
          <template #left="slotProps">
            <BaseIcon name="ArrowDownTrayIcon" :class="slotProps.class" />
          </template>
          {{ $t('reports.download_pdf') }}
        </BaseButton>
      </template>
    </BasePageHeader>

    <BaseTabGroup class="p-2">
      <BaseTab title="LR Receipts" tab-panel-container="px-0 py-0">
        <CustomerLrReceiptsReportView ref="lrReceiptsRef" />
      </BaseTab>

      <BaseTab title="Payments" tab-panel-container="px-0 py-0">
        <CustomerPaymentsReportView ref="paymentsRef" />
      </BaseTab>
    </BaseTabGroup>
  </BasePage>
</template>
