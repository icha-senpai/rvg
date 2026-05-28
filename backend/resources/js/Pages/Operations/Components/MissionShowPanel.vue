<script setup>
import HorizonButton from '@/Components/HorizonButton.vue'
import HorizonConfirmDialog from '@/Components/HorizonConfirmDialog.vue'
import ProgressPill from '@/Components/ProgressPill.vue'
import HorizonSelect from '@/Components/HorizonSelect.vue'

import { ref, reactive, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { Ziggy } from '../../../ziggy'

const props = defineProps({
  operation: Object,
  participants: Array,
  participantsBySlot: Object,
  unassignedParticipants: Array,
  currentParticipant: Object,
})

const operation = props.operation ?? {}
const currentParticipant = computed(() => props.currentParticipant ?? null)

const participantsList = computed(() => Array.isArray(props.participants) ? props.participants : [])
const participantsBySlotSafe = computed(() => props.participantsBySlot ?? {})
const unassignedParticipantsSafe = computed(() => Array.isArray(props.unassignedParticipants) ? props.unassignedParticipants : [])

const calendarChoice = ref('')
const calendarOptions = [
  { label: 'Add to Calendar', value: '' },
  { label: 'Apple / Outlook / Proton (.ics)', value: 'ics' },
  { label: 'Google Calendar', value: 'google' },
  { label: 'Outlook Web', value: 'outlook' },
]

const canAddToCalendar = computed(() => !!operation?.starts_at)

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

function formatTitle(value) {
  const raw = String(value ?? '').trim()
  if (!raw) return 'Not set'

  return raw
    .replace(/[_-]+/g, ' ')
    .split(' ')
    .map(word => word ? word.charAt(0).toUpperCase() + word.slice(1) : '')
    .join(' ')
}

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
  slot: '',
  notes: '',
})

const slotOptions = computed(() => {
  const slots = (operation.slots || []).map(slot => ({ label: slot, value: slot }))
  return [{ label: 'No Role', value: '' }, ...slots]
})

watch(
  currentParticipant,
  (p) => {
    if (p) {
      joinForm.slot = p.slot ?? ''
      joinForm.notes = p.notes ?? ''
    } else {
      joinForm.slot = ''
      joinForm.notes = ''
    }
  },
  { immediate: true }
)

const joinProcessing = ref(false)

async function join() {
  if (joinProcessing.value) return

  joinProcessing.value = true

  router.post(
    route('operations.join', operation.id, Ziggy),
    {
      slot: joinForm.slot,
      notes: joinForm.notes,
      operation_role_id: null,
    },
    {
      preserveScroll: true,
      preserveState: true,
      onError: () => {
        window.hzNotifyError({ message: 'Failed to join operation.' })
      },
      onFinish: () => {
        joinProcessing.value = false
      },
    }
  )
}

const leaveConfirmDialog = ref(null)

function askLeave() {
  if (joinProcessing.value) return
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
      onError: () => {
        window.hzNotifyError({ message: 'Failed to leave operation.' })
      },
      onFinish: () => {
        joinProcessing.value = false
      },
    }
  )
}

