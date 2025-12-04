<template>
  <div class="p-4 bg-bg-surface rounded-xl hz-hover-shallow hz-motion hz-trim flex items-center justify-between">
    <div>
      <div class="hz-h5 text-horizon-white">{{ title }}</div>
      <div class="hz-caption">{{ subtitle }}</div>
    </div>
    <div class="hz-accent-line"></div>
  </div>
</template>

<script setup>
defineProps({
  title: String,
  subtitle: String,
});
</script>
