<template>
  <BasePage v-if="receipt" class="xl:pl-96 xl:ml-8">
    <BasePageHeader :title="receipt.invoice_number">
      <template #default>
        <BaseBreadcrumb>
          <BaseBreadcrumbItem title="Home" to="customer-portal.dashboard" />
          <BaseBreadcrumbItem title="LR Receipts" to="customer-portal.lr-receipts" />
          <BaseBreadcrumbItem :title="receipt.invoice_number" to="#" active />
        </BaseBreadcrumb>
      </template>

      <template #actions>
        <BaseButton variant="primary-outline" @click="downloadPdf">
          <template #left="slotProps">
            <BaseIcon name="ArrowDownTrayIcon" :class="slotProps.class" />
          </template>
          Download PDF
        </BaseButton>
      </template>
    </BasePageHeader>

    <!-- Sidebar: scrollable LR Receipt list (XL only, same pattern as admin InvoiceDetailView) -->
    <div
      class="fixed top-0 left-0 hidden h-full pt-16 pb-[6.4rem] ml-56 bg-surface xl:ml-64 w-88 xl:block"
    >
      <div
        class="flex items-center justify-between px-4 pt-8 pb-2 border border-line-default border-solid height-full"
      >
        <div class="mb-6">
          <BaseInput
            v-model="searchData.searchText"
            placeholder="Search"
            type="text"
            variant="gray"
            @input="onSearched()"
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
              Sort By
            </div>

            <BaseDropdownItem class="flex px-1 py-2 cursor-pointer">
              <BaseInputGroup class="-mt-3 font-normal">
                <BaseRadio
                  id="filter_invoice_date"
                  v-model="searchData.orderByField"
                  label="Date"
                  size="sm"
                  name="filter"
                  value="invoice_date"
                  @update:model-value="onSearched"
                />
              </BaseInputGroup>
            </BaseDropdownItem>

            <BaseDropdownItem class="flex px-1 py-2 cursor-pointer">
              <BaseInputGroup class="-mt-3 font-normal">
                <BaseRadio
                  id="filter_invoice_number"
                  v-model="searchData.orderByField"
                  label="Docket Number"
                  value="invoice_number"
                  size="sm"
                  name="filter"
                  @update:model-value="onSearched"
                />
              </BaseInputGroup>
            </BaseDropdownItem>
          </BaseDropdown>

          <BaseButton class="ml-1" size="md" variant="gray" @click="sortData">
            <BaseIcon v-if="getOrderBy" name="BarsArrowUpIcon" />
            <BaseIcon v-else name="BarsArrowDownIcon" />
          </BaseButton>
        </div>
      </div>

      <div
        ref="receiptListSection"
        class="h-full overflow-y-scroll border-l border-line-default border-solid base-scroll"
      >
        <div v-for="(item, index) in receiptList" :key="index">
          <router-link
            v-if="item"
            :id="'receipt-' + item.id"
            :to="buildPath(`lr-receipts/${item.id}/view`)"
            :class="[
              'flex justify-between side-invoice p-4 cursor-pointer hover:bg-hover-strong items-center border-l-4 border-l-transparent',
              {
                'bg-surface-tertiary border-l-4 border-l-primary-500 border-solid':
                  hasActiveUrl(item.id),
              },
            ]"
            style="border-bottom: 1px solid rgba(185, 193, 209, 0.41)"
          >
            <div class="flex-2">
              <BaseText
                :text="item.from_name && item.to_name ? `${item.from_name} → ${item.to_name}` : (item.customer?.name ?? '')"
                class="pr-2 mb-2 text-sm not-italic font-normal leading-5 text-heading capitalize truncate"
              />
              <div
                class="mt-1 mb-2 text-xs not-italic font-medium leading-5 text-body"
              >
                {{ item.invoice_number }}
              </div>
              <BaseInvoiceStatusBadge
                :status="item.status"
                class="px-1 text-xs"
              >
                <BaseInvoiceStatusLabel :status="item.status" />
              </BaseInvoiceStatusBadge>
            </div>

            <div class="flex-1 whitespace-nowrap right">
              <BaseFormatMoney
                class="mb-2 text-xl not-italic font-semibold leading-8 text-right text-heading block"
                :amount="item.total"
                :currency="item.customer?.currency"
              />
              <div
                class="text-sm not-italic font-normal leading-5 text-right text-body est-date"
              >
                {{ item.formatted_invoice_date || formatDate(item.invoice_date) }}
              </div>
            </div>
          </router-link>
        </div>

        <div v-if="isLoading" class="flex justify-center p-4 items-center">
          <LoadingIcon class="h-6 m-1 animate-spin text-primary-400" />
        </div>
        <p
          v-if="!receiptList?.length && !isLoading"
          class="flex justify-center px-4 mt-5 text-sm text-body"
        >
          No matching receipts
        </p>
      </div>
    </div>

    <!-- PDF Preview (same as Regular Portal InvoiceDetailView) -->
    <BasePdfPreview :src="shareableLink" />
  </BasePage>

  <div v-else-if="loading" class="flex items-center justify-center pt-20">
    <LoadingIcon class="h-8 animate-spin text-primary-400" />
  </div>

  <BaseEmptyPlaceholder
    v-else
    title="LR Receipt not found"
    description="The requested LR receipt could not be found."
  />
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { client } from '@/scripts/api/client'
import { buildCustomerPortalPath } from '../utils/routes'
import { useCustomerPortalStore } from '../store'
import LoadingIcon from '@/scripts/components/icons/LoadingIcon.vue'

