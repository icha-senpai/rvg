<template>
  <div v-if="open" class="fixed inset-0 z-50 hz-curtain-heavy flex items-center justify-center">
    <div class="bg-bg-surface rounded-2xl p-8 w-full max-w-xl hz-holo hz-float-high">
      <slot />
    </div>
  </div>
</template>

<script setup>
defineProps({
  open: Boolean
});
</script>
