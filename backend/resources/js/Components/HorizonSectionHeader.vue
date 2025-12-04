<template>
  <div class="mb-6">
    <div class="hz-overline mb-1">{{ label }}</div>
    <h2 class="hz-h2 text-horizon-white hz-glyph">{{ title }}</h2>
  </div>
</template>

<script setup>
defineProps({
  label: String,
  title: String
});
</script>
