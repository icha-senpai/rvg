<script setup>
import { ref, onMounted, onUnmounted } from 'vue'

const emit = defineEmits(['close'])

const closing = ref(false)

function requestClose() {
  if (closing.value) return

  closing.value = true

  setTimeout(() => {
    emit('close')
  }, 160)
}

function onKeydown(event) {
  if (event.key === 'Escape') {
    requestClose()
  }
}

onMounted(() => {
  document.addEventListener('keydown', onKeydown)
  document.body.classList.add('overflow-hidden')
})

onUnmounted(() => {
  document.removeEventListener('keydown', onKeydown)
  document.body.classList.remove('overflow-hidden')
})
</script>

<template>
  <div class="fixed inset-0 z-40 flex items-center justify-center p-3 md:p-6">
    <!-- Backdrop -->
    <div
      class="absolute inset-0 bg-black/70 backdrop-blur-sm"
      @click.self="requestClose"
    ></div>

    <!-- Modal shell -->
    <section
      :class="[
        'relative z-50 flex max-h-[92vh] w-full max-w-6xl flex-col overflow-hidden rounded-[1.75rem]',
        'border border-white/10',
        'bg-[linear-gradient(135deg,var(--horizon-void-700),var(--horizon-void-900))]',
        'shadow-2xl',
        closing ? 'hz-animate-modal-out' : 'hz-animate-modal-in',
      ]"
      role="dialog"
      aria-modal="true"
    >
      <!-- Header -->
      <header class="relative shrink-0 border-b border-white/10 px-5 py-4 md:px-6">
        <div class="flex items-start justify-between gap-4">
          <div class="min-w-0 flex-1">
            <slot name="header" />
          </div>

          <button
            type="button"
            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-lg font-bold text-text-secondary transition hover:bg-white/[0.045] hover:text-horizon-white"
            aria-label="Close operation modal"
            @click="requestClose"
          >
            ✕
          </button>
        </div>
      </header>

      <!-- Body -->
      <div class="relative flex-1 overflow-y-auto">
        <slot />
      </div>

      <!-- Footer -->
      <footer
        v-if="$slots.footer"
        class="relative shrink-0 border-t border-white/10 px-5 py-4 md:px-6"
      >
        <slot name="footer" />
      </footer>
    </section>
  </div>
</template>
