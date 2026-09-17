import type { RouteRecordRaw } from 'vue-router'

const CustomerPortalAuthLayout = () => import('./components/CustomerPortalAuthLayout.vue')
const CustomerPortalLayout = () => import('./components/CustomerPortalLayout.vue')
const CustomerPortalLoginView = () => import('./views/auth/CustomerPortalLoginView.vue')
const CustomerPortalForgotPasswordView = () => import('./views/auth/CustomerPortalForgotPasswordView.vue')
const CustomerPortalResetPasswordView = () => import('./views/auth/CustomerPortalResetPasswordView.vue')
const CustomerDashboardView = () => import('./views/CustomerDashboardView.vue')
const CustomerInvoicesView = () => import('./views/CustomerInvoicesView.vue')
const CustomerInvoiceDetailView = () => import('./views/CustomerInvoiceDetailView.vue')
const CustomerEstimatesView = () => import('./views/CustomerEstimatesView.vue')
const CustomerEstimateDetailView = () => import('./views/CustomerEstimateDetailView.vue')
const CustomerPaymentsView = () => import('./views/CustomerPaymentsView.vue')
const CustomerPaymentDetailView = () => import('./views/CustomerPaymentDetailView.vue')
const CustomerSettingsView = () => import('./views/CustomerSettingsView.vue')
const CustomerLrReceiptsView = () => import('./views/CustomerLrReceiptsView.vue')
const CustomerLrReceiptDetailView = () => import('./views/CustomerLrReceiptDetailView.vue')
const CustomerLrTrackingView = () => import('./views/CustomerLrTrackingView.vue')

const CustomerWarehouseItemsView = () => import('./views/CustomerWarehouseItemsView.vue')
const CustomerLoadTripsView = () => import('./views/CustomerLoadTripsView.vue')
const CustomerLoadTripDetailView = () => import('./views/CustomerLoadTripDetailView.vue')
const CustomerConsignmentTrackingView = () => import('./views/CustomerConsignmentTrackingView.vue')
const CustomerWarehouseItemDetailView = () => import('./views/CustomerWarehouseItemDetailView.vue')
const CustomerReportsView = () => import('./views/CustomerReportsView.vue')


export const customerPortalRoutes: RouteRecordRaw[] = [

  {
    path: '/:company/customer',
    component: CustomerPortalAuthLayout,
    meta: {
      isCustomerPortal: true,
      customerPortalGuest: true,
    },
    children: [
      {
        path: 'login',
        alias: '',
        name: 'customer-portal.login',
        component: CustomerPortalLoginView,
      },
      {
        path: 'forgot-password',
        name: 'customer-portal.forgot-password',
        component: CustomerPortalForgotPasswordView,
      },
      {
        path: 'reset/password/:token',
        name: 'customer-portal.reset-password',
        component: CustomerPortalResetPasswordView,
      },
    ],
  },
  {
    path: '/:company/customer',
    component: CustomerPortalLayout,
    meta: {
      isCustomerPortal: true,
    },
    children: [
      {
        path: 'dashboard',
        name: 'customer-portal.dashboard',
        component: CustomerDashboardView,
      },
      {
        path: 'invoices',
        name: 'customer-portal.invoices',
        component: CustomerInvoicesView,
      },
      {
        path: 'invoices/:id/view',
        name: 'customer-portal.invoices.view',
        component: CustomerInvoiceDetailView,
      },
      {
        path: 'estimates',
        name: 'customer-portal.estimates',
        component: CustomerEstimatesView,
      },
      {
        path: 'estimates/:id/view',
        name: 'customer-portal.estimates.view',
        component: CustomerEstimateDetailView,
      },
      {
        path: 'payments',
        name: 'customer-portal.payments',
        component: CustomerPaymentsView,
      },
      {
        path: 'payments/:id/view',
        name: 'customer-portal.payments.view',
        component: CustomerPaymentDetailView,
      },
      {
        path: 'settings',
        name: 'customer-portal.settings',
        component: CustomerSettingsView,
      },
      {
        path: 'lr-receipts',
        name: 'customer-portal.lr-receipts',
        component: CustomerLrReceiptsView,
      },
      {
        path: 'lr-receipts/:id/view',
        name: 'customer-portal.lr-receipts.view',
        component: CustomerLrReceiptDetailView,
      },
      {
        path: 'lr-receipts/:id/tracking',
        name: 'customer-portal.lr-receipts.tracking',
        component: CustomerLrTrackingView,
      },

      {
        path: 'warehouse-items',

        name: 'customer-portal.warehouse-items',
        component: CustomerWarehouseItemsView,
      },
      {
        path: 'warehouse-items/:id/view',
        name: 'customer-portal.warehouse-items.view',
        component: CustomerWarehouseItemDetailView,
      },
      {
        path: 'load-trips',


        name: 'customer-portal.load-trips',
        component: CustomerLoadTripsView,
      },
      {
        path: 'load-trips/:id/view',
        name: 'customer-portal.load-trips.view',
        component: CustomerLoadTripDetailView,
      },
      {
        path: 'track',
        name: 'customer-portal.track',
        component: CustomerConsignmentTrackingView,
      },
      {
        path: 'reports',
        name: 'customer-portal.reports',
        component: CustomerReportsView,
      },
    ],
  },
]
