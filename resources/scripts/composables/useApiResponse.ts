/**
 * useApiResponse
 *
 * Shared error-extraction logic for API call failures.
 * Extracted from the repeated try/catch blocks in LoadTripIndexView,
 * WarehouseItemIndexView, TruckIndexView, TruckDetailView,
 * TruckMaintenancePlannerView, and FleetExpenseModal.
 *
 * Usage:
 *   const { extractErrorMessage } = useApiResponse()
 *   try { await store.createTrip(data) }
 *   catch (error: any) {
 *     const message = extractErrorMessage(error, 'Failed to create load trip')
 *     await dialogStore.openDialog({ title: 'Error', message, variant: 'danger', hideNoButton: true })
 *   }
 */

export function useApiResponse() {
  /**
   * Extracts a human-readable error message from an Axios error response.
   *
   * Priority:
   * 1. Laravel validation errors object (422) — joins all field messages
   * 2. Top-level `message` field from the response
   * 3. The provided fallback message
   */
  const extractErrorMessage = (error: any, fallback: string = 'An error occurred'): string => {
    const serverErrors = error?.response?.data?.errors
    const serverMessage = error?.response?.data?.message

    if (serverErrors) {
      const fieldMessages = Object.values(serverErrors).flat() as string[]
      return fieldMessages.join('\n') || serverMessage || fallback
    }

    if (serverMessage) {
      return serverMessage
    }

    return fallback
  }

  return {
    extractErrorMessage,
  }
}
