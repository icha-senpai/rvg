<template>
  <div class="bg-bg-surface rounded-2xl p-6 hz-elev-3 hz-rim hz-overlay-light hz-lift hz-glyph">
    <div class="flex justify-between items-start mb-4">
      <h3 class="hz-h3 text-horizon-white">{{ title }}</h3>
      <div class="hz-progress-pill">{{ status }}</div>
    </div>

    <p class="hz-body mb-4">{{ description }}</p>

    <div class="grid grid-cols-2 gap-4 mt-4">
      <div>
        <div class="hz-data-label">Start</div>
        <div class="hz-number">{{ start }}</div>
      </div>
      <div>
        <div class="hz-data-label">ETA</div>
        <div class="hz-number">{{ eta }}</div>
      </div>
    </div>

    <div class="hz-divider-blue my-4"></div>

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
