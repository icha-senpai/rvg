<script setup>
import HorizonButton from '@/Components/HorizonButton.vue'
import HorizonConfirmDialog from '@/Components/HorizonConfirmDialog.vue'
import HorizonContainer from '@/Components/HorizonContainer.vue'
import OperationFundsPrepSection from '@/Components/OperationFundsPrepSection.vue'
import HorizonInput from '@/Components/HorizonInput.vue'
import HorizonSelect from '@/Components/HorizonSelect.vue'
import ProgressPill from '@/Components/ProgressPill.vue'
import { Head, router } from '@inertiajs/vue3'
import { computed, reactive, ref, watch } from 'vue'
import { route } from 'ziggy-js'

const props = defineProps({
  operation: Object,
  participants: Array,
  runtime: Object,
  fundsPrep: Object,
  verifiedMembers: Array,
})

const operation = computed(() => props.operation ?? {})
const runtime = computed(() => props.runtime ?? {})
const runtimeParticipants = computed(() => Array.isArray(runtime.value?.participants) ? runtime.value.participants : [])
const rosterParticipants = computed(() => Array.isArray(props.participants) ? props.participants : runtimeParticipants.value)
const summary = computed(() => runtime.value?.summary ?? {})
const syncRuns = computed(() => Array.isArray(runtime.value?.sync_runs) ? runtime.value.sync_runs : [])
const lastSyncRun = computed(() => runtime.value?.last_sync_run ?? null)
const lobbyChannelIds = computed(() => Array.isArray(runtime.value?.lobby_channel_ids) ? runtime.value.lobby_channel_ids : [])

const syncProcessing = ref(false)
const transitionProcessing = ref(false)
const saveLayoutProcessing = ref(false)
const channelSyncProcessing = ref(false)
const walkInProcessing = ref(false)
const channelDrafts = ref([])
const participantAssignments = ref({})
const participantStatusDrafts = ref({})
const participantStatusProcessing = ref({})
const managedSlotAssignments = reactive({})
const rosterManagementOpen = ref(false)
const slotUpdateParticipantId = ref(null)
const nextDraftKey = ref(1)
const startConfirmDialog = ref(null)
const cancelConfirmDialog = ref(null)
const completeDialogOpen = ref(false)
const selectedWalkInUserId = ref(null)

watch(runtime, (value) => {
  const sourceChannels = Array.isArray(value?.discord_channels) ? value.discord_channels : []

  channelDrafts.value = sourceChannels.map((channel) => ({
    id: channel.id ?? null,
    client_key: channel.client_key ?? `saved-${channel.id}`,
    name: channel.name ?? '',
    discord_channel_id: channel.discord_channel_id ?? null,
  }))

  nextDraftKey.value = channelDrafts.value.length + 1

  const assignments = {}
  const statusDrafts = {}
  runtimeParticipants.value.forEach((participant) => {
    assignments[participant.id] = participant.operation_discord_channel_key ?? ''
    statusDrafts[participant.id] = editableRuntimeState(participant)
  })
  participantAssignments.value = assignments
  participantStatusDrafts.value = statusDrafts
}, { immediate: true, deep: true })

const presentParticipants = computed(() => runtimeParticipants.value.filter((participant) => participant?.synced_in_at))
const noShowParticipants = computed(() => runtimeParticipants.value.filter((participant) => participant?.runtime_status === 'no_show'))
const excusedParticipants = computed(() => runtimeParticipants.value.filter((participant) => participant?.runtime_status === 'excused'))
const signedOffParticipants = computed(() => runtimeParticipants.value.filter((participant) => participant?.runtime_status === 'signed_off_before_start'))
const waitingParticipants = computed(() => runtimeParticipants.value.filter((participant) => {
  if (participant?.runtime_status === 'signed_off_before_start') return false
  if (participant?.runtime_status === 'no_show') return false
  if (participant?.runtime_status === 'excused') return false
  return !participant?.synced_in_at
}))

const canStartOperation = computed(() => operation.value?.status === 'published')
const canCompleteOperation = computed(() => operation.value?.status === 'in_progress')
const canCancelOperation = computed(() => ['published', 'in_progress'].includes(operation.value?.status ?? ''))
const runtimeButtonsHidden = computed(() => ['completed', 'canceled'].includes(operation.value?.status ?? ''))
const verifiedMembers = computed(() => Array.isArray(props.verifiedMembers) ? props.verifiedMembers : [])
const canViewSlots = computed(() => Boolean(operation.value?.permissions?.can_view_slots))
const canAssignSlots = computed(() => Boolean(operation.value?.permissions?.can_assign_slots))
const operationRoles = computed(() => {
  if (Array.isArray(operation.value?.roles) && operation.value.roles.length) {
    return operation.value.roles
  }

  return Array.isArray(operation.value?.slots)
    ? operation.value.slots.map((slot) => ({
        id: `slot:${slot}`,
        role_name: slot,
        role_display_name: slot,
        capacity: null,
        filled_count: null,
        remaining_spots: null,
        is_full: false,
      }))
    : []
})
const rosterAssignmentCount = computed(() => {
  return rosterParticipants.value.filter((participant) => filledSlot(participant?.slot)).length
})
const rosterParticipantsBySlot = computed(() => {
  return rosterParticipants.value.reduce((groups, participant) => {
    const slotName = normalizeSlotName(participant?.slot)
    if (!slotName) return groups

    if (!groups[slotName]) {
      groups[slotName] = []
    }

    groups[slotName].push(participant)
    return groups
  }, {})
})
const rosterUnassignedParticipants = computed(() => {
  return rosterParticipants.value.filter((participant) => !filledSlot(participant?.slot))
})
const channelSyncLabel = computed(() => {
  if (operation.value?.status === 'in_progress') return 'Move All Members'
  if (channelDrafts.value.some((channel) => channel.discord_channel_id)) return 'Move All Members'
  return 'Create Discord Channels'
})
const startConfirmMessage = computed(() => {
  return operation.value?.title
    ? `You are about to mark "${operation.value.title}" as In Progress.`
    : 'You are about to start this operation.'
})

