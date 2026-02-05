<script setup>
import { ref, watch, computed, onMounted, onUnmounted } from 'vue'
import axios from 'axios'

import SquadronPanelHeader from './SquadronPanelHeader.vue'
import SquadronOverviewSection from './SquadronOverviewSection.vue'
import SquadronViewerStatus from './SquadronViewerStatus.vue'
import SquadronRoster from './SquadronRoster.vue'
import HorizonButton from '@/Components/HorizonButton.vue';
/* -------------------------------------------------
   Props
------------------------------------------------- */
const props = defineProps({
  squadronId: {
    type: Number,
    required: true,
  },
  variant: {
    type: String,
    default: 'inline',
  },
  showCloseButton: {
    type: Boolean,
    default: true,
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
  'updated',
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
const errorStatus = ref(null)

const verifyUrl = 'https://horizoninterstellar.com/verify'

const isUnauthenticatedError = computed(() => {
  if (errorStatus.value === 401 || errorStatus.value === 419) return true

  const message = String(errorMessage.value ?? '').toLowerCase()
  return message.includes('unauthenticated')
})

function goToVerify() {
  window.location.href = verifyUrl
}

const isOverlay = computed(() => props.variant === 'modal')
const isEmbedded = computed(() => props.variant === 'embedded')

const closing = ref(false)

function requestClose() {
  if (!isOverlay.value) {
    emit('close')
    return
  }

  if (closing.value) return
  closing.value = true

  setTimeout(() => {
    emit('close')
  }, 160)
}

function onKeydown(e) {
  if (e.key === 'Escape') {
    requestClose()
  }
}

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

const lieutenantCount = computed(() =>
  (members.value ?? []).filter(member => member?.is_lieutenant === true).length
)

const canPromoteLieutenant = computed(() => lieutenantCount.value < 2)

/* -------------------------------------------------
   Fetch squadron
------------------------------------------------- */
async function fetchSquadron() {
  isLoading.value = true
  errorMessage.value = null
  errorStatus.value = null

  try {
    const { data } = await axios.get(
      `/api/v1/squadrons/${props.squadronId}`
    )

    squadron.value = data.squadron
    members.value = data.members
    viewerMembership.value = data.viewer_membership
    permissions.value = data.permissions

    editForm.value.motto = data.squadron?.motto ?? ''
    editForm.value.description = data.squadron?.description ?? ''
  } catch (error) {
    errorStatus.value = error.response?.status ?? null

    if (errorStatus.value === 401 || errorStatus.value === 419) {
      errorMessage.value = null
      return
    }

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

async function applyToSquadron() {
  if (!squadron.value) return

  activeAction.value = 'apply'
  errorMessage.value = null
  errorStatus.value = null

  try {
    await axios.post(`/api/v1/squadrons/${squadron.value.id}/join`)
    await fetchSquadron()
  } catch (error) {
    errorStatus.value = error.response?.status ?? null

    if (errorStatus.value === 401 || errorStatus.value === 419) {
      errorMessage.value = null
      return
    }

    errorMessage.value =
      error.response?.data?.message ??
      error.message ??
      'Failed to apply to squadron.'
  } finally {
    activeAction.value = null
  }
}

async function leaveSquadron() {
  if (!squadron.value) return

  activeAction.value = 'leave'
  errorMessage.value = null
  errorStatus.value = null

  try {
    await axios.post(`/api/v1/squadrons/${squadron.value.id}/leave`)
    await fetchSquadron()
  } catch (error) {
    errorStatus.value = error.response?.status ?? null

    if (errorStatus.value === 401 || errorStatus.value === 419) {
      errorMessage.value = null
      return
    }

    errorMessage.value =
      error.response?.data?.message ??
      error.message ??
      'Failed to leave squadron.'
  } finally {
    activeAction.value = null
  }
}

async function acceptMember(member) {
  if (!squadron.value || !member?.id) return

  activeAction.value = 'accept-member'
  errorMessage.value = null
  errorStatus.value = null

  try {
    await axios.put(
      `/api/v1/squadrons/${squadron.value.id}/members/${member.id}`,
      {
        membership_status: 'active',
      }
    )
    await fetchSquadron()
  } catch (error) {
    errorStatus.value = error.response?.status ?? null

    if (errorStatus.value === 401 || errorStatus.value === 419) {
      errorMessage.value = null
      return
    }

    errorMessage.value =
      error.response?.data?.message ??
      error.message ??
      'Failed to accept member.'
  } finally {
    activeAction.value = null
  }
}

async function rejectMember(member) {
  if (!squadron.value || !member?.id) return

  activeAction.value = 'reject-member'
  errorMessage.value = null
  errorStatus.value = null

  try {
    await axios.delete(`/api/v1/squadrons/${squadron.value.id}/members/${member.id}`)
    await fetchSquadron()
  } catch (error) {
    errorStatus.value = error.response?.status ?? null

    if (errorStatus.value === 401 || errorStatus.value === 419) {
      errorMessage.value = null
      return
    }

    errorMessage.value =
      error.response?.data?.message ??
      error.message ??
      'Failed to reject member.'
  } finally {
    activeAction.value = null
  }
}

async function removeMember(member) {
  if (!squadron.value || !member?.id) return

  activeAction.value = 'remove-member'
  errorMessage.value = null
  errorStatus.value = null

  try {
    await axios.delete(`/api/v1/squadrons/${squadron.value.id}/members/${member.id}`)
    await fetchSquadron()
  } catch (error) {
    errorStatus.value = error.response?.status ?? null

    if (errorStatus.value === 401 || errorStatus.value === 419) {
      errorMessage.value = null
      return
    }

    errorMessage.value =
      error.response?.data?.message ??
      error.message ??
      'Failed to remove member.'
  } finally {
    activeAction.value = null
  }
}

async function promoteLieutenant(targetUser) {
  if (!squadron.value) return

  const userId = targetUser?.user_id ?? targetUser?.user?.id ?? targetUser?.id
  if (!userId) return

  activeAction.value = 'promote-lt'
  errorMessage.value = null
  errorStatus.value = null

  try {
    await axios.post(
      `/api/v1/squadrons/${squadron.value.id}/members/${userId}/promote-lieutenant`
    )
    await fetchSquadron()
  } catch (error) {
    errorStatus.value = error.response?.status ?? null

    if (errorStatus.value === 401 || errorStatus.value === 419) {
      errorMessage.value = null
      return
    }

    errorMessage.value =
      error.response?.data?.message ??
      error.message ??
      'Failed to promote member.'
  } finally {
    activeAction.value = null
  }
}

async function demoteLieutenant(memberOrUser) {
  if (!squadron.value) return

  const userId = memberOrUser?.user_id ?? memberOrUser?.user?.id ?? memberOrUser?.id
  if (!userId) return

  activeAction.value = 'demote-lt'
  errorMessage.value = null
  errorStatus.value = null

  try {
    await axios.post(
      `/api/v1/squadrons/${squadron.value.id}/members/${userId}/demote-lieutenant`
    )
    await fetchSquadron()
  } catch (error) {
    errorStatus.value = error.response?.status ?? null

    if (errorStatus.value === 401 || errorStatus.value === 419) {
      errorMessage.value = null
      return
    }

    errorMessage.value =
      error.response?.data?.message ??
      error.message ??
      'Failed to demote member.'
  } finally {
    activeAction.value = null
  }
}

/* -------------------------------------------------
   Lifecycle
------------------------------------------------- */
onMounted(() => {
  fetchSquadron()

  if (!isOverlay.value) return

  document.addEventListener('keydown', onKeydown)
  document.body.classList.add('overflow-hidden')
})

onUnmounted(() => {
  if (!isOverlay.value) return

  document.removeEventListener('keydown', onKeydown)
  document.body.classList.remove('overflow-hidden')
})

watch(
  () => props.squadronId,
  fetchSquadron
)
</script>

<template>
  <div
    v-if="!isOverlay && !isEmbedded"
    class="hz-panel hz-stack"
  >
    <div v-if="isLoading" class="hz-soft">
      Loading squadron…
    </div>

    <div v-if="errorMessage" class="hz-alert hz-alert-danger">
      <template v-if="isUnauthenticatedError">
        <div class="hz-alert-title">Verification Required</div>
        <div class="hz-alert-body">
          You need to verify your account to do squadron actions.
          <a
            :href="verifyUrl"
            target="_blank"
            rel="noopener noreferrer"
            class="underline"
          >
            Verify here
          </a>
        </div>
        <div class="hz-row mt-2">
          <HorizonButton variant="primary" size="sm" @click="goToVerify">
            Go to Verification
          </HorizonButton>
        </div>
      </template>

      <template v-else>
        {{ errorMessage }}
      </template>
    </div>

    <template v-if="squadron">
      <!-- Header -->
      <div class="hz-row-between items-start">
        <SquadronPanelHeader :squadron="squadron" />

        <div class="hz-row gap-2">
          <HorizonButton
            v-if="canEdit && !isEditing"
            variant="ghost"
            size="sm"
            @click="isEditing = true"
          >
            Edit
          </HorizonButton>

          <HorizonButton
            v-if="props.showCloseButton"
            variant="ghost"
            size="sm"
            @click="requestClose"
          >
            Close
          </HorizonButton>
        </div>
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
          <HorizonButton variant="primary" size="sm" @click="saveSettings">
            Save
          </HorizonButton>
          <HorizonButton variant="ghost" size="sm" @click="cancelEdit">
            Cancel
          </HorizonButton>
        </div>
      </div>

      <!-- Viewer Status -->
      <SquadronViewerStatus
        :viewerMembership="viewerMembership"
        :permissions="permissions"
        :squadron="squadron"
        :isLoading="isLoading"
        :activeAction="activeAction"
        @apply="applyToSquadron"
        @leave="leaveSquadron"
      />

      <!-- Roster -->
      <SquadronRoster
        :members="members"
        :permissions="permissions"
        :canPromoteLieutenant="canPromoteLieutenant"
        :activeAction="activeAction"
        @accept-member="acceptMember"
        @reject-member="rejectMember"
        @promote-lt="promoteLieutenant"
        @demote-lt="demoteLieutenant"
        @remove-member="removeMember"
      />
    </template>
  </div>

  <section
    v-else-if="isEmbedded"
    class="w-full bg-bg-surface rounded-2xl shadow-2xl flex flex-col overflow-hidden min-h-0 max-h-[85vh] !border !border-[color:var(--horizon-sunset-blue)]"
  >
    <header
      class="shrink-0 px-6 py-4 border-b border-white/10 flex items-start justify-between"
    >
      <SquadronPanelHeader :squadron="squadron" />

      <div class="hz-row gap-2">
        <HorizonButton
          v-if="canEdit && !isEditing"
          variant="ghost"
          size="sm"
          @click="isEditing = true"
        >
          Edit
        </HorizonButton>

        <button
          v-if="props.showCloseButton"
          class="text-horizon-offwhite hover:text-horizon-white transition"
          @click="requestClose"
        >
          ✕
        </button>
      </div>
    </header>

    <section class="flex-1 min-h-0 overflow-y-auto px-6 py-6 hz-stack">
      <div v-if="isLoading" class="hz-soft">
        Loading squadron…
      </div>

      <div v-if="errorMessage" class="hz-alert hz-alert-danger">
        <template v-if="isUnauthenticatedError">
          <div class="hz-alert-title">Verification Required</div>
          <div class="hz-alert-body">
            You need to verify your account to do squadron actions.
            <a
              :href="verifyUrl"
              target="_blank"
              rel="noopener noreferrer"
              class="underline"
            >
              Verify here
            </a>
          </div>
          <div class="hz-row mt-2">
            <HorizonButton variant="primary" size="sm" @click="goToVerify">
              Go to Verification
            </HorizonButton>
          </div>
        </template>

        <template v-else>
          {{ errorMessage }}
        </template>
      </div>

      <template v-if="squadron">
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
            <HorizonButton variant="primary" size="sm" @click="saveSettings">
              Save
            </HorizonButton>
            <HorizonButton variant="ghost" size="sm" @click="cancelEdit">
              Cancel
            </HorizonButton>
          </div>
        </div>

        <SquadronViewerStatus
          :viewerMembership="viewerMembership"
          :permissions="permissions"
          :squadron="squadron"
          :isLoading="isLoading"
          :activeAction="activeAction"
          @apply="applyToSquadron"
          @leave="leaveSquadron"
        />

        <SquadronRoster
          :members="members"
          :permissions="permissions"
          :canPromoteLieutenant="canPromoteLieutenant"
          :activeAction="activeAction"
          @accept-member="acceptMember"
          @reject-member="rejectMember"
          @promote-lt="promoteLieutenant"
          @demote-lt="demoteLieutenant"
          @remove-member="removeMember"
        />
      </template>
    </section>
  </section>

  <div
    v-else
    class="fixed inset-0 z-40 flex items-center justify-center p-4"
  >
    <div
      class="absolute inset-0 backdrop-blur-sm"
      @click.self="requestClose"
    />

    <section
      :class="[
        'relative z-50 w-full max-w-5xl max-h-[90vh]',
        'bg-bg-surface',
        '!border !border-[color:var(--horizon-sunset-blue)]',
        'rounded-2xl shadow-2xl',
        'flex flex-col overflow-hidden',
        closing ? 'hz-animate-modal-out' : 'hz-animate-modal-in'
      ]"
    >
      <header
        class="shrink-0 px-6 py-4 border-b border-white/10 flex items-start justify-between"
      >
        <SquadronPanelHeader :squadron="squadron" />

        <div class="hz-row gap-2">
          <HorizonButton
            v-if="canEdit && !isEditing"
            variant="ghost"
            size="sm"
            @click="isEditing = true"
          >
            Edit
          </HorizonButton>

          <button
            v-if="props.showCloseButton"
            class="text-horizon-offwhite hover:text-horizon-white transition"
            @click="requestClose"
          >
            ✕
          </button>
        </div>
      </header>

      <section class="flex-1 overflow-y-auto px-6 py-6 hz-stack">
        <div v-if="isLoading" class="hz-soft">
          Loading squadron…
        </div>

        <div v-if="errorMessage" class="hz-alert hz-alert-danger">
          <template v-if="isUnauthenticatedError">
            <div class="hz-alert-title">Verification Required</div>
            <div class="hz-alert-body">
              You need to verify your account to do squadron actions.
              <a
                :href="verifyUrl"
                target="_blank"
                rel="noopener noreferrer"
                class="underline"
              >
                Verify here
              </a>
            </div>
            <div class="hz-row mt-2">
              <HorizonButton variant="primary" size="sm" @click="goToVerify">
                Go to Verification
              </HorizonButton>
            </div>
          </template>

          <template v-else>
            {{ errorMessage }}
          </template>
        </div>

        <template v-if="squadron">
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
              <HorizonButton variant="primary" size="sm" @click="saveSettings">
                Save
              </HorizonButton>
              <HorizonButton variant="ghost" size="sm" @click="cancelEdit">
                Cancel
              </HorizonButton>
            </div>
          </div>

          <!-- Viewer Status -->
          <SquadronViewerStatus
            :viewerMembership="viewerMembership"
            :permissions="permissions"
            :squadron="squadron"
            :isLoading="isLoading"
            :activeAction="activeAction"
            @apply="applyToSquadron"
            @leave="leaveSquadron"
          />

          <!-- Roster -->
          <SquadronRoster
            :members="members"
            :permissions="permissions"
            :canPromoteLieutenant="canPromoteLieutenant"
            :activeAction="activeAction"
            @accept-member="acceptMember"
            @reject-member="rejectMember"
            @promote-lt="promoteLieutenant"
            @demote-lt="demoteLieutenant"
            @remove-member="removeMember"
          />
        </template>
      </section>
    </section>
  </div>
</template>
