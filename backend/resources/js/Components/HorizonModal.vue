<script setup>
import { onBeforeUnmount, onMounted, watch } from 'vue'

const props = defineProps({
  open: { type: Boolean, default: true },
  closeLabel: { type: String, default: 'Close modal' },
  showCloseButton: { type: Boolean, default: true },
  maxWidthClass: { type: String, default: 'max-w-4xl' },
  maxHeightClass: { type: String, default: 'sm:max-h-[88vh]' },
  panelClass: { type: String, default: '' },
  bodyClass: { type: String, default: 'relative flex-1 overflow-y-auto p-5' },
  footerClass: { type: String, default: 'hz-surface-welcome relative shrink-0 border-t border-[color:var(--horizon-sunset-blue)]/20 p-5' },
})

const emit = defineEmits(['close'])

function requestClose() {
  emit('close')
}

function syncBodyScrollLock(isOpen) {
  document.body.classList.toggle('overflow-hidden', Boolean(isOpen))
}

function handleKeydown(event) {
  if (!props.open) return

  if (event.key === 'Escape') {
    requestClose()
  }
}

onMounted(() => {
  window.addEventListener('keydown', handleKeydown)
  syncBodyScrollLock(props.open)
})

watch(() => props.open, (isOpen) => {
  syncBodyScrollLock(isOpen)
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleKeydown)
  document.body.classList.remove('overflow-hidden')
})
</script>

<template>
  <div
    v-if="open"
    class="fixed inset-0 z-[90] flex items-start justify-center overflow-y-auto bg-black/75 p-3 backdrop-blur-md sm:items-center sm:p-4"
    @click.self="requestClose"
  >
    <div
      :class="[
        'hz-surface-welcome relative z-10 my-auto flex max-h-[calc(100dvh-1.5rem)] w-full flex-col overflow-hidden rounded-[1.5rem] border border-white/[0.055] hz-animate-pop shadow-[0_24px_80px_rgba(0,0,0,0.45)] sm:rounded-[2rem]',
        maxWidthClass,
        maxHeightClass,
        panelClass,
      ]"
    >
      <div class="pointer-events-none absolute inset-0 opacity-40">
        <div class="absolute left-8 top-0 h-px w-56 bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-blue)] to-transparent"></div>
        <div class="absolute bottom-0 right-10 h-px w-72 bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-magenta)] to-transparent"></div>
      </div>

      <header class="hz-surface-welcome relative shrink-0 border-b border-[color:var(--horizon-sunset-blue)]/20 p-5">
        <div class="flex items-start justify-between gap-4">
          <div class="min-w-0 flex-1">
            <slot name="header" />
          </div>

          <button
            v-if="showCloseButton"
            type="button"
            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-white/[0.055] bg-white/[0.024] text-lg font-bold text-text-secondary transition hover:border-white/[0.09] hover:bg-white/[0.05] hover:text-horizon-white"
            :aria-label="closeLabel"
            @click="requestClose"
          >
            ✕
          </button>
        </div>
      </header>

      <div :class="bodyClass">
        <slot />
      </div>

      <footer
        v-if="$slots.footer"
        :class="footerClass"
      >
        <slot name="footer" />
      </footer>
    </div>
  </div>
</template>
