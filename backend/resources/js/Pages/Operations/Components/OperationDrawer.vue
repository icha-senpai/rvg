<script setup>
import { ref, onMounted, onUnmounted } from 'vue'

// ----------------------
// EMITS
// ----------------------
const emit = defineEmits(['close'])

// ----------------------
// CLOSE STATE
// ----------------------
const closing = ref(false)

function requestClose() {
  if (closing.value) return
  closing.value = true

  // wait for close animation to finish
  setTimeout(() => {
    emit('close')
  }, 140) // matches --motion-fast
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
  <!-- ROOT OVERLAY -->
  <div class="fixed inset-0 z-40 flex justify-end">

    <!-- BACKDROP -->
    <div
      class="absolute inset-0 backdrop-blur-sm"
      @click.self="requestClose"
    />

    <!-- DRAWER -->
    <aside
      :class="[
        'relative z-50 h-full w-full md:w-[70%] bg-[var(--color-bg-surface)] shadow-2xl flex flex-col !border !border-[color:var(--horizon-sunset-blue)]',
        closing ? 'hz-animate-drawer-out' : 'hz-animate-drawer-in'
      ]"
    >
      <!-- HEADER -->
      <header class="shrink-0 border-b border-white/10 px-6 py-4 flex items-center justify-between">
        <slot name="header" />

        <button
          class="text-horizon-offwhite hover:text-horizon-white transition"
          @click="requestClose"
        >
          ✕
        </button>
      </header>

      <!-- BODY -->
      <section class="flex-1 overflow-y-auto px-6 py-6">
        <slot />
      </section>

      <!-- FOOTER -->
      <footer
        v-if="$slots.footer"
        class="shrink-0 border-t border-white/10 px-6 py-4"
      >
        <slot name="footer" />
      </footer>
    </aside>
  </div>
</template>
