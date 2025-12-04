<template>
  <div class="bg-bg-surface p-6 rounded-2xl hz-elev-3 hz-overlay-light hz-trim-blue hz-lift">
    <div v-if="title" class="flex items-center gap-3 mb-4">
      <div class="hz-accent-dot"></div>
      <h3 class="hz-h3 text-horizon-white">{{ title }}</h3>
    </div>

    <slot />
  </div>
</template>

<script setup>
defineProps({
  title: String
});
</script>
