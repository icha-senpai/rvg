<template>
  <div
    class="w-full sticky top-0 hz-container-wide px-6 py-4 z-40
           bg-(--horizon-card) hz-scanlines hz-neon-edges
           rounded-2xl backdrop-blur-md
           flex justify-between items-center"
  >
    <!-- Left side -->
    <div class="flex items-center gap-4">
      <!-- Accent dot -->
      <div
        class="w-3 h-3 rounded-full"
        style="background: var(--color-horizon-blue-light);"
      ></div>

      <!-- Label -->
      <span class="hz-title-md text-horizon-white">
        {{ label }}
      </span>
    </div>

    <!-- Right side -->
    <div class="hz-title-lg text-horizon-white">
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
