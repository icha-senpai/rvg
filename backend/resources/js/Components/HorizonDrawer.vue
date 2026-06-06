<script setup>
import { ref, onMounted, onUnmounted } from 'vue'

const props = defineProps({
  closeLabel: { type: String, default: 'Close drawer' },
})

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
  <div class="fixed inset-0 z-40 flex justify-end">
    <!-- Backdrop -->
    <div
      class="hz-overlay-scrim absolute inset-0 backdrop-blur-md"
      @click.self="requestClose"
    ></div>

    <!-- Drawer -->
    <aside
      :class="[
        'relative z-50 flex h-full w-full flex-col overflow-hidden',
        'hz-shell-surface border-l',
        '',
        'md:w-[78%] xl:w-[68%] 2xl:w-[58%]',
        closing ? 'hz-animate-drawer-out' : 'hz-animate-drawer-in',
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
      <header class="hz-shell-header relative shrink-0 border-b px-5 py-4 md:px-6">
        <div class="flex items-start justify-between gap-4">
          <div class="min-w-0 flex-1">
            <slot name="header" />
          </div>

          <button
            type="button"
            class="hz-surface-soft hz-shell-hover flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-lg font-bold text-text-secondary transition hover:text-horizon-white"
            :aria-label="props.closeLabel"
            @click="requestClose"
          >
            ✕
          </button>
        </div>
      </header>

      <!-- Body -->
      <section class="relative flex-1 overflow-y-auto px-5 py-5 md:px-6 md:py-6">
        <slot />
      </section>

      <!-- Footer -->
      <footer
        v-if="$slots.footer"
        class="hz-shell-header relative shrink-0 border-t px-5 py-4 md:px-6"
      >
        <slot name="footer" />
      </footer>
    </aside>
  </div>
</template>






