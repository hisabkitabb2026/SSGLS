import type { RouteRecordRaw } from 'vue-router'
import { ABILITIES } from '@/scripts/config/abilities'

const settingsRoutes: RouteRecordRaw[] = [
  {
    path: 'settings',
    component: () => import('./views/SettingsLayoutView.vue'),
    children: [
      {
        path: 'roles-settings',
        redirect: { name: 'settings.roles' },
      },
      {
        path: 'exchange-rate-provider',
        redirect: { name: 'settings.exchange-rate' },
      },
      {
        path: 'payment-mode',
        redirect: { name: 'settings.payment-modes' },
      },
      {
        path: 'expense-category',
        redirect: { name: 'settings.expense-categories' },
      },
      {
        path: 'mail-configuration',
        redirect: { name: 'settings.mail-config' },
      },
      {
        path: 'modules-configuration',
        redirect: { name: 'settings.modules' },
      },
      {
        path: 'mail-configuration',
        name: 'settings.mail-config',
        meta: {
          requiresAuth: true,
          ability: ABILITIES.VIEW_SETTINGS_MAIL_CONFIG,
        },
        component: () => import('./views/MailConfigView.vue'),
      },
      {
        path: 'ai-config',
        name: 'settings.ai-config',
        meta: {
          requiresAuth: true,
          ability: ABILITIES.VIEW_SETTINGS_AI_CONFIG,
        },
        component: () => import('./views/AiConfigView.vue'),
      },
      {
        path: 'customization',
        name: 'settings.customization',
        meta: {
          requiresAuth: true,
          ability: ABILITIES.VIEW_SETTINGS_CUSTOMIZATION,
        },
        component: () => import('./views/CustomizationView.vue'),
      },
      {
        path: 'tax-types',
        name: 'settings.tax-types',
        meta: {
          requiresAuth: true,
          ability: ABILITIES.VIEW_TAX_TYPE,
        },
        component: () => import('./views/TaxTypesView.vue'),
      },
      {
        path: 'payment-modes',
        name: 'settings.payment-modes',
        meta: {
          requiresAuth: true,
          ability: ABILITIES.VIEW_SETTINGS_PAYMENT_MODES,
        },
        component: () => import('./views/PaymentModesView.vue'),
      },
      {
        path: 'custom-fields',
        name: 'settings.custom-fields',
        meta: {
          requiresAuth: true,
          ability: ABILITIES.VIEW_CUSTOM_FIELDS,
        },
        component: () => import('./views/CustomFieldsView.vue'),
      },
      {
        path: 'notes',
        name: 'settings.notes',
        meta: {
          requiresAuth: true,
          ability: ABILITIES.VIEW_NOTE,
        },
        component: () => import('./views/NotesView.vue'),
      },
      {
        path: 'notifications',
        name: 'settings.notifications',
        meta: {
          requiresAuth: true,
          ability: ABILITIES.VIEW_SETTINGS_NOTIFICATIONS,
        },
        component: () => import('./views/NotificationsView.vue'),
      },
      {
        path: 'expense-categories',
        name: 'settings.expense-categories',
        meta: {
          requiresAuth: true,
          ability: ABILITIES.VIEW_EXPENSE,
        },
        component: () => import('./views/ExpenseCategoriesView.vue'),
      },
      {
        path: 'exchange-rate',
        name: 'settings.exchange-rate',
        meta: {
          requiresAuth: true,
          ability: ABILITIES.VIEW_EXCHANGE_RATE,
        },
        component: () => import('./views/ExchangeRateView.vue'),
      },
      {
        path: 'mail-config',
        name: 'settings.mail-config',
        meta: {
          requiresAuth: true,
          ability: ABILITIES.VIEW_SETTINGS_MAIL_CONFIG,
        },
        component: () => import('./views/MailConfigView.vue'),
      },
      {
        path: 'ai-config',
        name: 'settings.ai-config',
        meta: {
          requiresAuth: true,
          ability: ABILITIES.VIEW_SETTINGS_AI_CONFIG,
        },
        component: () => import('./views/AiConfigView.vue'),
      },
      {
        path: 'roles',
        name: 'settings.roles',
        meta: {
          requiresAuth: true,
          ability: ABILITIES.VIEW_SETTINGS_ROLES,
        },
        component: () => import('./views/RolesView.vue'),
      },
      {
        path: 'modules',
        name: 'settings.modules',
        meta: {
          requiresAuth: true,
          ability: ABILITIES.VIEW_SETTINGS_MODULES,
        },
        component: () => import('@/scripts/features/company/modules/views/CompanyModulesIndexView.vue'),
      },
      {
        path: 'company-info',
        name: 'settings.company-info',
        meta: {
          requiresAuth: true,
          ability: ABILITIES.VIEW_SETTINGS_COMPANY_INFO,
        },
        component: () => import('./views/CompanyInfoView.vue'),
      },
      {
        path: 'preferences',
        name: 'settings.preferences',
        meta: {
          requiresAuth: true,
          ability: ABILITIES.VIEW_SETTINGS_PREFERENCES,
        },
        component: () => import('./views/PreferencesView.vue'),
      },
      {
        path: 'danger-zone',
        name: 'settings.danger-zone',
        meta: {
          requiresAuth: true,
          isOwner: true,
        },
        component: () => import('./views/DangerZoneView.vue'),
      },
      {
        path: 'account-settings',
        name: 'settings.account',
        meta: {
          requiresAuth: true,
        },
        component: () => import('./views/UserAccountView.vue'),
      },
    ],
  },
  {
    path: 'user-settings/:tab?',
    redirect: { name: 'settings.account' },
  },
  {
    path: 'settings/account-settings',
    redirect: { name: 'settings.account' },
  },
  {
    path: 'customization',
    redirect: { name: 'settings.customization' },
  },
  {
    path: 'notifications',
    redirect: { name: 'settings.notifications' },
  },
  {
    path: 'roles-settings',
    redirect: { name: 'settings.roles' },
  },
  {
    path: 'exchange-rate-provider',
    redirect: { name: 'settings.exchange-rate' },
  },
  {
    path: 'tax-types',
    redirect: { name: 'settings.tax-types' },
  },
  {
    path: 'payment-mode',
    redirect: { name: 'settings.payment-modes' },
  },
  {
    path: 'custom-fields',
    redirect: { name: 'settings.custom-fields' },
  },
  {
    path: 'notes',
    redirect: { name: 'settings.notes' },
  },
  {
    path: 'expense-category',
    redirect: { name: 'settings.expense-categories' },
  },
  {
    path: 'mail-configuration',
    redirect: { name: 'settings.mail-config' },
  },
  {
    path: 'modules',
    redirect: { name: 'settings.modules' },
  },
  {
    path: 'modules/:slug/settings',
    redirect: { name: 'settings.modules' },
  },
]

export default settingsRoutes
