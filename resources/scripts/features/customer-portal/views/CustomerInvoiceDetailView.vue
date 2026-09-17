<template>
  <BasePage v-if="store.selectedViewInvoice" class="xl:pl-96 xl:ml-8">
    <BasePageHeader :title="pageTitle">
      <template #actions>
        <BaseButton
          variant="primary-outline"
          class="mr-2"
          tag="a"
          :href="downloadLink"
          download
        >
          <template #left="slotProps">
            <BaseIcon name="DownloadIcon" :class="slotProps.class" />
            {{ $t('invoices.download') }}
          </template>
        </BaseButton>

        <BaseButton
          v-if="canPay"
          variant="primary"
          @click="payInvoice"
        >
          {{ $t('invoices.pay_invoice') }}
        </BaseButton>
      </template>
    </BasePageHeader>

    <!-- Secondary Sidebar: Invoice List -->
    <div
      class="fixed top-0 left-0 hidden h-full pt-16 pb-[6.4rem] ml-56 bg-surface xl:ml-64 w-88 xl:block"
    >
      <div
        class="flex items-center justify-between px-4 pt-8 pb-2 border border-line-default border-solid height-full"
      >
        <div class="mb-6">
          <BaseInput
            v-model="searchData.invoice_number"
            :placeholder="$t('general.search')"
            type="text"
            variant="gray"
            @input="onSearchDebounced"
          >
            <template #right>
              <BaseIcon name="MagnifyingGlassIcon" class="h-5 text-subtle" />
            </template>
          </BaseInput>
        </div>

        <div class="flex mb-6 ml-3" role="group" aria-label="First group">
          <BaseDropdown class="ml-3" position="bottom-start">
            <template #activator>
              <BaseButton size="md" variant="gray">
                <BaseIcon name="FunnelIcon" />
              </BaseButton>
            </template>
            <div
              class="px-2 py-1 pb-2 mb-1 mb-2 text-sm border-b border-line-default border-solid"
            >
              {{ $t('general.sort_by') }}
            </div>

            <BaseDropdownItem class="flex px-1 py-2 cursor-pointer">
              <BaseInputGroup class="-mt-3 font-normal">
                <BaseRadio
                  id="filter_invoice_date"
                  v-model="searchData.orderByField"
                  :label="$t('invoices.invoice_date')"
                  name="filter"
                  size="sm"
                  value="invoice_date"
                  @update:model-value="onSearchDebounced"
                />
              </BaseInputGroup>
            </BaseDropdownItem>

            <BaseDropdownItem class="flex px-1 py-2 cursor-pointer">
              <BaseInputGroup class="-mt-3 font-normal">
                <BaseRadio
                  id="filter_due_date"
                  v-model="searchData.orderByField"
                  :label="$t('invoices.due_date')"
                  value="due_date"
                  size="sm"
                  name="filter"
                  @update:model-value="onSearchDebounced"
                />
              </BaseInputGroup>
            </BaseDropdownItem>

            <BaseDropdownItem class="flex px-1 py-2 cursor-pointer">
              <BaseInputGroup class="-mt-3 font-normal">
                <BaseRadio
                  id="filter_invoice_number"
                  v-model="searchData.orderByField"
                  :label="$t('invoices.invoice_number')"
                  value="invoice_number"
                  size="sm"
                  name="filter"
                  @update:model-value="onSearchDebounced"
                />
              </BaseInputGroup>
            </BaseDropdownItem>
          </BaseDropdown>

          <BaseButton class="ml-1" size="md" variant="gray" @click="sortData">
            <BaseIcon v-if="isAscending" name="BarsArrowUpIcon" />
            <BaseIcon v-else name="BarsArrowDownIcon" />
          </BaseButton>
        </div>
      </div>

      <div
        class="h-full overflow-y-scroll border-l border-line-default border-solid base-scroll"
      >
        <router-link
          v-for="(inv, index) in store.invoices"
          :id="'invoice-' + inv.id"
          :key="index"
          :to="`/${store.companySlug}/customer/invoices/${inv.id}/view`"
          :class="[
            'flex justify-between side-invoice p-4 cursor-pointer hover:bg-hover-strong items-center border-l-4 border-l-transparent',
            {
              'bg-surface-tertiary border-l-4 border-l-primary-500 border-solid':
                hasActiveUrl(inv.id),
            },
          ]"
          style="border-bottom: 1px solid rgba(185, 193, 209, 0.41)"
        >
          <div class="flex-2">
            <BaseText
              :text="inv.customer?.name ?? ''"
              class="pr-2 mb-2 text-sm not-italic font-normal leading-5 text-heading capitalize truncate"
            />
            <div
              class="mt-1 mb-2 text-xs not-italic font-medium leading-5 text-body"
            >
              {{ inv.invoice_number }}
            </div>
            <BaseInvoiceStatusBadge
              :status="inv.status"
              class="px-1 text-xs"
            >
              <BaseInvoiceStatusLabel :status="inv.status" />
            </BaseInvoiceStatusBadge>
          </div>

          <div class="flex-1 whitespace-nowrap right">
            <BaseFormatMoney
              class="mb-2 text-xl not-italic font-semibold leading-8 text-right text-heading block"
              :amount="inv.total"
              :currency="inv.currency"
            />
            <div
              class="text-sm not-italic font-normal leading-5 text-right text-body est-date"
            >
              {{ inv.formatted_invoice_date }}
            </div>
          </div>
        </router-link>

        <p
          v-if="!store.invoices.length"
          class="flex justify-center px-4 mt-5 text-sm text-body"
        >
          {{ $t('invoices.no_matching_invoices') }}
        </p>
      </div>
    </div>

    <!-- PDF Preview -->
    <BasePdfPreview :src="shareableLink" />
  </BasePage>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useDebounceFn } from '@vueuse/core'
