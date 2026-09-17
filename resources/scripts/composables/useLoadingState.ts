import { ref } from 'vue'

export interface LoadingState {
isLoading: boolean
message?: string
}

export function useLoadingState(initialState = false) {
const isLoading = ref(initialState)
const message = ref('')

const startLoading = (msg = 'Loading...') => {
isLoading.value = true
message.value = msg
}

const stopLoading = () => {
isLoading.value = false
message.value = ''
}

const withLoading = async (
fn: () => Promise,
msg = 'Loading...'
): Promise => {
startLoading(msg)
try {
return await fn()
} finally {
stopLoading()
}
}

return {
isLoading,
message,
startLoading,
stopLoading,
withLoading,
}
}