import { ref } from 'vue'
import type { AxiosError } from 'axios'

export interface ApiError {
message: string
errors?: Record<string, string[]>
status: number
}

export function useApiError() {
const error = ref<ApiError | null>(null)
const isLoading = ref(false)

const handleError = (err: AxiosError): ApiError => {
const apiError: ApiError = {
message: 'An error occurred',
status: err.status || 500,
}

if (err.response?.data) {
const data = err.response.data as Record<string, any>
apiError.message = data.message || apiError.message
apiError.errors = data.errors
}

// Handle specific status codes
if (err.status === 401) {
apiError.message = 'Unauthorized. Please login again.'
} else if (err.status === 403) {
apiError.message = 'You do not have permission to perform this action.'
} else if (err.status === 404) {
apiError.message = 'Resource not found.'
} else if (err.status === 422) {
apiError.message = 'Validation failed.'
} else if (err.status === 500) {
apiError.message = 'Server error. Please try again later.'
}

error.value = apiError
return apiError
}

const clearError = () => {
error.value = null
}

return {
error,
isLoading,
handleError,
clearError,
}
}