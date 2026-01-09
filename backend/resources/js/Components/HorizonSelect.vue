<template>
  <div class="hz-select-container relative" ref="container">
    <!-- LABEL -->
    <label
      v-if="label"
      class="hz-label block mb-1 text-[var(--color-text-soft)]"
    >
      {{ label }}
    </label>

    <!-- TRIGGER BUTTON -->
    <HorizonButton
      type="button"
      variant="ghost"
      size="md"
      class="hz-select-button w-full flex justify-between items-center font-normal
             bg-[var(--color-horizon-blue-20)] border border-[var(--color-bg-hover)]
             rounded-lg px-3 py-2 text-[var(--color-text-primary)]
             hover:border-[var(--color-horizon-blue)] transition
             focus:outline-none focus:ring-2 focus:ring-[var(--color-horizon-blue)]"
      @click="toggle"
    >
      <span>
        {{ selectedLabel }}
      </span>

      <!-- Dropdown Arrow -->
      <svg
        class="w-4 h-4 transition-transform"
        :class="{ 'rotate-180': open }"
        fill="var(--color-text-primary)"
        viewBox="0 0 24 24"
      >
        <path d="M7 10l5 5 5-5z" />
      </svg>
    </HorizonButton>

    <!-- DROPDOWN MENU -->
    <transition name="fade-scale">
      <ul
        v-if="open"
        class="hz-select-menu absolute z-50 w-full mt-1
               bg-[var(--color-bg-surface)] border border-[var(--color-bg-hover)]
               rounded-lg shadow-lg overflow-hidden"
      >
        <li
          v-for="opt in options"
          :key="opt.value"
          @click="choose(opt.value)"
          class="px-3 py-2 cursor-pointer hover:bg-[var(--color-horizon-blue-20)]
                 text-[var(--color-text-primary)]"
          :class="{ 'bg-[var(--color-bg-elevated)]': isSelected(opt.value) }"
        >
          {{ opt.label }}
        </li>
      </ul>
    </transition>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import HorizonButton from '@/Components/HorizonButton.vue';
const props = defineProps({
  modelValue: [String, Number, Array, null],
  options: { type: Array, required: true },
  label: { type: String, default: '' },
  multiple: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const open = ref(false);
const container = ref(null);

const model = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v),
});

const selectedLabel = computed(() => {
  if (props.multiple) {
    const selected = Array.isArray(model.value) ? model.value : [];
    const labels = selected
      .map((v) => props.options.find((o) => o.value === v)?.label)
      .filter(Boolean);
    return labels.length ? labels.join(', ') : 'Select...';
  }

  const match = props.options.find((o) => o.value === model.value);
  return match ? match.label : 'Select...';
});

function isSelected(value) {
  if (props.multiple) {
    return Array.isArray(model.value) && model.value.includes(value);
  }

  return model.value === value;
}

function toggle() {
  open.value = !open.value;
}

function choose(value) {
  if (props.multiple) {
    const current = Array.isArray(model.value) ? [...model.value] : [];
    const index = current.indexOf(value);
    if (index === -1) {
      current.push(value);
    } else {
      current.splice(index, 1);
    }
    model.value = current;
    return;
  }

  model.value = value;
  open.value = false;
}

function handleClickOutside(e) {
  if (!container.value) return;
  if (!container.value.contains(e.target)) open.value = false;
}

onMounted(() => {
  document.addEventListener('mousedown', handleClickOutside);
});

onBeforeUnmount(() => {
  document.removeEventListener('mousedown', handleClickOutside);
});
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
