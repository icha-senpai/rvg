<template>
  <div class="hz-card hz-shadow-soft hz-stack">

    <!-- Header -->
    <div class="flex justify-between items-start">
      <h3 class="hz-title-md text-horizon-white">
        {{ title }}
      </h3>

      <div class="px-3 py-1 rounded-full bg-[var(--color-bg-elevated)] text-[var(--color-horizon-blue-light)] text-xs font-semibold">
        {{ status }}
      </div>
    </div>

    <!-- Description -->
    <p class="hz-body hz-text-soft">
      {{ description }}
    </p>

    <!-- Mission timings -->
    <div class="grid grid-cols-2 gap-4">

      <div class="hz-stack-sm">
        <div class="hz-section-label">Start</div>
        <div class="hz-title-md text-horizon-white">{{ start }}</div>
      </div>

      <div class="hz-stack-sm">
        <div class="hz-section-label">ETA</div>
        <div class="hz-title-md text-horizon-white">{{ eta }}</div>
      </div>

    </div>

    <!-- Divider -->
    <div class="border-t border-[var(--color-bg-hover)] my-2"></div>

    <!-- Slot for actions or footer -->
    <slot />

  </div>
</template>

<script setup>
defineProps({
  title: String,
  description: String,
  start: String,
  eta: String,
  status: String,
});
</script>
