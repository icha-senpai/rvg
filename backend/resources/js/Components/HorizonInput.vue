<template>
  <div class="space-y-1">
    <label v-if="label" class="hz-data-label">{{ label }}</label>
    <input
      :type="type"
      v-model="model"
      :placeholder="placeholder"
      class="w-full px-3 py-2 rounded-lg bg-bg-elevated text-text-primary hz-input hz-motion"
    />
  </div>
</template>

<script setup>
const model = defineModel();

defineProps({
  label: String,
  placeholder: String,
  type: { type: String, default: 'text' }
});
</script>
