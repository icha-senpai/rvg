<template>
  <div :class="['hz-alert hz-stack-sm', variantMap[variant]]">
    <div class="hz-title-md">{{ title }}</div>
    <div class="hz-body hz-text-soft">{{ message }}</div>
  </div>
</template>

<script setup>
defineProps({
  title: String,
  message: String,
  variant: { type: String, default: 'info' }
});

/*
  Map Vue variants -> Horizon alert classes
*/
const variantMap = {
  info: 'hz-alert-info',
  warning: 'hz-alert-warn',
  danger: 'hz-alert-danger',
  success: 'hz-alert-success',
};
</script>
