export { useQuotationStore } from './store'
export type { QuotationStore, QuotationFormData, QuotationState } from './store'
export { quotationRoutes } from './routes'

// Views
export { default as QuotationIndexView } from './views/QuotationIndexView.vue'
export { default as QuotationCreateView } from './views/QuotationCreateView.vue'
export { default as QuotationDetailView } from './views/QuotationDetailView.vue'

// Components
export { default as QuotationBasicFields } from './components/QuotationBasicFields.vue'
export { default as QuotationDropdown } from './components/QuotationDropdown.vue'
export { default as SendQuotationModal } from './components/SendQuotationModal.vue'
