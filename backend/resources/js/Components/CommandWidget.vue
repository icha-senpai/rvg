<template>
  <div class="bg-bg-elevated rounded-xl p-5 hz-float hz-overlay-blue hz-border hz-bracket">
    <h4 class="hz-h5 mb-2">{{ title }}</h4>

    <div class="hz-number-large mb-1">{{ stat }}</div>
    <div class="hz-caption">{{ label }}</div>

    <div class="mt-4 hz-accent-split"></div>

    <slot />
  </div>
</template>

<script setup>
defineProps({
  title: String,
  stat: [String, Number],
  label: String,
});
</script>
