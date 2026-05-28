<script setup>
import { ref, watch, computed, onMounted, onUnmounted } from 'vue'
import { router, usePage } from '@inertiajs/vue3'

import SquadronPanelHeader from './SquadronPanelHeader.vue'
import SquadronOverviewSection from './SquadronOverviewSection.vue'
import SquadronViewerStatus from './SquadronViewerStatus.vue'
import SquadronRoster from './SquadronRoster.vue'
import HorizonButton from '@/Components/HorizonButton.vue';
import MediaPickerModal from '@/Components/MediaPickerModal.vue'
import HorizonRichTextEditor from '@/Components/HorizonRichTextEditor.vue'
import { canEditSquadronEmblem as userCanEditSquadronEmblem } from '@/auth'
import { extractFirstErrorMessage, notifyErrorFromErrors } from '@/errors'
import HorizonConfirmDialog from '@/Components/HorizonConfirmDialog.vue'

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

/* -------------------------------------------------
   Confirm dialog refs + pending state
------------------------------------------------- */
const leaveConfirmDialog = ref(null)

const removeConfirmDialog = ref(null)
const pendingRemoveMember = ref(null)

const rejectConfirmDialog = ref(null)
const pendingRejectMember = ref(null)

const demoteConfirmDialog = ref(null)
const pendingDemoteMember = ref(null)

const promoteConfirmDialog = ref(null)
const pendingPromoteMember = ref(null)

const verifyUrl = 'https://horizoninterstellar.com/verify'
const page = usePage()
const activeSquadronPayload = computed(() => page.props?.activeSquadron ?? null)

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
  errorMessage.value = null

  router.put(route('squadrons.emblem.select', { squadron: squadron.value.id }), {
      emblem_media_id: media?.id ?? null,
    }, {
      preserveScroll: true,
      onSuccess: (visitPage) => {
        syncFromPayload(visitPage?.props?.activeSquadron ?? null)
        emit('updated', visitPage?.props?.activeSquadron?.squadron ?? squadron.value)
      },
      onError: (errors) => {
        const message = extractFirstErrorMessage(errors, 'Failed to update squadron emblem.')
        errorMessage.value = message
        notifyErrorFromErrors(errors)
      },
      onFinish: () => {
        emblemSaving.value = false
        closeEmblemPicker()
      },
    })
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

const authUser = computed(() => page.props.auth?.user ?? null)
const canUpdateSquadron = computed(() =>
  permissions.value?.can_update_squadron === true
)

const canEditEmblem = computed(() => {
  return userCanEditSquadronEmblem(authUser.value, viewerMembership.value)
})

const activeMemberCount = computed(() =>
  (members.value ?? []).filter(m => m?.membership_status === 'active').length
)

const maxRosterSize = 21

const lieutenantCount = computed(() =>
  (members.value ?? []).filter(member => member?.is_lieutenant === true).length
)

const canPromoteLieutenant = computed(() => lieutenantCount.value < 2)

function syncFromPayload(payload) {
  squadron.value = payload?.squadron ?? null
  members.value = Array.isArray(payload?.members) ? payload.members : []
  viewerMembership.value = payload?.viewer_membership ?? null
  permissions.value = payload?.permissions ?? {}

  editForm.value.motto = squadron.value?.motto ?? ''
  editForm.value.description = squadron.value?.description ?? ''
  editForm.value.recruitment_propaganda = squadron.value?.recruitment_propaganda ?? ''
}

function handlePanelError(errors, fallback) {
  errorStatus.value = null
  errorMessage.value = extractFirstErrorMessage(errors, fallback)
}

