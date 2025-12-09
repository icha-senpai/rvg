<template>
  <button
    :class="[
      'hz-btn',
      sizeMap[size],
      variantMap[variant],
      disabled ? 'hz-btn-disabled' : ''
    ]"
    :disabled="disabled"
  >
    <slot />
  </button>
</template>

<script setup>
defineProps({
  variant: { type: String, default: 'primary' },
  size: { type: String, default: 'md' },
  disabled: { type: Boolean, default: false },
});

/*
  Map variants → Horizon button styles
*/
const variantMap = {
  primary: 'hz-btn-primary',
  secondary: 'hz-btn-secondary',
  ghost: 'hz-btn-ghost',
  danger: 'hz-btn-danger',
};

/*
  Size mapping → Horizon size classes
*/
const sizeMap = {
  xs: 'hz-btn-xs',
  sm: 'hz-btn-sm',
  md: '',
  lg: 'hz-btn-lg',
};
</script>
