<template>
  <BasePage v-if="store.selectedViewPayment" class="xl:pl-96 xl:ml-8">
    <BasePageHeader :title="pageTitle">
      <template #actions>
        <BaseButton
          variant="primary-outline"
          tag="a"
          download
          :href="downloadLink"
        >
          <template #left="slotProps">
            <BaseIcon name="DownloadIcon" :class="slotProps.class" />
            {{ $t('general.download') }}
          </template>
        </BaseButton>
      </template>
    </BasePageHeader>

    <!-- Secondary Sidebar: Payment List -->
    <div
      class="fixed top-0 left-0 hidden h-full pt-16 pb-[6.4rem] ml-56 bg-surface xl:ml-64 w-88 xl:block"
    >
      <div
        class="flex items-center justify-between px-4 pt-8 pb-2 border border-line-default border-solid height-full"
      >
        <div class="mb-6">
          <BaseInput
            v-model="searchData.payment_number"
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
                  id="filter_invoice_number"
                  v-model="searchData.orderByField"
                  :label="$t('invoices.title')"
                  name="filter"
                  size="sm"
                  value="invoice_number"
                  @update:model-value="onSearchDebounced"
                />
              </BaseInputGroup>
            </BaseDropdownItem>

            <BaseDropdownItem class="flex px-1 py-2 cursor-pointer">
              <BaseInputGroup class="-mt-3 font-normal">
                <BaseRadio
                  id="filter_payment_date"
                  v-model="searchData.orderByField"
                  :label="$t('payments.date')"
                  value="payment_date"
                  size="sm"
                  name="filter"
                  @update:model-value="onSearchDebounced"
                />
              </BaseInputGroup>
            </BaseDropdownItem>

            <BaseDropdownItem class="flex px-1 py-2 cursor-pointer">
              <BaseInputGroup class="-mt-3 font-normal">
                <BaseRadio
                  id="filter_payment_number"
                  v-model="searchData.orderByField"
                  :label="$t('payments.payment_number')"
                  value="payment_number"
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
          v-for="(pmt, index) in store.payments"
          :id="'payment-' + pmt.id"
          :key="index"
          :to="`/${store.companySlug}/customer/payments/${pmt.id}/view`"
          :class="[
            'flex justify-between side-payment p-4 cursor-pointer hover:bg-hover-strong items-center border-l-4 border-l-transparent',
            {
              'bg-surface-tertiary border-l-4 border-l-primary-500 border-solid':
                hasActiveUrl(pmt.id),
            },
          ]"
          style="border-bottom: 1px solid rgba(185, 193, 209, 0.41)"
        >
          <div class="flex-2">
            <BaseText
              :text="pmt.payment_number"
              class="pr-2 mb-2 text-sm not-italic font-medium leading-5 text-heading capitalize truncate"
            />
            <div
              class="mt-1 mb-2 text-xs not-italic font-normal leading-5 text-body"
            >
              {{ pmt.formatted_payment_date }}
            </div>
          </div>

          <div class="flex-1 whitespace-nowrap right">
            <BaseFormatMoney
              class="mb-2 text-xl not-italic font-semibold leading-8 text-right text-heading block"
              :amount="pmt.amount"
              :currency="pmt.currency"
            />
          </div>
        </router-link>

        <p
          v-if="!store.payments.length"
          class="flex justify-center px-4 mt-5 text-sm text-body"
        >
          {{ $t('payments.no_matching_payments') }}
        </p>
      </div>
    </div>

    <!-- PDF Preview -->
    <BasePdfPreview :src="shareableLink" />
  </BasePage>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useDebounceFn } from '@vueuse/core'
import { useCustomerPortalStore } from '../store'
import type { Payment } from '../../../types/domain/payment'

const store = useCustomerPortalStore()
const route = useRoute()

const payment = ref<Partial<Payment>>({})

const searchData = reactive<{
  orderBy: string
  orderByField: string
  payment_number: string
}>({
  orderBy: '',
  orderByField: '',
  payment_number: '',
})

const pageTitle = computed<string>(() => {
  return store.selectedViewPayment?.payment_number ?? ''
})

const isAscending = computed<boolean>(() => {
  return searchData.orderBy === 'asc' || !searchData.orderBy
})

const shareableLink = computed<string | false>(() => {
  return payment.value.unique_hash
    ? `/payments/pdf/${payment.value.unique_hash}`
    : false
})

const downloadLink = computed<string>(() => {
  return `/payments/pdf/${payment.value.unique_hash ?? ''}`
})

watch(() => route.params.id, () => {
  loadPayment()
})

onMounted(() => {
  loadPayments()
  loadPayment()
})

function hasActiveUrl(id: number): boolean {
  return Number(route.params.id) === id
}

async function loadPayments(): Promise<void> {
  await store.fetchPayments({ limit: 'all' })
  setTimeout(() => scrollToPayment(), 500)
}

async function loadPayment(): Promise<void> {
  const id = route.params.id
  if (!id) return
  const response = await store.fetchViewPayment(id as string)
  if (response.data?.data) {
    payment.value = response.data.data
  }
}

function scrollToPayment(): void {
  const el = document.getElementById(`payment-${route.params.id}`)
  if (el) {
    el.scrollIntoView({ behavior: 'smooth' })
    el.classList.add('shake')
  }
}

async function onSearch(): Promise<void> {
  const params: Record<string, string> = {}
  if (searchData.payment_number) params.payment_number = searchData.payment_number
  if (searchData.orderBy) params.orderBy = searchData.orderBy
  if (searchData.orderByField) params.orderByField = searchData.orderByField
  await store.searchPayments(params)
}

const onSearchDebounced = useDebounceFn(onSearch, 500)

function sortData(): void {
  searchData.orderBy = searchData.orderBy === 'asc' ? 'desc' : 'asc'
  onSearch()
}
</script>
