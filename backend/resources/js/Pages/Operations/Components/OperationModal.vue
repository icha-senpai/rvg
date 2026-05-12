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
      class="absolute inset-0 bg-black/75 backdrop-blur-md"
      @click.self="requestClose"
    ></div>

    <!-- Ambient glow -->
    <div class="pointer-events-none absolute inset-0 overflow-hidden">
      <div class="absolute left-1/2 top-8 h-72 w-72 -translate-x-1/2 rounded-full bg-[color:var(--horizon-sunset-blue)]/20 blur-3xl"></div>
      <div class="absolute bottom-8 right-12 h-80 w-80 rounded-full bg-[color:var(--horizon-sunset-magenta)]/16 blur-3xl"></div>
    </div>

    <!-- Modal shell -->
    <section
      :class="[
        'relative z-50 flex max-h-[92vh] w-full max-w-6xl flex-col overflow-hidden rounded-[2rem]',
        'border border-[color:var(--horizon-sunset-indigo)]/45',
        'bg-[linear-gradient(135deg,var(--horizon-void-700),var(--horizon-void-900))]',
        'shadow-[0_0_72px_rgba(67,56,202,0.28)]',
        closing ? 'hz-animate-modal-out' : 'hz-animate-modal-in',
      ]"
      role="dialog"
      aria-modal="true"
    >
      <!-- Decorative scanlines -->
      <div class="pointer-events-none absolute inset-0 opacity-40">
        <div class="absolute left-8 top-0 h-px w-56 bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-blue)] to-transparent"></div>
        <div class="absolute bottom-0 right-10 h-px w-72 bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-magenta)] to-transparent"></div>
      </div>

      <!-- Header -->
      <header class="relative shrink-0 border-b border-[color:var(--horizon-sunset-blue)]/20 bg-white/[0.025] px-5 py-4 md:px-6">
        <div class="flex items-start justify-between gap-4">
          <div class="min-w-0 flex-1">
            <slot name="header" />
          </div>

          <button
            type="button"
            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-white/10 bg-white/[0.035] text-lg font-bold text-text-secondary transition hover:border-[color:var(--horizon-sunset-magenta)]/35 hover:bg-[color:var(--horizon-sunset-magenta)]/10 hover:text-horizon-white"
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
        class="relative shrink-0 border-t border-[color:var(--horizon-sunset-blue)]/20 bg-white/[0.025] px-5 py-4 md:px-6"
      >
        <slot name="footer" />
      </footer>
    </section>
  </div>
</template>