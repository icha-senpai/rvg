<template>
  <div class="w-40 h-52 bg-bg-surface rounded-3xl hz-holo hz-elev-3 hz-overlay-blue hz-lift flex flex-col items-center justify-center text-center px-4">
    <img :src="logo" alt="" class="w-16 h-16 mb-3 hz-rim" />
    <h3 class="hz-h4 text-horizon-white mb-1">{{ name }}</h3>
    <p class="hz-caption">{{ motto }}</p>
  </div>
</template>

<script setup>
defineProps({
  name: String,
  motto: String,
  logo: String,
});
</script>