const channelOptions = computed(() => {
  return [
    { label: 'Unassigned', value: '' },
    ...channelDrafts.value.map((channel) => ({
      label: channel.name?.trim() || 'Untitled Channel',
      value: channel.client_key,
    })),
  ]
})
const runtimeStatusOptions = [
  { label: 'Waiting / Unsynced', value: 'waiting' },
  { label: 'Present', value: 'present' },
  { label: 'Not Here', value: 'no_show' },
  { label: 'Excused', value: 'excused' },
  { label: 'Signed Off Before Start', value: 'signed_off_before_start' },
  { label: 'Tech Issues', value: 'technical_issue' },
]
const walkInMemberOptions = computed(() => {
  const activeRosterUserIds = new Set(
    runtimeParticipants.value
      .filter((participant) => participant?.runtime_status !== 'signed_off_before_start')
      .map((participant) => Number(participant?.user?.id))
      .filter(Number.isFinite),
  )

  return verifiedMembers.value
    .filter((member) => !activeRosterUserIds.has(Number(member?.id)))
    .map((member) => ({
      label: member?.rsi_handle
        ?? member?.discord_name
        ?? member?.name
        ?? `Member #${member?.id}`,
      value: member?.id,
    }))
})

function statusVariant(value) {
  switch (value) {
    case 'published': return 'blue'
    case 'in_progress': return 'green'
    case 'completed': return 'green'
    case 'canceled': return 'red'
    default: return 'blue'
  }
}

function runtimeStatusVariant(value, syncedInAt) {
  if (value === 'signed_off_before_start') return 'red'
  if (value === 'no_show') return 'red'
  if (value === 'excused') return 'yellow'
  if (value === 'technical_issue') return 'yellow'
  if (value === 'operation_finished') return 'green'
  if (syncedInAt) return 'green'
  return 'blue'
}

function runtimeStatusLabel(participant) {
  if (participant?.runtime_status === 'signed_off_before_start') return 'Signed Off'
  if (participant?.runtime_status === 'no_show') return 'Not Here'
  if (participant?.runtime_status === 'excused') return 'Excused'
  if (participant?.runtime_status === 'technical_issue') return 'Tech Issues'
  if (participant?.runtime_status === 'operation_finished') return 'Finished'
  if (participant?.synced_in_at) return 'Present'
  return 'Waiting'
}

function editableRuntimeState(participant) {
  if (participant?.runtime_status === 'signed_off_before_start') return 'signed_off_before_start'
  if (participant?.runtime_status === 'no_show') return 'no_show'
  if (participant?.runtime_status === 'excused') return 'excused'
  if (participant?.runtime_status === 'technical_issue') return 'technical_issue'
  if (participant?.runtime_status === 'operation_finished') return 'operation_finished'
  if (participant?.synced_in_at) return 'present'
  return 'waiting'
}

function participantName(participant) {
  return participant?.user?.rsi_handle
    ?? participant?.user?.display_name
    ?? participant?.user?.name
    ?? 'Unknown member'
}

function participantAvatar(participant) {
  return participant?.user?.discord_avatar ?? participant?.user?.avatar ?? null
}

function participantInitial(participant) {
  return String(participantName(participant)).slice(0, 1).toUpperCase()
}

function participantRole(participant) {
  return participant?.role?.role_display_name ?? participant?.slot ?? null
}

function filledSlot(value) {
  return typeof value === 'string' && value.trim().length > 0
}

function normalizeSlotName(value) {
  return filledSlot(value) ? value.trim() : ''
}

function participantsForChannel(channelKey) {
  return runtimeParticipants.value.filter((participant) => participantAssignments.value[participant.id] === channelKey)
}

function formatDateTime(value) {
  if (!value) return 'Not yet'

  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return String(value)

  return new Intl.DateTimeFormat('en-US', {
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  }).format(date)
}

function participantStatusChanged(participant) {
  return participantStatusDrafts.value[participant.id] !== editableRuntimeState(participant)
}

function firstErrorMessage(errors, fallback) {
  const values = Object.values(errors ?? {})
  const first = values[0]

  if (Array.isArray(first)) {
    return first[0] ?? fallback
  }

  return first ?? fallback
}

function roleCapacityLabel(role, selectedRoleId = null) {
  if (!role) return ''

  const roleId = String(role.id ?? '')
  const isSelectedRole = String(selectedRoleId ?? '') === roleId

  if (role.is_full && !isSelectedRole) {
    return `${role.role_display_name} - FULL`
  }

  if (typeof role.remaining_spots === 'number') {
    const noun = role.remaining_spots === 1 ? 'spot' : 'spots'
    return `${role.role_display_name} - ${role.remaining_spots} ${noun} left`
  }

  return role.role_display_name
}

function roleOptionsFor(selectedRoleId = null) {
  return [
    { label: 'No Role', value: '' },
    ...operationRoles.value.map((role) => ({
      label: roleCapacityLabel(role, selectedRoleId),
      value: String(role.id),
    })),
  ]
}

