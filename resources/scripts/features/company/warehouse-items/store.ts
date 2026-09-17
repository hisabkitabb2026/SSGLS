import { defineStore } from 'pinia'
import { ref } from 'vue'
import { client } from '@/scripts/api/client'
import { useCompanyStore } from '@/scripts/stores/company.store'

export const useWarehouseItemStore = defineStore('warehouseItems', () => {
  const companyStore = useCompanyStore()

  const items = ref([])
  const loading = ref(false)
  const lrOptions = ref([])
  const dashboardStats = ref(null)

  const fetchItems = async () => {
    loading.value = true
    try {
      const response = await client.get('/api/v1/warehouse-items')
      if (Array.isArray(response)) {
        items.value = response
      } else if (Array.isArray(response.data)) {
        items.value = response.data
      } else if (response.data && Array.isArray(response.data.data)) {
        items.value = response.data.data
      } else {
        items.value = []
      }
    } catch (error) {
      console.error('Error fetching items:', error)
      items.value = []
    } finally {
      loading.value = false
    }
  }

  const fetchDashboard = async () => {
    try {
      const response = await client.get('/api/v1/warehouse-items/dashboard')
      dashboardStats.value = response.data?.data || response.data || null
    } catch (error) {
      console.error('Error fetching dashboard:', error)
      dashboardStats.value = null
    }
  }

  const fetchLrOptions = async () => {
    try {
      const response = await client.get('/api/v1/warehouse-items/lr-options')
      lrOptions.value = response.data || []
    } catch (error) {
      console.error('Error fetching LR options:', error)
    }
  }

  const lookupLr = async (invoiceNumber: string) => {
    try {
      const response = await client.post('/api/v1/warehouse-items/lookup/lr', { invoice_number: invoiceNumber })
      const data = response.data?.data || response.data
      return data || null
    } catch (error) {
      console.error('Error looking up LR:', error)
      return null
    }
  }

  const getItem = async (id: number) => {
    try {
      const response = await client.get(`/api/v1/warehouse-items/${id}`)
      return response.data
    } catch (error) {
      console.error('Error fetching item:', error)
      return null
    }
  }

  const createItem = async (data: any) => {
    try {
      await client.post('/api/v1/warehouse-items', data)
      await fetchItems()
      await fetchDashboard()
    } catch (error) {
      console.error('Error creating item:', error)
      throw error
    }
  }

  const updateItem = async (id: number, data: any) => {
    try {
      await client.put(`/api/v1/warehouse-items/${id}`, data)
      const itemIndex = items.value.findIndex((item: any) => item.id === id)
      if (itemIndex !== -1) {
        items.value[itemIndex] = { ...items.value[itemIndex], ...data }
      }
      await fetchDashboard()
    } catch (error) {
      console.error('Error updating item:', error)
      throw error
    }
  }

  const deleteItem = async (id: number) => {
    try {
      await client.delete(`/api/v1/warehouse-items/${id}`)
      await fetchItems()
      await fetchDashboard()
    } catch (error) {
      console.error('Error deleting item:', error)
      throw error
    }
  }

  return {
    items,
    loading,
    lrOptions,
    dashboardStats,
    fetchItems,
    fetchDashboard,
    fetchLrOptions,
    lookupLr,
    getItem,
    createItem,
    updateItem,
    deleteItem,
  }
})
