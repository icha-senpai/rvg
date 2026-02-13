<script setup>
import { ref, watch, computed, onMounted, onUnmounted } from 'vue'
import { usePage } from '@inertiajs/vue3'
import axios from 'axios'

import SquadronPanelHeader from './SquadronPanelHeader.vue'
import SquadronOverviewSection from './SquadronOverviewSection.vue'
import SquadronViewerStatus from './SquadronViewerStatus.vue'
import SquadronRoster from './SquadronRoster.vue'
import HorizonButton from '@/Components/HorizonButton.vue';
import MediaPickerModal from '@/Components/MediaPickerModal.vue'
import HorizonRichTextEditor from '@/Components/HorizonRichTextEditor.vue'
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

function openEmblemPicker() {
  if (!squadron.value) return
  if (!canEditEmblem.value) return
  emblemPickerOpen.value = true
}

function closeEmblemPicker() {
  emblemPickerOpen.value = false
}

async function setEmblem(media) {
  if (!squadron.value) return
  if (emblemSaving.value) return

  emblemSaving.value = true
  try {
    await axios.put(`/api/v1/squadrons/${squadron.value.id}`, {
      emblem_media_id: media?.id ?? null,
    }, {
      headers: {
        Accept: 'application/json',
      },
      hzSkipErrorDialog: true,
    })

    await fetchSquadron()
    emit('updated', squadron.value)
  } catch (error) {
    const status = error?.response?.status ?? null
    if (status === 403) {
      window.hzNotifyError({
        message: 'You do not have permission to update the squadron emblem.',
      })
      return
    }

    window.hzNotifyError({
      message:
        error.response?.data?.message ??
        error.message ??
        'Failed to update squadron emblem.',
    })
  } finally {
    emblemSaving.value = false
    closeEmblemPicker()
  }
}

async function clearEmblem() {
  if (!squadron.value) return
  if (emblemSaving.value) return
  await setEmblem(null)
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
const isRecruitingEditing = ref(false)

const activeTab = ref('recruiting')

const emblemPickerOpen = ref(false)
const emblemSaving = ref(false)

function setActiveTab(tab) {
  if (isTabLocked.value && tab !== activeTab.value) {
    const from = activeTab.value
    const isEditableTab = (t) => t === 'overview' || t === 'recruiting'
    if (!(isEditableTab(from) && isEditableTab(tab))) return
  }
  activeTab.value = tab
}

const isTabLocked = computed(() => isEditing.value || isRecruitingEditing.value)

function isTabDisabled(tab) {
  if (!isTabLocked.value) return false
  return tab === 'status' || tab === 'roster'
}

function cancelSettingsEdit() {
  isEditing.value = false
  isRecruitingEditing.value = false
  editForm.value.motto = squadron.value?.motto ?? ''
  editForm.value.description = squadron.value?.description ?? ''
  editForm.value.recruitment_propaganda = squadron.value?.recruitment_propaganda ?? ''
}

const editForm = ref({
  motto: '',
  description: '',
  recruitment_propaganda: '',
})

const canEdit = computed(() =>
  permissions.value?.can_manage_members === true
)

const page = usePage()
const authUser = computed(() => page.props.auth?.user ?? null)
const authRankLevel = computed(() => Number(authUser.value?.rank_level ?? 0))

const isDirectorLike = computed(() => {
  const roles = authUser.value?.roles ?? []
  return roles.some(r => r?.slug === 'director' || r?.slug === 'tech_director')
})

const canEditEmblem = computed(() => {
  if (isDirectorLike.value) return true
  if (viewerMembership.value?.is_leader === true) return true

  return (
    authRankLevel.value >= 2
    && viewerMembership.value?.membership_status === 'active'
  )
})

const activeMemberCount = computed(() =>
  (members.value ?? []).filter(m => m?.membership_status === 'active').length
)

const maxRosterSize = 21

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
    editForm.value.recruitment_propaganda = data.squadron?.recruitment_propaganda ?? ''
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
    isRecruitingEditing.value = false
    await fetchSquadron()

    emit('updated', squadron.value)
  } catch (error) {
    window.hzNotifyError({
      message:
        error.response?.data?.message ??
        'Failed to save squadron settings.',
    })
  }
}

