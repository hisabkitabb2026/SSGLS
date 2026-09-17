import type { RouteRecordRaw } from 'vue-router' 
import { useDocumentTypeGuard } from '@/scripts/composables/useDocumentTypeGuard' 
  
/** 
* Standard Invoice routes — these use the upstream InvoiceShelf invoice flow 
* (items table, taxes, discounts, template selector with invoice1/2/3). 
* 
* The "Invoice Receipts" menu item at /admin/invoices continues to use the 
* transport office_invoice format. These routes provide the standard invoice 
* experience that was previously inaccessible from the UI. 
*/ 
const InvoiceIndexView = () => import('../invoices/views/InvoiceIndexView.vue') 
const InvoiceCreateView = () => import('../invoices/views/InvoiceCreateView.vue') 
const InvoiceDetailView = () => import('../invoices/views/InvoiceDetailView.vue') 
  
export const standardInvoiceRoutes: RouteRecordRaw[] = [ 
  { 
    path: 'standard-invoices', 
    name: 'standard-invoices.index', 
    component: InvoiceIndexView, 
    beforeEnter: useDocumentTypeGuard, 
    meta: { 
      requiresAuth: true, 
      ability: 'view-invoice', 
      title: 'invoices.title', 
    }, 
  }, 
  { 
    path: 'standard-invoices/create', 
    name: 'standard-invoices.create', 
    component: InvoiceCreateView, 
    beforeEnter: useDocumentTypeGuard, 
    meta: { 
      requiresAuth: true, 
      ability: 'create-invoice', 
      title: 'invoices.new_invoice', 
    }, 
  }, 
  { 
    path: 'standard-invoices/:id/edit', 
    name: 'standard-invoices.edit', 
    component: InvoiceCreateView, 
    beforeEnter: useDocumentTypeGuard, 
    meta: { 
      requiresAuth: true, 
      ability: 'edit-invoice', 
      title: 'invoices.edit_invoice', 
    }, 
  }, 
  { 
    path: 'standard-invoices/:id/view', 
    name: 'standard-invoices.view', 
    component: InvoiceDetailView, 
    beforeEnter: useDocumentTypeGuard, 
    meta: { 
      requiresAuth: true, 
      ability: 'view-invoice', 
      title: 'invoices.title', 
    }, 
  }, 
] 
 
