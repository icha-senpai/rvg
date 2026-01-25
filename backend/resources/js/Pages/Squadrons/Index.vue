<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import axios from 'axios'
import SquadronExpandedPanel from './Components/SquadronExpandedPanel.vue'
import HorizonContainer from '@/Components/HorizonContainer.vue'
import HorizonButton from '@/Components/HorizonButton.vue'

const squadrons = ref([])
const isLoading = ref(false)
const errorMessage = ref(null)
const errorStatus = ref(null)

const isExpanded = ref(false)
const activeSquadronId = ref(null)

function handleKeydown(event) {
  if (event.key !== 'Escape') return
  if (!isExpanded.value) return

  closePanel()
}

/* -------------------------------------------------
   Fetch all squadrons
------------------------------------------------- */
async function fetchSquadrons() {
  isLoading.value = true
  errorMessage.value = null
  errorStatus.value = null

  try {
    const { data } = await axios.get('/api/v1/squadrons')
    squadrons.value = data
  } catch (error) {
    errorStatus.value = error?.response?.status ?? null

    if (errorStatus.value === 401 || errorStatus.value === 419) {
      errorMessage.value = null
      return
    }

    errorMessage.value =
      error.response?.data?.message ??
      'Failed to load squadrons.'
  } finally {
    isLoading.value = false
  }
}

/* -------------------------------------------------
   Panel controls
------------------------------------------------- */
function openSquadron(squadronId) {
  activeSquadronId.value = squadronId
  isExpanded.value = true
}

function closePanel() {
  isExpanded.value = false
  activeSquadronId.value = null
}

/* -------------------------------------------------
   Handle updates from expanded panel
------------------------------------------------- */
function handleSquadronUpdated(updated) {
  const index = squadrons.value.findIndex(
    s => s.id === updated.id
  )

  if (index !== -1) {
    squadrons.value[index] = {
      ...squadrons.value[index],
      ...updated,
    }
  }
}

onMounted(() => {
  fetchSquadrons()
  window.addEventListener('keydown', handleKeydown)
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleKeydown)
})
</script>

<template>
  <HorizonContainer class="space-y-10">
    <div class="mx-auto max-w-5xl hz-stack">

      <div class="hz-title-lg">Squadrons</div>

      <div v-if="isLoading" class="hz-soft">
        Loading squadrons…
      </div>

      <div v-if="errorMessage" class="hz-alert hz-alert-danger">
        {{ errorMessage }}
      </div>

      <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
        <div
          v-for="squadron in squadrons"
          :key="squadron.id"
          class="hz-card-soft hz-stack cursor-pointer"
          @click="openSquadron(squadron.id)"
        >
          <div class="hz-title-md">{{ squadron.name }}</div>
          <div class="hz-text-soft">
            {{ squadron.motto ?? 'No motto provided.' }}
          </div>
        </div>
      </div>

      <div
        v-if="isExpanded && activeSquadronId"
        class="hz-overlay flex items-center justify-center p-4"
        @click.self="closePanel"
      >
        <div class="w-full max-w-5xl max-h-[85vh] overflow-y-auto hz-overlay-light rounded-2xl p-4 sm:p-6 hz-stack">
          <div class="flex justify-end">
            <HorizonButton
              variant="ghost"
              size="xs"
              @click="closePanel"
            >
              ✕
            </HorizonButton>
          </div>

          <SquadronExpandedPanel
            :squadronId="activeSquadronId"
            :show-close-button="false"
            @close="closePanel"
            @updated="handleSquadronUpdated"
          />
        </div>
      </div>
    </div>
  </HorizonContainer>
</template>
