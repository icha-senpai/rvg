<script setup>
const props = defineProps({
  squadron: Object,
})
</script>

<template>
  <div class="hz-stack">
    <div class="hz-section-label">Overview</div>
    <p class="hz-soft whitespace-pre-line">
      {{ squadron?.description ?? 'No description provided.' }}
    </p>
  </div>
</template>
