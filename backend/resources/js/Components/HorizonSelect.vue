<template>
  <div
    ref="container"
    class="hz-select-container relative"
    :class="open ? 'z-[10050]' : 'z-10'"
  >
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
      class="hz-select-button flex w-full items-center justify-between rounded-lg px-3 py-2 font-normal text-[var(--color-text-primary)] transition hover:border-[color:var(--color-divider-subtle)] focus:outline-none focus:ring-2 focus:ring-[rgba(42,120,200,0.25)]"
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
      <div
        v-if="open"
        class="hz-popover-surface absolute left-0 z-[10060] max-h-64 w-full overflow-y-auto rounded-lg"
        :class="menuOpensUpward ? 'bottom-full mb-1' : 'top-full mt-1'"
      >
        <div
          v-if="searchable"
          class="hz-shell-header sticky top-0 z-[1] border-b p-2"
        >
          <input
            ref="searchInput"
            v-model="searchQuery"
            type="text"
            class="hz-input"
            :placeholder="searchPlaceholder"
            @keydown.esc.stop="open = false"
          />
        </div>

        <ul>
        <li
          v-for="opt in filteredOptions"
          :key="opt.value"
          class="cursor-pointer px-3 py-2 text-[var(--color-text-primary)] hover:bg-[color:var(--color-hover-frost)]"
          :class="{ 'bg-[color:var(--color-panel-active)]': isSelected(opt.value) }"
          @click="choose(opt.value)"
        >
          {{ opt.label }}
        </li>
          <li
            v-if="!filteredOptions.length"
            class="px-3 py-3 text-sm text-[var(--color-text-soft)]"
          >
            {{ emptyLabel }}
          </li>
        </ul>
      </div>
    </transition>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, nextTick } from 'vue'
import HorizonButton from '@/Components/HorizonButton.vue'

const props = defineProps({
  modelValue: [String, Number, Array, null],
  options: { type: Array, required: true },
  label: { type: String, default: '' },
  multiple: { type: Boolean, default: false },
  placeholder: { type: String, default: 'Select...' },
  searchable: { type: Boolean, default: false },
  searchPlaceholder: { type: String, default: 'Search...' },
  emptyLabel: { type: String, default: 'No matches found.' },
})

const emit = defineEmits(['update:modelValue'])

const open = ref(false)
const container = ref(null)
const menuOpensUpward = ref(false)
const searchInput = ref(null)
const searchQuery = ref('')

const model = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value),
})

function valuesMatch(left, right) {
  if (left === right) {
    return true
  }

  if (left === null || left === undefined || right === null || right === undefined) {
    return false
  }

  return String(left) === String(right)
}

const selectedLabel = computed(() => {
  if (props.multiple) {
    const selected = Array.isArray(model.value) ? model.value : []

    const labels = selected
      .map(value => props.options.find(option => valuesMatch(option.value, value))?.label)
      .filter(Boolean)

    return labels.length ? labels.join(', ') : props.placeholder
  }

  const match = props.options.find(option => valuesMatch(option.value, model.value))

  return match ? match.label : props.placeholder
})

const filteredOptions = computed(() => {
  const query = searchQuery.value.trim().toLowerCase()

  if (!props.searchable || !query) {
    return props.options
  }

  return props.options.filter(option => String(option.label ?? '')
    .toLowerCase()
    .includes(query))
})

function isSelected(value) {
  if (props.multiple) {
    return Array.isArray(model.value) && model.value.some(selectedValue => valuesMatch(selectedValue, value))
  }

  return valuesMatch(model.value, value)
}

function updateMenuDirection() {
  if (!container.value) return

  const rect = container.value.getBoundingClientRect()
  const maxMenuHeight = 256
  const menuOffset = 8
  const spaceBelow = window.innerHeight - rect.bottom
  const spaceAbove = rect.top

  menuOpensUpward.value = spaceBelow < (maxMenuHeight + menuOffset) && spaceAbove > spaceBelow
}

async function toggle() {
  open.value = !open.value

  if (open.value) {
    searchQuery.value = ''
    await nextTick()
    updateMenuDirection()

    if (props.searchable) {
      searchInput.value?.focus()
    }
  } else {
    searchQuery.value = ''
  }
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
  searchQuery.value = ''
}

function handleViewportChange() {
  if (!open.value) return
  updateMenuDirection()
}

function handleClickOutside(event) {
  if (!container.value) return
  if (!container.value.contains(event.target)) open.value = false
}

onMounted(() => {
  document.addEventListener('mousedown', handleClickOutside)
  window.addEventListener('resize', handleViewportChange)
  window.addEventListener('scroll', handleViewportChange, true)
})

onBeforeUnmount(() => {
  document.removeEventListener('mousedown', handleClickOutside)
  window.removeEventListener('resize', handleViewportChange)
  window.removeEventListener('scroll', handleViewportChange, true)
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




