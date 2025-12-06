<template>
  <div class="hz-panel p-5 rounded-xl hz-overlay-light">
    <div class="flex justify-between items-center cursor-pointer" @click="open = !open">
      <div class="hz-title-sm text-horizon-white">{{ title }}</div>
      <div class="hz-caption">{{ open ? 'Hide' : 'Show' }}</div>
    </div>

    <div v-if="open" class="mt-4">
      <slot></slot>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
const props = defineProps({ title: String });
const open = ref(true);
</script>
