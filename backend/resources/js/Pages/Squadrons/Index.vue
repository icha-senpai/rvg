<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import SquadronExpandedPanel from './Components/SquadronExpandedPanel.vue'
import HorizonContainer from '@/Components/HorizonContainer.vue'

const squadrons = ref([])
const isLoading = ref(false)
const errorMessage = ref(null)
const errorStatus = ref(null)

const isExpanded = ref(false)
const activeSquadronId = ref(null)

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

      <SquadronExpandedPanel
        v-if="isExpanded && activeSquadronId"
        variant="modal"
        :squadronId="activeSquadronId"
        :show-close-button="true"
        @close="closePanel"
        @updated="handleSquadronUpdated"
      />
    </div>
  </HorizonContainer>
</template>
