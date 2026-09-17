import type { RouteRecordRaw } from 'vue-router'
import { ABILITIES } from '@/scripts/config/abilities'

const LoadTripIndexView = () => import('./views/LoadTripIndexView.vue')
const LoadTripDetailView = () => import('./views/LoadTripDetailView.vue')
const TruckIndexView = () => import('./views/TruckIndexView.vue')
const TruckDetailView = () => import('./views/TruckDetailView.vue')
const TruckMaintenancePlannerView = () => import('./views/TruckMaintenancePlannerView.vue')

export const loadTripRoutes: RouteRecordRaw[] = [
  {
    path: 'trucks',
    name: 'trucks.index',
    component: TruckIndexView,
    meta: {
      requiresAuth: true,
      ability: ABILITIES.VIEW_TRUCK,
      title: 'Fleet Management',
    },
  },
  {
    path: 'trucks/:id',
    name: 'trucks.show',
    component: TruckDetailView,
    meta: {
      requiresAuth: true,
      ability: ABILITIES.VIEW_TRUCK,
      title: 'Truck Details',
    },
  },
  {
    path: 'trucks/:id/maintenance',
    name: 'trucks.maintenance',
    component: TruckMaintenancePlannerView,
    meta: {
      requiresAuth: true,
      ability: ABILITIES.VIEW_TRUCK_MAINTENANCE,
      title: 'Truck Maintenance',
    },
  },
  {
    path: 'load-trips',
    name: 'load-trips.index',
    component: LoadTripIndexView,
    meta: {
      requiresAuth: true,
      ability: ABILITIES.VIEW_LOAD_TRIP,
      title: 'Load Trips',
    },
  },
  {
    path: 'load-trips/:id',
    name: 'load-trips.show',
    component: LoadTripDetailView,
    meta: {
      requiresAuth: true,
      ability: ABILITIES.VIEW_LOAD_TRIP,
      title: 'Load Trip',
    },
  },
]