function roleIdForParticipant(participant) {
  if (participant?.role?.id) {
    return String(participant.role.id)
  }

  const slotName = normalizeSlotName(participant?.slot)
  if (!slotName) return ''

  const matchingRole = operationRoles.value.find((role) => normalizeSlotName(role?.role_display_name) === slotName)
  return matchingRole ? String(matchingRole.id) : ''
}

function selectedSlotForParticipant(participant) {
  return managedSlotAssignments[participant.id] ?? roleIdForParticipant(participant)
}

function participantSlotChanged(participant) {
  return String(selectedSlotForParticipant(participant) ?? '') !== String(roleIdForParticipant(participant) ?? '')
}

function findRoleById(roleId) {
  if (roleId === null || roleId === undefined || roleId === '') return null

  return operationRoles.value.find((role) => String(role.id) === String(roleId)) ?? null
}

function payloadForSelectedRole(roleId) {
  const role = findRoleById(roleId)

  if (!role) {
    return {
      slot: null,
      operation_role_id: null,
    }
  }

  return {
    slot: role.role_display_name,
    operation_role_id: String(role.id).startsWith('slot:') ? null : role.id,
  }
}

function addChannel() {
  const clientKey = `draft-${nextDraftKey.value++}`

  channelDrafts.value.push({
    id: null,
    client_key: clientKey,
    name: `Channel ${channelDrafts.value.length + 1}`,
    discord_channel_id: null,
  })
}

function removeChannel(channelKey) {
  channelDrafts.value = channelDrafts.value.filter((channel) => channel.client_key !== channelKey)

  Object.keys(participantAssignments.value).forEach((participantId) => {
    if (participantAssignments.value[participantId] === channelKey) {
      participantAssignments.value[participantId] = ''
    }
  })
}

function layoutPayload() {
  return {
    channels: channelDrafts.value.map((channel) => ({
      id: channel.id,
      client_key: channel.client_key,
      name: channel.name,
    })),
    participant_assignments: runtimeParticipants.value.map((participant) => ({
      participant_id: participant.id,
      channel_key: participantAssignments.value[participant.id] || null,
    })),
  }
}

function saveLayout() {
  if (saveLayoutProcessing.value) return

  saveLayoutProcessing.value = true

  router.post(route('operations.run.layout', operation.value.id), layoutPayload(), {
    preserveScroll: true,
    onFinish: () => {
      saveLayoutProcessing.value = false
    },
  })
}

function syncLobby() {
  if (syncProcessing.value) return

  syncProcessing.value = true

  router.post(route('operations.run.sync', operation.value.id), {}, {
    preserveScroll: true,
    onFinish: () => {
      syncProcessing.value = false
    },
  })
}

function syncChannels() {
  if (channelSyncProcessing.value) return

  channelSyncProcessing.value = true

  router.post(route('operations.run.channels.sync', operation.value.id), layoutPayload(), {
    preserveScroll: true,
    onFinish: () => {
      channelSyncProcessing.value = false
    },
  })
}

function saveParticipantStatus(participant) {
  if (participantStatusProcessing.value[participant.id]) return

  participantStatusProcessing.value = {
    ...participantStatusProcessing.value,
    [participant.id]: true,
  }

  router.post(route('operations.run.participants.update', {
    operation: operation.value.id,
    participant: participant.id,
  }), {
    status: participantStatusDrafts.value[participant.id] ?? editableRuntimeState(participant),
  }, {
    preserveScroll: true,
    onFinish: () => {
      participantStatusProcessing.value = {
        ...participantStatusProcessing.value,
        [participant.id]: false,
      }
    },
  })
}

function updateParticipantSlot(participant) {
  if (!participant?.id) return
  if (!canAssignSlots.value) return
  if (slotUpdateParticipantId.value) return
  if (!participantSlotChanged(participant)) return

  slotUpdateParticipantId.value = participant.id

  router.post(route('operations.participants.slot', {
    operation: operation.value.id,
    participant: participant.id,
  }), {
    ...payloadForSelectedRole(selectedSlotForParticipant(participant)),
  }, {
    preserveScroll: true,
    preserveState: true,
    onError: (errors) => {
      window.hzNotifyError?.({
        message: firstErrorMessage(errors, 'Failed to update role.'),
      })
    },
    onFinish: () => {
      slotUpdateParticipantId.value = null
    },
  })
}

function addWalkIn() {
  if (walkInProcessing.value || !selectedWalkInUserId.value) return

  walkInProcessing.value = true

  router.post(route('operations.run.walk-ins.store', operation.value.id), {
    user_id: selectedWalkInUserId.value,
  }, {
    preserveScroll: true,
    onSuccess: () => {
      selectedWalkInUserId.value = null
    },
    onFinish: () => {
      walkInProcessing.value = false
    },
  })
}

function askStartOperation() {
  if (!canStartOperation.value || transitionProcessing.value) return

  startConfirmDialog.value?.show()
}

function confirmStartOperation({ close, finish }) {
  if (!canStartOperation.value) {
    finish()
    return
  }

  transitionProcessing.value = true

  router.post(route('operations.start', operation.value.id), {}, {
    preserveScroll: true,
    onSuccess: () => {
      close()
    },
    onError: () => {
      window.hzNotifyError?.({ message: 'Failed to start operation.' })
      finish()
    },
    onFinish: () => {
      transitionProcessing.value = false
    },
  })
}

