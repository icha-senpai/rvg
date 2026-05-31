<script setup>
import HorizonButton from '@/Components/HorizonButton.vue'
import HorizonConfirmDialog from '@/Components/HorizonConfirmDialog.vue'
import ProgressPill from '@/Components/ProgressPill.vue'
import HorizonSelect from '@/Components/HorizonSelect.vue'

import { ref, reactive, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { Ziggy } from '../../../ziggy'

const emit = defineEmits([
  'edit-operation',
  'start-operation',
  'complete-operation',
  'cancel-operation',
])

const props = defineProps({
  operation: Object,
  participants: Array,
  participantsBySlot: Object,
  unassignedParticipants: Array,
  currentParticipant: Object,
  canManageOperation: {
    type: Boolean,
    default: false,
  },
  transitionProcessing: {
    type: Boolean,
    default: false,
  },
})

const operation = props.operation ?? {}
const currentParticipant = computed(() => props.currentParticipant ?? null)

const participantsList = computed(() => Array.isArray(props.participants) ? props.participants : [])
const participantsBySlotSafe = computed(() => props.participantsBySlot ?? {})
const unassignedParticipantsSafe = computed(() => Array.isArray(props.unassignedParticipants) ? props.unassignedParticipants : [])
const canViewSlots = computed(() => !!operation?.permissions?.can_view_slots)
const canAssignSlots = computed(() => !!operation?.permissions?.can_assign_slots)
const canManageOperation = computed(() => !!props.canManageOperation)
const transitionProcessing = computed(() => !!props.transitionProcessing)
const participantCount = computed(() => Number(operation?.participants_count ?? participantsList.value.length ?? 0))
const hasSlotOptions = computed(() => Array.isArray(operation?.slots) && operation.slots.length > 0)
const operationRoles = computed(() => {
  if (Array.isArray(operation?.roles) && operation.roles.length) {
    return operation.roles
  }

  return Array.isArray(operation?.slots)
    ? operation.slots.map((slot) => ({
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

const calendarChoice = ref('')
const calendarOptions = [
  { label: 'Add to Calendar', value: '' },
  { label: 'Apple / Outlook / Proton (.ics)', value: 'ics' },
  { label: 'Google Calendar', value: 'google' },
  { label: 'Outlook Web', value: 'outlook' },
]

const canAddToCalendar = computed(() => {
  if (!operation?.starts_at) return false

  return !['completed', 'canceled', 'in_progress'].includes(operation?.status ?? '')
})
const canStartOperation = computed(() => canManageOperation.value && operation?.status === 'published')
const canCompleteOperation = computed(() => canManageOperation.value && operation?.status === 'in_progress')
const canCancelOperation = computed(() => {
  return canManageOperation.value && ['published', 'in_progress'].includes(operation?.status ?? '')
})

function operationKindLabel(kind) {
  switch (kind) {
    case 'operation': return 'Operation'
    case 'squadron_training': return 'Squadron Training'
    case 'wing_training': return 'Wing Training'
    case 'roleplay': return 'Roleplay'
    case 'meeting': return 'Meeting'
    case 'event': return 'Event'
    default: return 'Operation'
  }
}

function operationTitlePrefix(kind) {
  switch (kind) {
    case 'squadron_training': return 'Squadron Training'
    case 'wing_training': return 'Wing Training'
    default: return ''
  }
}

const displayTitle = computed(() => {
  const title = operation?.title ?? ''
  const prefix = operationTitlePrefix(operation?.operation_type ?? operation?.operation_kind)

  return prefix ? `${prefix}: ${title}` : title
})

function toCalendarUtcStamp(d) {
  if (!d) return null
  return d.toISOString().replace(/[-:]/g, '').replace(/\.(\d{3})Z$/i, 'Z')
}

const calendarBody = computed(() => {
  const parts = [
    operation?.description,
    operation?.extended_description ?? operation?.notes,
    operation?.id ? route('operations.show', operation.id, Ziggy) : null,
  ].filter(Boolean)

  return parts.join('\n\n')
})

const startDate = computed(() => toDate(operation?.starts_at))

const endDate = computed(() => {
  const end = toDate(operation?.ends_at)
  if (end) return end
  if (startDate.value) return new Date(startDate.value.getTime() + 60 * 60 * 1000)

  return null
})

const icsUrl = computed(() => {
  const id = operation?.id
  if (!id) return null

  try {
    return route('operations.calendar', id, Ziggy)
  } catch (e) {
    if (typeof window !== 'undefined' && window?.location?.origin) {
      return `${window.location.origin}/operations/${id}/calendar`
    }

    return `/operations/${id}/calendar`
  }
})

const googleCalendarUrl = computed(() => {
  if (!startDate.value || !endDate.value) return null

  const dates = `${toCalendarUtcStamp(startDate.value)}/${toCalendarUtcStamp(endDate.value)}`

  const params = new URLSearchParams({
    action: 'TEMPLATE',
    text: operation?.title ?? `Operation #${operation?.id ?? ''}`,
    details: calendarBody.value,
    dates,
  })

  return `https://calendar.google.com/calendar/render?${params.toString()}`
})

const outlookWebUrl = computed(() => {
  if (!startDate.value || !endDate.value) return null

  const params = new URLSearchParams({
    path: '/calendar/action/compose',
    rru: 'addevent',
    subject: operation?.title ?? `Operation #${operation?.id ?? ''}`,
    body: calendarBody.value,
    startdt: startDate.value.toISOString(),
    enddt: endDate.value.toISOString(),
  })

  return `https://outlook.office.com/calendar/0/deeplink/compose?${params.toString()}`
})

function openUrl(url) {
  if (!url) return

  const w = window.open(url, '_blank', 'noopener,noreferrer')
  if (w) w.focus()
}

function acquireExternalOpenLock(kind) {
  const id = operation?.id
  if (!id) return true

  const now = Date.now()
  const key = `operation-${id}-${kind}`
  const locks = (window.__externalOpenLocks ||= {})

  if (locks[key] && now - locks[key] < 1500) {
    return false
  }

  locks[key] = now
  return true
}

function acquireIcsDownloadLock() {
  const id = operation?.id
  if (!id) return true

  const now = Date.now()
  const key = `operation-${id}`
  const locks = (window.__icsDownloadLocks ||= {})

  if (locks[key] && now - locks[key] < 1500) {
    return false
  }

  locks[key] = now
  return true
}

let downloadingIcs = false

async function downloadIcs(url) {
  if (!url) return
  if (downloadingIcs) return
  if (!acquireIcsDownloadLock()) return

  downloadingIcs = true

  const a = document.createElement('a')
  a.href = url
  a.rel = 'noopener noreferrer'
  a.style.display = 'none'
  document.body.appendChild(a)
  a.click()
  a.remove()

  setTimeout(() => {
    downloadingIcs = false
  }, 1200)
}

watch(calendarChoice, (v) => {
  if (!v) return

  if (v === 'ics') downloadIcs(icsUrl.value)
  if (v === 'google' && acquireExternalOpenLock('google')) openUrl(googleCalendarUrl.value)
  if (v === 'outlook' && acquireExternalOpenLock('outlook')) openUrl(outlookWebUrl.value)

  calendarChoice.value = ''
})

function formatFirstLetter(value) {
  if (!value) return ''

  const text = String(value).trim()
  if (!text) return ''

  return text.charAt(0).toUpperCase() + text.slice(1)
}

function firstErrorMessage(errors, fallback) {
  const values = Object.values(errors ?? {})
  const first = values[0]

  if (Array.isArray(first)) {
    return first[0] ?? fallback
  }

  return first ?? fallback
}

function formatTitle(value) {
  const raw = String(value ?? '').trim()
  if (!raw) return 'Not set'

  return raw
    .replace(/[_-]+/g, ' ')
    .split(' ')
    .map(word => word ? word.charAt(0).toUpperCase() + word.slice(1) : '')
    .join(' ')
}

function creatorName() {
  return operation?.creator?.rsi_handle
    ?? operation?.creator?.display_name
    ?? operation?.creator?.name
    ?? 'TBD'
}

function creatorAvatar() {
  return operation?.creator?.discord_avatar
    ?? operation?.creator?.avatar
    ?? null
}

function creatorInitial() {
  return String(creatorName() ?? 'C').slice(0, 1).toUpperCase()
}

const creatorRankLabel = computed(() => {
  return operation?.creator?.rank_name
    ?? operation?.creator?.rank
    ?? 'Operation Lead'
})

function formatUTC(dt) {
  if (!dt) return 'TBD'

  const d = toDate(dt)
  if (!d) return String(dt)

  const date = new Intl.DateTimeFormat('en-GB', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
    timeZone: 'UTC',
  }).format(d)

  const time = new Intl.DateTimeFormat('en-GB', {
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
    timeZone: 'UTC',
  }).format(d)

  return `${date} ${time} UTC`
}

function formatLocal(dt) {
  if (!dt) return 'TBD'

  const d = toDate(dt)
  if (!d) return String(dt)

  const date = new Intl.DateTimeFormat('en-GB', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  }).format(d)

  const timeParts = new Intl.DateTimeFormat('en-GB', {
    hour: '2-digit',
    minute: '2-digit',
    hour12: true,
  }).formatToParts(d)

  const hour = timeParts.find(p => p.type === 'hour')?.value
  const minute = timeParts.find(p => p.type === 'minute')?.value
  const dayPeriod = (timeParts.find(p => p.type === 'dayPeriod')?.value ?? '').toLowerCase()

  const time = hour && minute && dayPeriod
    ? `${hour}:${minute} ${dayPeriod}`
    : new Intl.DateTimeFormat('en-GB', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: true,
      }).format(d)

  return `${date} ${time}`
}

function toDate(value) {
  if (!value) return null

  let v = String(value).trim()
  v = v.replace(' ', 'T')
  v = v.replace(/\.(\d{3})\d+Z$/i, '.$1Z')
  v = v.replace(/\.(\d{3})\d+$/i, '.$1')

  const hasTimezone = /([zZ]|[+-]\d{2}:\d{2})$/.test(v)
  if (!hasTimezone) v = `${v}Z`

  const d = new Date(v)
  return Number.isNaN(d.getTime()) ? null : d
}

const statusVariant = computed(() => {
  switch (operation.status) {
    case 'draft': return 'neutral'
    case 'published': return 'info'
    case 'in_progress': return 'primary'
    case 'completed': return 'success'
    case 'canceled': return 'danger'
    default: return 'neutral'
  }
})

const joinForm = reactive({
  roleId: '',
  slot: '',
  notes: '',
})
const managedSlotAssignments = reactive({})
const slotUpdateParticipantId = ref(null)

const participationLockedByStatus = computed(() => {
  return ['completed', 'canceled'].includes(operation?.status ?? '')
})

const rsvpDeadlineDate = computed(() => toDate(operation?.rsvp_deadline))
const operationStartDate = computed(() => toDate(operation?.starts_at))

const signUpCloseValue = computed(() => operation?.rsvp_deadline ?? operation?.starts_at ?? null)

const signUpCloseLeadMinutes = computed(() => {
  if (!operationStartDate.value || !signUpCloseValue.value) return null

  const closeDate = toDate(signUpCloseValue.value)
  if (!closeDate) return null

  const diffMs = operationStartDate.value.getTime() - closeDate.getTime()
  if (diffMs <= 0) return 0

  return Math.round(diffMs / 60000)
})

const signUpCloseSummary = computed(() => {
  if (!signUpCloseValue.value) return 'Not set'
  if (signUpCloseLeadMinutes.value === null) return formatLocal(signUpCloseValue.value)
  if (signUpCloseLeadMinutes.value === 0) return 'At operation start'

  const noun = signUpCloseLeadMinutes.value === 1 ? 'minute' : 'minutes'
  return `${signUpCloseLeadMinutes.value} ${noun} before start`
})

const signUpsClosedByDeadline = computed(() => {
  if (!rsvpDeadlineDate.value) return false

  return Date.now() >= rsvpDeadlineDate.value.getTime()
})

const signUpsClosedByStart = computed(() => {
  if (operation?.status === 'in_progress') return true
  if (!operationStartDate.value) return false

  return Date.now() >= operationStartDate.value.getTime()
})

const canJoinOperation = computed(() => {
  return !currentParticipant.value
    && !participationLockedByStatus.value
    && !signUpsClosedByStart.value
    && !signUpsClosedByDeadline.value
})

const canUpdateParticipation = computed(() => {
  return !!currentParticipant.value && !participationLockedByStatus.value
})

const currentParticipantSlotChanged = computed(() => {
  return String(joinForm.roleId ?? '') !== String(currentParticipant.value?.role?.id ?? '')
})

const participationStatusMessage = computed(() => {
  if (operation?.status === 'completed') {
    return 'This operation is completed. Participation is locked.'
  }

  if (operation?.status === 'canceled') {
    return 'This operation was canceled. Participation is locked.'
  }

  if (operation?.status === 'in_progress') {
    return currentParticipant.value
      ? 'This operation is underway. New sign-ups are closed.'
      : 'This operation is underway. Sign-ups are closed.'
  }

  if (signUpsClosedByStart.value) {
    return currentParticipant.value
      ? 'This operation has already started. New sign-ups are closed.'
      : 'This operation has already started. Sign-ups are closed.'
  }

  if (signUpsClosedByDeadline.value) {
    return currentParticipant.value
      ? 'Sign-ups are closed. You are already on the roster.'
      : 'The sign-up deadline has passed.'
  }

  return null
})

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
    ...operationRoles.value.map(role => ({
      label: roleCapacityLabel(role, selectedRoleId),
      value: String(role.id),
    })),
  ]
}

function findRoleById(roleId) {
  if (roleId === null || roleId === undefined || roleId === '') return null

  return operationRoles.value.find(role => String(role.id) === String(roleId)) ?? null
}

function roleIdForParticipant(participant) {
  return participant?.role?.id ? String(participant.role.id) : ''
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

watch(
  participantsList,
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

watch(
  currentParticipant,
  (p) => {
    if (p) {
      joinForm.roleId = roleIdForParticipant(p)
      joinForm.slot = p.slot ?? ''
      joinForm.notes = p.notes ?? ''
    } else {
      joinForm.roleId = ''
      joinForm.slot = ''
      joinForm.notes = ''
    }
  },
  { immediate: true }
)

const joinProcessing = ref(false)

async function join() {
  if (joinProcessing.value) return
  if (!canJoinOperation.value) return

  joinProcessing.value = true

  router.post(
    route('operations.join', operation.id, Ziggy),
    {
      ...payloadForSelectedRole(joinForm.roleId),
      notes: joinForm.notes,
    },
    {
      preserveScroll: true,
      preserveState: true,
      onError: (errors) => {
        window.hzNotifyError({
          message: firstErrorMessage(errors, 'Failed to join operation.'),
        })
      },
      onFinish: () => {
        joinProcessing.value = false
      },
    }
  )
}

async function updateOwnSlot() {
  if (!currentParticipant.value?.id) return
  if (!canUpdateParticipation.value) return
  if (slotUpdateParticipantId.value) return
  if (!currentParticipantSlotChanged.value) return

  slotUpdateParticipantId.value = currentParticipant.value.id

  router.post(
    route('operations.participants.slot', {
      operation: operation.id,
      participant: currentParticipant.value.id,
    }, Ziggy),
    {
      ...payloadForSelectedRole(joinForm.roleId),
    },
    {
      preserveScroll: true,
      preserveState: true,
      onError: (errors) => {
        window.hzNotifyError({
          message: firstErrorMessage(errors, 'Failed to update role request.'),
        })
      },
      onFinish: () => {
        slotUpdateParticipantId.value = null
      },
    }
  )
}

const leaveConfirmDialog = ref(null)

function askLeave() {
  if (joinProcessing.value) return
  if (!canUpdateParticipation.value) return
  leaveConfirmDialog.value?.show()
}

function confirmLeave({ close }) {
  joinProcessing.value = true

  router.post(
    route('operations.leave', operation.id, Ziggy),
    {},
    {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => {
        close()
      },
      onError: (errors) => {
        window.hzNotifyError({
          message: firstErrorMessage(errors, 'Failed to leave operation.'),
        })
      },
      onFinish: () => {
        joinProcessing.value = false
      },
    }
  )
}

function selectedSlotForParticipant(participant) {
  return managedSlotAssignments[participant.id] ?? roleIdForParticipant(participant)
}

function participantSlotChanged(participant) {
  return String(selectedSlotForParticipant(participant) ?? '') !== String(roleIdForParticipant(participant) ?? '')
}

async function updateParticipantSlot(participant) {
  if (!participant?.id) return
  if (!canAssignSlots.value) return
  if (slotUpdateParticipantId.value) return
  if (!participantSlotChanged(participant)) return

  slotUpdateParticipantId.value = participant.id

  router.post(
    route('operations.participants.slot', {
      operation: operation.id,
      participant: participant.id,
    }, Ziggy),
    {
      ...payloadForSelectedRole(selectedSlotForParticipant(participant)),
    },
    {
      preserveScroll: true,
      preserveState: true,
      onError: (errors) => {
        window.hzNotifyError({
          message: firstErrorMessage(errors, 'Failed to update role.'),
        })
      },
      onFinish: () => {
        slotUpdateParticipantId.value = null
      },
    }
  )
}

function userName(user) {
  return user?.rsi_handle ?? user?.display_name ?? user?.name ?? 'Unknown'
}

function participantName(participant) {
  return userName(participant?.user)
}

function participantAvatar(participant) {
  return participant?.user?.discord_avatar
    ?? participant?.user?.avatar
    ?? null
}

function participantInitial(participant) {
  return String(participantName(participant) ?? 'U').slice(0, 1).toUpperCase()
}

function participantRoleLabel(participant) {
  return participant?.role?.role_display_name ?? participant?.slot ?? null
}

function branchLogoSrc(branch) {
  switch (branch) {
    case 'defence': return '/images/Horizon_Defence_Logo.png'
    case 'frontiers': return '/images/Horizon_Frontiers_Logo.png'
    case 'industries': return '/images/Horizon_Industries_logo.png'
    case 'lifelines': return '/images/Horizon_Lifeline_logo.png'
    default: return null
  }
}

const selectedSquadrons = computed(() => {
  if (Array.isArray(operation?.selected_squadrons) && operation.selected_squadrons.length) {
    return operation.selected_squadrons
  }

  return typeof operation?.squadron_name === 'string' && operation.squadron_name.trim()
    ? operation.squadron_name.split(',').map((name) => ({ id: null, name: name.trim(), emblem_url: null, emblem: null })).filter((squadron) => squadron.name)
    : []
})

function squadronEmblemSrc(squadron) {
  return squadron?.emblem?.thumbnail_url
    ?? squadron?.emblem?.medium_url
    ?? squadron?.emblem?.url
    ?? squadron?.emblem_url
    ?? null
}

const operationImageSrc = computed(() => {
  return operation?.media_image?.medium_url
    ?? operation?.media_image?.url
    ?? null
})

const hasMetaInformation = computed(() => {
  return !!(
    operation.start_location
    || operation.operation_location
    || operation.branch
    || operation.squadron_name
    || operation.gameplay_type
    || operation.type
  )
})

function emitEditOperation() {
  if (!canManageOperation.value || transitionProcessing.value) return

  emit('edit-operation', operation)
}

function emitStartOperation() {
  if (!canStartOperation.value || transitionProcessing.value) return

  emit('start-operation', operation)
}

function emitCompleteOperation(outcome = 'success') {
  if (!canCompleteOperation.value || transitionProcessing.value) return

  emit('complete-operation', {
    operation,
    outcome,
  })
}

function emitCancelOperation() {
  if (!canCancelOperation.value || transitionProcessing.value) return

  emit('cancel-operation', operation)
}
</script>

<template>
  <div class="space-y-6 p-5 md:p-6">
    <section class="hz-surface-welcome relative rounded-[2rem] border border-white/[0.055]">
      <div class="pointer-events-none absolute inset-0 opacity-20">
        <div class="absolute inset-x-8 top-0 h-px bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-blue)] to-transparent"></div>
        <div class="absolute inset-x-10 bottom-0 h-px bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-magenta)] to-transparent"></div>
      </div>

      <div class="relative p-5 md:p-6">
        <div class="grid gap-8 xl:grid-cols-[16rem_minmax(0,1fr)]">
        <aside class="space-y-5 xl:border-r xl:border-white/[0.055] xl:pr-8">
          <section>
            <div class="text-xs font-bold uppercase tracking-[0.2em] text-text-muted">
              Operation Leader
            </div>

            <div class="mt-4 flex flex-col items-center text-center">
                <img
                  v-if="creatorAvatar()"
                  :src="creatorAvatar()"
                  :alt="creatorName()"
                  class="h-28 w-28 rounded-full border border-white/10 object-cover shadow-[0_16px_34px_rgb(0_0_0/0.28)]"
                  loading="lazy"
                />
                <div
                  v-else
                  class="flex h-28 w-28 items-center justify-center rounded-full border border-white/10 bg-white/[0.05] text-3xl font-black text-horizon-white shadow-[0_16px_34px_rgb(0_0_0/0.28)]"
                >
                  {{ creatorInitial() }}
                </div>

              <div class="mt-4 text-sm font-black uppercase tracking-[0.08em] text-horizon-white">
                {{ creatorName() }}
              </div>

              <div class="mt-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-[color:var(--horizon-sunset-orange)]">
                {{ creatorRankLabel }}
              </div>
            </div>
          </section>

          <section v-if="canManageOperation || canAddToCalendar" class="space-y-3 border-t border-white/[0.055] pt-5">
            <div v-if="canManageOperation" class="space-y-2">
              <div class="text-xs font-bold uppercase tracking-[0.2em] text-text-muted">
                Officer Tools
              </div>

              <HorizonButton
                variant="ghost"
                class="w-full"
                :disabled="transitionProcessing"
                @click="emitEditOperation"
              >
                Edit Operation
              </HorizonButton>

              <HorizonButton
                v-if="canStartOperation"
                variant="primary"
                class="w-full"
                :disabled="transitionProcessing"
                @click="emitStartOperation"
              >
                {{ transitionProcessing ? 'Running…' : 'Run Operation' }}
              </HorizonButton>

              <HorizonButton
                v-if="canCompleteOperation"
                variant="primary"
                class="w-full"
                :disabled="transitionProcessing"
                @click="emitCompleteOperation('success')"
              >
                {{ transitionProcessing ? 'Saving…' : 'Finish Success' }}
              </HorizonButton>

              <HorizonButton
                v-if="canCompleteOperation"
                variant="danger"
                class="w-full"
                :disabled="transitionProcessing"
                @click="emitCompleteOperation('failed')"
              >
                {{ transitionProcessing ? 'Saving…' : 'Finish Failed' }}
              </HorizonButton>

              <HorizonButton
                v-if="canCancelOperation"
                variant="danger"
                class="w-full"
                :disabled="transitionProcessing"
                @click="emitCancelOperation"
              >
                {{ transitionProcessing ? 'Canceling…' : 'Cancel Operation' }}
              </HorizonButton>
            </div>

            <div v-if="canAddToCalendar" class="space-y-2">
              <div class="text-xs font-bold uppercase tracking-[0.2em] text-text-muted">
                Calendar
              </div>
              <HorizonSelect v-model="calendarChoice" :options="calendarOptions" size="sm" />
            </div>

            <div class="space-y-3 border-t border-white/[0.055] pt-4">
              <div class="text-xs font-bold uppercase tracking-[0.2em] text-text-muted">
                Your Status
              </div>

              <p
                v-if="participationStatusMessage"
                class="rounded-2xl border border-white/[0.055] bg-white/[0.03] px-3 py-2 text-xs text-text-secondary"
              >
                {{ participationStatusMessage }}
              </p>

              <template v-if="currentParticipant">
                <div class="text-2xl font-black text-horizon-white">
                  Joined
                </div>

                <p class="text-sm text-text-secondary">
                  You are signed up with status
                  <strong class="text-horizon-white">{{ formatTitle(currentParticipant.attendance_status) }}</strong>.
                  <template v-if="participantRoleLabel(currentParticipant)">
                    Your current slot is
                    <strong class="text-horizon-white">{{ participantRoleLabel(currentParticipant) }}</strong>.
                  </template>
                </p>

                <div v-if="hasSlotOptions && canUpdateParticipation && !canAssignSlots" class="space-y-3">
                  <div class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Your Role Request</div>
                  <HorizonSelect v-model="joinForm.roleId" :options="roleOptionsFor(currentParticipant?.role?.id)" size="sm" />
                  <HorizonButton
                    variant="outline"
                    class="w-full"
                    :disabled="slotUpdateParticipantId === currentParticipant.id || !currentParticipantSlotChanged"
                    @click="updateOwnSlot"
                  >
                    {{ slotUpdateParticipantId === currentParticipant.id ? 'Updating…' : 'Update Role Request' }}
                  </HorizonButton>
                </div>

                <div v-if="canUpdateParticipation">
                  <HorizonButton variant="outline" class="w-full" :disabled="joinProcessing" @click="askLeave">
                    Leave Operation
                  </HorizonButton>
                </div>

                <p v-if="canAssignSlots" class="text-xs text-text-secondary">
                  Slot assignments can be changed in the roster section.
                </p>
              </template>

              <template v-else>
                <div class="text-2xl font-black text-horizon-white">
                  Not Joined
                </div>

                <p class="text-sm text-text-secondary">
                  Join this {{ operationKindLabel(operation.operation_type ?? operation.operation_kind).toLowerCase() }} when you are ready to deploy.
                </p>

                <div v-if="canJoinOperation" class="space-y-3">
                  <div v-if="hasSlotOptions" class="space-y-2">
                    <div class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Requested Role</div>
                    <HorizonSelect v-model="joinForm.roleId" :options="roleOptionsFor()" size="sm" />
                  </div>

                  <HorizonButton variant="primary" class="w-full" :disabled="joinProcessing" @click="join">
                    {{ joinProcessing ? 'Joining…' : 'Join Operation' }}
                  </HorizonButton>
                </div>
              </template>
            </div>
          </section>
        </aside>

        <main class="min-w-0 divide-y divide-white/[0.055]">
          <div class="min-w-0 pb-6">
            <div class="flex flex-wrap items-center gap-2">
              <span class="text-xs font-bold uppercase tracking-[0.2em] text-[color:var(--horizon-text-secondary)]">
                {{ operationKindLabel(operation.operation_type ?? operation.operation_kind) }}
              </span>
              <span class="text-xs text-text-muted">•</span>
              <ProgressPill :variant="statusVariant" size="sm">
                {{ formatTitle(operation.status) }}
              </ProgressPill>
              <span class="rounded-full border border-white/[0.055] bg-white/[0.03] px-3 py-1 text-[11px] font-bold uppercase tracking-[0.18em] text-text-secondary">
                {{ formatTitle(operation.visibility ?? 'open') }}
              </span>
              <span class="rounded-full border border-white/[0.055] bg-white/[0.03] px-3 py-1 text-[11px] font-bold uppercase tracking-[0.18em] text-text-secondary">
                {{ participantCount }} joined
              </span>
            </div>

            <h1 class="mt-3 text-2xl font-black tracking-tight text-horizon-white md:text-3xl">
              {{ displayTitle }}
            </h1>

            <div class="mt-4 flex flex-wrap gap-x-5 gap-y-2 text-sm text-text-secondary">
              <span v-if="operation.branch" class="inline-flex items-center gap-2">
                <img
                  v-if="branchLogoSrc(operation.branch)"
                  :src="branchLogoSrc(operation.branch)"
                  :alt="formatTitle(operation.branch)"
                  class="h-5 w-5 object-contain"
                  loading="lazy"
                />
                <span>{{ formatTitle(operation.branch) }}</span>
              </span>

              <template v-if="selectedSquadrons.length">
                <span
                  v-for="squadron in selectedSquadrons"
                  :key="`hero-squadron-${squadron.id ?? squadron.name}`"
                  class="inline-flex items-center gap-2"
                >
                  <img
                    v-if="squadronEmblemSrc(squadron)"
                    :src="squadronEmblemSrc(squadron)"
                    :alt="`${squadron.name} emblem`"
                    class="h-5 w-5 rounded-full object-cover"
                    loading="lazy"
                  />
                  <span>{{ squadron.name }}</span>
                </span>
              </template>

              <span v-if="operation.gameplay_type || operation.type">
                {{ formatTitle(operation.gameplay_type ?? operation.type) }}
              </span>
            </div>
          </div>

          <section
            v-if="operationImageSrc"
            class="relative z-0 overflow-hidden pt-6"
          >
            <img
              :src="operationImageSrc"
              :alt="operation.media_image?.alt_text || operation.title"
              class="max-h-[520px] w-full object-contain"
              loading="lazy"
            />
          </section>

          <section class="grid gap-6 pt-6 md:grid-cols-2 xl:grid-cols-3">
            <div>
              <div class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">When</div>
              <div class="mt-2 text-sm font-semibold text-horizon-white">{{ formatLocal(operation.starts_at) }}</div>
              <div class="mt-1 text-xs text-text-secondary">{{ formatUTC(operation.starts_at) }}</div>
            </div>

            <div>
              <div class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Where</div>
              <div class="mt-2 text-sm font-semibold text-horizon-white">
                {{ operation.operation_location || operation.start_location || 'TBD' }}
              </div>
              <div
                v-if="operation.operation_location && operation.start_location && operation.operation_location !== operation.start_location"
                class="mt-1 text-xs text-text-secondary"
              >
                Start point: {{ operation.start_location }}
              </div>
            </div>

            <div>
              <div class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Strictness</div>
              <div class="mt-2 text-sm font-semibold text-horizon-white">{{ formatTitle(operation.operation_strictness ?? 'normal') }}</div>
              <div class="mt-1 text-xs text-text-secondary">Voice and deployment expectations</div>
            </div>

            <div>
              <div class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Sign-ups Close</div>
              <div class="mt-2 text-sm font-semibold" :class="signUpCloseValue ? 'text-horizon-white' : 'text-text-secondary'">
                {{ signUpCloseSummary }}
              </div>
              <div v-if="signUpCloseValue" class="mt-1 text-xs text-text-secondary">
                {{ formatLocal(signUpCloseValue) }}
              </div>
              <div v-if="signUpCloseValue" class="mt-1 text-xs text-text-secondary">
                {{ formatUTC(signUpCloseValue) }}
              </div>
            </div>

            <div v-if="hasMetaInformation">
              <div class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Overview</div>
              <div class="mt-2 space-y-1 text-sm text-text-secondary">
                <div v-if="operation.branch" class="flex items-center gap-2">
                  <img
                    v-if="branchLogoSrc(operation.branch)"
                    :src="branchLogoSrc(operation.branch)"
                    :alt="formatTitle(operation.branch)"
                    class="h-4 w-4 object-contain"
                    loading="lazy"
                  />
                  <span class="font-semibold text-horizon-white">{{ formatTitle(operation.branch) }}</span>
                  </div>
                <div v-if="selectedSquadrons.length" class="space-y-1">
                  <div
                    v-for="squadron in selectedSquadrons"
                    :key="`overview-squadron-${squadron.id ?? squadron.name}`"
                    class="flex items-center gap-2"
                  >
                    <img
                      v-if="squadronEmblemSrc(squadron)"
                      :src="squadronEmblemSrc(squadron)"
                      :alt="`${squadron.name} emblem`"
                      class="h-4 w-4 rounded-full object-cover"
                      loading="lazy"
                    />
                    <span>{{ squadron.name }}</span>
                  </div>
                </div>
                <div v-if="operation.gameplay_type || operation.type">{{ formatTitle(operation.gameplay_type ?? operation.type) }}</div>
                <div v-if="operation.ends_at">Ends {{ formatLocal(operation.ends_at) }}</div>
              </div>
            </div>
          </section>

          <section class="pt-6">
            <div class="text-xs font-bold uppercase tracking-[0.2em] text-text-muted">
              Description
            </div>

            <div class="mt-4 space-y-4">
              <p v-if="operation.description" class="whitespace-pre-line text-sm leading-7 text-text-secondary">
                {{ operation.description }}
              </p>

              <p
                v-if="operation.extended_description || operation.notes"
                class="whitespace-pre-line border-t border-white/[0.055] pt-4 text-sm leading-7 text-text-secondary"
              >
                {{ operation.extended_description ?? operation.notes }}
              </p>

              <div class="border-t border-white/[0.055] pt-4 space-y-4">
                <p class="whitespace-pre-line text-sm leading-7 text-text-secondary">
                  If you want to join, please use the signup button. Make sure your character is at the designated location and you are in the voice channel at least 10 minutes before the operation starts.
                </p>

                <p class="whitespace-pre-line text-sm font-semibold leading-7 text-horizon-white">
                  For optimal deployment, please have OP Leader added to your contact list beforehand.
                </p>
              </div>
            </div>
          </section>

          <section v-if="canViewSlots" class="pt-6">
            <div class="flex items-center justify-between gap-3">
              <div class="text-xs font-bold uppercase tracking-[0.2em] text-text-muted">
                Players
              </div>
              <div class="text-sm font-semibold text-horizon-white">
                {{ participantCount }} joined
              </div>
            </div>

              <div class="mt-4 max-h-72 space-y-1 overflow-y-auto pr-1">
                <div
                  v-for="p in participantsList"
                  :key="p.id"
                  class="flex items-center justify-between gap-3 border-t border-white/[0.055] px-1 py-3 first:border-t-0"
                >
                  <div class="flex min-w-0 items-center gap-3">
                    <img
                    v-if="participantAvatar(p)"
                    :src="participantAvatar(p)"
                    :alt="participantName(p)"
                    class="h-8 w-8 shrink-0 rounded-full object-cover"
                    loading="lazy"
                  />
                  <div
                    v-else
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-white/[0.05] text-xs font-bold text-horizon-white"
                  >
                    {{ participantInitial(p) }}
                  </div>
                  <div class="min-w-0">
                    <div class="truncate text-sm font-semibold text-horizon-white">
                      {{ participantName(p) }}
                    </div>
                  </div>
                </div>

                <span class="shrink-0 text-xs text-text-muted">
                  {{ participantRoleLabel(p) ?? '—' }}
                </span>
              </div>

                <div v-if="!participantsList.length" class="rounded-[1rem] border border-dashed border-white/[0.08] px-4 py-5 text-sm text-text-secondary">
                  No participants yet.
                </div>
              </div>
            </section>

          <section v-if="canViewSlots" class="space-y-4 pt-6">
            <div class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">
              Roster Assignments
            </div>

            <div v-if="(operation.slots || []).length" class="space-y-4">
              <div v-for="slotName in operation.slots" :key="slotName" class="border-t border-white/[0.055] pt-4 first:border-t-0 first:pt-0">
                <div class="flex items-center justify-between text-sm">
                  <span class="font-semibold text-horizon-white">{{ slotName }}</span>
                  <span class="text-xs text-text-muted">{{ (participantsBySlotSafe[slotName] || []).length }}</span>
                </div>

                <div class="mt-3 space-y-2">
                  <div
                    v-for="p in participantsBySlotSafe[slotName] || []"
                    :key="p.id"
                    class="flex flex-wrap items-center justify-between gap-3 border-t border-white/[0.04] px-1 py-3 first:border-t-0"
                  >
                    <div class="flex min-w-0 items-center gap-2">
                      <img v-if="participantAvatar(p)" :src="participantAvatar(p)" :alt="participantName(p)" class="h-6 w-6 shrink-0 rounded-full object-cover" loading="lazy" />
                      <div v-else class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-white/[0.05] text-[10px] font-bold text-horizon-white">{{ participantInitial(p) }}</div>
                      <span class="truncate text-sm text-horizon-white">{{ participantName(p) }}</span>
                    </div>

                    <div class="flex flex-wrap items-center justify-end gap-2">
                      <span class="shrink-0 text-xs text-text-muted">{{ formatTitle(p.attendance_status) }}</span>

                      <template v-if="canAssignSlots">
                        <div class="min-w-[12rem]">
                          <HorizonSelect v-model="managedSlotAssignments[p.id]" :options="roleOptionsFor(roleIdForParticipant(p))" size="sm" />
                        </div>
                        <HorizonButton
                          variant="outline"
                          size="sm"
                          :disabled="slotUpdateParticipantId === p.id || !participantSlotChanged(p)"
                          @click="updateParticipantSlot(p)"
                        >
                          {{ slotUpdateParticipantId === p.id ? 'Moving…' : 'Move' }}
                        </HorizonButton>
                      </template>
                    </div>
                  </div>

                  <div v-if="!(participantsBySlotSafe[slotName] || []).length" class="border-t border-dashed border-white/[0.08] px-1 py-4 text-sm italic text-text-muted">
                    Empty
                  </div>
                </div>
              </div>
            </div>

            <div v-else class="rounded-[1.5rem] border border-dashed border-white/[0.08] bg-white/[0.015] px-4 py-5 text-sm text-text-secondary">
              No slots defined.
            </div>

            <div v-if="unassignedParticipantsSafe.length" class="border-t border-white/[0.055] pt-4">
              <div class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">No Role</div>

              <div class="mt-3 space-y-2">
                <div
                  v-for="p in unassignedParticipantsSafe"
                  :key="p.id"
                  class="flex flex-wrap items-center justify-between gap-3 border-t border-white/[0.04] px-1 py-3 first:border-t-0"
                >
                  <div class="flex min-w-0 items-center gap-2">
                    <img v-if="participantAvatar(p)" :src="participantAvatar(p)" :alt="participantName(p)" class="h-6 w-6 shrink-0 rounded-full object-cover" loading="lazy" />
                    <div v-else class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-white/[0.05] text-[10px] font-bold text-horizon-white">{{ participantInitial(p) }}</div>
                    <span class="truncate text-sm text-horizon-white">{{ participantName(p) }}</span>
                  </div>

                  <div class="flex flex-wrap items-center justify-end gap-2">
                    <span class="shrink-0 text-xs text-text-muted">{{ formatTitle(p.attendance_status) }}</span>

                    <template v-if="canAssignSlots">
                      <div class="min-w-[12rem]">
                        <HorizonSelect v-model="managedSlotAssignments[p.id]" :options="roleOptionsFor(roleIdForParticipant(p))" size="sm" />
                      </div>
                      <HorizonButton
                        variant="outline"
                        size="sm"
                        :disabled="slotUpdateParticipantId === p.id || !participantSlotChanged(p)"
                        @click="updateParticipantSlot(p)"
                      >
                        {{ slotUpdateParticipantId === p.id ? 'Moving…' : 'Move' }}
                      </HorizonButton>
                    </template>
                  </div>
                </div>
              </div>
            </div>
          </section>
        </main>
        </div>
      </div>
    </section>

    <HorizonConfirmDialog
      ref="leaveConfirmDialog"
      title="Leave Operation"
      confirm-label="Leave"
      cancel-label="Cancel"
      variant="warning"
      message="Leave this operation?"
      @confirm="confirmLeave"
    />
  </div>
</template>
