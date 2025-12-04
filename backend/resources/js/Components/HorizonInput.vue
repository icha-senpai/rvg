<template>
  <div class="hz-stack-sm">

    <!-- Label -->
    <label v-if="label" class="hz-section-label">
      {{ label }}
    </label>

    <!-- Input -->
    <input
      :type="type"
      v-model="model"
      :placeholder="placeholder"
      class="hz-input"
    />

  </div>
</template>

<script setup>
const model = defineModel();

defineProps({
  label: String,
  placeholder: String,
  type: { type: String, default: 'text' },
});
</script>
