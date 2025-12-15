<script setup>
import { ref, onMounted } from 'vue'
import SquadronExpandedPanel from './Components/SquadronExpandedPanel.vue'
import axios from 'axios'
onMounted(() => {
  fetchSquadron()
})
/* -------------------------------------------------
   Props from Inertia
------------------------------------------------- */
const props = defineProps({
  squadronId: Number,
})

/* -------------------------------------------------
   Page state
------------------------------------------------- */
const isExpanded = ref(false)
const isLoading = ref(false)
const activeAction = ref(null)
const errorMessage = ref(null)

/* -------------------------------------------------
   Data state (hydrated from API)
------------------------------------------------- */
const squadron = ref(null)
const members = ref([])
const viewerMembership = ref(null)
const permissions = ref({})

/* -------------------------------------------------
   Panel controls
------------------------------------------------- */
function openPanel() {
  isExpanded.value = true
}

function closePanel() {
  isExpanded.value = false
}

/* -------------------------------------------------
   Fetch squadron data
------------------------------------------------- */
async function fetchSquadron() {
  isLoading.value = true
  errorMessage.value = null

  try {
    const { data } = await axios.get(
      `/api/v1/squadrons/${props.squadronId}`
    )

    // 🔹 exact payload mapping
    squadron.value = data.squadron
    members.value = data.members
    viewerMembership.value = data.viewer_membership
    permissions.value = data.permissions

  } catch (error) {
    console.error('Squadron fetch failed:', error)
    console.error('Response:', error.response)

    errorMessage.value =
      error.response?.data?.message
      ?? error.message
      ?? 'Failed to load squadron.'

  } finally {
    isLoading.value = false
  }
}

/* -------------------------------------------------
   Placeholder handlers (no mutation yet)
------------------------------------------------- */
async function applyToSquadron() {
  if (!squadron.value) return

  activeAction.value = 'apply'

  try {
    await axios.post(
      `/api/v1/squadrons/${squadron.value.id}/join`
    )

    await fetchSquadron()
  } catch (error) {
    console.error('Apply failed', error)
    alert(
      error.response?.data?.message
      ?? 'Failed to apply to squadron.'
    )
  } finally {
    activeAction.value = null
  }
}

async function leaveSquadron() {
  if (!squadron.value) return

  activeAction.value = 'leave'

  try {
    await axios.post(
      `/api/v1/squadrons/${squadron.value.id}/leave`
    )

    await fetchSquadron()
  } catch (error) {
    console.error('Leave failed', error)
    alert(
      error.response?.data?.message
      ?? 'Failed to leave squadron.'
    )
  } finally {
    activeAction.value = null
  }
}

async function acceptMember(member) {
  try {
    await axios.put(
      `/api/v1/squadrons/${squadron.value.id}/members/${member.id}`,
      { membership_status: 'active' }
    )

    await fetchSquadron()
  } catch (error) {
    alert(
      error.response?.data?.message
      ?? 'Failed to accept member.'
    )
  }
}

async function rejectMember(member) {
  try {
    await axios.delete(
      `/api/v1/squadrons/${squadron.value.id}/members/${member.id}`
    )

    await fetchSquadron()
  } catch (error) {
    alert(
      error.response?.data?.message
      ?? 'Failed to reject member.'
    )
  }
}

async function promoteLieutenant(member) {
  if (!squadron.value) return

  activeAction.value = 'promote'

  try {
    await axios.post(
       `/api/v1/squadrons/${squadron.value.id}/members/${member.user.id}/promote-lieutenant`
    )

    await fetchSquadron()
  } catch (error) {
    alert(
      error.response?.data?.message
      ?? 'Failed to promote member.'
    )
  } finally {
    activeAction.value = null
  }
}
async function demoteLieutenant(member) {
  if (!squadron.value) return

  activeAction.value = 'demote'

  try {
    await axios.post(
      `/api/v1/squadrons/${squadron.value.id}/members/${member.user.id}/demote`
    )

    await fetchSquadron()
  } catch (error) {
    alert(
      error.response?.data?.message
      ?? 'Failed to demote member.'
    )
  } finally {
    activeAction.value = null
  }
}

</script>


<template>
  <div class="hz-container hz-stack">

    <div v-if="errorMessage" class="hz-alert hz-alert-danger">
      {{ errorMessage }}
    </div>

    <div v-if="isLoading" class="hz-soft">
      Loading squadron…
    </div>

    <div v-if="squadron" class="hz-card hz-row-between">
      <div>
        <div class="hz-title-md">{{ squadron.name }}</div>
        <div class="hz-text-soft">{{ squadron.motto }}</div>
      </div>

      <button
        class="hz-btn hz-btn-primary hz-btn-sm"
        @click="openPanel"
      >
        View Squadron
      </button>
    </div>
    <div v-if="isLoading">Loading…</div>
    <div v-if="errorMessage">{{ errorMessage }}</div>
    <SquadronExpandedPanel
      v-if="isExpanded && squadron"
      :squadron="squadron"
      :members="members"
      :viewerMembership="viewerMembership"
      :permissions="permissions"
      :isLoading="isLoading"
      :activeAction="activeAction"
      @close="closePanel"
      @apply="applyToSquadron"
      @leave="leaveSquadron"
      @promote-lt="promoteLieutenant"
      @demote-lt="demoteLieutenant"
      @accept-member="acceptMember"
      @reject-member="rejectMember"
    />
  </div>
</template>
