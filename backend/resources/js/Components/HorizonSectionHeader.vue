<template>
  <div class="hz-stack-sm mb-6">
    
    <!-- Label (Overline) -->
    <div class="hz-section-label">
      {{ label }}
    </div>

    <!-- Title -->
    <h2 class="hz-title-lg text-horizon-white">
      {{ title }}
    </h2>

  </div>
</template>

<script setup>
defineProps({
  label: String,
  title: String,
});
</script>








