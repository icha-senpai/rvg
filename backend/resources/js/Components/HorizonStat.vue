<template>
  <div class="hz-card hz-shadow-soft hz-stack">

    <!-- Label -->
    <div class="hz-section-label">
      {{ label }}
    </div>

    <!-- Value -->
    <div class="hz-title-xl text-horizon-white">
      {{ value }}
    </div>

  </div>
</template>

<script setup>
defineProps({
  label: String,
  value: [String, Number],
});
</script>
