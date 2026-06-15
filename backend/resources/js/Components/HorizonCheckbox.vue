<script setup>
import { computed, useAttrs } from 'vue'

defineOptions({
  inheritAttrs: false,
})

const props = defineProps({
  modelValue: {
    type: [Boolean, Array, String, Number, null],
    default: false,
  },
  value: {
    type: [Boolean, String, Number, Object, null],
    default: undefined,
  },
  label: {
    type: String,
    default: '',
  },
  description: {
    type: String,
    default: '',
  },
  variant: {
    type: String,
    default: 'checkbox',
  },
  disabled: {
    type: Boolean,
    default: false,
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

const emit = defineEmits(['update:modelValue', 'change'])
const attrs = useAttrs()

function valuesMatch(left, right) {
  if (left === right) {
    return true
  }

  if (left === null || left === undefined || right === null || right === undefined) {
    return false
  }

  return String(left) === String(right)
}

const isArrayModel = computed(() => Array.isArray(props.modelValue))

const checked = computed(() => {
  if (isArrayModel.value) {
    return props.modelValue.some(item => valuesMatch(item, props.value))
  }

  return Boolean(props.modelValue)
})

const wrapperClasses = computed(() => {
  const base = 'group relative rounded-2xl border border-white/[0.055] bg-white/[0.024] text-text-secondary transition'
  const state = props.disabled
    ? 'cursor-not-allowed opacity-60'
    : 'cursor-pointer hover:border-white/[0.09] hover:bg-white/[0.045]'
  const layout = props.variant === 'toggle'
    ? 'flex items-center justify-between gap-4 px-4 py-3'
    : 'flex items-start gap-3 px-4 py-3'

  return [base, state, layout, props.panelClass]
})

const labelClasses = computed(() => [
  props.variant === 'toggle' ? 'text-sm font-semibold text-horizon-white' : 'text-sm font-semibold text-text-secondary',
  props.labelClass,
])

function handleChange(event) {
  let nextValue

  if (isArrayModel.value) {
    const current = [...props.modelValue]
    const existingIndex = current.findIndex(item => valuesMatch(item, props.value))

    if (event.target.checked && existingIndex === -1) {
      current.push(props.value)
    }

    if (!event.target.checked && existingIndex !== -1) {
      current.splice(existingIndex, 1)
    }

    nextValue = current
  } else {
    nextValue = event.target.checked
  }

  emit('update:modelValue', nextValue)
  emit('change', nextValue)
}
</script>

<template>
  <label :class="wrapperClasses">
    <input
      v-bind="attrs"
      type="checkbox"
      class="peer sr-only"
      :checked="checked"
      :disabled="disabled"
      @change="handleChange"
    >

    <template v-if="variant === 'toggle'">
      <span class="min-w-0 flex-1">
        <span v-if="label || $slots.default" :class="labelClasses">
          <slot>{{ label }}</slot>
        </span>

        <span
          v-if="description"
          class="mt-1 block text-xs text-text-muted"
        >
          {{ description }}
        </span>
      </span>

      <span
        class="relative inline-flex h-7 w-12 shrink-0 items-center rounded-full border transition"
        :class="checked
          ? 'border-[color:var(--horizon-sunset-blue)]/45 bg-[color:var(--horizon-sunset-blue)]/18'
          : 'border-white/[0.1] bg-black/20'"
      >
        <span
          class="absolute h-5 w-5 rounded-full shadow-[0_6px_18px_rgba(0,0,0,0.35)] transition"
          :class="checked
            ? 'translate-x-6 bg-[color:var(--horizon-sunset-blue)]'
            : 'translate-x-1 bg-white/80'"
        />
      </span>
    </template>

    <template v-else>
      <span
        class="relative mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-md border transition"
        :class="checked
          ? 'border-[color:var(--horizon-sunset-blue)]/55 bg-[color:var(--horizon-sunset-blue)]/18 text-[color:var(--horizon-sunset-blue)]'
          : 'border-white/[0.14] bg-black/15 text-transparent'"
      >
        <svg
          viewBox="0 0 20 20"
          class="h-3.5 w-3.5"
          fill="currentColor"
          aria-hidden="true"
        >
          <path
            fill-rule="evenodd"
            d="M16.704 5.29a1 1 0 0 1 .006 1.414l-7.25 7.312a1 1 0 0 1-1.42 0L3.29 9.267a1 1 0 1 1 1.414-1.414l4.046 4.045 6.54-6.601a1 1 0 0 1 1.414-.006Z"
            clip-rule="evenodd"
          />
        </svg>
      </span>

      <span class="min-w-0 flex-1">
        <span v-if="label || $slots.default" :class="labelClasses">
          <slot>{{ label }}</slot>
        </span>

        <span
          v-if="description"
          class="mt-1 block text-xs text-text-muted"
        >
          {{ description }}
        </span>
      </span>
    </template>
  </label>
</template>
