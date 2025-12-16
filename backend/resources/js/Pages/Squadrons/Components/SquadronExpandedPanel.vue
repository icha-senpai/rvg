<script setup>
import { ref, watch, computed, onMounted } from 'vue'
import axios from 'axios'

import SquadronPanelHeader from './SquadronPanelHeader.vue'
import SquadronOverviewSection from './SquadronOverviewSection.vue'
import SquadronViewerStatus from './SquadronViewerStatus.vue'
import SquadronRoster from './SquadronRoster.vue'

/* -------------------------------------------------
   Props
------------------------------------------------- */
const props = defineProps({
  squadronId: {
    type: Number,
    required: true,
  },
})

/* -------------------------------------------------
   Emits
------------------------------------------------- */
const emit = defineEmits([
  'close',
  'apply',
  'leave',
  'accept-member',
  'reject-member',
  'promote-lt',
  'remove-member',
  'updated', // 🔹 NEW
])

/* -------------------------------------------------
   State
------------------------------------------------- */
const squadron = ref(null)
const members = ref([])
const viewerMembership = ref(null)
const permissions = ref({})

const isLoading = ref(false)
const activeAction = ref(null)
const errorMessage = ref(null)

/* -------------------------------------------------
   Edit state
------------------------------------------------- */
const isEditing = ref(false)

const editForm = ref({
  motto: '',
  description: '',
})

const canEdit = computed(() =>
  permissions.value?.can_manage_members === true
)

/* -------------------------------------------------
   Fetch squadron
------------------------------------------------- */
async function fetchSquadron() {
  isLoading.value = true
  errorMessage.value = null

  try {
    const { data } = await axios.get(
      `/api/v1/squadrons/${props.squadronId}`
    )

    squadron.value = data.squadron
    members.value = data.members
    viewerMembership.value = data.viewer_membership
    permissions.value = data.permissions

    // sync edit form
    editForm.value.motto = data.squadron?.motto ?? ''
    editForm.value.description = data.squadron?.description ?? ''
  } catch (error) {
    errorMessage.value =
      error.response?.data?.message ??
      error.message ??
      'Failed to load squadron.'
  } finally {
    isLoading.value = false
  }
}

/* -------------------------------------------------
   Save settings
------------------------------------------------- */
async function saveSettings() {
  if (!squadron.value) return

  try {
    await axios.post(
      `/squadrons/${squadron.value.id}/settings`,
      editForm.value
    )

    isEditing.value = false
    await fetchSquadron()

    // 🔥 inform parent so card updates immediately
    emit('updated', squadron.value)

  } catch (error) {
    alert(
      error.response?.data?.message ??
      'Failed to save squadron settings.'
    )
  }
}

function cancelEdit() {
  isEditing.value = false
  editForm.value.motto = squadron.value?.motto ?? ''
  editForm.value.description = squadron.value?.description ?? ''
}

/* -------------------------------------------------
   Lifecycle
------------------------------------------------- */
onMounted(fetchSquadron)

watch(
  () => props.squadronId,
  fetchSquadron
)
</script>

<template>
  <div class="hz-panel hz-stack">
    <div v-if="isLoading" class="hz-soft">
      Loading squadron…
    </div>

    <div v-if="errorMessage" class="hz-alert hz-alert-danger">
      {{ errorMessage }}
    </div>

    <template v-if="squadron">
      <!-- Header -->
      <div class="hz-row-between">
        <SquadronPanelHeader
          :squadron="squadron"
          @close="emit('close')"
        />

        <button
          v-if="canEdit && !isEditing"
          class="hz-btn hz-btn-ghost hz-btn-sm"
          @click="isEditing = true"
        >
          Edit
        </button>
      </div>

      <!-- Overview / Edit -->
      <SquadronOverviewSection
        v-if="!isEditing"
        :squadron="squadron"
      />

      <div v-else class="hz-stack gap-2">
        <label class="hz-label">Motto</label>
        <input
          v-model="editForm.motto"
          class="hz-input"
          placeholder="Optional motto"
        />

        <label class="hz-label">Description</label>
        <textarea
          v-model="editForm.description"
          class="hz-textarea"
          rows="4"
          placeholder="Squadron description"
        />

        <div class="hz-row gap-2">
          <button class="hz-btn hz-btn-primary" @click="saveSettings">
            Save
          </button>
          <button class="hz-btn hz-btn-ghost" @click="cancelEdit">
            Cancel
          </button>
        </div>
      </div>

      <!-- Viewer Status -->
      <SquadronViewerStatus
        :viewerMembership="viewerMembership"
        :permissions="permissions"
        :squadron="squadron"
        :isLoading="isLoading"
        @apply="emit('apply')"
        @leave="emit('leave')"
      />

      <!-- Roster -->
      <SquadronRoster
        :members="members"
        :permissions="permissions"
        :activeAction="activeAction"
        @accept-member="emit('accept-member', $event)"
        @reject-member="emit('reject-member', $event)"
        @promote-lt="emit('promote-lt', $event)"
        @remove-member="emit('remove-member', $event)"
      />
    </template>
  </div>
</template>
