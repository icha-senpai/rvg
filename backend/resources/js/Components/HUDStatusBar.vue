<template>
  <div class="fixed top-0 w-full bg-bg-elevated hz-scanlines hz-neon-edges hz-overlay-light py-3 px-6 flex justify-between items-center z-40">
    <div class="flex items-center gap-3">
      <div class="hz-accent-dot"></div>
      <span class="hz-h5">{{ label }}</span>
    </div>
    <div class="hz-number">{{ value }}</div>
  </div>
</template>

<script setup>
defineProps({
  label: String,
  value: [String, Number],
});
</script>