function cancelEdit() {
  cancelSettingsEdit()
}

function startRecruitingEdit() {
  isRecruitingEditing.value = true
}

function cancelRecruitingEdit() {
  cancelSettingsEdit()
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
  () => {
    activeTab.value = 'recruiting'
    fetchSquadron()
  }
)

watch(
  () => isEditing.value,
  (editing) => {
    if (editing) {
      activeTab.value = 'overview'
    }
  }
)

watch(
  () => isRecruitingEditing.value,
  (editing) => {
    if (editing) {
      activeTab.value = 'recruiting'
    }
  }
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
            v-if="(canEdit || canEditEmblem) && !isTabLocked && activeTab === 'overview'"
            variant="ghost"
            size="sm"
            @click="isEditing = true"
          >
            Edit
          </HorizonButton>

          <HorizonButton
            v-if="canEdit && !isTabLocked && activeTab === 'recruiting'"
            variant="ghost"
            size="sm"
            @click="startRecruitingEdit"
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

      <!-- Tabs -->
      <div
        class="hz-row hz-text-soft gap-2"
        style="border-bottom: 1px solid var(--color-bg-hover); padding-bottom: var(--space-sm);"
      >
        <HorizonButton
          size="sm"
          :disabled="isTabDisabled('recruiting')"
          :variant="activeTab === 'recruiting' ? 'primary' : 'ghost'"
          @click="setActiveTab('recruiting')"
        >
          Recruiting
        </HorizonButton>

        <HorizonButton
          size="sm"
          :disabled="isTabDisabled('overview')"
          :variant="activeTab === 'overview' ? 'primary' : 'ghost'"
          @click="setActiveTab('overview')"
        >
          Overview
        </HorizonButton>

        <HorizonButton
          size="sm"
          :disabled="isTabDisabled('status')"
          :variant="activeTab === 'status' ? 'primary' : 'ghost'"
          @click="setActiveTab('status')"
        >
          Status
        </HorizonButton>

        <HorizonButton
          size="sm"
          :disabled="isTabDisabled('roster')"
          :variant="activeTab === 'roster' ? 'primary' : 'ghost'"
          @click="setActiveTab('roster')"
        >
          Roster ({{ activeMemberCount }}/{{ maxRosterSize }})
        </HorizonButton>
      </div>

      <div v-if="activeTab === 'overview'" class="hz-animate-fade">
        <!-- Overview / Edit -->
        <SquadronOverviewSection
          v-if="!isTabLocked"
          :squadron="squadron"
        />

        <div v-else class="hz-stack gap-2">
          <label class="hz-label">Emblem</label>

          <div class="hz-row gap-3 items-center">
            <div class="w-20 h-20 rounded-lg overflow-hidden border border-(--horizon-sunset-blue) bg-bg-surface shrink-0">
              <img
                v-if="squadron?.emblem_url"
                :src="squadron?.emblem?.medium_url || squadron?.emblem?.url || squadron?.emblem_url"
                :alt="squadron?.emblem?.alt_text || `${squadron?.name} emblem`"
                class="w-full h-full object-contain"
                loading="lazy"
              />
            </div>

            <div class="hz-row gap-2">
              <HorizonButton
                variant="ghost"
                size="sm"
                :disabled="emblemSaving"
                v-if="canEditEmblem"
                @click="openEmblemPicker"
              >
                Choose Emblem
              </HorizonButton>

              <HorizonButton
                variant="ghost"
                size="sm"
                :disabled="emblemSaving || !squadron?.emblem_url"
                v-if="canEditEmblem"
                @click="clearEmblem"
              >
                Clear
              </HorizonButton>
            </div>
          </div>

          <label class="hz-label">Motto</label>
          <input
            v-model="editForm.motto"
            class="hz-input"
            placeholder="Optional motto"
          />

          <label class="hz-label">Description</label>
          <HorizonRichTextEditor
            v-model="editForm.description"
            :rows="8"
            placeholder="Squadron description"
          />

          <div class="hz-row gap-2">
            <HorizonButton
              v-if="canEdit"
              variant="primary"
              size="sm"
              @click="saveSettings"
            >
              Save
            </HorizonButton>
            <HorizonButton variant="ghost" size="sm" @click="cancelEdit">
              Cancel
            </HorizonButton>
          </div>
        </div>
      </div>

      <div v-if="activeTab === 'status'" class="hz-animate-fade">
        <SquadronViewerStatus
          :viewerMembership="viewerMembership"
          :permissions="permissions"
          :squadron="squadron"
          :isLoading="isLoading"
          :activeAction="activeAction"
          @apply="applyToSquadron"
          @leave="leaveSquadron"
        />
      </div>

      <div v-if="activeTab === 'recruiting'" class="hz-animate-fade">
        <div class="hz-stack">
          <div class="hz-row-between items-center">
            <div class="hz-section-label">Recruiting</div>
          </div>

          <div
            v-if="(!isTabLocked || !canEdit) && squadron?.recruitment_propaganda"
            class="hz-soft space-y-2 [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:list-decimal [&_ol]:pl-5 [&_a]:underline [&_h1]:text-xl [&_h1]:font-semibold [&_h2]:text-lg [&_h2]:font-semibold [&_h3]:text-base [&_h3]:font-semibold [&_h4]:text-sm [&_h4]:font-semibold [&_h5]:text-sm [&_h5]:font-medium [&_h6]:text-xs [&_h6]:font-medium [&_blockquote]:border-l-2 [&_blockquote]:border-(--color-bg-hover) [&_blockquote]:pl-3 [&_blockquote]:opacity-90 [&_hr]:my-3 [&_hr]:border-(--color-bg-hover) [&_code]:rounded [&_code]:px-1 [&_code]:py-0.5 [&_code]:bg-bg-elevated [&_pre]:rounded [&_pre]:p-3 [&_pre]:bg-bg-elevated [&_table]:w-full [&_table]:border-collapse [&_th]:border [&_th]:border-(--color-bg-hover) [&_th]:bg-bg-hover [&_th]:p-2 [&_td]:border [&_td]:border-(--color-bg-hover) [&_td]:bg-bg-elevated [&_td]:p-2 [&_mark]:rounded [&_mark]:px-1 [&_img]:max-w-full [&_img]:h-auto [&_img]:rounded-lg"
            v-html="squadron.recruitment_propaganda"
          />
          <div v-else-if="!isTabLocked || !canEdit" class="hz-soft">
            No recruitment message provided.
          </div>

          <div v-else class="hz-stack gap-2">
            <label class="hz-label">Recruitment Propaganda</label>
            <HorizonRichTextEditor
              v-model="editForm.recruitment_propaganda"
              :rows="10"
              placeholder="Write a recruitment message…"
            />

            <div class="hz-row gap-2">
              <HorizonButton
                v-if="canEdit"
                variant="primary"
                size="sm"
                @click="saveSettings"
              >
                Save
              </HorizonButton>
              <HorizonButton variant="ghost" size="sm" @click="cancelRecruitingEdit">
                Cancel
              </HorizonButton>
            </div>
          </div>
        </div>
      </div>

      <div v-if="activeTab === 'roster'" class="hz-animate-fade">
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
      </div>
    </template>
  </div>

  <section
    v-else-if="isEmbedded"
    class="w-full bg-bg-surface rounded-2xl shadow-2xl flex flex-col border! border-(--horizon-sunset-blue)!"
  >
    <header
      class="shrink-0 px-6 py-4 border-b border-white/10 flex items-start justify-between"
    >
      <SquadronPanelHeader :squadron="squadron" />

      <div class="hz-row gap-2">
        <HorizonButton
          v-if="(canEdit || canEditEmblem) && !isTabLocked && activeTab === 'overview'"
          variant="ghost"
          size="sm"
          @click="isEditing = true"
        >
          Edit
        </HorizonButton>

        <HorizonButton
          v-if="canEdit && !isTabLocked && activeTab === 'recruiting'"
          variant="ghost"
          size="sm"
          @click="startRecruitingEdit"
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

    <section class="px-6 py-6 hz-stack">
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
        <!-- Tabs -->
        <div
          class="hz-row hz-text-soft gap-2"
          style="border-bottom: 1px solid var(--color-bg-hover); padding-bottom: var(--space-sm);"
        >
          <HorizonButton
            size="sm"
            :disabled="isTabDisabled('recruiting')"
            :variant="activeTab === 'recruiting' ? 'primary' : 'ghost'"
            @click="setActiveTab('recruiting')"
          >
            Recruiting
          </HorizonButton>

          <HorizonButton
            size="sm"
            :disabled="isTabDisabled('overview')"
            :variant="activeTab === 'overview' ? 'primary' : 'ghost'"
            @click="setActiveTab('overview')"
          >
            Overview
          </HorizonButton>

          <HorizonButton
            size="sm"
            :disabled="isTabDisabled('status')"
            :variant="activeTab === 'status' ? 'primary' : 'ghost'"
            @click="setActiveTab('status')"
          >
            Status
          </HorizonButton>

          <HorizonButton
            size="sm"
            :disabled="isTabDisabled('roster')"
            :variant="activeTab === 'roster' ? 'primary' : 'ghost'"
            @click="setActiveTab('roster')"
          >
            Roster ({{ activeMemberCount }}/{{ maxRosterSize }})
          </HorizonButton>
        </div>

        <div v-if="activeTab === 'overview'" class="hz-animate-fade">
          <SquadronOverviewSection
            v-if="!isTabLocked"
            :squadron="squadron"
          />

          <div v-else class="hz-stack gap-2">
            <label class="hz-label">Emblem</label>

            <div class="hz-row gap-3 items-center">
              <div class="w-20 h-20 rounded-lg overflow-hidden border border-(--horizon-sunset-blue) bg-bg-surface shrink-0">
                <img
                  v-if="squadron?.emblem_url"
                  :src="squadron?.emblem?.medium_url || squadron?.emblem?.url || squadron?.emblem_url"
                  :alt="squadron?.emblem?.alt_text || `${squadron?.name} emblem`"
                  class="w-full h-full object-contain"
                  loading="lazy"
                />
              </div>

              <div class="hz-row gap-2">
                <HorizonButton
                  variant="ghost"
                  size="sm"
                  :disabled="emblemSaving"
                  v-if="canEditEmblem"
                  @click="openEmblemPicker"
                >
                  Choose Emblem
                </HorizonButton>

                <HorizonButton
                  variant="ghost"
                  size="sm"
                  :disabled="emblemSaving || !squadron?.emblem_url"
                  v-if="canEditEmblem"
                  @click="clearEmblem"
                >
                  Clear
                </HorizonButton>
              </div>
            </div>

            <label class="hz-label">Motto</label>
            <input
              v-model="editForm.motto"
              class="hz-input"
              placeholder="Optional motto"
            />

            <label class="hz-label">Description</label>
            <HorizonRichTextEditor
              v-model="editForm.description"
              :rows="8"
              placeholder="Squadron description"
            />

            <div class="hz-row gap-2">
              <HorizonButton
                v-if="canEdit"
                variant="primary"
                size="sm"
                @click="saveSettings"
              >
                Save
              </HorizonButton>
              <HorizonButton variant="ghost" size="sm" @click="cancelEdit">
                Cancel
              </HorizonButton>
            </div>
          </div>
        </div>

        <div v-if="activeTab === 'status'" class="hz-animate-fade">
          <SquadronViewerStatus
            :viewerMembership="viewerMembership"
            :permissions="permissions"
            :squadron="squadron"
            :isLoading="isLoading"
            :activeAction="activeAction"
            @apply="applyToSquadron"
            @leave="leaveSquadron"
          />
        </div>

        <div v-if="activeTab === 'recruiting'" class="hz-animate-fade">
          <div class="hz-stack">
            <div class="hz-row-between items-center">
              <div class="hz-section-label">Recruiting</div>
            </div>

            <div
              v-if="(!isTabLocked || !canEdit) && squadron?.recruitment_propaganda"
              class="hz-soft space-y-2 [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:list-decimal [&_ol]:pl-5 [&_a]:underline [&_h1]:text-xl [&_h1]:font-semibold [&_h2]:text-lg [&_h2]:font-semibold [&_h3]:text-base [&_h3]:font-semibold [&_h4]:text-sm [&_h4]:font-semibold [&_h5]:text-sm [&_h5]:font-medium [&_h6]:text-xs [&_h6]:font-medium [&_blockquote]:border-l-2 [&_blockquote]:border-(--color-bg-hover) [&_blockquote]:pl-3 [&_blockquote]:opacity-90 [&_hr]:my-3 [&_hr]:border-(--color-bg-hover) [&_code]:rounded [&_code]:px-1 [&_code]:py-0.5 [&_code]:bg-bg-elevated [&_pre]:rounded [&_pre]:p-3 [&_pre]:bg-bg-elevated [&_table]:w-full [&_table]:border-collapse [&_th]:border [&_th]:border-(--color-bg-hover) [&_th]:bg-bg-hover [&_th]:p-2 [&_td]:border [&_td]:border-(--color-bg-hover) [&_td]:bg-bg-elevated [&_td]:p-2 [&_mark]:rounded [&_mark]:px-1 [&_img]:max-w-full [&_img]:h-auto [&_img]:rounded-lg"
              v-html="squadron.recruitment_propaganda"
            />
            <div v-else-if="!isTabLocked || !canEdit" class="hz-soft">
              No recruitment message provided.
            </div>

            <div v-else class="hz-stack gap-2">
              <label class="hz-label">Recruitment Propaganda</label>
              <HorizonRichTextEditor
                v-model="editForm.recruitment_propaganda"
                :rows="10"
                placeholder="Write a recruitment message…"
              />

              <div class="hz-row gap-2">
                <HorizonButton variant="primary" size="sm" @click="saveSettings">
                  Save
                </HorizonButton>
                <HorizonButton variant="ghost" size="sm" @click="cancelRecruitingEdit">
                  Cancel
                </HorizonButton>
              </div>
            </div>
          </div>
        </div>

        <div v-if="activeTab === 'roster'" class="hz-animate-fade">
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
        </div>
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
        'border! border-(--horizon-sunset-blue)!',
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
            v-if="(canEdit || canEditEmblem) && !isTabLocked && activeTab === 'overview'"
            variant="ghost"
            size="sm"
            @click="isEditing = true"
          >
            Edit
          </HorizonButton>

          <HorizonButton
            v-if="canEdit && !isTabLocked && activeTab === 'recruiting'"
            variant="ghost"
            size="sm"
            @click="startRecruitingEdit"
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
          <!-- Tabs -->
          <div
            class="hz-row hz-text-soft gap-2"
            style="border-bottom: 1px solid var(--color-bg-hover); padding-bottom: var(--space-sm);"
          >
            <HorizonButton
              size="sm"
              :disabled="isTabDisabled('recruiting')"
              :variant="activeTab === 'recruiting' ? 'primary' : 'ghost'"
              @click="setActiveTab('recruiting')"
            >
              Recruiting
            </HorizonButton>

            <HorizonButton
              size="sm"
              :disabled="isTabDisabled('overview')"
              :variant="activeTab === 'overview' ? 'primary' : 'ghost'"
              @click="setActiveTab('overview')"
            >
              Overview
            </HorizonButton>

            <HorizonButton
              size="sm"
              :disabled="isTabDisabled('status')"
              :variant="activeTab === 'status' ? 'primary' : 'ghost'"
              @click="setActiveTab('status')"
            >
              Status
            </HorizonButton>

            <HorizonButton
              size="sm"
              :disabled="isTabDisabled('roster')"
              :variant="activeTab === 'roster' ? 'primary' : 'ghost'"
              @click="setActiveTab('roster')"
            >
              Roster ({{ activeMemberCount }}/{{ maxRosterSize }})
            </HorizonButton>
          </div>

          <div v-if="activeTab === 'overview'" class="hz-animate-fade">
            <!-- Overview / Edit -->
            <SquadronOverviewSection
              v-if="!isTabLocked"
              :squadron="squadron"
            />

            <div v-else class="hz-stack gap-2">
              <label class="hz-label">Emblem</label>

              <div class="hz-row gap-3 items-center">
                <div class="w-20 h-20 rounded-lg overflow-hidden border border-(--horizon-sunset-blue) bg-bg-surface shrink-0">
                  <img
                    v-if="squadron?.emblem_url"
                    :src="squadron?.emblem?.medium_url || squadron?.emblem?.url || squadron?.emblem_url"
                    :alt="squadron?.emblem?.alt_text || `${squadron?.name} emblem`"
                    class="w-full h-full object-contain"
                    loading="lazy"
                  />
                </div>

                <div class="hz-row gap-2">
                  <HorizonButton
                    variant="ghost"
                    size="sm"
                    :disabled="emblemSaving"
                    v-if="canEditEmblem"
                    @click="openEmblemPicker"
                  >
                    Choose Emblem
                  </HorizonButton>

                  <HorizonButton
                    variant="ghost"
                    size="sm"
                    :disabled="emblemSaving || !squadron?.emblem_url"
                    v-if="canEditEmblem"
                    @click="clearEmblem"
                  >
                    Clear
                  </HorizonButton>
                </div>
              </div>

              <label class="hz-label">Motto</label>
              <input
                v-model="editForm.motto"
                class="hz-input"
                placeholder="Optional motto"
              />

              <label class="hz-label">Description</label>
              <HorizonRichTextEditor
                v-model="editForm.description"
                :rows="8"
                placeholder="Squadron description"
              />

              <div class="hz-row gap-2">
                <HorizonButton
                  v-if="canEdit"
                  variant="primary"
                  size="sm"
                  @click="saveSettings"
                >
                  Save
                </HorizonButton>
                <HorizonButton variant="ghost" size="sm" @click="cancelEdit">
                  Cancel
                </HorizonButton>
              </div>
            </div>
          </div>

          <div v-if="activeTab === 'status'" class="hz-animate-fade">
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
          </div>

          <div v-if="activeTab === 'recruiting'" class="hz-animate-fade">
            <div class="hz-stack">
              <div class="hz-row-between items-center">
                <div class="hz-section-label">Recruiting</div>
              </div>

              <div
                v-if="(!isTabLocked || !canEdit) && squadron?.recruitment_propaganda"
                class="hz-soft space-y-2 [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:list-decimal [&_ol]:pl-5 [&_a]:underline [&_h1]:text-xl [&_h1]:font-semibold [&_h2]:text-lg [&_h2]:font-semibold [&_h3]:text-base [&_h3]:font-semibold [&_h4]:text-sm [&_h4]:font-semibold [&_h5]:text-sm [&_h5]:font-medium [&_h6]:text-xs [&_h6]:font-medium [&_blockquote]:border-l-2 [&_blockquote]:border-(--color-bg-hover) [&_blockquote]:pl-3 [&_blockquote]:opacity-90 [&_hr]:my-3 [&_hr]:border-(--color-bg-hover) [&_code]:rounded [&_code]:px-1 [&_code]:py-0.5 [&_code]:bg-bg-elevated [&_pre]:rounded [&_pre]:p-3 [&_pre]:bg-bg-elevated [&_table]:w-full [&_table]:border-collapse [&_th]:border [&_th]:border-(--color-bg-hover) [&_th]:bg-bg-hover [&_th]:p-2 [&_td]:border [&_td]:border-(--color-bg-hover) [&_td]:bg-bg-elevated [&_td]:p-2 [&_mark]:rounded [&_mark]:px-1 [&_img]:max-w-full [&_img]:h-auto [&_img]:rounded-lg"
                v-html="squadron.recruitment_propaganda"
              />
              <div v-else-if="!isTabLocked || !canEdit" class="hz-soft">
                No recruitment message provided.
              </div>

              <div v-else class="hz-stack gap-2">
                <label class="hz-label">Recruitment Propaganda</label>
                <HorizonRichTextEditor
                  v-model="editForm.recruitment_propaganda"
                  :rows="10"
                  placeholder="Write a recruitment message…"
                />

                <div class="hz-row gap-2">
                  <HorizonButton variant="primary" size="sm" @click="saveSettings">
                    Save
                  </HorizonButton>
                  <HorizonButton variant="ghost" size="sm" @click="cancelRecruitingEdit">
                    Cancel
                  </HorizonButton>
                </div>
              </div>
            </div>
          </div>

          <div v-if="activeTab === 'roster'" class="hz-animate-fade">
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
          </div>
        </template>
      </section>
    </section>
  </div>

  <MediaPickerModal
    :open="emblemPickerOpen"
    collection="squadron_emblem"
    :squadronId="props.squadronId"
    :allowUpload="canEditEmblem"
    title="Select Squadron Emblem"
    @close="closeEmblemPicker"
    @selected="setEmblem"
  />
</template>
