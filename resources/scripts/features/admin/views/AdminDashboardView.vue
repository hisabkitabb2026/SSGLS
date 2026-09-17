<template>
  <BasePage>
    <BasePageHeader :title="$t('navigation.dashboard')">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem
          :title="$t('navigation.administration')"
          to="/admin/administration/dashboard"
          active
        />
      </BaseBreadcrumb>
    </BasePageHeader>

    <!-- ── Loading state ────────────────────────────────────────── -->
    <div v-if="isLoading" class="flex justify-center py-16">
      <BaseGlobalLoader />
    </div>

    <template v-else>
      <!-- ── System Health KPIs ──────────────────────────────────── -->
      <div class="grid grid-cols-1 gap-4 mt-6 sm:grid-cols-2 lg:grid-cols-4 xl:gap-5">
        <!-- App Version -->
        <div class="bg-surface rounded-xl border border-line-light p-5 shadow-sm">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-xs font-medium text-muted">App Version</p>
              <p class="mt-1 text-2xl font-bold text-heading">{{ data.app_version }}</p>
            </div>
            <div class="flex items-center justify-center w-11 h-11 rounded-xl bg-primary-50">
              <BaseIcon name="ServerIcon" class="w-5 h-5 text-primary-500" />
            </div>
          </div>
        </div>

        <!-- PHP Version -->
        <div class="bg-surface rounded-xl border border-line-light p-5 shadow-sm">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-xs font-medium text-muted">PHP</p>
              <p class="mt-1 text-2xl font-bold text-heading">{{ data.php_version }}</p>
            </div>
            <div class="flex items-center justify-center w-11 h-11 rounded-xl bg-status-purple/10">
              <BaseIcon name="CodeBracketIcon" class="w-5 h-5 text-status-purple" />
            </div>
          </div>
        </div>

        <!-- Database -->
        <div class="bg-surface rounded-xl border border-line-light p-5 shadow-sm">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-xs font-medium text-muted">Database</p>
              <p class="mt-1 text-2xl font-bold text-heading">{{ data.database?.driver?.toUpperCase() }}</p>
              <p class="text-xs text-subtle mt-0.5">{{ data.database?.version }}</p>
            </div>
            <div class="flex items-center justify-center w-11 h-11 rounded-xl bg-status-blue/10">
              <BaseIcon name="CircleStackIcon" class="w-5 h-5 text-status-blue" />
            </div>
          </div>
        </div>

        <!-- System Status -->
        <div class="bg-surface rounded-xl border border-line-light p-5 shadow-sm">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-xs font-medium text-muted">System Status</p>
              <p class="mt-1 text-2xl font-bold text-status-green">Healthy</p>
            </div>
            <div class="flex items-center justify-center w-11 h-11 rounded-xl bg-status-green/10">
              <BaseIcon name="CheckCircleIcon" class="w-5 h-5 text-status-green" />
            </div>
          </div>
        </div>
      </div>

      <!-- ── Companies & Users cards ─────────────────────────────── -->
      <div class="grid grid-cols-1 gap-6 mt-6 lg:grid-cols-2">
        <!-- Companies -->
        <BaseCard container-class="p-5">
          <div class="mb-4 flex items-center justify-between">
            <h4 class="flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-muted">
              <BaseIcon name="BuildingOfficeIcon" class="h-4 w-4" />
              Companies
            </h4>
            <BaseButton size="xs" variant="primary-outline" @click="$router.push('/admin/administration/companies')">
              Manage
            </BaseButton>
          </div>

          <div class="flex items-center gap-4">
            <div class="flex items-center justify-center w-16 h-16 rounded-2xl bg-primary-50">
              <span class="text-3xl font-bold text-primary-500">{{ data.counts?.companies }}</span>
            </div>
            <div>
              <p class="text-sm font-medium text-heading">Registered Companies</p>
              <p class="text-xs text-muted mt-0.5">Active tenants on the platform</p>
            </div>
          </div>
        </BaseCard>

        <!-- Users -->
        <BaseCard container-class="p-5">
          <div class="mb-4 flex items-center justify-between">
            <h4 class="flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-muted">
              <BaseIcon name="UsersIcon" class="h-4 w-4" />
              Users
            </h4>
            <BaseButton size="xs" variant="primary-outline" @click="$router.push('/admin/administration/users')">
              Manage
            </BaseButton>
          </div>

          <div class="flex items-center gap-4">
            <div class="flex items-center justify-center w-16 h-16 rounded-2xl bg-status-blue/10">
              <span class="text-3xl font-bold text-status-blue">{{ data.counts?.users }}</span>
            </div>
            <div>
              <p class="text-sm font-medium text-heading">Total Users</p>
              <p class="text-xs text-muted mt-0.5">Across all companies</p>
            </div>
          </div>
        </BaseCard>
      </div>

      <!-- ── Quick Links ─────────────────────────────────────────── -->
      <div class="mt-6">
        <h3 class="mb-4 text-lg font-bold text-heading">Quick Actions</h3>
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
          <router-link
            to="/admin/administration/companies"
            class="flex flex-col items-center gap-2 p-5 bg-surface rounded-xl border border-line-light hover:border-line-default hover:shadow-md transition-all"
          >
            <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-primary-50">
              <BaseIcon name="BuildingOffice2Icon" class="w-5 h-5 text-primary-500" />
            </div>
            <span class="text-sm font-medium text-heading">Companies</span>
          </router-link>

          <router-link
            to="/admin/administration/users"
            class="flex flex-col items-center gap-2 p-5 bg-surface rounded-xl border border-line-light hover:border-line-default hover:shadow-md transition-all"
          >
            <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-status-blue/10">
              <BaseIcon name="UserGroupIcon" class="w-5 h-5 text-status-blue" />
            </div>
            <span class="text-sm font-medium text-heading">Users</span>
          </router-link>

          <router-link
            to="/admin/administration/settings"
            class="flex flex-col items-center gap-2 p-5 bg-surface rounded-xl border border-line-light hover:border-line-default hover:shadow-md transition-all"
          >
            <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-status-green/10">
              <BaseIcon name="Cog6ToothIcon" class="w-5 h-5 text-status-green" />
            </div>
            <span class="text-sm font-medium text-heading">Settings</span>
          </router-link>

          <router-link
            to="/admin/administration/settings/backup"
            class="flex flex-col items-center gap-2 p-5 bg-surface rounded-xl border border-line-light hover:border-line-default hover:shadow-md transition-all"
          >
            <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-status-yellow/10">
              <BaseIcon name="CircleStackIcon" class="w-5 h-5 text-status-yellow" />
            </div>
            <span class="text-sm font-medium text-heading">Backups</span>
          </router-link>
        </div>
      </div>

      <!-- ── Platform Info ──────────────────────────────────────── -->
      <div class="mt-6">
        <h3 class="mb-4 text-lg font-bold text-heading">Platform Information</h3>
        <div class="bg-surface rounded-xl border border-line-light shadow-sm overflow-hidden">
          <dl class="divide-y divide-line-light">
            <div class="flex items-center justify-between px-5 py-4">
              <dt class="flex items-center gap-2 text-sm text-muted">
                <BaseIcon name="ServerIcon" class="w-4 h-4 text-subtle" />
                Application Version
              </dt>
              <dd class="text-sm font-semibold text-heading">{{ data.app_version }}</dd>
            </div>
            <div class="flex items-center justify-between px-5 py-4">
              <dt class="flex items-center gap-2 text-sm text-muted">
                <BaseIcon name="CodeBracketIcon" class="w-4 h-4 text-subtle" />
                PHP Version
              </dt>
              <dd class="text-sm font-semibold text-heading">{{ data.php_version }}</dd>
            </div>
            <div class="flex items-center justify-between px-5 py-4">
              <dt class="flex items-center gap-2 text-sm text-muted">
                <BaseIcon name="CircleStackIcon" class="w-4 h-4 text-subtle" />
                Database Driver
              </dt>
              <dd class="text-sm font-semibold text-heading">{{ data.database?.driver?.toUpperCase() }}</dd>
            </div>
            <div class="flex items-center justify-between px-5 py-4">
              <dt class="flex items-center gap-2 text-sm text-muted">
                <BaseIcon name="CircleStackIcon" class="w-4 h-4 text-subtle" />
                Database Version
              </dt>
              <dd class="text-sm font-semibold text-heading">{{ data.database?.version }}</dd>
            </div>
            <div class="flex items-center justify-between px-5 py-4">
              <dt class="flex items-center gap-2 text-sm text-muted">
                <BaseIcon name="BuildingOfficeIcon" class="w-4 h-4 text-subtle" />
                Total Companies
              </dt>
              <dd class="text-sm font-semibold text-heading">{{ data.counts?.companies }}</dd>
            </div>
            <div class="flex items-center justify-between px-5 py-4">
              <dt class="flex items-center gap-2 text-sm text-muted">
                <BaseIcon name="UsersIcon" class="w-4 h-4 text-subtle" />
                Total Users
              </dt>
              <dd class="text-sm font-semibold text-heading">{{ data.counts?.users }}</dd>
            </div>
          </dl>
        </div>
      </div>
    </template>
  </BasePage>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useAdminStore } from '../stores/admin.store'
import type { AdminDashboardData } from '../stores/admin.store'

const adminStore = useAdminStore()
const isLoading = ref<boolean>(true)
const data = ref<Partial<AdminDashboardData>>({})

onMounted(async () => {
  try {
    data.value = await adminStore.fetchDashboard()
  } finally {
    isLoading.value = false
  }
})
</script>