/* -------------------------------------------------
   Save settings
------------------------------------------------- */
async function saveSettings() {
  if (!squadron.value) return

  router.post(
      route('squadrons.settings.update', { squadron: squadron.value.id }),
      editForm.value
    , {
      preserveScroll: true,
      onSuccess: (visitPage) => {
        syncFromPayload(visitPage?.props?.activeSquadron ?? null)
        isEditing.value = false
        isRecruitingEditing.value = false
        emit('updated', visitPage?.props?.activeSquadron?.squadron ?? squadron.value)
      },
      onError: (errors) => {
        const message = extractFirstErrorMessage(errors, 'Failed to save squadron settings.')
        errorMessage.value = message
        window.hzNotifyError({ message })
      },
    }
  )
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

  router.post(route('squadrons.join', { squadron: squadron.value.id }), {}, {
    preserveScroll: true,
    onSuccess: (visitPage) => {
      syncFromPayload(visitPage?.props?.activeSquadron ?? null)
      emit('updated', visitPage?.props?.activeSquadron?.squadron ?? squadron.value)
    },
    onError: (errors) => {
      handlePanelError(errors, 'Failed to apply to squadron.')
    },
    onFinish: () => {
      activeAction.value = null
    },
  })
}

function askLeaveSquadron() {
  leaveConfirmDialog.value?.show()
}

async function leaveSquadron() {
  if (!squadron.value) return

  activeAction.value = 'leave'
  errorMessage.value = null
  errorStatus.value = null

  router.post(route('squadrons.leave', { squadron: squadron.value.id }), {}, {
    preserveScroll: true,
    onSuccess: (visitPage) => {
      syncFromPayload(visitPage?.props?.activeSquadron ?? null)
      emit('updated', visitPage?.props?.activeSquadron?.squadron ?? squadron.value)
    },
    onError: (errors) => {
      handlePanelError(errors, 'Failed to leave squadron.')
    },
    onFinish: () => {
      activeAction.value = null
    },
  })
}

async function acceptMember(member) {
  if (!squadron.value || !member?.id) return

  activeAction.value = 'accept-member'
  errorMessage.value = null
  errorStatus.value = null

  router.post(
      route('squadrons.members.update', { squadron: squadron.value.id }),
      {
        id: member.id,
        membership_status: 'active',
        role: null,
      }
    , {
      preserveScroll: true,
      onSuccess: (visitPage) => {
        syncFromPayload(visitPage?.props?.activeSquadron ?? null)
        emit('updated', visitPage?.props?.activeSquadron?.squadron ?? squadron.value)
      },
      onError: (errors) => {
        handlePanelError(errors, 'Failed to accept member.')
      },
      onFinish: () => {
        activeAction.value = null
      },
    }
  )
}

function askRejectMember(member) {
  pendingRejectMember.value = member
  rejectConfirmDialog.value?.show()
}

async function rejectMember(member) {
  if (!squadron.value || !member?.id) return

  activeAction.value = 'reject-member'
  errorMessage.value = null
  errorStatus.value = null

  router.post(route('squadrons.members.remove', { squadron: squadron.value.id }), {
    id: member.id,
  }, {
    preserveScroll: true,
    onSuccess: (visitPage) => {
      syncFromPayload(visitPage?.props?.activeSquadron ?? null)
      emit('updated', visitPage?.props?.activeSquadron?.squadron ?? squadron.value)
    },
    onError: (errors) => {
      handlePanelError(errors, 'Failed to reject member.')
    },
    onFinish: () => {
      activeAction.value = null
    },
  })
}

function askRemoveMember(member) {
  pendingRemoveMember.value = member
  removeConfirmDialog.value?.show()
}

async function removeMember(member) {
  if (!squadron.value || !member?.id) return

  activeAction.value = 'remove-member'
  errorMessage.value = null
  errorStatus.value = null

  router.post(route('squadrons.members.remove', { squadron: squadron.value.id }), {
    id: member.id,
  }, {
    preserveScroll: true,
    onSuccess: (visitPage) => {
      syncFromPayload(visitPage?.props?.activeSquadron ?? null)
      emit('updated', visitPage?.props?.activeSquadron?.squadron ?? squadron.value)
    },
    onError: (errors) => {
      handlePanelError(errors, 'Failed to remove member.')
    },
    onFinish: () => {
      activeAction.value = null
    },
  })
}

function askPromoteLieutenant(targetUser) {
  pendingPromoteMember.value = targetUser
  promoteConfirmDialog.value?.show()
}

