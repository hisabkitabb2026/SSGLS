import { defineStore } from 'pinia'
import { reactive, ref } from 'vue'
import { lorryPartyProfileService } from '@/scripts/api/services/lorry-party-profile.service'
import type { LorryPartyProfileListParams } from '@/scripts/api/services/lorry-party-profile.service'
import type { LorryPartyProfile, LorryPartyProfilePayload } from '@/scripts/types/domain/lorry-party-profile'

const emptyForm: LorryPartyProfile = {
  id: undefined,
  customer_id: null,
  type: 'OWNER',
  code: '',
  name: '',
  address: '',
  phone: '',
  alternate_phone: '',
  pan_number: '',
  gstin: '',
  bank_name: '',
  bank_account_holder_name: '',
  ifsc_code: '',
  upi_id: '',
  status: 'active',
  notes: '',
  place: '',
  bank_account_no: '',
  licence_no: '',
  licence_date: '',
  licence_issued_by: '',
  rto_address: '',
  valid_up_to: '',
  advice_no: '',
  advice_date: '',
  destination_broker_name: '',
  destination_broker_address: '',
  rc_front_path: '',
  rc_back_path: '',
  pan_front_path: '',
  insurance_path: '',
  license_front_path: '',
  license_back_path: '',
  pan_front_path_broker: '',
}

export const useLorryPartyProfileStore = defineStore('lorryPartyProfile', () => {
  const profiles = ref<LorryPartyProfile[]>([])
  const currentProfile = reactive<LorryPartyProfile>({ ...emptyForm })
  const isLoading = ref<boolean>(false)
  const totalProfiles = ref<number>(0)

  // Tracks the most recently saved profile so that sibling components
  // (LorryPartySelectPopup, LorryReceiptPartyFields) can react to saves
  // from a single shared LorryPartyProfileModal instance at the page level.
  const lastSavedProfile = ref<LorryPartyProfile | null>(null)
  const lastSavedAt = ref<number>(0)

  function notifyProfileSaved(profile: LorryPartyProfile): void {
    lastSavedProfile.value = profile
    lastSavedAt.value = Date.now()
  }


  function resetCurrentProfile(): void {
    Object.assign(currentProfile, emptyForm)
  }

  function setCurrentProfile(profile: LorryPartyProfile | null): void {
    Object.assign(currentProfile, emptyForm, profile ?? {})
  }

  async function fetchProfiles(params?: LorryPartyProfileListParams): Promise<{ data: LorryPartyProfile[] }> {
    const response = await lorryPartyProfileService.list(params)
    profiles.value = response.data
    totalProfiles.value = response.meta?.total ?? response.data.length
    return response
  }

  async function fetchProfile(id: number): Promise<{ data: LorryPartyProfile }> {
    const response = await lorryPartyProfileService.get(id)
    return response
  }

  async function saveProfile(payload: LorryPartyProfilePayload, id?: number): Promise<{ data: LorryPartyProfile }> {
    if (id) {
      return await lorryPartyProfileService.update(id, payload)
    }
    return await lorryPartyProfileService.create(payload)
  }

  async function deleteProfile(id: number): Promise<{ success: boolean }> {
    return await lorryPartyProfileService.delete(id)
  }

  return {
    profiles,
    currentProfile,
    isLoading,
    totalProfiles,
    lastSavedProfile,
    lastSavedAt,
    notifyProfileSaved,
    resetCurrentProfile,
    setCurrentProfile,
    fetchProfiles,
    fetchProfile,
    saveProfile,
    deleteProfile,
  }

})
