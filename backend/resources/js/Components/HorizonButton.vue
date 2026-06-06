<template>
  <button
    :class="[
      'hz-btn overflow-visible whitespace-normal sm:whitespace-nowrap leading-normal',
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
  success: 'hz-btn-success',
  secondary: 'hz-btn-secondary',
  outline: 'hz-btn-ghost',
  neutral: 'hz-btn-secondary',
  ghost: 'hz-btn-ghost',
  ghostDanger: 'hz-btn-ghost-danger',
  danger: 'hz-btn-danger',
};

/*
  Size mapping → Horizon size classes
*/
const sizeMap = {
  xs: 'min-h-8 hz-btn-xs',
  sm: 'min-h-9 hz-btn-sm',
  md: 'min-h-10',
  lg: 'min-h-12 hz-btn-lg',
};
</script>





