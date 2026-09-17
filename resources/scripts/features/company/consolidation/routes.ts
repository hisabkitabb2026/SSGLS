import type { RouteRecordRaw } from 'vue-router'

const ConsolidationBoardView = () => import('./views/ConsolidationBoardView.vue')
const ConsolidationDetailView = () => import('./views/ConsolidationDetailView.vue')

export const consolidationRoutes: RouteRecordRaw[] = [
  {
    path: 'consolidation',
    name: 'consolidation.index',
    component: ConsolidationBoardView,
    meta: {
      requiresAuth: true,
      title: 'Consolidation Board',
    },
  },
  {
    path: 'consolidation/:id',
    name: 'consolidation.show',
    component: ConsolidationDetailView,
    meta: {
      requiresAuth: true,
      title: 'Consolidation Group',
    },
  },
]
