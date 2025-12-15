<script setup>
const props = defineProps({
  squadron: Object,
})

const emit = defineEmits(['close'])
</script>

<template>
  <div class="hz-row-between">
    <div>
      <div class="hz-title-md">{{ squadron?.name }}</div>
      <div class="hz-text-soft">{{ squadron?.motto }}</div>
    </div>

    <button class="hz-btn hz-btn-ghost" @click="emit('close')">
      Close
    </button>
  </div>
</template>
