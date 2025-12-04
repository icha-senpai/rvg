<template>
  <div class="flex gap-4 items-start">
    <div class="hz-accent-dot mt-1"></div>
    <div class="flex-1">
      <div class="flex justify-between">
        <h4 class="hz-h5">{{ title }}</h4>
        <span class="hz-caption">{{ time }}</span>
      </div>
      <p class="hz-body-strong">{{ detail }}</p>
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
