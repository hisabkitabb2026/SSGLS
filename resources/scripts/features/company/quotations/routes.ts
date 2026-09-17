import type { RouteRecordRaw } from 'vue-router'
import { useDocumentTypeGuard } from '@/scripts/composables/useDocumentTypeGuard'

const QuotationIndexView = () => import('./views/QuotationIndexView.vue')
const QuotationCreateView = () => import('./views/QuotationCreateView.vue')
const QuotationDetailView = () => import('./views/QuotationDetailView.vue')

export const quotationRoutes: RouteRecordRaw[] = [
  {
    path: 'quotations',
    name: 'quotations.index',
    component: QuotationIndexView,
    beforeEnter: useDocumentTypeGuard,
    meta: {
      requiresAuth: true,
      ability: 'view-quotation',
      title: 'quotations.title',
    },
  },
  {
    path: 'quotations/create',
    name: 'quotations.create',
    component: QuotationCreateView,
    meta: {
      requiresAuth: true,
      ability: 'create-quotation',
      title: 'quotations.new_quotation',
    },
  },
  {
    path: 'quotations/:id/edit',
    name: 'quotations.edit',
    component: QuotationCreateView,
    meta: {
      requiresAuth: true,
      ability: 'edit-quotation',
      title: 'quotations.edit_quotation',
    },
  },
  {
    path: 'quotations/:id/view',
    name: 'quotations.view',
    component: QuotationDetailView,
    meta: {
      requiresAuth: true,
      ability: 'view-quotation',
      title: 'quotations.title',
    },
  },
]
