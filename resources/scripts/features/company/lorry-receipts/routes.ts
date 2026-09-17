import type { RouteRecordRaw } from 'vue-router'
import { useDocumentTypeGuard } from '@/scripts/composables/useDocumentTypeGuard'
import { ABILITIES } from '@/scripts/config/abilities'

const routes: RouteRecordRaw[] = [
  {
    path: 'lorry-receipts',
    name: 'lorry-receipts.index',
    component: () => import('./views/LorryReceiptIndexView.vue'),
    beforeEnter: useDocumentTypeGuard,
    meta: {
      requiresAuth: true,
      ability: ABILITIES.VIEW_LORRY_RECEIPT,
      title: 'Lorry Receipts',
    },
  },
  {
    path: 'lorry-receipts/create',
    name: 'lorry-receipts.create',
    component: () => import('../invoices/views/InvoiceCreateView.vue'),
    beforeEnter: useDocumentTypeGuard,
    meta: {
      requiresAuth: true,
      ability: ABILITIES.CREATE_LORRY_RECEIPT,
      title: 'New Lorry Receipt',
    },
  },
  {
    path: 'lorry-receipts/:id/edit',
    name: 'lorry-receipts.edit',
    component: () => import('../invoices/views/InvoiceCreateView.vue'),
    beforeEnter: useDocumentTypeGuard,
    meta: {
      requiresAuth: true,
      ability: ABILITIES.EDIT_LORRY_RECEIPT,
      title: 'Edit Lorry Receipt',
    },
  },
  {
    path: 'lorry-receipts/:id/view',
    name: 'lorry-receipts.view',
    component: () => import('../invoices/views/InvoiceDetailView.vue'),
    beforeEnter: useDocumentTypeGuard,
    meta: {
      requiresAuth: true,
      ability: ABILITIES.VIEW_LORRY_RECEIPT,
      title: 'Lorry Receipts',
    },
  },
]

export default routes