function askCancelOperation() {
  if (!canCancelOperation.value || transitionProcessing.value) return

  cancelConfirmDialog.value?.show()
}

function confirmCancelOperation({ close, finish, text }) {
  if (!canCancelOperation.value) {
    finish()
    return
  }

  transitionProcessing.value = true

  router.post(route('operations.cancel', operation.value.id), {
    reason: text,
  }, {
    preserveScroll: true,
    onSuccess: () => {
      close()
    },
    onError: () => {
      window.hzNotifyError?.({ message: 'Failed to cancel operation.' })
      finish()
    },
    onFinish: () => {
      transitionProcessing.value = false
    },
  })
}

function openCompleteDialog() {
  if (!canCompleteOperation.value || transitionProcessing.value) return

  completeDialogOpen.value = true
}

function closeCompleteDialog() {
  if (transitionProcessing.value) return

  completeDialogOpen.value = false
}

function submitCompleteOperation(outcome) {
  if (!canCompleteOperation.value || !outcome || transitionProcessing.value) return

  transitionProcessing.value = true
  const afterActionUrl = `${route('operations.show', operation.value.id)}#after-action-report`

  router.post(route('operations.complete', operation.value.id), {
    outcome,
  }, {
    preserveScroll: true,
    onSuccess: () => {
      completeDialogOpen.value = false
      router.visit(afterActionUrl)
    },
    onError: () => {
      window.hzNotifyError?.({ message: 'Failed to complete operation.' })
    },
    onFinish: () => {
      transitionProcessing.value = false
    },
  })
}

watch(
  rosterParticipants,
  (participants) => {
    const activeParticipantIds = new Set()

    participants.forEach((participant) => {
      activeParticipantIds.add(String(participant.id))
      managedSlotAssignments[participant.id] = roleIdForParticipant(participant)
    })

    Object.keys(managedSlotAssignments).forEach((participantId) => {
      if (!activeParticipantIds.has(String(participantId))) {
        delete managedSlotAssignments[participantId]
      }
    })
  },
  { immediate: true }
)
</script>

