<template>
  <div class="hz-card-glow hz-shadow-soft hz-stack">

    <!-- Header -->
    <div class="flex justify-between items-start">
      <h3 class="hz-title-lg text-horizon-white">
        {{ title }}
      </h3>

      <div class="px-3 py-1 rounded-full bg-[var(--color-horizon-offwhite)] text-[var(--color-horizon-alloy)] text-xs font-semibold">
        {{ status }}
      </div>
    </div>

    <!-- DESCRIPTION -->
    <p class="text-base hz-text-soft leading-relaxed">
      {{ description }}
    </p>

    <!-- META: START + ETA -->
    <div class="grid grid-cols-2 gap-4 mt-3">

      <div class="hz-stack-sm">
        <div class="hz-section-label">Start</div>
        <div class="hz-caption text-horizon-offwhite">{{ startFormatted }}</div>
      </div>

    </div>

    <!-- Divider -->
    <div class="border-t border-horizon-offwhite my-3"></div>

    <!-- Slot (footer/actions) -->
    <slot />

  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  title: String,
  description: String,
  start: String,
  status: String,
})

function formatDate(dt) {
  if (!dt) return 'N/A'
  return new Date(dt).toLocaleString(undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

const startFormatted = computed(() => formatDate(props.start))
</script>

