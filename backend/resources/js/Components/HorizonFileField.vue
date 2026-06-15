<script setup>
import { computed, ref, useAttrs, watch } from 'vue'
import HorizonButton from '@/Components/HorizonButton.vue'

defineOptions({
  inheritAttrs: false,
})

const props = defineProps({
  label: {
    type: String,
    default: '',
  },
  description: {
    type: String,
    default: '',
  },
  buttonLabel: {
    type: String,
    default: 'Choose File',
  },
  placeholder: {
    type: String,
    default: 'No file selected',
  },
  selectedName: {
    type: String,
    default: '',
  },
  accept: {
    type: String,
    default: '',
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  panelClass: {
    type: String,
    default: '',
  },
})

const emit = defineEmits(['change'])
const attrs = useAttrs()
const inputRef = ref(null)
const localSelectedName = ref('')

const displayName = computed(() => props.selectedName || localSelectedName.value || props.placeholder)
const rootClasses = computed(() => [
  'hz-surface-welcome rounded-[1.25rem] border border-white/[0.055] p-4',
  props.disabled ? 'opacity-60' : '',
  props.panelClass,
  attrs.class,
])
const inputAttrs = computed(() => {
  const { class: _class, ...rest } = attrs
  return rest
})

function openPicker() {
  if (props.disabled) return
  inputRef.value?.click()
}

function handleChange(event) {
  localSelectedName.value = event.target.files?.[0]?.name ?? ''
  emit('change', event)
}

function clear() {
  if (inputRef.value) {
    inputRef.value.value = ''
  }

  localSelectedName.value = ''
}

watch(() => props.selectedName, (value) => {
  if (!value) {
    localSelectedName.value = ''
  }
})

defineExpose({
  clear,
  inputRef,
  openPicker,
})
</script>

<template>
  <div class="space-y-2">
    <label
      v-if="label"
      class="block text-xs font-bold uppercase tracking-[0.16em] text-text-muted"
    >
      {{ label }}
    </label>

    <div :class="rootClasses">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0 flex-1">
          <div class="truncate text-sm font-semibold text-horizon-white">
            {{ displayName }}
          </div>

          <div
            v-if="description"
            class="mt-1 text-xs text-text-muted"
          >
            {{ description }}
          </div>
        </div>

        <HorizonButton
          type="button"
          variant="ghost"
          size="xs"
          :disabled="disabled"
          @click="openPicker"
        >
          {{ buttonLabel }}
        </HorizonButton>
      </div>

      <input
        ref="inputRef"
        v-bind="inputAttrs"
        type="file"
        :accept="accept"
        class="sr-only"
        :disabled="disabled"
        @change="handleChange"
      >
    </div>
  </div>
</template>
