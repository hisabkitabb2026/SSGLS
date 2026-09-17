import type { RouteRecordRaw } from 'vue-router'
import { useDocumentTypeGuard } from '@/scripts/composables/useDocumentTypeGuard'
import { ABILITIES } from '@/scripts/config/abilities'

const InvoiceIndexView = () => import('./views/InvoiceIndexView.vue')
const InvoiceCreateView = () => import('./views/InvoiceCreateView.vue')
const InvoiceDetailView = () => import('./views/InvoiceDetailView.vue')

export const invoiceRoutes: RouteRecordRaw[] = [
  {
    path: 'invoices',
    name: 'invoices.index',
    component: InvoiceIndexView,
    beforeEnter: useDocumentTypeGuard,
    meta: {
      requiresAuth: true,
      ability: ABILITIES.VIEW_INVOICE_RECEIPT,
      title: 'invoices.title',
    },
  },
  {
    path: 'invoices/create',
    name: 'invoices.create',
    component: InvoiceCreateView,
    beforeEnter: useDocumentTypeGuard,
    meta: {
      requiresAuth: true,
      ability: ABILITIES.CREATE_INVOICE_RECEIPT,
      title: 'invoices.new_invoice',
    },
  },
  {
    path: 'invoices/:id/edit',
    name: 'invoices.edit',
    component: InvoiceCreateView,
    beforeEnter: useDocumentTypeGuard,
    meta: {
      requiresAuth: true,
      ability: ABILITIES.EDIT_INVOICE_RECEIPT,
      title: 'invoices.edit_invoice',
    },
  },
  {
    path: 'invoices/:id/view',
    name: 'invoices.view',
    component: InvoiceDetailView,
    beforeEnter: useDocumentTypeGuard,
    meta: {
      requiresAuth: true,
      ability: ABILITIES.VIEW_INVOICE_RECEIPT,
      title: 'invoices.title',
    },
  },
]
