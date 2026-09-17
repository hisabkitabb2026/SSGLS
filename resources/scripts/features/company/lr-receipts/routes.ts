import type { RouteRecordRaw } from 'vue-router'
import { useDocumentTypeGuard } from '@/scripts/composables/useDocumentTypeGuard'
import { ABILITIES } from '@/scripts/config/abilities'

export default <RouteRecordRaw[]>[
  {
    path: 'lr-receipts',
    name: 'lr-receipts.index',
    component: () => import('./views/LrReceiptIndexView.vue'),
    beforeEnter: useDocumentTypeGuard,
    meta: {
      requiresAuth: true,
      ability: ABILITIES.VIEW_LR_RECEIPT,
      title: 'LR Receipts',
    },
  },
  {
    path: 'lr-receipts/create',
    name: 'lr-receipts.create',
    component: () => import('../invoices/views/InvoiceCreateView.vue'),
    beforeEnter: useDocumentTypeGuard,
    meta: {
      requiresAuth: true,
      ability: ABILITIES.CREATE_LR_RECEIPT,
      title: 'New LR Receipt',
    },
  },
  {
    path: 'lr-receipts/:id/edit',
    name: 'lr-receipts.edit',
    component: () => import('../invoices/views/InvoiceCreateView.vue'),
    beforeEnter: useDocumentTypeGuard,
    meta: {
      requiresAuth: true,
      ability: ABILITIES.EDIT_LR_RECEIPT,
      title: 'Edit LR Receipt',
    },
  },
  {
    path: 'lr-receipts/:id/view',
    name: 'lr-receipts.view',
    component: () => import('../invoices/views/InvoiceDetailView.vue'),
    beforeEnter: useDocumentTypeGuard,
    meta: {
      requiresAuth: true,
      ability: ABILITIES.VIEW_LR_RECEIPT,
      title: 'LR Receipts',
    },
  },
]
