import { defineStore } from 'pinia'
import { ref } from 'vue'
import { client } from '@/scripts/api/client'

export const useLoadTripStore = defineStore('loadTrips', () => {
  const trips = ref([])
  const currentTrip = ref(null)
  const loading = ref(false)
  const pagination = ref({
    currentPage: 1,
    lastPage: 1,
    perPage: 10,
    total: 0,
  })

  const fetchTrips = async (params: Record<string, any> = {}) => {
    loading.value = true
    try {
      const query = new URLSearchParams()
      if (params.status) query.append('status', params.status)
      if (params.destination) query.append('destination', params.destination)
      if (params.lr) query.append('lr', params.lr)
      if (params.driver) query.append('driver', params.driver)
      if (params.truck_number) query.append('truck_number', params.truck_number)
      if (params.route) query.append('route', params.route)
      if (params.page) query.append('page', String(params.page))
      if (params.per_page) query.append('per_page', String(params.per_page))
      if (params.dispatch_date_from) query.append('dispatch_date_from', params.dispatch_date_from)
      if (params.dispatch_date_to) query.append('dispatch_date_to', params.dispatch_date_to)
      if (params.delivered_date_from) query.append('delivered_date_from', params.delivered_date_from)
      if (params.delivered_date_to) query.append('delivered_date_to', params.delivered_date_to)
      const qs = query.toString()
      const url = `/api/v1/load-trips${qs ? `?${qs}` : ''}`
      const response = await client.get(url)

      // Support both paginated ({ data, meta }) and non-paginated (array) responses
      if (response.data && Array.isArray(response.data.data)) {
        trips.value = response.data.data
        if (response.data.meta) {
          pagination.value = {
            currentPage: response.data.meta.current_page,
            lastPage: response.data.meta.last_page,
            perPage: response.data.meta.per_page,
            total: response.data.meta.total,
          }
        }
      } else if (Array.isArray(response.data)) {
        trips.value = response.data
      } else if (Array.isArray(response)) {
        trips.value = response
      } else {
        trips.value = []
      }
    } catch (error) {
      console.error('Error fetching load trips:', error)
      trips.value = []
    } finally {
      loading.value = false
    }
  }

  const getTrip = async (id: number) => {
    try {
      const response = await client.get(`/api/v1/load-trips/${id}`)
      const data = response.data?.data || response.data
      currentTrip.value = data
      return data
    } catch (error) {
      console.error('Error fetching trip:', error)
      return null
    }
  }

  const createTrip = async (data: any) => {
    try {
      await client.post('/api/v1/load-trips', data)
      await fetchTrips()
    } catch (error) {
      console.error('Error creating trip:', error)
      throw error
    }
  }



  const updateTrip = async (id: number, data: any) => {
    try {
      await client.put(`/api/v1/load-trips/${id}`, data)
      await fetchTrips()
    } catch (error) {
      console.error('Error updating trip:', error)
      throw error
    }
  }

  const deleteTrip = async (id: number) => {
    try {
      await client.delete(`/api/v1/load-trips/${id}`)
      await fetchTrips()
    } catch (error) {
      console.error('Error deleting trip:', error)
      throw error
    }
  }

  const dispatchTrip = async (id: number) => {
    try {
      await client.patch(`/api/v1/load-trips/${id}/dispatch`)
      await fetchTrips()
    } catch (error) {
      console.error('Error dispatching trip:', error)
      throw error
    }
  }

  const markDelivered = async (id: number) => {
    try {
      await client.patch(`/api/v1/load-trips/${id}/deliver`)
      await fetchTrips()
    } catch (error) {
      console.error('Error marking trip delivered:', error)
      throw error
    }
  }

  const cancelTrip = async (id: number) => {
    try {
      await client.patch(`/api/v1/load-trips/${id}/cancel`)
      await fetchTrips()
    } catch (error) {
      console.error('Error cancelling trip:', error)
      throw error
    }
  }

  return {
    trips,
    currentTrip,
    loading,
    pagination,
    fetchTrips,
    getTrip,
    createTrip,
    updateTrip,
    deleteTrip,
    dispatchTrip,
    markDelivered,
    cancelTrip,
  }
})
