<template>
  <div class="hz-stack-sm">
    <label v-if="label" class="hz-section-label">
      {{ label }}
    </label>

    <select
      v-if="type === 'select'"
      v-model="model"
      v-bind="$attrs"
      class="hz-input cursor-pointer"
    >
      <option
        v-for="opt in options"
        :key="String(opt.value)"
        :value="opt.value"
      >
        {{ opt.label }}
      </option>
    </select>

    <textarea
      v-else-if="type === 'textarea'"
      v-model="model"
      v-bind="$attrs"
      :placeholder="placeholder"
      class="hz-textarea hz-input"
    ></textarea>

    <input
      v-else
      v-model="model"
      v-bind="$attrs"
      :type="type"
      :placeholder="placeholder"
      class="hz-input"
    />
  </div>
</template>

<script setup>
defineOptions({
  inheritAttrs: false,
})

const model = defineModel()

defineProps({
  label: String,
  placeholder: String,
  type: { type: String, default: 'text' },
  options: {
    type: Array,
    default: () => [],
  },
})
</script>
