import { defineStore } from 'pinia'
import { ref } from 'vue'
import { client } from '@/scripts/api/client'

export const useConsolidationStore = defineStore('consolidation', () => {
  const groups = ref([])
  const candidates = ref([])
  const currentGroup = ref(null)
  const loading = ref(false)

  const fetchGroups = async (params: Record<string, any> = {}) => {
    loading.value = true
    try {
      const query = new URLSearchParams()
      if (params.status) query.append('status', params.status)
      if (params.destination) query.append('destination', params.destination)
      const qs = query.toString()
      const url = `/api/v1/consolidation-groups${qs ? `?${qs}` : ''}`
      const response = await client.get(url)
      if (Array.isArray(response)) {
        groups.value = response
      } else if (Array.isArray(response.data)) {
        groups.value = response.data
      } else if (response.data && Array.isArray(response.data.data)) {
        groups.value = response.data.data
      } else {
        groups.value = []
      }
    } catch (error) {
      console.error('Error fetching consolidation groups:', error)
      groups.value = []
    } finally {
      loading.value = false
    }
  }

  const fetchCandidates = async () => {
    try {
      const response = await client.get('/api/v1/consolidation-groups/candidates')
      candidates.value = response.data?.data || response.data || []
    } catch (error) {
      console.error('Error fetching candidates:', error)
      candidates.value = []
    }
  }

  const getGroup = async (id: number) => {
    try {
      const response = await client.get(`/api/v1/consolidation-groups/${id}`)
      const data = response.data?.data || response.data
      currentGroup.value = data
      return data
    } catch (error) {
      console.error('Error fetching group:', error)
      return null
    }
  }

  const createGroup = async (data: any) => {
    try {
      await client.post('/api/v1/consolidation-groups', data)
      await fetchGroups()
    } catch (error) {
      console.error('Error creating group:', error)
      throw error
    }
  }

  const updateGroup = async (id: number, data: any) => {
    try {
      await client.put(`/api/v1/consolidation-groups/${id}`, data)
      await fetchGroups()
    } catch (error) {
      console.error('Error updating group:', error)
      throw error
    }
  }

  const deleteGroup = async (id: number) => {
    try {
      await client.delete(`/api/v1/consolidation-groups/${id}`)
      await fetchGroups()
    } catch (error) {
      console.error('Error deleting group:', error)
      throw error
    }
  }

  const addItemToGroup = async (groupId: number, itemId: number) => {
    try {
      await client.post(`/api/v1/consolidation-groups/${groupId}/items`, {
        item_id: itemId,
      })
    } catch (error) {
      console.error('Error adding item to group:', error)
      throw error
    }
  }

  const splitItemToGroup = async (groupId: number, itemId: number, data: { weight_kg: number; no_of_packages?: number }) => {
    try {
      await client.post(`/api/v1/consolidation-groups/${groupId}/items/${itemId}/split`, data)
    } catch (error) {
      console.error('Error splitting item into group:', error)
      throw error
    }
  }

  const removeItemFromGroup = async (groupId: number, itemId: number) => {
    try {
      await client.delete(`/api/v1/consolidation-groups/${groupId}/items/${itemId}`)
    } catch (error) {
      console.error('Error removing item from group:', error)
      throw error
    }
  }

  const markReady = async (id: number) => {
    try {
      await client.patch(`/api/v1/consolidation-groups/${id}/ready`)
      await fetchGroups()
    } catch (error) {
      console.error('Error marking group ready:', error)
      throw error
    }
  }

  return {
    groups,
    candidates,
    currentGroup,
    loading,
    fetchGroups,
    fetchCandidates,
    getGroup,
    createGroup,
    updateGroup,
    deleteGroup,
    addItemToGroup,
    splitItemToGroup,
    removeItemFromGroup,
    markReady,
  }
})
