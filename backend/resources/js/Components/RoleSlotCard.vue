<template>
  <div class="p-4 bg-bg-elevated rounded-xl hz-rim hz-hover-shallow hz-motion flex items-center justify-between">
    <div>
      <div class="hz-h5">{{ role }}</div>
      <div class="hz-caption">{{ count }}/{{ max }}</div>
    </div>

    <div :class="['hz-accent-dot', available ? 'bg-green-400/70' : 'bg-red-400/70']"></div>
  </div>
</template>

<script setup>
defineProps({
  role: String,
  count: Number,
  max: Number,
  available: Boolean,
});
</script>
