<template>
  <div class="hz-select-container relative z-[9999]" ref="container">
    <label
      v-if="label"
      class="hz-label mb-1 block text-[var(--color-text-soft)]"
    >
      {{ label }}
    </label>

    <HorizonButton
      type="button"
      variant="ghost"
      size="md"
      class="hz-select-button flex w-full items-center justify-between rounded-lg border border-[var(--color-bg-hover)] bg-horizon-blue-dark px-3 py-2 font-normal text-[var(--color-text-primary)] transition hover:border-[var(--color-horizon-blue)] focus:outline-none focus:ring-2 focus:ring-[var(--color-horizon-blue)]"
      @click="toggle"
    >
      <span class="truncate">
        {{ selectedLabel }}
      </span>

      <svg
        class="h-4 w-4 shrink-0 transition-transform"
        :class="{ 'rotate-180': open }"
        fill="var(--color-text-primary)"
        viewBox="0 0 24 24"
      >
        <path d="M7 10l5 5 5-5z" />
      </svg>
    </HorizonButton>

    <transition name="fade-scale">
      <ul
        v-if="open"
        class="hz-select-menu absolute left-0 top-full z-[9999] mt-1 max-h-64 w-full overflow-y-auto rounded-lg border border-[var(--color-bg-hover)] bg-[var(--color-bg-surface)] shadow-[0_18px_50px_rgba(0,0,0,0.45)]"
      >
        <li
          v-for="opt in options"
          :key="opt.value"
          class="cursor-pointer px-3 py-2 text-[var(--color-text-primary)] hover:bg-[var(--color-horizon-blue-20)]"
          :class="{ 'bg-[var(--color-bg-elevated)]': isSelected(opt.value) }"
          @click="choose(opt.value)"
        >
          {{ opt.label }}
        </li>
      </ul>
    </transition>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import HorizonButton from '@/Components/HorizonButton.vue'

const props = defineProps({
  modelValue: [String, Number, Array, null],
  options: { type: Array, required: true },
  label: { type: String, default: '' },
  multiple: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

const open = ref(false)
const container = ref(null)

const model = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value),
})

const selectedLabel = computed(() => {
  if (props.multiple) {
    const selected = Array.isArray(model.value) ? model.value : []

    const labels = selected
      .map(value => props.options.find(option => option.value === value)?.label)
      .filter(Boolean)

    return labels.length ? labels.join(', ') : 'Select...'
  }

  const match = props.options.find(option => option.value === model.value)

  return match ? match.label : 'Select...'
})

function isSelected(value) {
  if (props.multiple) {
    return Array.isArray(model.value) && model.value.includes(value)
  }

  return model.value === value
}

function toggle() {
  open.value = !open.value
}

function choose(value) {
  if (props.multiple) {
    const current = Array.isArray(model.value) ? [...model.value] : []
    const index = current.indexOf(value)

    if (index === -1) {
      current.push(value)
    } else {
      current.splice(index, 1)
    }

    model.value = current
    return
  }

  model.value = value
  open.value = false
}

function handleClickOutside(event) {
  if (!container.value) return
  if (!container.value.contains(event.target)) open.value = false
}

onMounted(() => {
  document.addEventListener('mousedown', handleClickOutside)
})

onBeforeUnmount(() => {
  document.removeEventListener('mousedown', handleClickOutside)
})
</script>

<style>
.fade-scale-enter-active,
.fade-scale-leave-active {
  transition: all 150ms ease;
}

.fade-scale-enter-from {
  opacity: 0;
  transform: scale(0.98);
}

.fade-scale-leave-to {
  opacity: 0;
  transform: scale(0.98);
}
</style>