const route = useRoute()
const store = useCustomerPortalStore()

const receipt = ref<any>(null)
const loading = ref(true)

// Sidebar list state (mirrors admin InvoiceDetailView pattern)
const receiptList = ref<any[] | null>(null)
const isLoading = ref<boolean>(false)
const currentPageNumber = ref<number>(1)
const lastPageNumber = ref<number>(1)
const receiptListSection = ref<HTMLElement | null>(null)

interface SearchData {
  orderBy: string | null
  orderByField: string | null
  searchText: string | null
}

const searchData = reactive<SearchData>({
  orderBy: null,
  orderByField: null,
  searchText: null,
})

const getOrderBy = computed<boolean>(() => {
  return searchData.orderBy === 'asc' || searchData.orderBy === null
})

const shareableLink = computed<string>(() => {
  return `/invoices/pdf/${receipt.value?.unique_hash ?? ''}`
})

function buildPath(page: string): string {
  return buildCustomerPortalPath(store.companySlug, page)
}

function hasActiveUrl(id: number): boolean {
  return Number(route.params.id) === id
}

function formatDate(date?: string): string {
  if (!date) return '—'
  return new Date(date).toLocaleDateString('en-IN', { day: '2-digit', month: 'short' })
}

async function fetchReceipt() {
  loading.value = true
  try {
    const companySlug = route.params.company as string
    const { data } = await client.get(`/api/v1/${companySlug}/customer/lr-receipts/${route.params.id}`)
    receipt.value = data.data || data
  } catch (e) {
    console.error('Failed to fetch LR receipt:', e)
  } finally {
    loading.value = false
  }
}

async function loadReceiptList(pageNumber?: number, fromScrollListener = false): Promise<void> {
  if (isLoading.value) return

  const companySlug = route.params.company as string
  const params: Record<string, unknown> = { limit: 20 }

  if (searchData.searchText) {
    params.search = searchData.searchText
  }
  if (searchData.orderBy != null) {
    params.orderBy = searchData.orderBy
  }
  if (searchData.orderByField != null) {
    params.orderByField = searchData.orderByField
  }

  isLoading.value = true
  const { data } = await client.get(`/api/v1/${companySlug}/customer/lr-receipts`, {
    params: { ...params, page: pageNumber },
  })
  isLoading.value = false

  receiptList.value = receiptList.value ?? []
  receiptList.value = [...receiptList.value, ...data.data]

  currentPageNumber.value = pageNumber ?? 1
  lastPageNumber.value = data.meta?.last_page || 1

  const receiptFound = receiptList.value.find(
    (r) => r.id === Number(route.params.id),
  )

  const hasUserFilters =
    Object.keys(params).filter((k) => k !== 'limit').length > 0

  if (
    !fromScrollListener &&
    !receiptFound &&
    currentPageNumber.value < lastPageNumber.value &&
    !hasUserFilters
  ) {
    loadReceiptList(++currentPageNumber.value)
  }

  if (receiptFound && !fromScrollListener) {
    setTimeout(() => scrollToReceipt(), 500)
  }
}

function scrollToReceipt(): void {
  const el = document.getElementById(`receipt-${route.params.id}`)
  if (el) {
    el.scrollIntoView({ behavior: 'smooth' })
    el.classList.add('shake')
    addScrollListener()
  }
}

function addScrollListener(): void {
  receiptListSection.value?.addEventListener('scroll', (ev) => {
    const target = ev.target as HTMLElement
    if (
      target.scrollTop > 0 &&
      target.scrollTop + target.clientHeight > target.scrollHeight - 200
    ) {
      if (currentPageNumber.value < lastPageNumber.value) {
        loadReceiptList(++currentPageNumber.value, true)
      }
    }
  })
}

let searchTimeout: ReturnType<typeof setTimeout> | null = null

function onSearched(): void {
  if (searchTimeout) clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    receiptList.value = []
    loadReceiptList()
  }, 500)
}

function sortData(): void {
  if (searchData.orderBy === 'asc') {
    searchData.orderBy = 'desc'
  } else {
    searchData.orderBy = 'asc'
  }
  onSearched()
}

function downloadPdf() {
  if (receipt.value?.unique_hash) {
    window.open(`/invoices/pdf/${receipt.value.unique_hash}`, '_blank')
  }
}

// Watch route changes to reload when navigating between receipts via sidebar
watch(route, (to) => {
  if (to.name === 'customer-portal.lr-receipts.view') {
    fetchReceipt().then(() => {
      receiptList.value = []
      loadReceiptList()
    })
  }
})

onMounted(() => {
  fetchReceipt().then(() => {
    loadReceiptList()
  })
})
</script>