async function promoteLieutenant(targetUser) {
  if (!squadron.value) return

  const userId = targetUser?.user_id ?? targetUser?.user?.id ?? targetUser?.id
  if (!userId) return

  activeAction.value = 'promote-lt'
  errorMessage.value = null
  errorStatus.value = null

  router.post(route('squadrons.promoteLieutenant', { squadron: squadron.value.id }), {
    user_id: userId,
  }, {
    preserveScroll: true,
    onSuccess: (visitPage) => {
      syncFromPayload(visitPage?.props?.activeSquadron ?? null)
      emit('updated', visitPage?.props?.activeSquadron?.squadron ?? squadron.value)
    },
    onError: (errors) => {
      handlePanelError(errors, 'Failed to promote member.')
    },
    onFinish: () => {
      activeAction.value = null
    },
  })
}

function askDemoteLieutenant(memberOrUser) {
  pendingDemoteMember.value = memberOrUser
  demoteConfirmDialog.value?.show()
}

async function demoteLieutenant(memberOrUser) {
  if (!squadron.value) return

  const userId = memberOrUser?.user_id ?? memberOrUser?.user?.id ?? memberOrUser?.id
  if (!userId) return

  activeAction.value = 'demote-lt'
  errorMessage.value = null
  errorStatus.value = null

  router.post(route('squadrons.demoteLieutenant', { squadron: squadron.value.id }), {
    user_id: userId,
  }, {
    preserveScroll: true,
    onSuccess: (visitPage) => {
      syncFromPayload(visitPage?.props?.activeSquadron ?? null)
      emit('updated', visitPage?.props?.activeSquadron?.squadron ?? squadron.value)
    },
    onError: (errors) => {
      handlePanelError(errors, 'Failed to demote member.')
    },
    onFinish: () => {
      activeAction.value = null
    },
  })
}