<template>
  <Head :title="`Run Tool - ${operation.title ?? 'Operation'}`" />

  <HorizonContainer class="py-8 md:py-10">
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6">
      <section class="hz-surface-welcome relative overflow-hidden rounded-[2rem] border border-white/[0.055]">
        <div class="pointer-events-none absolute inset-0 opacity-20">
          <div class="absolute inset-x-8 top-0 h-px bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-blue)] to-transparent"></div>
          <div class="absolute inset-x-10 bottom-0 h-px bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-magenta)] to-transparent"></div>
        </div>

        <div class="relative flex flex-wrap items-start justify-between gap-4 p-5 md:p-6">
          <div class="space-y-3">
            <div class="flex flex-wrap items-center gap-3">
              <span class="text-xs font-bold uppercase tracking-[0.2em] text-text-muted">Operation Run Tool</span>
              <ProgressPill :variant="statusVariant(operation.status)" size="sm">
                {{ String(operation.status ?? 'draft').replaceAll('_', ' ') }}
              </ProgressPill>
            </div>

            <div class="text-3xl font-black tracking-tight text-horizon-white">
              {{ operation.title || `Operation #${operation.id}` }}
            </div>

            <div class="flex flex-wrap gap-2 text-sm text-text-secondary">
              <span class="hz-surface-welcome rounded-full border border-white/[0.055] px-3 py-1.5">Lobby 1: {{ lobbyChannelIds[0] || 'Not set' }}</span>
              <span class="hz-surface-welcome rounded-full border border-white/[0.055] px-3 py-1.5">Lobby 2: {{ lobbyChannelIds[1] || 'Not set' }}</span>
              <span class="hz-surface-welcome rounded-full border border-white/[0.055] px-3 py-1.5">Last sync: {{ formatDateTime(lastSyncRun?.synced_at) }}</span>
            </div>
          </div>

          <div class="flex flex-wrap gap-3">
            <a :href="route('operations.show', operation.id)">
              <HorizonButton variant="outline">
                Back to Operation
              </HorizonButton>
            </a>

            <HorizonButton v-if="!runtimeButtonsHidden" variant="outline" :disabled="syncProcessing" @click="syncLobby">
              {{ syncProcessing ? 'Syncing…' : 'Sync Lobbies' }}
            </HorizonButton>

            <HorizonButton v-if="!runtimeButtonsHidden" variant="primary" :disabled="channelSyncProcessing" @click="syncChannels">
              {{ channelSyncProcessing ? 'Syncing…' : channelSyncLabel }}
            </HorizonButton>

            <HorizonButton v-if="!runtimeButtonsHidden && canStartOperation" variant="primary" :disabled="transitionProcessing" @click="askStartOperation">
              {{ transitionProcessing ? 'Starting…' : 'Start Operation' }}
            </HorizonButton>

            <HorizonButton v-if="!runtimeButtonsHidden && canCompleteOperation" variant="primary" :disabled="transitionProcessing" @click="openCompleteDialog">
              {{ transitionProcessing ? 'Finishing…' : 'Finish Operation' }}
            </HorizonButton>

            <HorizonButton v-if="!runtimeButtonsHidden && canCancelOperation" variant="danger" :disabled="transitionProcessing" @click="askCancelOperation">
              {{ transitionProcessing ? 'Canceling…' : 'Cancel Operation' }}
            </HorizonButton>
          </div>
        </div>
      </section>

      <div class="space-y-6">
        <OperationFundsPrepSection
          :operation="operation"
          :funds-prep="props.fundsPrep"
          :runtime-participants="runtimeParticipants"
        />

        <section
          v-if="canViewSlots"
          class="hz-surface-welcome rounded-[1.5rem] border border-white/[0.055] p-5"
        >
          <button
            type="button"
            class="flex w-full items-start justify-between gap-4 text-left"
            :aria-expanded="rosterManagementOpen"
            @click="rosterManagementOpen = !rosterManagementOpen"
          >
            <div>
              <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Roster Management</div>
              <div class="mt-2 text-lg font-black text-horizon-white">Adjust the roles and slots people picked during signups</div>
              <p class="mt-3 max-w-4xl text-sm text-text-secondary">
                This mirrors the roster assignment area, so you can reshuffle people in the run tool without backing out to the operation page.
              </p>
            </div>

            <div class="flex shrink-0 flex-wrap items-center justify-end gap-2">
              <div class="hz-surface-welcome rounded-full border border-white/[0.055] px-3 py-1 text-xs font-semibold text-text-secondary">
                {{ rosterAssignmentCount }}/{{ rosterParticipants.length }} assigned
              </div>
              <div class="hz-surface-welcome flex h-9 w-9 items-center justify-center rounded-full border border-white/[0.055] text-sm font-bold text-horizon-white transition-transform duration-200" :class="rosterManagementOpen ? 'rotate-180' : ''">
                ⌄
              </div>
            </div>
          </button>

          <div v-if="rosterManagementOpen" class="mt-4 space-y-5">
            <div v-if="(operation.slots || []).length" class="space-y-4">
              <div
                v-for="slotName in operation.slots || []"
                :key="slotName"
                class="hz-surface-welcome rounded-[1.25rem] border border-white/[0.055] p-4"
              >
                <div class="flex items-center justify-between gap-3 text-sm">
                  <span class="font-semibold text-horizon-white">{{ slotName }}</span>
                  <span class="text-xs text-text-muted">{{ (rosterParticipantsBySlot[slotName] || []).length }}</span>
                </div>

                <div class="mt-3 space-y-2">
                  <div
                    v-for="participant in rosterParticipantsBySlot[slotName] || []"
                    :key="`slot-${slotName}-${participant.id}`"
                    class="flex flex-wrap items-center justify-between gap-3 border-t border-white/[0.04] px-1 py-3 first:border-t-0"
                  >
                    <div class="flex min-w-0 items-center gap-2">
                      <img v-if="participantAvatar(participant)" :src="participantAvatar(participant)" :alt="participantName(participant)" class="h-7 w-7 shrink-0 rounded-full object-cover" loading="lazy" />
                      <div v-else class="hz-shell-avatar flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-[10px] font-bold">{{ participantInitial(participant) }}</div>
                      <span class="truncate text-sm text-horizon-white">{{ participantName(participant) }}</span>
                    </div>

                    <div class="flex w-full flex-col items-stretch gap-2 sm:w-auto sm:flex-row sm:flex-wrap sm:items-center sm:justify-end">
                      <ProgressPill :variant="runtimeStatusVariant(participant.runtime_status, participant.synced_in_at)" size="sm">
                        {{ runtimeStatusLabel(participant) }}
                      </ProgressPill>

                      <template v-if="canAssignSlots">
                        <div class="w-full sm:min-w-[12rem]">
                          <HorizonSelect
                            v-model="managedSlotAssignments[participant.id]"
                            :options="roleOptionsFor(roleIdForParticipant(participant))"
                            size="sm"
                          />
                        </div>
                        <HorizonButton
                          variant="outline"
                          size="sm"
                          :disabled="slotUpdateParticipantId === participant.id || !participantSlotChanged(participant)"
                          @click="updateParticipantSlot(participant)"
                        >
                          {{ slotUpdateParticipantId === participant.id ? 'Moving…' : 'Move' }}
                        </HorizonButton>
                      </template>
                    </div>
                  </div>

                  <div v-if="!(rosterParticipantsBySlot[slotName] || []).length" class="border-t border-dashed border-white/[0.08] px-1 py-4 text-sm italic text-text-muted">
                    Empty
                  </div>
                </div>
              </div>
            </div>

            <div v-else class="rounded-[1.25rem] border border-dashed border-white/15 bg-white/[0.025] px-4 py-5 text-sm text-text-secondary">
              No slots defined for this operation yet.
            </div>

            <div v-if="rosterUnassignedParticipants.length" class="hz-surface-welcome rounded-[1.25rem] border border-white/[0.055] p-4">
              <div class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">No Role</div>

              <div class="mt-3 space-y-2">
                <div
                  v-for="participant in rosterUnassignedParticipants"
                  :key="`unassigned-${participant.id}`"
                  class="flex flex-wrap items-center justify-between gap-3 border-t border-white/[0.04] px-1 py-3 first:border-t-0"
                >
                  <div class="flex min-w-0 items-center gap-2">
                    <img v-if="participantAvatar(participant)" :src="participantAvatar(participant)" :alt="participantName(participant)" class="h-7 w-7 shrink-0 rounded-full object-cover" loading="lazy" />
                    <div v-else class="hz-shell-avatar flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-[10px] font-bold">{{ participantInitial(participant) }}</div>
                    <span class="truncate text-sm text-horizon-white">{{ participantName(participant) }}</span>
                  </div>

                  <div class="flex w-full flex-col items-stretch gap-2 sm:w-auto sm:flex-row sm:flex-wrap sm:items-center sm:justify-end">
                    <ProgressPill :variant="runtimeStatusVariant(participant.runtime_status, participant.synced_in_at)" size="sm">
                      {{ runtimeStatusLabel(participant) }}
                    </ProgressPill>

                    <template v-if="canAssignSlots">
                      <div class="w-full sm:min-w-[12rem]">
                        <HorizonSelect
                          v-model="managedSlotAssignments[participant.id]"
                          :options="roleOptionsFor(roleIdForParticipant(participant))"
                          size="sm"
                        />
                      </div>
                      <HorizonButton
                        variant="outline"
                        size="sm"
                        :disabled="slotUpdateParticipantId === participant.id || !participantSlotChanged(participant)"
                        @click="updateParticipantSlot(participant)"
                      >
                        {{ slotUpdateParticipantId === participant.id ? 'Moving…' : 'Move' }}
                      </HorizonButton>
                    </template>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>

        <section class="hz-surface-welcome rounded-[1.75rem] border border-white/[0.055] p-5 md:p-6">
          <div class="flex items-center justify-between gap-3">
            <div>
              <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Discord Channel Layout</div>
              <div class="mt-2 text-lg font-black text-horizon-white">Create channels and assign synced people to them</div>
            </div>

            <div v-if="!runtimeButtonsHidden" class="flex flex-wrap justify-end gap-2">
              <HorizonButton variant="outline" @click="addChannel">
                Add Channel
              </HorizonButton>

              <HorizonButton variant="outline" :disabled="saveLayoutProcessing" @click="saveLayout">
                {{ saveLayoutProcessing ? 'Saving…' : 'Save Channel Layout' }}
              </HorizonButton>
            </div>
          </div>

          <p class="mt-4 max-w-3xl text-sm text-text-secondary">
            Only members who were actually synced in and have a linked Discord account will get join permission and be moved when you run the Discord channel sync.
          </p>

          <div class="mt-6 space-y-4">
            <div
              v-for="channel in channelDrafts"
              :key="channel.client_key"
              class="hz-surface-welcome rounded-[1.25rem] border border-white/[0.055] p-4"
            >
              <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                  <HorizonInput v-model="channel.name" placeholder="Voice channel name" />
                  <div class="mt-2 text-xs text-text-secondary">
                    Discord channel id: {{ channel.discord_channel_id || 'Not created yet' }}
                  </div>
                </div>

                <HorizonButton v-if="!runtimeButtonsHidden" variant="ghost" @click="removeChannel(channel.client_key)">
                  Remove
                </HorizonButton>
              </div>

              <div class="mt-4">
                <div class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Assigned Members</div>
                <div class="mt-3 grid gap-3 md:grid-cols-2">
                  <div
                    v-for="participant in participantsForChannel(channel.client_key)"
                    :key="`channel-${channel.client_key}-${participant.id}`"
                    class="hz-surface-welcome rounded-[1rem] border border-white/[0.055] px-3 py-3"
                  >
                    <div class="flex items-center gap-3">
                      <img v-if="participantAvatar(participant)" :src="participantAvatar(participant)" :alt="participantName(participant)" class="h-10 w-10 rounded-full object-cover" loading="lazy" />
                      <div v-else class="hz-shell-avatar flex h-10 w-10 items-center justify-center rounded-full text-sm font-black">{{ participantInitial(participant) }}</div>
                      <div class="min-w-0">
                        <div class="truncate text-sm font-semibold text-horizon-white">{{ participantName(participant) }}</div>
                        <div class="text-xs text-text-secondary">{{ participantRole(participant) || 'No role set' }}</div>
                      </div>
                    </div>
                  </div>

                  <div v-if="!participantsForChannel(channel.client_key).length" class="rounded-[1rem] border border-dashed border-white/15 bg-white/[0.025] px-4 py-4 text-sm text-text-secondary">
                    Nobody assigned to this channel yet.
                  </div>
                </div>
              </div>
            </div>

            <div v-if="!channelDrafts.length" class="rounded-[1.25rem] border border-dashed border-white/15 bg-white/[0.025] px-4 py-5 text-sm text-text-secondary">
              No operation Discord channels configured yet.
            </div>
          </div>
        </section>

        <section class="hz-surface-welcome rounded-[1.75rem] border border-white/[0.055] p-5 md:p-6">
          <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Assign Members</div>
          <div class="mt-2 text-lg font-black text-horizon-white">Choose which operation channel each person belongs in</div>

          <div class="mt-6 grid gap-4">
            <div
              v-for="participant in runtimeParticipants"
              :key="`assignment-${participant.id}`"
              class="hz-surface-welcome rounded-[1.25rem] border border-white/[0.055] p-4"
            >
              <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex min-w-0 items-center gap-3">
                  <img v-if="participantAvatar(participant)" :src="participantAvatar(participant)" :alt="participantName(participant)" class="h-11 w-11 rounded-full object-cover" loading="lazy" />
                  <div v-else class="hz-shell-avatar flex h-11 w-11 items-center justify-center rounded-full text-sm font-black">{{ participantInitial(participant) }}</div>
                  <div class="min-w-0">
                    <div class="truncate text-sm font-semibold text-horizon-white">{{ participantName(participant) }}</div>
                    <div class="text-xs text-text-secondary">{{ participantRole(participant) || 'No role set' }}</div>
                  </div>
                </div>

                <ProgressPill :variant="runtimeStatusVariant(participant.runtime_status, participant.synced_in_at)" size="sm">
                  {{ runtimeStatusLabel(participant) }}
                </ProgressPill>
              </div>

              <div class="mt-4 grid gap-3 md:grid-cols-[minmax(0,1fr)_14rem] md:items-end">
                <div class="text-sm text-text-secondary">
                  {{ participant.synced_in_at ? `Synced ${formatDateTime(participant.synced_in_at)}` : 'Not currently synced into the live op roster.' }}
                </div>

                <HorizonSelect
                  v-model="participantAssignments[participant.id]"
                  :options="channelOptions"
                  placeholder="Assign channel"
                  size="sm"
                />
              </div>

              <div v-if="!runtimeButtonsHidden" class="mt-4 grid gap-3 md:grid-cols-[minmax(0,1fr)_auto] md:items-end">
                <HorizonSelect
                  v-model="participantStatusDrafts[participant.id]"
                  :options="runtimeStatusOptions"
                  placeholder="Choose live status"
                  size="sm"
                />

                <HorizonButton
                  variant="outline"
                  :disabled="participantStatusProcessing[participant.id] || !participantStatusChanged(participant)"
                  @click="saveParticipantStatus(participant)"
                >
                  {{ participantStatusProcessing[participant.id] ? 'Saving…' : 'Save Status' }}
                </HorizonButton>
              </div>
            </div>
          </div>
        </section>

        <section
          v-if="!runtimeButtonsHidden"
          class="hz-surface-welcome rounded-[1.5rem] border border-white/[0.055] p-5"
        >
          <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Walk-Ins</div>
          <div class="mt-2 text-lg font-black text-horizon-white">Add someone straight to the live roster</div>
          <p class="mt-3 text-sm text-text-secondary">
            Use this when somebody shows up for the op but was not on the original sign-up list.
          </p>

          <div class="mt-4 grid gap-3 md:grid-cols-[minmax(0,1fr)_auto] md:items-end">
            <HorizonSelect
              v-model="selectedWalkInUserId"
              :options="walkInMemberOptions"
              placeholder="Select a verified member"
              searchable
              search-placeholder="Search verified members..."
              empty-label="No extra verified members are available."
            />

            <HorizonButton variant="primary" :disabled="walkInProcessing || !selectedWalkInUserId" @click="addWalkIn">
              {{ walkInProcessing ? 'Adding…' : 'Add Walk-In' }}
            </HorizonButton>
          </div>
        </section>

          <section class="hz-surface-welcome rounded-[1.5rem] border border-white/[0.055] p-5">
            <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Latest Sync</div>
            <div class="mt-4 space-y-3 text-sm text-text-secondary">
              <div>When: <span class="font-semibold text-horizon-white">{{ formatDateTime(lastSyncRun?.synced_at) }}</span></div>
              <div>Present: <span class="font-semibold text-horizon-white">{{ lastSyncRun?.present_user_ids?.length ?? 0 }}</span></div>
              <div>Not here: <span class="font-semibold text-horizon-white">{{ lastSyncRun?.no_show_user_ids?.length ?? 0 }}</span></div>
              <div>Walk in: <span class="font-semibold text-horizon-white">{{ lastSyncRun?.walk_in_user_ids?.length ?? 0 }}</span></div>
              <div>By: <span class="font-semibold text-horizon-white">{{ lastSyncRun?.synced_by?.name ?? 'Unknown' }}</span></div>
            </div>
          </section>

          <section class="hz-surface-welcome rounded-[1.5rem] border border-white/[0.055] p-5">
            <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Sync History</div>
            <div class="mt-4 space-y-3">
              <div
                v-for="syncRun in syncRuns"
                :key="syncRun.id"
                class="hz-surface-welcome rounded-[1.15rem] border border-white/[0.055] p-4"
              >
                <div class="flex items-center justify-between gap-3">
                  <div class="text-sm font-semibold text-horizon-white">{{ formatDateTime(syncRun.synced_at) }}</div>
                  <div class="text-xs text-text-secondary">{{ syncRun.synced_by?.name ?? 'Unknown' }}</div>
                </div>
                <div class="mt-3 flex flex-wrap gap-2 text-xs text-text-secondary">
                  <span>{{ syncRun.present_user_ids?.length ?? 0 }} present</span>
                  <span>{{ syncRun.no_show_user_ids?.length ?? 0 }} not here</span>
                  <span>{{ syncRun.walk_in_user_ids?.length ?? 0 }} walk-in</span>
                </div>
              </div>

              <div v-if="!syncRuns.length" class="rounded-[1.15rem] border border-dashed border-white/15 bg-white/[0.025] px-4 py-5 text-sm text-text-secondary">
                No sync history yet.
              </div>
            </div>
          </section>

          <section class="hz-surface-welcome rounded-[1.5rem] border border-white/[0.055] p-5">
            <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Roster Snapshot</div>
            <div class="mt-4 space-y-4">
              <div>
                <div class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Present</div>
                <div class="mt-3 space-y-2">
                  <div v-for="participant in presentParticipants" :key="`present-${participant.id}`" class="run-tool-status-card run-tool-status-card--success rounded-[1rem] px-3 py-3 text-sm text-horizon-white">
                    {{ participantName(participant) }}
                  </div>
                  <div v-if="!presentParticipants.length" class="rounded-[1rem] border border-dashed border-white/15 bg-white/[0.025] px-3 py-3 text-sm text-text-secondary">Nobody present yet.</div>
                </div>
              </div>

              <div>
                <div class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Waiting / Unsynced</div>
                <div class="mt-3 space-y-2">
                  <div v-for="participant in waitingParticipants" :key="`waiting-${participant.id}`" class="hz-surface-welcome rounded-[1rem] border border-white/[0.055] px-3 py-3 text-sm text-horizon-white">
                    {{ participantName(participant) }}
                  </div>
                  <div v-if="!waitingParticipants.length" class="rounded-[1rem] border border-dashed border-white/15 bg-white/[0.025] px-3 py-3 text-sm text-text-secondary">Nobody waiting right now.</div>
                </div>
              </div>

              <div>
                <div class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">No Show</div>
                <div class="mt-3 space-y-2">
                  <div v-for="participant in noShowParticipants" :key="`no-show-${participant.id}`" class="run-tool-status-card run-tool-status-card--danger rounded-[1rem] px-3 py-3 text-sm text-horizon-white">
                    {{ participantName(participant) }}
                  </div>
                  <div v-if="!noShowParticipants.length" class="rounded-[1rem] border border-dashed border-white/15 bg-white/[0.025] px-3 py-3 text-sm text-text-secondary">Nobody marked no-show.</div>
                </div>
              </div>

              <div>
                <div class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Excused</div>
                <div class="mt-3 space-y-2">
                  <div v-for="participant in excusedParticipants" :key="`excused-${participant.id}`" class="run-tool-status-card run-tool-status-card--warning rounded-[1rem] px-3 py-3 text-sm text-horizon-white">
                    {{ participantName(participant) }}
                  </div>
                  <div v-if="!excusedParticipants.length" class="rounded-[1rem] border border-dashed border-white/15 bg-white/[0.025] px-3 py-3 text-sm text-text-secondary">Nobody marked excused.</div>
                </div>
              </div>

              <div>
                <div class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Signed Off</div>
                <div class="mt-3 space-y-2">
                  <div v-for="participant in signedOffParticipants" :key="`signed-off-${participant.id}`" class="run-tool-status-card run-tool-status-card--danger rounded-[1rem] px-3 py-3 text-sm text-horizon-white">
                    {{ participantName(participant) }}
                  </div>
                  <div v-if="!signedOffParticipants.length" class="rounded-[1rem] border border-dashed border-white/15 bg-white/[0.025] px-3 py-3 text-sm text-text-secondary">Nobody signed off.</div>
                </div>
              </div>
            </div>
          </section>
      </div>
    </div>
  </HorizonContainer>

  <HorizonConfirmDialog
    ref="startConfirmDialog"
    title="Start Operation"
    confirm-label="Start Operation"
    cancel-label="Back"
    variant="success"
    :message="startConfirmMessage"
    :close-on-confirm="false"
    @confirm="confirmStartOperation"
  />

  <HorizonConfirmDialog
    ref="cancelConfirmDialog"
    title="Cancel Operation"
    confirm-label="Cancel Operation"
    cancel-label="Back"
    variant="danger"
    message="This action cannot be undone."
    :close-on-confirm="false"
    :requires-text-input="true"
    text-input-label="Cancellation reason:"
    text-input-placeholder="Enter reason..."
    @confirm="confirmCancelOperation"
  />

  <div
    v-if="completeDialogOpen"
    class="hz-overlay flex items-center justify-center p-4"
    @click.self="closeCompleteDialog"
  >
    <div class="w-full max-w-xl rounded-[2rem] border border-white/[0.08] bg-[color:var(--horizon-void-900)] p-6 shadow-[0_24px_90px_rgb(0_0_0/0.45)]">
      <div class="text-xs font-bold uppercase tracking-[0.22em] text-text-muted">
        Complete Operation
      </div>

      <div class="mt-3 text-2xl font-black text-horizon-white">
        How should "{{ operation.title }}" be closed?
      </div>

      <p class="mt-3 text-sm text-text-secondary">
        Pick the outcome for this operation. The after action report tools will become available once it is completed.
      </p>

      <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:justify-end">
        <HorizonButton
          variant="ghost"
          :disabled="transitionProcessing"
          @click="closeCompleteDialog"
        >
          Back
        </HorizonButton>

        <HorizonButton
          variant="danger"
          :disabled="transitionProcessing"
          @click="submitCompleteOperation('failed')"
        >
          {{ transitionProcessing ? 'Saving…' : 'Finish as Failed' }}
        </HorizonButton>

        <HorizonButton
          variant="primary"
          :disabled="transitionProcessing"
          @click="submitCompleteOperation('success')"
        >
          {{ transitionProcessing ? 'Saving…' : 'Finish as Success' }}
        </HorizonButton>
      </div>
    </div>
  </div>
</template>

<style scoped>
.run-tool-status-card {
  border: 1px solid var(--color-surface-border);
}

.run-tool-status-card--success {
  border-color: color-mix(in srgb, var(--color-state-success) 30%, transparent);
  background: color-mix(in srgb, var(--color-state-success-soft) 38%, var(--color-surface-soft));
}

.run-tool-status-card--danger {
  border-color: color-mix(in srgb, var(--color-state-danger) 32%, transparent);
  background: color-mix(in srgb, var(--color-state-danger-soft) 38%, var(--color-surface-soft));
}

.run-tool-status-card--warning {
  border-color: color-mix(in srgb, var(--color-state-warning) 30%, transparent);
  background: color-mix(in srgb, var(--color-state-warning-soft) 34%, var(--color-surface-soft));
}
</style>
