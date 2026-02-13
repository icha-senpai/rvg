<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import HorizonButton from '@/Components/HorizonButton.vue'

const open = ref(false)
const title = ref('Error')
const message = ref('')

function close() {
  open.value = false
}

function handleErrorEvent(event) {
  const detail = event?.detail ?? {}

  title.value = typeof detail.title === 'string' && detail.title ? detail.title : 'Error'
  message.value = typeof detail.message === 'string' && detail.message ? detail.message : 'Something went wrong.'
  open.value = true
}

function handleKeydown(event) {
  if (!open.value) return
  if (event?.key === 'Escape') close()
}

onMounted(() => {
  window.addEventListener('hz:error', handleErrorEvent)
  window.addEventListener('keydown', handleKeydown)
})

onBeforeUnmount(() => {
  window.removeEventListener('hz:error', handleErrorEvent)
  window.removeEventListener('keydown', handleKeydown)
})
</script>

<template>
  <div
    v-if="open"
    class="hz-overlay flex items-center justify-center"
    @click.self="close"
  >
    <div
      class="hz-modal hz-shadow-deep hz-stack hz-animate-pop"
      style="max-width: 42rem; max-height: 85vh; overflow-y: auto;"
    >
      <div class="hz-row-between">
        <div class="hz-title-lg">{{ title }}</div>
        <HorizonButton variant="primary" size="sm" @click="close">✕</HorizonButton>
      </div>

      <div class="hz-body hz-text-soft whitespace-pre-line">{{ message }}</div>

      <div class="hz-row mt-2 justify-end">
        <HorizonButton variant="primary" size="sm" @click="close">Close</HorizonButton>
      </div>
    </div>
  </div>
</template>