/* -------------------------------------------------
   Lifecycle
------------------------------------------------- */
onMounted(() => {
  syncFromPayload(activeSquadronPayload.value)

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
  () => activeSquadronPayload.value,
  (payload) => {
    syncFromPayload(payload)
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
            v-if="(canUpdateSquadron || canEditEmblem) && !isTabLocked && activeTab === 'overview'"
            variant="ghost"
            size="sm"
            @click="isEditing = true"
          >
            Edit
          </HorizonButton>

          <HorizonButton
            v-if="canUpdateSquadron && !isTabLocked && activeTab === 'recruiting'"
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
      <div class="relative">
        <div
          class="hz-row hz-text-soft gap-2 overflow-x-auto whitespace-nowrap flex-nowrap pr-10 sm:pr-0 sm:overflow-visible"
          style="border-bottom: 1px solid var(--color-bg-hover); padding-bottom: var(--space-sm);"
        >
          <HorizonButton
            class="shrink-0"
            size="sm"
            :disabled="isTabDisabled('recruiting')"
            :variant="activeTab === 'recruiting' ? 'primary' : 'ghost'"
            @click="setActiveTab('recruiting')"
          >
            Recruiting
          </HorizonButton>

          <HorizonButton
            class="shrink-0"
            size="sm"
            :disabled="isTabDisabled('overview')"
            :variant="activeTab === 'overview' ? 'primary' : 'ghost'"
            @click="setActiveTab('overview')"
          >
            Overview
          </HorizonButton>

          <HorizonButton
            class="shrink-0"
            size="sm"
            :disabled="isTabDisabled('status')"
            :variant="activeTab === 'status' ? 'primary' : 'ghost'"
            @click="setActiveTab('status')"
          >
            Status
          </HorizonButton>

          <HorizonButton
            class="shrink-0"
            size="sm"
            :disabled="isTabDisabled('roster')"
            :variant="activeTab === 'roster' ? 'primary' : 'ghost'"
            @click="setActiveTab('roster')"
          >
            Roster ({{ activeMemberCount }}/{{ maxRosterSize }})
          </HorizonButton>
        </div>

        <div
          class="pointer-events-none absolute inset-y-0 right-0 w-10 sm:hidden"
          style="background: linear-gradient(to left, var(--horizon-card), transparent);"
        ></div>

        <div class="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 text-[10px] font-semibold text-text-secondary sm:hidden">
          Swipe →
        </div>
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
              v-if="canUpdateSquadron"
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
          @leave="askLeaveSquadron"
        />
      </div>

      <div v-if="activeTab === 'recruiting'" class="hz-animate-fade">
        <div class="hz-stack">
          <div class="hz-row-between items-center">
            <div class="hz-section-label">Recruiting</div>
          </div>

          <div
            v-if="(!isTabLocked || !canUpdateSquadron) && squadron?.recruitment_propaganda"
            class="hz-soft space-y-2 [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:list-decimal [&_ol]:pl-5 [&_a]:underline [&_h1]:text-xl [&_h1]:font-semibold [&_h2]:text-lg [&_h2]:font-semibold [&_h3]:text-base [&_h3]:font-semibold [&_h4]:text-sm [&_h4]:font-semibold [&_h5]:text-sm [&_h5]:font-medium [&_h6]:text-xs [&_h6]:font-medium [&_blockquote]:border-l-2 [&_blockquote]:border-(--color-bg-hover) [&_blockquote]:pl-3 [&_blockquote]:opacity-90 [&_hr]:my-3 [&_hr]:border-(--color-bg-hover) [&_code]:rounded [&_code]:px-1 [&_code]:py-0.5 [&_code]:bg-bg-elevated [&_pre]:rounded [&_pre]:p-3 [&_pre]:bg-bg-elevated [&_table]:w-full [&_table]:border-collapse [&_th]:border [&_th]:border-(--color-bg-hover) [&_th]:bg-bg-hover [&_th]:p-2 [&_td]:border [&_td]:border-(--color-bg-hover) [&_td]:bg-bg-elevated [&_td]:p-2 [&_mark]:rounded [&_mark]:px-1 [&_img]:max-w-full [&_img]:h-auto [&_img]:rounded-lg"
            v-html="squadron.recruitment_propaganda"
          />
          <div v-else-if="!isTabLocked || !canUpdateSquadron" class="hz-soft">
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
                v-if="canUpdateSquadron"
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
          @reject-member="askRejectMember"
          @promote-lt="askPromoteLieutenant"
          @demote-lt="askDemoteLieutenant"
          @remove-member="askRemoveMember"
        />
      </div>
    </template>
  </div>

  <section
    v-else-if="isEmbedded"
    class="flex w-full flex-col rounded-[2rem] border! border-white/[0.055]! bg-[rgba(21,25,42,0.64)] "
  >
    <header
      class="flex shrink-0 items-start justify-between border-b border-[color:var(--horizon-sunset-blue)]/20 px-6 py-4"
    >
      <SquadronPanelHeader :squadron="squadron" />

      <div class="hz-row gap-2">
        <HorizonButton
          v-if="(canUpdateSquadron || canEditEmblem) && !isTabLocked && activeTab === 'overview'"
          variant="ghost"
          size="sm"
          @click="isEditing = true"
        >
          Edit
        </HorizonButton>

        <HorizonButton
          v-if="canUpdateSquadron && !isTabLocked && activeTab === 'recruiting'"
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
        <div class="relative">
          <div
            class="hz-row hz-text-soft gap-2 overflow-x-auto whitespace-nowrap flex-nowrap pr-10 sm:pr-0 sm:overflow-visible"
            style="border-bottom: 1px solid var(--color-bg-hover); padding-bottom: var(--space-sm);"
          >
            <HorizonButton
              class="shrink-0"
              size="sm"
              :disabled="isTabDisabled('recruiting')"
              :variant="activeTab === 'recruiting' ? 'primary' : 'ghost'"
              @click="setActiveTab('recruiting')"
            >
              Recruiting
            </HorizonButton>

            <HorizonButton
              class="shrink-0"
              size="sm"
              :disabled="isTabDisabled('overview')"
              :variant="activeTab === 'overview' ? 'primary' : 'ghost'"
              @click="setActiveTab('overview')"
            >
              Overview
            </HorizonButton>

            <HorizonButton
              class="shrink-0"
              size="sm"
              :disabled="isTabDisabled('status')"
              :variant="activeTab === 'status' ? 'primary' : 'ghost'"
              @click="setActiveTab('status')"
            >
              Status
            </HorizonButton>

            <HorizonButton
              class="shrink-0"
              size="sm"
              :disabled="isTabDisabled('roster')"
              :variant="activeTab === 'roster' ? 'primary' : 'ghost'"
              @click="setActiveTab('roster')"
            >
              Roster ({{ activeMemberCount }}/{{ maxRosterSize }})
            </HorizonButton>
          </div>

          <div
            class="pointer-events-none absolute inset-y-0 right-0 w-10 sm:hidden"
            style="background: linear-gradient(to left, var(--horizon-card), transparent);"
          ></div>

          <div class="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 text-[10px] font-semibold text-text-secondary sm:hidden">
            Swipe →
          </div>
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
                v-if="canUpdateSquadron"
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
            @leave="askLeaveSquadron"
          />
        </div>

        <div v-if="activeTab === 'recruiting'" class="hz-animate-fade">
          <div class="hz-stack">
            <div class="hz-row-between items-center">
              <div class="hz-section-label">Recruiting</div>
            </div>

            <div
              v-if="(!isTabLocked || !canUpdateSquadron) && squadron?.recruitment_propaganda"
              class="hz-soft space-y-2 [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:list-decimal [&_ol]:pl-5 [&_a]:underline [&_h1]:text-xl [&_h1]:font-semibold [&_h2]:text-lg [&_h2]:font-semibold [&_h3]:text-base [&_h3]:font-semibold [&_h4]:text-sm [&_h4]:font-semibold [&_h5]:text-sm [&_h5]:font-medium [&_h6]:text-xs [&_h6]:font-medium [&_blockquote]:border-l-2 [&_blockquote]:border-(--color-bg-hover) [&_blockquote]:pl-3 [&_blockquote]:opacity-90 [&_hr]:my-3 [&_hr]:border-(--color-bg-hover) [&_code]:rounded [&_code]:px-1 [&_code]:py-0.5 [&_code]:bg-bg-elevated [&_pre]:rounded [&_pre]:p-3 [&_pre]:bg-bg-elevated [&_table]:w-full [&_table]:border-collapse [&_th]:border [&_th]:border-(--color-bg-hover) [&_th]:bg-bg-hover [&_th]:p-2 [&_td]:border [&_td]:border-(--color-bg-hover) [&_td]:bg-bg-elevated [&_td]:p-2 [&_mark]:rounded [&_mark]:px-1 [&_img]:max-w-full [&_img]:h-auto [&_img]:rounded-lg"
              v-html="squadron.recruitment_propaganda"
            />
            <div v-else-if="!isTabLocked || !canUpdateSquadron" class="hz-soft">
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
            @reject-member="askRejectMember"
            @promote-lt="askPromoteLieutenant"
            @demote-lt="askDemoteLieutenant"
            @remove-member="askRemoveMember"
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
            v-if="(canUpdateSquadron || canEditEmblem) && !isTabLocked && activeTab === 'overview'"
            variant="ghost"
            size="sm"
            @click="isEditing = true"
          >
            Edit
          </HorizonButton>

          <HorizonButton
            v-if="canUpdateSquadron && !isTabLocked && activeTab === 'recruiting'"
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
          <div class="relative">
            <div
              class="hz-row hz-text-soft gap-2 overflow-x-auto whitespace-nowrap flex-nowrap pr-10 sm:pr-0 sm:overflow-visible"
              style="border-bottom: 1px solid var(--color-bg-hover); padding-bottom: var(--space-sm);"
            >
              <HorizonButton
                class="shrink-0"
                size="sm"
                :disabled="isTabDisabled('recruiting')"
                :variant="activeTab === 'recruiting' ? 'primary' : 'ghost'"
                @click="setActiveTab('recruiting')"
              >
                Recruiting
              </HorizonButton>

              <HorizonButton
                class="shrink-0"
                size="sm"
                :disabled="isTabDisabled('overview')"
                :variant="activeTab === 'overview' ? 'primary' : 'ghost'"
                @click="setActiveTab('overview')"
              >
                Overview
              </HorizonButton>

              <HorizonButton
                class="shrink-0"
                size="sm"
                :disabled="isTabDisabled('status')"
                :variant="activeTab === 'status' ? 'primary' : 'ghost'"
                @click="setActiveTab('status')"
              >
                Status
              </HorizonButton>

              <HorizonButton
                class="shrink-0"
                size="sm"
                :disabled="isTabDisabled('roster')"
                :variant="activeTab === 'roster' ? 'primary' : 'ghost'"
                @click="setActiveTab('roster')"
              >
                Roster ({{ activeMemberCount }}/{{ maxRosterSize }})
              </HorizonButton>
            </div>

            <div
              class="pointer-events-none absolute inset-y-0 right-0 w-10 sm:hidden"
              style="background: linear-gradient(to left, var(--horizon-card), transparent);"
            ></div>

            <div class="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 text-[10px] font-semibold text-text-secondary sm:hidden">
              Swipe →
            </div>
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
                  v-if="canUpdateSquadron"
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
              @leave="askLeaveSquadron"
            />
          </div>

          <div v-if="activeTab === 'recruiting'" class="hz-animate-fade">
            <div class="hz-stack">
              <div class="hz-row-between items-center">
                <div class="hz-section-label">Recruiting</div>
              </div>

              <div
                v-if="(!isTabLocked || !canUpdateSquadron) && squadron?.recruitment_propaganda"
                class="hz-soft space-y-2 [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:list-decimal [&_ol]:pl-5 [&_a]:underline [&_h1]:text-xl [&_h1]:font-semibold [&_h2]:text-lg [&_h2]:font-semibold [&_h3]:text-base [&_h3]:font-semibold [&_h4]:text-sm [&_h4]:font-semibold [&_h5]:text-sm [&_h5]:font-medium [&_h6]:text-xs [&_h6]:font-medium [&_blockquote]:border-l-2 [&_blockquote]:border-(--color-bg-hover) [&_blockquote]:pl-3 [&_blockquote]:opacity-90 [&_hr]:my-3 [&_hr]:border-(--color-bg-hover) [&_code]:rounded [&_code]:px-1 [&_code]:py-0.5 [&_code]:bg-bg-elevated [&_pre]:rounded [&_pre]:p-3 [&_pre]:bg-bg-elevated [&_table]:w-full [&_table]:border-collapse [&_th]:border [&_th]:border-(--color-bg-hover) [&_th]:bg-bg-hover [&_th]:p-2 [&_td]:border [&_td]:border-(--color-bg-hover) [&_td]:bg-bg-elevated [&_td]:p-2 [&_mark]:rounded [&_mark]:px-1 [&_img]:max-w-full [&_img]:h-auto [&_img]:rounded-lg"
                v-html="squadron.recruitment_propaganda"
              />
              <div v-else-if="!isTabLocked || !canUpdateSquadron" class="hz-soft">
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
              @reject-member="askRejectMember"
              @promote-lt="askPromoteLieutenant"
              @demote-lt="askDemoteLieutenant"
              @remove-member="askRemoveMember"
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

  <HorizonConfirmDialog
    ref="leaveConfirmDialog"
    title="Leave Squadron"
    confirm-label="Leave"
    cancel-label="Cancel"
    variant="warning"
    message="Leave this squadron? You will need to re-apply to rejoin."
    @confirm="leaveSquadron"
  />

  <HorizonConfirmDialog
    ref="removeConfirmDialog"
    title="Remove Member"
    confirm-label="Remove"
    cancel-label="Cancel"
    variant="danger"
    message="Remove this member from the squadron?"
    @confirm="() => { if (pendingRemoveMember) removeMember(pendingRemoveMember) }"
  />

  <HorizonConfirmDialog
    ref="rejectConfirmDialog"
    title="Reject Application"
    confirm-label="Reject"
    cancel-label="Cancel"
    variant="danger"
    message="Reject this member's application?"
    @confirm="() => { if (pendingRejectMember) rejectMember(pendingRejectMember) }"
  />

  <HorizonConfirmDialog
    ref="promoteConfirmDialog"
    title="Promote to Lieutenant"
    confirm-label="Promote"
    cancel-label="Cancel"
    variant="default"
    message="Promote this member to Lieutenant?"
    @confirm="() => { if (pendingPromoteMember) promoteLieutenant(pendingPromoteMember) }"
  />

  <HorizonConfirmDialog
    ref="demoteConfirmDialog"
    title="Demote Lieutenant"
    confirm-label="Demote"
    cancel-label="Cancel"
    variant="warning"
    message="Remove this member's Lieutenant rank?"
    @confirm="() => { if (pendingDemoteMember) demoteLieutenant(pendingDemoteMember) }"
  />
</template>








