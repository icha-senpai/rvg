<template>
  <nav class="w-full bg-bg-surface hz-overlay-blue hz-elev-2 hz-bevel flex items-center justify-between px-6 py-4">
    <div class="flex items-center gap-3">
      <div class="hz-accent-dot"></div>
      <h1 class="hz-h4 text-horizon-white">{{ brand }}</h1>
    </div>

    <div class="flex items-center gap-4">
      <slot />
    </div>
  </nav>
</template>

<script setup>
defineProps({
  brand: String
});
</script>
