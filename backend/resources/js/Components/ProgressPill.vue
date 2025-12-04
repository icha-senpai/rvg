<template>
  <div :class="['px-3 py-1 rounded-full hz-motion text-sm font-semibold hz-text-glow', variantClasses[variant]]">
    <slot />
  </div>
</template>

<script setup>
defineProps({
  variant: { type: String, default: 'blue' }
});

const variantClasses = {
  blue: 'bg-horizon-blue/20 text-horizon-blue-light border border-horizon-blue/50',
  green: 'bg-green-400/20 text-green-300 border border-green-300/40',
  red: 'bg-red-500/20 text-red-300 border border-red-400/40',
  yellow: 'bg-yellow-400/20 text-yellow-300 border border-yellow-300/40',
};
</script>
