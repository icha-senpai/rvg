<template>
  <div class="bg-bg-surface p-8 rounded-2xl hz-panel hz-vignette hz-overlay-light hz-trim-blue space-y-6">
    <h2 class="hz-h2 text-horizon-white hz-glyph">{{ title }}</h2>

    <div class="space-y-2">
      <div class="hz-data-label">Summary</div>
      <p class="hz-body">{{ summary }}</p>
    </div>

    <div class="hz-divider my-4"></div>

    <div>
      <div class="hz-data-label mb-2">Key Events</div>
      <ul class="space-y-1">
        <li v-for="item in events" :key="item" class="hz-body">{{ item }}</li>
      </ul>
    </div>

    <slot />
  </div>
</template>

<script setup>
defineProps({
  title: String,
  summary: String,
  events: Array,
});
</script>
