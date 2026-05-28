<template>
  <!-- Only render when open -->
  <div v-if="open" class="hz-overlay flex items-center justify-center">

    <!-- Modal Panel -->
    <div class="hz-modal hz-shadow-deep hz-stack">
      <slot />
    </div>

  </div>
</template>

<script setup>
defineProps({
  open: Boolean
});
</script>








