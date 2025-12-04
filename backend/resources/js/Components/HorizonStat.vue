<template>
  <div class="bg-bg-elevated rounded-xl p-5 hz-elev-2 hz-overlay-blue hz-rim">
    <div class="hz-data-label mb-1">{{ label }}</div>
    <div class="hz-number-large">{{ value }}</div>
  </div>
</template>

<script setup>
defineProps({
  label: String,
  value: [String, Number]
});
</script>