import { useCustomerPortalStore } from '../store'

const store = useCustomerPortalStore()
const route = useRoute()
const router = useRouter()

const searchData = reactive<{
  orderBy: string
  orderByField: string
  invoice_number: string
}>({
  orderBy: '',
  orderByField: '',
  invoice_number: '',
})

const pageTitle = computed<string>(() => {
  return store.selectedViewInvoice?.invoice_number ?? ''
})

const isAscending = computed<boolean>(() => {
  return searchData.orderBy === 'asc' || !searchData.orderBy
})

const shareableLink = computed<string | false>(() => {
  return store.selectedViewInvoice?.unique_hash
    ? `/invoices/pdf/${store.selectedViewInvoice.unique_hash}`
    : false
})

const downloadLink = computed<string>(() => {
  return `/invoices/pdf/${store.selectedViewInvoice?.unique_hash ?? ''}`
})

const canPay = computed<boolean>(() => {
  return (
    store.selectedViewInvoice?.paid_status !== 'PAID' &&
    store.enabledModules.includes('Payments')
  )
})

watch(() => route.params.id, () => {
  loadInvoice()
})

onMounted(() => {
  loadInvoices()
  loadInvoice()
})

function hasActiveUrl(id: number): boolean {
  return Number(route.params.id) === id
}

async function loadInvoices(): Promise<void> {
  await store.fetchInvoices({ limit: 'all' })
  setTimeout(() => scrollToInvoice(), 500)
}

async function loadInvoice(): Promise<void> {
  const id = route.params.id
  if (!id) return
  await store.fetchViewInvoice(id as string)
}

function scrollToInvoice(): void {
  const el = document.getElementById(`invoice-${route.params.id}`)
  if (el) {
    el.scrollIntoView({ behavior: 'smooth' })
    el.classList.add('shake')
  }
}

async function onSearch(): Promise<void> {
  const params: Record<string, string> = {}
  if (searchData.invoice_number) params.invoice_number = searchData.invoice_number
  if (searchData.orderBy) params.orderBy = searchData.orderBy
  if (searchData.orderByField) params.orderByField = searchData.orderByField
  await store.searchInvoices(params)
}

const onSearchDebounced = useDebounceFn(onSearch, 500)

function sortData(): void {
  searchData.orderBy = searchData.orderBy === 'asc' ? 'desc' : 'asc'
  onSearch()
}

function payInvoice(): void {
  if (!store.selectedViewInvoice) return
  router.push({
    name: 'invoice.portal.payment',
    params: {
      id: String(store.selectedViewInvoice.id),
      company: (store.selectedViewInvoice.company as { slug: string } | undefined)?.slug ?? store.companySlug,
    },
  })
}
</script>
