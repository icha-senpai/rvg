<script setup>
import { computed } from 'vue'

const props = defineProps({
  variant: {
    type: String,
    default: 'neutral',
  },
  size: {
    type: String,
    default: 'sm',
  },
  uppercase: {
    type: Boolean,
    default: false,
  },
})

const sizeClasses = {
  xs: 'px-2.5 py-1 text-[10px]',
  sm: 'px-3 py-1 text-xs',
  md: 'px-4 py-1.5 text-sm',
}

const variantClasses = {
  neutral: 'border border-white/[0.055] bg-white/[0.042] text-[color:var(--horizon-text-primary)]',
  muted: 'border border-white/[0.055] bg-white/[0.024] text-text-secondary',
  info: 'border border-[color:var(--horizon-sunset-blue)]/25 bg-[color:var(--horizon-sunset-blue)]/10 text-horizon-white',
  accent: 'border border-[color:var(--horizon-sunset-magenta)]/25 bg-[color:var(--horizon-sunset-magenta)]/10 text-horizon-white',
  success: 'border border-emerald-300/25 bg-emerald-300/10 text-emerald-100',
  warning: 'border border-amber-300/25 bg-amber-300/10 text-amber-100',
  danger: 'border border-red-300/25 bg-red-300/10 text-red-100',
}

const classes = computed(() => [
  'inline-flex items-center rounded-full font-semibold',
  props.uppercase ? 'uppercase tracking-[0.16em]' : '',
  sizeClasses[props.size] ?? sizeClasses.sm,
  variantClasses[props.variant] ?? variantClasses.neutral,
])
</script>

<template>
  <span :class="classes">
    <slot />
  </span>
</template>
