<template>
  <div class="flex gap-4 items-start">

    <!-- Timeline Dot -->
    <div
      class="mt-1 w-2 h-2 rounded-full"
      style="background: var(--color-horizon-blue-light);"
    ></div>

    <!-- Content Block -->
    <div class="flex-1 hz-stack-sm">

      <!-- Title + Time -->
      <div class="flex justify-between items-baseline">
        <h4 class="hz-title-md">{{ title }}</h4>
        <span class="hz-tiny hz-text-muted">{{ time }}</span>
      </div>

      <!-- Detail -->
      <p class="hz-body">{{ detail }}</p>

    </div>
  </div>
</template>

<script setup>
defineProps({
  title: String,
  time: String,
  detail: String,
});
</script>








