<template>
  <div
    v-if="store[storeProp].tax_included"
    class="flex items-center justify-between w-full"
  >
    <BaseContentPlaceholders v-if="isLoading">
      <BaseContentPlaceholdersText :lines="1" class="w-16 h-5" />
    </BaseContentPlaceholders>
    <label
      v-else
      class="text-sm font-semibold leading-5 text-muted uppercase"
    >
      {{ $t('quotations.net_total') }}
    </label>

    <BaseContentPlaceholders v-if="isLoading">
      <BaseContentPlaceholdersText :lines="1" class="w-16 h-5" />
    </BaseContentPlaceholders>

    <label
      v-else
      class="flex items-center justify-center m-0 text-lg text-heading uppercase"
    >
      <BaseFormatMoney
        :amount="store.getNetTotal"
        :currency="currency"
      />
    </label>
  </div>
</template>

<script setup lang="ts">
interface Store {
  getNetTotal: number
  [key: string]: any
}

interface Props {
  store: Store | null
  storeProp: string
  currency: Record<string, any> | string
  isLoading?: boolean
}

withDefaults(defineProps<Props>(), {
  storeProp: '',
  isLoading: false,
})
</script>
