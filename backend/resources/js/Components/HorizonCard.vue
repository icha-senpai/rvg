<template>
  <div class="hz-card hz-stack">

    <!-- Optional Title -->
    <div v-if="title" class="flex items-center gap-3">
      
      <!-- Accent Dot -->
      <div
        class="w-2 h-2 rounded-full mt-1"
        style="background: var(--color-horizon-blue-light);"
      ></div>

      <!-- Title -->
      <h3 class="hz-title-md text-horizon-white">
        {{ title }}
      </h3>

    </div>

    <!-- Content -->
    <slot />

  </div>
</template>

<script setup>
defineProps({
  title: String
});
</script>
