<template>
  <header
    class="
      fixed top-0 left-0 z-20 flex items-center justify-between w-full
      px-4 py-3 md:h-16 md:px-8 bg-linear-to-r from-header-from to-header-to
    "
  >
    <!-- Left: Logo + mobile hamburger -->
    <div class="flex items-center gap-3">
      <!-- Mobile sidebar toggle -->
      <button
        class="flex items-center justify-center w-8 h-8 text-white bg-white/20 rounded-lg hover:bg-white/30 md:hidden"
        @click="$emit('toggle-sidebar')"
      >
        <BaseIcon name="Bars3Icon" class="w-5 h-5 text-white" />
      </button>

      <router-link :to="dashboardPath" class="shrink-0">
        <MainLogo
          v-if="!customerLogo"
          class="h-6 w-auto text-primary-500"
          light-color="white"
          dark-color="white"
        />
        <img v-else :src="customerLogo" class="h-6 w-auto" />
      </router-link>
    </div>

    <!-- Right: User dropdown -->
    <ul class="flex items-center gap-2 m-0 list-none">
      <li class="relative block float-left">
        <BaseDropdown width-class="w-48">
          <template #activator>
            <img
              :src="previewAvatar"
              class="block w-8 h-8 rounded-full ring-2 ring-white/30 md:h-9 md:w-9 object-cover cursor-pointer"
            />
          </template>

          <!-- Theme Toggle -->
          <div class="px-3 py-2">
            <div class="flex items-center justify-between rounded-lg bg-surface-secondary p-1">
              <button
                v-for="opt in themeOptions"
                :key="opt.value"
                :class="[
                  'flex items-center justify-center rounded-md px-2.5 py-1.5 text-xs font-medium transition-colors',
                  currentTheme === opt.value
                    ? 'bg-surface text-heading shadow-sm'
                    : 'text-muted hover:text-body',
                ]"
                @click.stop="setTheme(opt.value)"
              >
                <BaseIcon :name="opt.icon" class="w-3.5 h-3.5" />
              </button>
            </div>
          </div>

          <router-link :to="settingsPath">
            <BaseDropdownItem>
              <BaseIcon
                name="CogIcon"
                class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
                aria-hidden="true"
              />
              {{ $t('navigation.settings') }}
            </BaseDropdownItem>
          </router-link>

          <div class="my-1 border-t border-line-light" />

          <BaseDropdownItem @click="logout">
            <BaseIcon
              name="ArrowRightOnRectangleIcon"
              class="w-5 h-5 mr-3 text-alert-error-text"
              aria-hidden="true"
            />
            <span class="text-alert-error-text">{{ $t('navigation.logout') }}</span>
          </BaseDropdownItem>
        </BaseDropdown>
      </li>
    </ul>
  </header>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useCustomerPortalStore } from '../store'
import { buildCustomerPortalPath } from '../utils/routes'
import { useTheme } from '@/scripts/composables/use-theme'
import { THEME } from '@/scripts/config/constants'
import type { Theme } from '@/scripts/config/constants'
import MainLogo from '@/scripts/components/icons/MainLogo.vue'

defineEmits<{
  'toggle-sidebar': []
}>()

interface ThemeOption {
  value: Theme
  icon: string
}

const store = useCustomerPortalStore()
const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const { currentTheme, setTheme } = useTheme()

const customerLogo = computed<string | false>(() => {
  return window.customer_logo || false
})

const dashboardPath = computed<string>(() => {
  return buildCustomerPortalPath(store.companySlug, 'dashboard')
})

const settingsPath = computed<string>(() => {
  return buildCustomerPortalPath(store.companySlug, 'settings')
})

const previewAvatar = computed<string>(() => {
  if (typeof store.currentUser?.avatar === 'string' && store.currentUser.avatar) {
    return store.currentUser.avatar
  }
  return getDefaultAvatar()
})

function getDefaultAvatar(): string {
  const imageUrl = new URL('$images/default-avatar.jpg', import.meta.url)
  return imageUrl.href
}

async function logout(): Promise<void> {
  const companySlug = store.companySlug
  await store.logout()
  await router.push({
    name: 'customer-portal.login',
    params: { company: companySlug },
  })
}

const themeOptions: ThemeOption[] = [
  { value: THEME.LIGHT, icon: 'SunIcon' },
  { value: THEME.DARK, icon: 'MoonIcon' },
  { value: THEME.SYSTEM, icon: 'ComputerDesktopIcon' },
]
</script>
