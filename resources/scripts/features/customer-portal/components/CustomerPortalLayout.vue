<template>
  <div v-if="isAppLoaded" class="h-full">
    <NotificationRoot />

    <!-- Header (matches Regular Portal SiteHeader) -->
    <CustomerPortalHeader @toggle-sidebar="sidebarOpen = true" />

    <!-- MOBILE SIDEBAR (Headless UI Dialog, same pattern as admin) -->
    <TransitionRoot as="template" :show="sidebarOpen">
      <Dialog as="div" class="fixed inset-0 z-40 flex md:hidden" @close="sidebarOpen = false">
        <TransitionChild
          as="template"
          enter="transition-opacity ease-linear duration-300"
          enter-from="opacity-0"
          enter-to="opacity-100"
          leave="transition-opacity ease-linear duration-300"
          leave-from="opacity-100"
          leave-to="opacity-0"
        >
          <DialogOverlay class="fixed inset-0 bg-surface-muted/75" />
        </TransitionChild>

        <TransitionChild
          as="template"
          enter="transition ease-in-out duration-300"
          enter-from="-translate-x-full"
          enter-to="translate-x-0"
          leave="transition ease-in-out duration-300"
          leave-from="translate-x-0"
          leave-to="-translate-x-full"
        >
          <div class="relative flex flex-col flex-1 w-full max-w-xs bg-surface">
            <div class="absolute top-0 right-0 pt-2 -mr-12">
              <button
                class="flex items-center justify-center w-10 h-10 ml-1 rounded-full"
                @click="sidebarOpen = false"
              >
                <BaseIcon name="XMarkIcon" class="w-6 h-6 text-white" />
              </button>
            </div>

            <!-- Mobile sidebar content -->
            <div class="flex-1 h-0 pt-5 pb-4 overflow-y-auto">
              <div class="flex items-center shrink-0 px-4 mb-6 gap-2">
                <div class="flex items-center justify-center w-9 h-9 rounded-lg bg-primary-500 text-white">
                  <BaseIcon name="TruckIcon" class="w-5 h-5" />
                </div>
                <div>
                  <div class="text-base font-bold text-heading">SSGLS</div>
                  <div class="text-xs text-muted">Transport Portal</div>
                </div>
              </div>

              <nav class="mt-4 space-y-1">
                <router-link
                  v-for="item in menuItems"
                  :key="item.link"
                  :to="buildPath(item.link)"
                  :class="[
                    isActive(item.link)
                      ? 'text-primary-600 bg-primary-50 font-semibold'
                      : 'text-body hover:bg-hover',
                    'cursor-pointer mx-3 px-3 py-2.5 flex items-center rounded-lg text-sm font-medium transition-colors',
                  ]"
                  @click="sidebarOpen = false"
                >
                  <BaseIcon
                    :name="item.icon"
                    :class="[
                      isActive(item.link) ? 'text-primary-500' : 'text-subtle',
                      'mr-3 shrink-0 h-5 w-5',
                    ]"
                  />
                  {{ item.title }}
                </router-link>
              </nav>
            </div>
          </div>
        </TransitionChild>

        <div class="shrink-0 w-14" />
      </Dialog>
    </TransitionRoot>

    <!-- DESKTOP SIDEBAR (same pattern as admin SiteSidebar) -->
    <div
      class="hidden h-screen pb-0 overflow-y-auto overflow-x-hidden bg-surface border-r border-line-default md:fixed md:flex md:flex-col md:inset-y-0 pt-16 w-56 xl:w-64 transition-all duration-300"
    >
      <div class="p-0 m-0 mt-4 list-none">
        <router-link
          v-for="item in menuItems"
          :key="item.link"
          :to="buildPath(item.link)"
          :class="[
            isActive(item.link)
              ? 'text-primary-600 bg-primary-50 font-semibold'
              : 'text-body hover:bg-hover',
            'cursor-pointer mx-3 px-3 py-2.5 group flex items-center rounded-lg text-sm font-medium transition-colors',
          ]"
        >
          <BaseIcon
            :name="item.icon"
            :class="[
              isActive(item.link) ? 'text-primary-500' : 'text-subtle group-hover:text-body',
              'mr-3 shrink-0 h-5 w-5',
            ]"
          />
          <span class="whitespace-nowrap">{{ item.title }}</span>
        </router-link>
      </div>
    </div>

    <!-- MAIN CONTENT (same padding pattern as admin: md:pl-56 xl:pl-64) -->
    <main
      class="h-screen overflow-y-auto min-h-0 transition-all duration-300 md:pl-56 xl:pl-64"
    >
      <div class="pt-16 pb-16 px-4 md:px-8">
        <router-view />
      </div>

    </main>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  Dialog,
  DialogOverlay,
  TransitionChild,
  TransitionRoot,
} from '@headlessui/vue'
import { useCustomerPortalStore } from '../store'
import { resolveCompanySlug, buildCustomerPortalPath } from '../utils/routes'
import NotificationRoot from '@/scripts/components/notifications/NotificationRoot.vue'
import CustomerPortalHeader from './CustomerPortalHeader.vue'

const store = useCustomerPortalStore()
const route = useRoute()
const router = useRouter()

const sidebarOpen = ref(false)

const isAppLoaded = computed<boolean>(() => store.isAppLoaded)

// Flat menu definition (no group labels — same style as the admin portal sidebar)
const menuItems = computed(() => [
  { title: 'Dashboard', link: 'dashboard', icon: 'ChartBarSquareIcon' },
  { title: 'Track Consignment', link: 'track', icon: 'MagnifyingGlassIcon' },
  { title: 'LR Receipts', link: 'lr-receipts', icon: 'DocumentTextIcon' },
  { title: 'Invoices', link: 'invoices', icon: 'CreditCardIcon' },
  { title: 'Quotation', link: 'estimates', icon: 'ClipboardDocumentListIcon' },
  { title: 'Payments', link: 'payments', icon: 'BanknotesIcon' },
  { title: 'Reports', link: 'reports', icon: 'ChartPieIcon' },
  { title: 'Settings', link: 'settings', icon: 'Cog6ToothIcon' },
])

const currentPageTitle = computed<string>(() => {
  const matched = route.name?.toString() || ''
  if (matched.includes('dashboard')) return 'Dashboard'
  if (matched.includes('track')) return 'Track'
  if (matched.includes('lr-receipts')) return 'LR Receipts'
  if (matched.includes('lorry-receipts')) return 'Lorry Receipts'
  if (matched.includes('office-invoices')) return 'Office Invoices'
  if (matched.includes('warehouse')) return 'Warehouse'
  if (matched.includes('load-trips')) return 'Load Trips'
  if (matched.includes('invoice')) return 'Invoices'
  if (matched.includes('estimate')) return 'Estimates'
  if (matched.includes('payment')) return 'Payments'
  if (matched.includes('reports')) return 'Reports'
  if (matched.includes('settings')) return 'Settings'
  return 'Portal'
})

function buildPath(page: string): string {
  return buildCustomerPortalPath(store.companySlug, page)
}

function isActive(page: string): boolean {
  const pagePath = buildPath(page)
  if (page === 'dashboard') {
    return route.path === pagePath
  }
  return route.path.startsWith(pagePath)
}

watch(
  () => route.params.company,
  async (companyParam) => {
    const companySlug = resolveCompanySlug(companyParam)
    if (!companySlug) return
    if (store.isAppLoaded && store.companySlug === companySlug) return
    try {
      await store.bootstrap(companySlug)
    } catch {
      await router.push({
        name: 'customer-portal.login',
        params: { company: companySlug },
      })
    }
  },
  { immediate: true },
)
</script>
