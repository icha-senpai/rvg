<template>
  <div class="hz-panel hz-shadow-soft hz-stack">

    <!-- Title -->
    <h4 class="hz-title-md">
      {{ title }}
    </h4>

    <!-- Statistic -->
    <div class="hz-title-xl mt-1">
      {{ stat }}
    </div>

    <!-- Label -->
    <div class="hz-soft">
      {{ label }}
    </div>

    <!-- Divider -->
    <div class="border-t border-[var(--color-bg-hover)] my-3"></div>

    <!-- Slot -->
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








