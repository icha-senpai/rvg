<template>
  <button
    :class="[
      'hz-motion hz-press rounded-lg px-4 py-2 font-semibold',
      sizeClasses[size],
      variantClasses[variant],
      disabled ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'
    ]"
    :disabled="disabled"
  >
    <slot />
  </button>
</template>

<script setup>
const props = defineProps({
  variant: { type: String, default: 'primary' },
  size: { type: String, default: 'md' },
  disabled: { type: Boolean, default: false },
});

const variantClasses = {
  primary: 'bg-horizon-blue text-horizon-white hz-lift hz-shadow-base',
  outline: 'border border-horizon-blue text-horizon-blue hover:bg-bg-hover',
  ghost: 'text-horizon-blue hover:bg-bg-hover',
  neutral: 'bg-bg-elevated text-text-secondary hover:bg-bg-hover',
  danger: 'bg-red-600 text-white hover:bg-red-700',
};

const sizeClasses = {
  sm: 'text-sm py-1 px-3',
  md: 'text-base py-2 px-4',
  lg: 'text-lg py-3 px-5',
};
</script>
