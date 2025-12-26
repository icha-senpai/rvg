<script setup>
import { ref, onMounted, onUnmounted } from 'vue'

// ----------------------
// EMITS
// ----------------------
const emit = defineEmits(['close'])

// ----------------------
// STATE
// ----------------------
const closing = ref(false)

function requestClose() {
  if (closing.value) return
  closing.value = true

  // allow exit animation to complete
  setTimeout(() => {
    emit('close')
  }, 160)
}

// ----------------------
// ESC KEY HANDLING
// ----------------------
function onKeydown(e) {
  if (e.key === 'Escape') {
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
  <!-- OVERLAY -->
  <div class="fixed inset-0 z-40 flex items-center justify-center">

    <!-- BACKDROP -->
    <div
      class="absolute inset-0 backdrop-blur-sm"
      @click.self="requestClose"
    />

    <!-- MODAL -->
    <section
      :class="[
        'relative z-50 w-full max-w-5xl max-h-[90vh]',
        'bg-[var(--color-bg-surface)]',
        'rounded-2xl shadow-2xl',
        'flex flex-col overflow-hidden',
        closing ? 'hz-animate-modal-out' : 'hz-animate-modal-in'
      ]"
    >

      <!-- HEADER -->
      <header
        class="shrink-0 px-6 py-4 border-b border-white/10
               flex items-center justify-between"
      >
        <slot name="header" />

        <button
          class="text-horizon-offwhite hover:text-horizon-white transition"
          @click="requestClose"
        >
          ✕
        </button>
      </header>

      <!-- BODY -->
      <div class="flex-1 overflow-y-auto">
        <slot />
      </div>

      <!-- FOOTER -->
      <footer
        v-if="$slots.footer"
        class="shrink-0 px-6 py-4 border-t border-white/10"
      >
        <slot name="footer" />
      </footer>

    </section>
  </div>
</template>