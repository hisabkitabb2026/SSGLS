<script setup lang="ts">
import type { Component } from 'vue'
import { computed } from 'vue'

type Accent = 'primary' | 'blue' | 'green' | 'purple' | 'yellow' | 'red'

interface Props {
  iconComponent: Component
  loading?: boolean
  route: string
  label: string
  large?: boolean
  accent?: Accent
  trend?: 'up' | 'down' | 'flat'
  trendValue?: string
}

const props = withDefaults(defineProps<Props>(), {
  loading: false,
  large: false,
  accent: 'primary',
  trend: undefined,
  trendValue: undefined,
})

/**
 * Accent → Tailwind class maps.
 * Each accent provides:
 *  - iconBg: tinted background for the icon circle
 *  - iconText: text color for the icon
 *  - trendUp / trendDown: colors for trend indicators
 */
const ACCENT_MAP: Record<Accent, { iconBg: string; iconText: string }> = {
  primary: {
    iconBg: 'bg-primary-50',
    iconText: 'text-primary-500',
  },
  blue: {
    iconBg: 'bg-status-blue/10',
    iconText: 'text-status-blue',
  },
  green: {
    iconBg: 'bg-status-green/10',
    iconText: 'text-status-green',
  },
  purple: {
    iconBg: 'bg-status-purple/10',
    iconText: 'text-status-purple',
  },
  yellow: {
    iconBg: 'bg-status-yellow/10',
    iconText: 'text-status-yellow',
  },
  red: {
    iconBg: 'bg-status-red/10',
    iconText: 'text-status-red',
  },
}

const accentClasses = computed(() => ACCENT_MAP[props.accent])

const trendIcon = computed(() => {
  if (props.trend === 'up') return 'ArrowTrendingUpIcon'
  if (props.trend === 'down') return 'ArrowTrendingDownIcon'
  return null
})

const trendClass = computed(() => {
  if (props.trend === 'up') return 'text-status-green'
  if (props.trend === 'down') return 'text-status-red'
  return 'text-muted'
})
</script>

<template>
  <!-- ── Loaded state ─────────────────────────────────────────── -->
  <router-link
    v-if="!loading"
    class="group relative flex items-center justify-between p-5 bg-surface rounded-xl shadow-sm border border-line-light hover:shadow-md hover:border-line-default transition-all duration-200 xl:p-6"
    :class="{ 'sm:col-span-2': large }"
    :to="route"
  >
    <!-- Left: value + label + trend -->
    <div class="min-w-0 flex-1">
      <span
        class="block text-2xl font-bold leading-tight text-heading xl:text-3xl"
      >
        <slot />
      </span>
      <span class="block mt-1 text-sm font-medium leading-tight text-muted xl:text-base">
        {{ label }}
      </span>

      <!-- Trend indicator -->
      <span
        v-if="trend && trendValue"
        class="mt-2 inline-flex items-center gap-1 text-xs font-semibold"
        :class="trendClass"
      >
        <BaseIcon v-if="trendIcon" :name="trendIcon" class="w-3.5 h-3.5" />
        {{ trendValue }}
      </span>
    </div>

    <!-- Right: icon in tinted circle -->
    <div
      class="flex items-center justify-center w-12 h-12 rounded-xl shrink-0 transition-transform group-hover:scale-110 xl:w-14 xl:h-14"
      :class="accentClasses.iconBg"
    >
      <component
        :is="iconComponent"
        class="w-6 h-6 xl:w-7 xl:h-7"
        :class="accentClasses.iconText"
      />
    </div>
  </router-link>

  <!-- ── Loading skeleton ─────────────────────────────────────── -->
  <BaseContentPlaceholders
    v-else
    :rounded="true"
    class="relative flex items-center justify-between w-full p-5 bg-surface rounded-xl border border-line-light xl:p-6"
    :class="{ 'sm:col-span-2': large }"
  >
    <div class="flex-1">
      <BaseContentPlaceholdersText
        class="h-7 w-24 xl:h-8"
        :lines="1"
      />
      <BaseContentPlaceholdersText class="mt-2 h-3 w-20 xl:h-4" :lines="1" />
    </div>
    <div class="flex items-center justify-center w-12 h-12 rounded-xl xl:w-14 xl:h-14">
      <BaseContentPlaceholdersBox
        :circle="true"
        class="w-10 h-10 xl:w-12 xl:h-12"
      />
    </div>
  </BaseContentPlaceholders>
</template>
