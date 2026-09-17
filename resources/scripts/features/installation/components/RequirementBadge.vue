<template>
  <span
    :class="[
      'inline-flex items-center justify-center h-6 w-6 rounded-full',
      ok
        ? 'bg-status-green/10 text-status-green'
        : 'bg-status-red/10 text-status-red',
    ]"
    :aria-label="ok ? 'OK' : 'Missing'"
  >
    <BaseIcon
      :name="ok ? 'CheckIcon' : 'XMarkIcon'"
      class="h-4 w-4"
    />
  </span>
</template>

<script setup lang="ts">
defineProps<{
  ok: boolean
}>()
</script>
