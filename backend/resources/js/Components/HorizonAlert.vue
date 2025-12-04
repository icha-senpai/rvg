<template>
  <div :class="['p-4 rounded-xl hz-panel hz-overlay-light border-l-4', variantClasses[variant]]">
    <div class="hz-h5 mb-1">{{ title }}</div>
    <div class="hz-body">{{ message }}</div>
  </div>
</template>

<script setup>
defineProps({
  title: String,
  message: String,
  variant: { type: String, default: 'info' }
});

const variantClasses = {
  info: 'border-horizon-blue text-text-secondary',
  warning: 'border-yellow-400 text-yellow-300',
  danger: 'border-red-500 text-red-400',
  success: 'border-green-400 text-green-300',
};
</script>
