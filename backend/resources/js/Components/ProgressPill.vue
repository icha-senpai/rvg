<script setup>
import { computed } from 'vue'
import HorizonBadge from '@/Components/HorizonBadge.vue'

const props = defineProps({
  variant: { type: String, default: 'blue' },
  size: { type: String, default: 'sm' },
})

const mappedVariant = computed(() => ({
  blue: 'info',
  green: 'success',
  red: 'danger',
  yellow: 'warning',
}[props.variant] ?? 'neutral'))
</script>

<template>
  <HorizonBadge :variant="mappedVariant" :size="size">
    <slot />
  </HorizonBadge>
</template>







