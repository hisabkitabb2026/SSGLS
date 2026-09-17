/**
 * useSettings — Replaces the old provide/inject `mergeSettings` pattern.
 *
 * Provides a typed, testable API for reading and writing company settings
 * from any settings component.
 */
import { computed } from 'vue'
import { useCompanyStore } from '@/scripts/stores/company.store'

export function useSettings() {
  const companyStore = useCompanyStore()

  /**
   * Get a raw setting value by key.
   */
  function getSetting(key: string): string | undefined {
    return companyStore.selectedCompanySettings[key]
  }

  /**
   * Get a boolean setting (stored as 'YES'/'NO').
   */
  function getSettingBool(key: string): boolean {
    return getSetting(key) === 'YES'
  }

  /**
   * Get a setting with a fallback default.
   */
  function getSettingOrDefault(key: string, defaultValue: string): string {
    return getSetting(key) ?? defaultValue
  }

  /**
   * Save settings to the backend and update the local store.
   */
  async function saveSettings(
    settings: Record<string, string>,
    message?: string,
  ): Promise<void> {
    await companyStore.updateCompanySettings({
      data: { settings },
      message,
    })
  }

  /**
   * Save a single setting key-value pair.
   */
  async function saveSetting(
    key: string,
    value: string,
    message?: string,
  ): Promise<void> {
    await saveSettings({ [key]: value }, message)
  }

  /**
   * Merge source settings into a target reactive object.
   * Only copies keys that already exist in the target.
   * This replaces the old `provide('utils', { mergeSettings })` pattern.
   */
  function mergeSettings(
    target: Record<string, unknown>,
    source: Record<string, string>,
  ): void {
    Object.keys(source).forEach((key) => {
      if (key in target) {
        target[key] = source[key]
      }
    })
  }

  /**
   * Reactive boolean computed for a YES/NO setting.
   * On set, immediately saves to the backend.
   */
  function useBoolSetting(key: string, message?: string) {
    return computed<boolean>({
      get: () => getSettingBool(key),
      set: async (newValue: boolean) => {
        const value = newValue ? 'YES' : 'NO'
        await saveSetting(key, value, message)
      },
    })
  }

  return {
    // Return a computed so consumers can watch reactivity when the store
    // replaces the entire settings object (e.g. during bootstrap).
    selectedCompanySettings: computed(() => companyStore.selectedCompanySettings),
    getSetting,
    getSettingBool,
    getSettingOrDefault,
    saveSettings,
    saveSetting,
    mergeSettings,
    useBoolSetting,
  }

}
