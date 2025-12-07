<template>
  <div class="hz-stack-sm">

    <!-- Label -->
    <label v-if="label" class="hz-section-label">
      {{ label }}
    </label>

    <!-- SELECT -->
    <select
      v-if="type === 'select'"
      v-model="model"
      class="hz-input cursor-pointer"
    >
      <option
        v-for="opt in options"
        :key="opt.value"
        :value="opt.value"
      >
        {{ opt.label }}
      </option>
    </select>

    <!-- TEXTAREA -->
    <textarea
      v-else-if="type === 'textarea'"
      v-model="model"
      :placeholder="placeholder"
      class="hz-textarea hz-input"
      rows="5"
    ></textarea>

    <!-- INPUT -->
    <input
      v-else
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

  // Only used if type === 'select'
  options: {
    type: Array,
    default: () => [],
  },
});
</script>
