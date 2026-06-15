<script setup>
import HorizonModal from '@/Components/HorizonModal.vue'

const emit = defineEmits(['close'])
</script>

<template>
  <HorizonModal
    close-label="Close operation modal"
    max-width-class="max-w-6xl"
    @close="emit('close')"
  >
    <template #header>
      <slot name="header" />
    </template>

    <slot />

    <template v-if="$slots.footer" #footer>
      <slot name="footer" />
    </template>
  </HorizonModal>
</template>
