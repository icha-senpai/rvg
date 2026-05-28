<template>
  <div
    class="hz-panel hz-shadow-soft flex items-center justify-between hover:bg-[var(--color-bg-hover)] transition-colors duration-150 cursor-pointer"
  >
    <!-- Left block -->
    <div class="hz-stack-sm">
      <div class="hz-title-md text-horizon-white">
        {{ title }}
      </div>
      <div class="hz-tiny hz-text-muted">
        {{ subtitle }}
      </div>
    </div>

    <!-- Accent line (visual indicator) -->
    <div
      class="w-1.5 h-6 rounded-full"
      style="background: var(--color-horizon-blue-light);"
    ></div>
  </div>
</template>

<script setup>
defineProps({
  title: String,
  subtitle: String,
});
</script>








