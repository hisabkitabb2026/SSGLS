<script setup lang="ts">

interface Props {
  /** Note: original prop name was "sucess" (sic) — preserved for backwards compatibility */
  sucess?: boolean
}

withDefaults(defineProps<Props>(), {
  sucess: false,
})
</script>

<template>
  <span
    :class="[
      sucess ? 'bg-status-green/10 text-status-green' : 'bg-status-red/10 text-status-red',
      'px-2 py-1 text-sm font-normal text-center uppercase',
    ]"
  >
    <slot />
  </span>
</template>
