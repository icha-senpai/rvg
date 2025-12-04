<template>
  <div
    class="hz-panel hz-shadow-soft flex items-center justify-between cursor-pointer hover:bg-[var(--color-bg-hover)] transition-colors duration-150"
  >
    <!-- Role and count -->
    <div class="hz-stack-sm">
      <div class="hz-title-md text-horizon-white">
        {{ role }}
      </div>
      <div class="hz-tiny hz-text-muted">
        {{ count }}/{{ max }}
      </div>
    </div>

    <!-- Availability Dot -->
    <div
      class="w-3 h-3 rounded-full"
      :style="{
        background: available
          ? 'rgba(34, 197, 94, 0.75)'   // green-400-ish
          : 'rgba(239, 68, 68, 0.75)'   // red-400-ish
      }"
    ></div>

  </div>
</template>

<script setup>
defineProps({
  role: String,
  count: Number,
  max: Number,
  available: Boolean,
});
</script>
