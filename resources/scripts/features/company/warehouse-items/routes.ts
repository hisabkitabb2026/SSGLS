import type { RouteRecordRaw } from 'vue-router'
import { ABILITIES } from '@/scripts/config/abilities'

const WarehouseItemIndexView = () => import('./views/WarehouseItemIndexView.vue')
const WarehouseItemCreateView = () => import('./views/WarehouseItemCreateView.vue')
const WarehouseItemDetailView = () => import('./views/WarehouseItemDetailView.vue')

export const warehouseItemRoutes: RouteRecordRaw[] = [
  {
    path: 'warehouse-items',
    name: 'warehouse-items.index',
    component: WarehouseItemIndexView,
    meta: {
      requiresAuth: true,
      ability: ABILITIES.VIEW_WAREHOUSE_ITEM,
      title: 'Warehouse Items',
    },
  },
  {
    path: 'warehouse-items/create',
    name: 'warehouse-items.create',
    component: WarehouseItemCreateView,
    meta: {
      requiresAuth: true,
      ability: ABILITIES.CREATE_WAREHOUSE_ITEM,
      title: 'Add Warehouse Item',
    },
  },
  {
    path: 'warehouse-items/:id',
    name: 'warehouse-items.show',
    component: WarehouseItemDetailView,
    meta: {
      requiresAuth: true,
      ability: ABILITIES.VIEW_WAREHOUSE_ITEM,
      title: 'Warehouse Item Details',
    },
  },
  {
    path: 'warehouse-items/:id/edit',
    name: 'warehouse-items.edit',
    component: WarehouseItemCreateView,
    meta: {
      requiresAuth: true,
      ability: ABILITIES.EDIT_WAREHOUSE_ITEM,
      title: 'Edit Warehouse Item',
    },
  },
]