async function updateSlot() {
  if (!currentParticipant.value) return
  if (joinProcessing.value) return

  joinProcessing.value = true

  router.post(
    route('operations.participants.slot', {
      operation: operation.id,
      participant: currentParticipant.value.id,
    }, Ziggy),
    {
      slot: joinForm.slot,
      operation_role_id: null,
    },
    {
      preserveScroll: true,
      preserveState: true,
      onError: () => {
        window.hzNotifyError({ message: 'Failed to update role.' })
      },
      onFinish: () => {
        joinProcessing.value = false
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

function branchLogoSrc(branch) {
  switch (branch) {
    case 'defence': return '/images/Horizon_Defence_Logo.png'
    case 'frontiers': return '/images/Horizon_Frontiers_Logo.png'
    case 'industries': return '/images/Horizon_Industries_logo.png'
    case 'lifelines': return '/images/Horizon_Lifeline_logo.png'
    default: return null
  }
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
</script>

<template>
  <div class="space-y-8 p-5 md:p-6">
    <!-- Mission top bar -->
    <section class="relative overflow-visible rounded-[2rem] border border-white/[0.055] bg-[rgba(21,25,42,0.46)] p-5 ">
      <div class="pointer-events-none absolute inset-0 opacity-40">
        <div class="absolute left-8 top-0 h-px w-48 bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-blue)] to-transparent"></div>
        <div class="absolute bottom-0 right-10 h-px w-64 bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-magenta)] to-transparent"></div>
      </div>

      <div class="relative flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div class="min-w-0">
          <div class="text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--horizon-text-secondary)]">
            {{ operationKindLabel(operation.operation_type ?? operation.operation_kind) }}
          </div>

          <h1 class="mt-2 text-2xl font-black tracking-tight text-horizon-white md:text-4xl">
            {{ displayTitle }}
          </h1>

          <p class="mt-2 max-w-3xl text-sm text-text-secondary">
            Mission briefing, roster assignments, schedule, and deployment status.
          </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
          <ProgressPill :variant="statusVariant">
            {{ formatTitle(operation.status) }}
          </ProgressPill>

          <div v-if="canAddToCalendar" class="w-60">
            <HorizonSelect
              v-model="calendarChoice"
              :options="calendarOptions"
            />
          </div>
        </div>
      </div>
    </section>

    <!-- Operation image -->
    <section
      v-if="operationImageSrc"
       class="relative z-0 overflow-hidden rounded-[2rem] border border-white/[0.055] bg-[color:var(--horizon-void-800)] "
>
    
      <img
        :src="operationImageSrc"
        :alt="operation.media_image?.alt_text || operation.title"
        class="max-h-[520px] w-full object-contain"
        loading="lazy"
      />
    </section>

    <!-- Main layout -->
    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
      <main class="space-y-6">
        <!-- Time command strip -->
        <section class="grid gap-4 md:grid-cols-2">
          <div class="rounded-[1.5rem] border border-white/[0.055] bg-[linear-gradient(135deg,rgba(30,64,175,0.14),rgba(255,255,255,0.025))] p-5 ">
            <div class="text-xs font-bold uppercase tracking-[0.2em] text-text-muted">
              Start Window
            </div>

            <div class="mt-2 text-sm font-semibold text-horizon-white">
              {{ formatUTC(operation.starts_at) }}
            </div>

            <div class="mt-1 text-xs text-text-secondary">
              {{ formatLocal(operation.starts_at) }} local
            </div>

            <div v-if="operation.ends_at" class="mt-4 border-t border-white/10 pt-4">
              <div class="text-xs font-bold uppercase tracking-[0.2em] text-text-muted">
                End Window
              </div>

              <div class="mt-2 text-sm font-semibold text-horizon-white">
                {{ formatUTC(operation.ends_at) }}
              </div>

              <div class="mt-1 text-xs text-text-secondary">
                {{ formatLocal(operation.ends_at) }} local
              </div>
            </div>
          </div>

          <div class="rounded-[1.5rem] border border-white/[0.055] bg-[rgba(21,25,42,0.42)] p-5">
            <div class="text-xs font-bold uppercase tracking-[0.2em] text-text-muted">
              Sign Up Deadline
            </div>

            <template v-if="operation.rsvp_deadline">
              <div class="mt-2 text-sm font-semibold text-horizon-white">
                {{ formatUTC(operation.rsvp_deadline) }}
              </div>

              <div class="mt-1 text-xs text-text-secondary">
                {{ formatLocal(operation.rsvp_deadline) }} local
              </div>
            </template>

            <div v-else class="mt-2 text-sm text-text-secondary">
              No sign up deadline set.
            </div>
          </div>
        </section>

        <!-- Briefing -->
        <section
          v-if="operation.description"
          class="rounded-[2rem] border border-white/[0.055] bg-[color:var(--horizon-void-700)]/70 p-6 "
        >
          <div class="text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--horizon-text-secondary)]">
            Operation Briefing
          </div>

          <p class="mt-3 whitespace-pre-line text-sm leading-7 text-text-secondary">
            {{ operation.description }}
          </p>
        </section>

        <!-- Extended briefing -->
        <section
          v-if="operation.extended_description || operation.notes"
          class="rounded-[2rem] border border-white/[0.055] bg-white/[0.024] p-6"
        >
          <div class="text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--horizon-text-secondary)]">
            Extended Briefing
          </div>

          <p class="mt-3 whitespace-pre-line text-sm leading-7 text-text-secondary">
            {{ operation.extended_description ?? operation.notes }}
          </p>
        </section>

        <!-- Meta information -->
        <section
          v-if="hasMetaInformation"
          class="rounded-[2rem] border border-white/[0.055] bg-white/[0.024] p-6"
        >
          <div class="text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--horizon-text-secondary)]">
            Mission Metadata
          </div>

          <div class="mt-5 grid gap-4 md:grid-cols-2">
            <div v-if="operation.start_location" class="rounded-2xl border border-white/[0.055] bg-white/[0.024] p-4">
              <div class="text-xs uppercase tracking-wide text-text-muted">
                Start Location
              </div>
              <div class="mt-1 font-semibold text-horizon-white">
                {{ operation.start_location }}
              </div>
            </div>

            <div v-if="operation.operation_location" class="rounded-2xl border border-white/[0.055] bg-white/[0.024] p-4">
              <div class="text-xs uppercase tracking-wide text-text-muted">
                Operation Location
              </div>
              <div class="mt-1 font-semibold text-horizon-white">
                {{ operation.operation_location }}
              </div>
            </div>

            <div v-if="operation.branch" class="rounded-2xl border border-white/[0.055] bg-white/[0.024] p-4">
              <div class="text-xs uppercase tracking-wide text-text-muted">
                Branch
              </div>
              <div class="mt-2 flex items-center gap-2 font-semibold text-horizon-white">
                <img
                  v-if="branchLogoSrc(operation.branch)"
                  :src="branchLogoSrc(operation.branch)"
                  :alt="`${formatTitle(operation.branch)} branch logo`"
                  class="h-5 w-5 object-contain"
                  loading="lazy"
                />
                {{ formatTitle(operation.branch) }}
              </div>
            </div>

            <div v-if="operation.squadron_name" class="rounded-2xl border border-white/[0.055] bg-white/[0.024] p-4">
              <div class="text-xs uppercase tracking-wide text-text-muted">
                Squadron
              </div>
              <div class="mt-1 font-semibold text-horizon-white">
                {{ operation.squadron_name }}
              </div>
            </div>

            <div v-if="operation.gameplay_type || operation.type" class="rounded-2xl border border-white/[0.055] bg-white/[0.024] p-4">
              <div class="text-xs uppercase tracking-wide text-text-muted">
                Gameplay Type
              </div>
              <div class="mt-1 font-semibold text-horizon-white">
                {{ formatTitle(operation.gameplay_type ?? operation.type) }}
              </div>
            </div>

            <div class="rounded-2xl border border-white/[0.055] bg-white/[0.024] p-4">
              <div class="text-xs uppercase tracking-wide text-text-muted">
                Comms Strictness
              </div>
              <div class="mt-1 font-semibold text-horizon-white">
                {{ formatTitle(operation.operation_strictness ?? 'normal') }}
              </div>
            </div>
          </div>
        </section>

        <!-- Roles -->
        <section class="rounded-[2rem] border border-white/[0.055] bg-[color:var(--horizon-void-700)]/80 p-6 ">
          <div class="mb-5 flex items-center justify-between gap-4">
            <div>
              <div class="text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--horizon-text-secondary)]">
                Role Assignments
              </div>
              <h2 class="mt-1 text-xl font-black text-horizon-white">
                Deployment Slots
              </h2>
            </div>

            <div class="rounded-full border border-white/[0.055] bg-white/[0.042] px-3 py-1 text-xs font-bold text-[color:var(--horizon-text-primary)]">
              {{ participantsList.length }} total
            </div>
          </div>

          <div v-if="(operation.slots || []).length" class="grid gap-4 md:grid-cols-2">
            <article
              v-for="slotName in operation.slots"
              :key="slotName"
              class="rounded-2xl border border-white/[0.055] bg-white/[0.024] p-4"
            >
              <div class="flex items-center justify-between gap-3">
                <div class="font-bold text-horizon-white">
                  {{ slotName }}
                </div>

                <div class="rounded-full border border-white/[0.055] bg-white/[0.024] px-2.5 py-1 text-xs font-semibold text-text-secondary">
                  {{ (participantsBySlotSafe[slotName] || []).length }}
                </div>
              </div>

              <div class="mt-4 space-y-2">
                <div
                  v-for="p in participantsBySlotSafe[slotName] || []"
                  :key="p.id"
                  class="flex items-center justify-between gap-3 rounded-xl border border-white/10 bg-black/10 px-3 py-2"
                >
                  <div class="flex min-w-0 items-center gap-2">
                    <img
                      v-if="participantAvatar(p)"
                      :src="participantAvatar(p)"
                      alt=""
                      class="h-7 w-7 shrink-0 rounded-lg object-cover"
                      loading="lazy"
                    />

                    <div
                      v-else
                      class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-white/[0.06] text-xs font-black text-horizon-white"
                    >
                      {{ participantInitial(p) }}
                    </div>

                    <span class="truncate text-sm font-semibold text-horizon-white">
                      {{ participantName(p) }}
                    </span>
                  </div>

                  <span class="shrink-0 text-xs text-text-muted">
                    {{ formatTitle(p.attendance_status) }}
                  </span>
                </div>

                <div
                  v-if="!(participantsBySlotSafe[slotName] || []).length"
                  class="rounded-xl border border-dashed border-white/[0.075] bg-white/[0.018] [0.02] px-3 py-2 text-sm text-text-muted"
                >
                  No one assigned yet.
                </div>
              </div>
            </article>
          </div>

          <div v-else class="rounded-2xl border border-dashed border-white/15 bg-white/[0.025] p-4 text-sm text-text-secondary">
            No roles defined. Participants join as “No Role”.
          </div>

          <div v-if="unassignedParticipantsSafe.length" class="mt-5 rounded-2xl border border-white/[0.055] bg-white/[0.024] p-4">
            <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
              No Role
            </div>

            <div class="mt-3 grid gap-2 md:grid-cols-2">
              <div
                v-for="p in unassignedParticipantsSafe"
                :key="p.id"
                class="flex items-center justify-between gap-3 rounded-xl border border-white/10 bg-black/10 px-3 py-2"
              >
                <div class="flex min-w-0 items-center gap-2">
                  <img
                    v-if="participantAvatar(p)"
                    :src="participantAvatar(p)"
                    alt=""
                    class="h-7 w-7 shrink-0 rounded-lg object-cover"
                    loading="lazy"
                  />

                  <div
                    v-else
                    class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-white/[0.06] text-xs font-black text-horizon-white"
                  >
                    {{ participantInitial(p) }}
                  </div>

                  <span class="truncate text-sm font-semibold text-horizon-white">
                    {{ participantName(p) }}
                  </span>
                </div>

                <span class="shrink-0 text-xs text-text-muted">
                  {{ formatTitle(p.attendance_status) }}
                </span>
              </div>
            </div>
          </div>
        </section>
      </main>

      <aside class="space-y-6">
        <!-- Your status -->
        <section class="rounded-[2rem] border border-white/[0.055] bg-[linear-gradient(135deg,rgba(30,64,175,0.12),rgba(255,255,255,0.025))] p-5 ">
          <div class="text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--horizon-text-secondary)]">
            Your Status
          </div>

          <template v-if="currentParticipant">
            <div class="mt-3 text-2xl font-black text-horizon-white">
              Joined
            </div>

            <p class="mt-2 text-sm text-text-secondary">
              You are signed up as
              <strong class="text-horizon-white">{{ currentParticipant.slot ?? 'No Role' }}</strong>
              with status
              <strong class="text-horizon-white">{{ formatTitle(currentParticipant.attendance_status) }}</strong>.
            </p>

            <div class="mt-4 space-y-3">
              <HorizonSelect
                label="Role"
                v-model="joinForm.slot"
                :options="slotOptions"
              />

              <div class="grid gap-2">
                <HorizonButton
                  variant="primary"
                  class="w-full"
                  @click="updateSlot"
                  :disabled="joinProcessing"
                >
                  {{ joinProcessing ? 'Updating…' : 'Update Role' }}
                </HorizonButton>

                <HorizonButton
                  variant="outline"
                  class="w-full"
                  @click="askLeave"
                  :disabled="joinProcessing"
                >
                  Leave Operation
                </HorizonButton>
              </div>
            </div>
          </template>

          <template v-else>
            <div class="mt-3 text-2xl font-black text-horizon-white">
              Not Joined
            </div>

            <p class="mt-2 text-sm text-text-secondary">
              Join this {{ operationKindLabel(operation.operation_type ?? operation.operation_kind).toLowerCase() }} with an optional role.
            </p>

            <div class="mt-4 space-y-3">
              <HorizonSelect
                label="Role (optional)"
                v-model="joinForm.slot"
                :options="slotOptions"
              />

              <HorizonButton
                variant="primary"
                class="w-full"
                @click="join"
                :disabled="joinProcessing"
              >
                {{ joinProcessing ? 'Joining…' : 'Join Operation' }}
              </HorizonButton>
            </div>
          </template>
        </section>

        <!-- Quick facts -->
        <section class="rounded-[2rem] border border-white/[0.055] bg-white/[0.024] p-5">
          <div class="text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--horizon-text-secondary)]">
            Quick Facts
          </div>

          <div class="mt-4 space-y-3">
            <div class="flex items-center justify-between gap-3 border-b border-white/10 pb-3">
              <span class="text-sm text-text-muted">Status</span>
              <span class="text-sm font-semibold text-horizon-white">
                {{ formatTitle(operation.status) }}
              </span>
            </div>

            <div class="flex items-center justify-between gap-3 border-b border-white/10 pb-3">
              <span class="text-sm text-text-muted">Visibility</span>
              <span class="text-sm font-semibold text-horizon-white">
                {{ formatTitle(operation.visibility ?? 'open') }}
              </span>
            </div>

            <div class="flex items-center justify-between gap-3 border-b border-white/10 pb-3">
              <span class="text-sm text-text-muted">Participants</span>
              <span class="text-sm font-semibold text-horizon-white">
                {{ participantsList.length }}
              </span>
            </div>

            <div class="flex items-center justify-between gap-3">
              <span class="text-sm text-text-muted">Creator</span>
              <span class="text-sm font-semibold text-horizon-white">
                {{ operation.creator?.rsi_handle ?? operation.creator?.display_name ?? 'TBD' }}
              </span>
            </div>
          </div>
        </section>

        <!-- Participants -->
        <section class="rounded-[2rem] border border-white/[0.055] bg-white/[0.024] p-5">
          <div class="flex items-center justify-between gap-3">
            <div class="text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--horizon-text-secondary)]">
              Participants
            </div>

            <span class="rounded-full border border-white/[0.055] bg-white/[0.024] px-2.5 py-1 text-xs font-bold text-text-secondary">
              {{ participantsList.length }}
            </span>
          </div>

          <div class="mt-4 max-h-72 space-y-2 overflow-y-auto pr-1">
            <div
              v-for="p in participantsList"
              :key="p.id"
              class="flex items-center justify-between gap-3 rounded-xl border border-white/[0.055] bg-white/[0.024] px-3 py-2"
            >
              <div class="flex min-w-0 items-center gap-2">
                <img
                  v-if="participantAvatar(p)"
                  :src="participantAvatar(p)"
                  alt=""
                  class="h-8 w-8 shrink-0 rounded-lg object-cover"
                  loading="lazy"
                />

                <div
                  v-else
                  class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-white/[0.06] text-xs font-black text-horizon-white"
                >
                  {{ participantInitial(p) }}
                </div>

                <div class="min-w-0">
                  <div class="truncate text-sm font-semibold text-horizon-white">
                    {{ participantName(p) }}
                  </div>

                  <div class="truncate text-xs text-text-muted">
                    {{ p.slot ?? 'No Role' }}
                  </div>
                </div>
              </div>

              <span class="shrink-0 text-xs text-text-muted">
                {{ formatTitle(p.attendance_status) }}
              </span>
            </div>

            <div
              v-if="!participantsList.length"
              class="rounded-xl border border-dashed border-white/15 bg-white/[0.025] px-3 py-4 text-center text-sm text-text-secondary"
            >
              No participants have joined yet.
            </div>
          </div>
        </section>
      </aside>
    </div>

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







