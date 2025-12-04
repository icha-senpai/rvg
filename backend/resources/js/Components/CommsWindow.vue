<template>
  <div class="rounded-2xl p-6 hz-holo-light hz-neon-edges hz-elev-3 hz-overlay-blue space-y-3">
    <h3 class="hz-h4 text-horizon-white">{{ sender }}</h3>
    <p class="hz-body">{{ message }}</p>
    <div class="hz-divider-blue"></div>
    <div class="hz-caption text-right">{{ timestamp }}</div>
  </div>
</template>

<script setup>
defineProps({
  sender: String,
  message: String,
  timestamp: String,
});
</script>
