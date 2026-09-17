<template>
  <div>
    <!-- Error display -->
    <Teleport v-if="error" to="body">
      <div class="fixed inset-0 z-50 pointer-events-none">
        <Transition
          name="fade"
          @after-leave="error = null"
        >
          <div
            v-if="error"
            class="fixed top-4 right-4 max-w-md pointer-events-auto"
          >
            <div class="bg-alert-error-bg border border-alert-error-text rounded-lg shadow-lg p-4">
              <div class="flex items-start gap-3">
                <BaseIcon name="ExclamationIcon" class="h-5 w-5 text-alert-error-text flex-shrink-0 mt-0.5" />
                <div class="flex-1">
                  <h3 class="font-semibold text-alert-error-text">{{ error.title }}</h3>
                  <p v-if="error.message" class="text-sm text-alert-error-text opacity-90 mt-1">
                    {{ error.message }}
                  </p>
                  <ul v-if="error.details?.length" class="mt-2 space-y-1">
                    <li
                      v-for="(detail, index) in error.details"
                      :key="index"
                      class="text-sm text-alert-error-text opacity-90"
                    >
                      • {{ detail }}
                    </li>
                  </ul>
                </div>
                <button
                  class="text-alert-error-text hover:opacity-75 flex-shrink-0"
                  @click="clearError"
                >
                  <BaseIcon name="XMarkIcon" class="h-5 w-5" />
                </button>
              </div>
            </div>
          </div>
        </Transition>
      </div>
    </Teleport>

    <!-- Slot for child content -->
    <slot />
  </div>
</template>

<script setup lang="ts">
import { ref, onErrorCaptured } from 'vue'

interface AppError {
  title: string
  message?: string
  details?: string[]
}

const error = ref<AppError | null>(null)

const setError = (appError: AppError) => {
  error.value = appError
  console.error('[Error Boundary]', appError)
}

const clearError = () => {
  error.value = null
}

onErrorCaptured((err) => {
  const errorMessage = err instanceof Error ? err.message : String(err)
  setError({
    title: 'Something went wrong',
    message: errorMessage,
  })
  return false
})

defineExpose({
  setError,
  clearError,
})
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>