<script setup>
import { ref, computed, onMounted, onBeforeUnmount, nextTick } from 'vue'
import HorizonButton from '@/Components/HorizonButton.vue'

const props = defineProps({
  title: { type: String, default: 'Confirm Action' },
  message: { type: String, default: '' },
  confirmLabel: { type: String, default: 'Confirm' },
  cancelLabel: { type: String, default: 'Cancel' },
  variant: { type: String, default: 'default' },

  requiresTextInput: { type: Boolean, default: false },
  textInputLabel: { type: String, default: 'Type "confirm" to proceed' },
  textInputPlaceholder: { type: String, default: 'confirm' },
  confirmText: { type: String, default: '' },

  closeOnConfirm: { type: Boolean, default: true },
})

const emit = defineEmits(['confirm', 'cancel', 'close'])

const open = ref(false)
const textInput = ref('')
const resolving = ref(false)
const textInputRef = ref(null)

const variantConfig = computed(() => {
  const configs = {
    default: {
      icon: '➤',
      iconClass: 'text-[var(--color-horizon-blue-light)]',
      confirmVariant: 'primary',
    },
    danger: {
      icon: '⚠',
      iconClass: 'text-[var(--color-state-danger)]',
      confirmVariant: 'danger',
    },
    success: {
      icon: '✓',
      iconClass: 'text-[var(--color-state-success)]',
      confirmVariant: 'primary',
    },
    warning: {
      icon: '▲',
      iconClass: 'text-[var(--color-state-warn)]',
      confirmVariant: 'primary',
    },
  }

  return configs[props.variant] || configs.default
})

const canConfirm = computed(() => {
  if (resolving.value) return false

  if (!props.requiresTextInput) return true

  const trimmed = textInput.value.trim()
  if (!trimmed) return false

  // If confirmText is provided, require exact match (safety confirmation)
  // If confirmText is empty, any non-empty text is valid (arbitrary input)
  const confirmTextTrimmed = props.confirmText.trim()
  if (!confirmTextTrimmed) return true

  return trimmed.toLowerCase() === confirmTextTrimmed.toLowerCase()
})

async function show() {
  textInput.value = ''
  resolving.value = false
  open.value = true

  await nextTick()

  if (props.requiresTextInput) {
    textInputRef.value?.focus()
  }
}

function close() {
  open.value = false
  resolving.value = false

  emit('cancel')
  emit('close')
}

function finish() {
  resolving.value = false
}

function confirm() {
  if (!canConfirm.value) return

  resolving.value = true

  emit('confirm', {
    text: textInput.value,
    close,
    finish,
  })

  if (props.closeOnConfirm) {
    open.value = false
    resolving.value = false
  }
}

function handleKeydown(event) {
  if (!open.value) return

  if (event?.key === 'Escape') {
    close()
  }
}

onMounted(() => {
  window.addEventListener('keydown', handleKeydown)
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleKeydown)
})

defineExpose({ show, close, finish })
</script>

<template>
  <div
    v-if="open"
    class="hz-overlay flex items-center justify-center p-4"
    @click.self="close"
  >
    <div
      class="hz-modal hz-shadow-deep hz-stack hz-animate-pop w-full"
      style="max-width: 28rem; max-height: 85vh; overflow-y: auto;"
      role="dialog"
      aria-modal="true"
    >
      <div class="hz-row gap-3">
        <div
          class="text-2xl"
          :class="variantConfig.iconClass"
        >
          {{ variantConfig.icon }}
        </div>

        <div class="hz-title-md">
          {{ title }}
        </div>
      </div>

      <div
        v-if="message"
        class="hz-body hz-text-soft whitespace-pre-line"
      >
        {{ message }}
      </div>

      <slot name="summary" />

      <div
        v-if="requiresTextInput"
        class="hz-stack-xs"
      >
        <label class="hz-soft hz-text-muted">
          {{ textInputLabel }}
        </label>

        <input
          ref="textInputRef"
          v-model="textInput"
          type="text"
          :placeholder="textInputPlaceholder"
          class="hz-input"
          @keydown.enter.prevent="confirm"
        >
      </div>

      <div class="hz-row justify-end gap-2 pt-2">
        <HorizonButton
          variant="secondary"
          size="sm"
          :disabled="resolving"
          @click="close"
        >
          {{ cancelLabel }}
        </HorizonButton>

        <HorizonButton
          :variant="variantConfig.confirmVariant"
          size="sm"
          :disabled="!canConfirm"
          @click="confirm"
        >
          {{ resolving ? 'Working...' : confirmLabel }}
        </HorizonButton>
      </div>
    </div>
  </div>
</template>






