<script setup>
const props = defineProps({
  label: {
    type: String,
    default: '',
  },
  helper: {
    type: String,
    default: '',
  },
  panelClass: {
    type: String,
    default: '',
  },
  labelClass: {
    type: String,
    default: '',
  },
})
</script>

<template>
  <div :class="['hz-surface-welcome rounded-[1.25rem] border border-white/[0.055] p-4', panelClass]">
    <label
      v-if="label"
      :class="['mb-2 block text-xs font-bold uppercase tracking-[0.18em] text-text-muted', labelClass]"
    >
      {{ label }}
    </label>

    <slot />

    <p
      v-if="helper"
      class="mt-2 text-xs text-text-muted"
    >
      {{ helper }}
    </p>
  </div>
</template>
