<script setup>
import HorizonDrawer from '@/Components/HorizonDrawer.vue'

const emit = defineEmits(['close'])
</script>

<template>
  <HorizonDrawer close-label="Close operation drawer" @close="emit('close')">
    <template #header>
      <slot name="header" />
    </template>

    <slot />

    <template v-if="$slots.footer" #footer>
      <slot name="footer" />
    </template>
  </HorizonDrawer>
</template>








