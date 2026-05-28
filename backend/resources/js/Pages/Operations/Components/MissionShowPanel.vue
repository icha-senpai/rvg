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
    <!-- Header -->
    <div class="border-b border-white/[0.055] pb-5">
      <div class="flex flex-wrap items-center gap-2">
        <span class="text-xs font-bold uppercase tracking-[0.2em] text-[color:var(--horizon-text-secondary)]">
          {{ operationKindLabel(operation.operation_type ?? operation.operation_kind) }}
        </span>
        <span class="text-xs text-text-muted">•</span>
        <ProgressPill :variant="statusVariant" size="sm">
          {{ formatTitle(operation.status) }}
        </ProgressPill>
      </div>

      <h1 class="mt-2 text-2xl font-black tracking-tight text-horizon-white md:text-3xl">
        {{ displayTitle }}
      </h1>

    </div>

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
        <!-- Time info -->
        <div class="flex flex-wrap gap-x-6 gap-y-3 text-sm">
          <div>
            <span class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Start</span>
            <div class="font-semibold text-horizon-white">{{ formatUTC(operation.starts_at) }}</div>
            <div class="text-xs text-text-secondary">{{ formatLocal(operation.starts_at) }} local</div>
          </div>
          <div v-if="operation.ends_at">
            <span class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">End</span>
            <div class="font-semibold text-horizon-white">{{ formatUTC(operation.ends_at) }}</div>
            <div class="text-xs text-text-secondary">{{ formatLocal(operation.ends_at) }} local</div>
          </div>
          <div>
            <span class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Deadline</span>
            <div class="font-semibold" :class="operation.rsvp_deadline ? 'text-horizon-white' : 'text-text-secondary'">
              {{ operation.rsvp_deadline ? formatUTC(operation.rsvp_deadline) : 'None set' }}
            </div>
          </div>
        </div>

        <!-- Briefing -->
        <div v-if="operation.description || operation.extended_description || operation.notes" class="space-y-4">
          <p v-if="operation.description" class="whitespace-pre-line text-sm leading-7 text-text-secondary">
            {{ operation.description }}
          </p>
          <p v-if="operation.extended_description || operation.notes" class="whitespace-pre-line text-sm leading-7 text-text-secondary border-t border-white/[0.055] pt-4">
            {{ operation.extended_description ?? operation.notes }}
          </p>
        </div>

        <!-- Meta information -->
        <div v-if="hasMetaInformation" class="space-y-3">
          <div class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Details</div>
          <div class="flex flex-wrap gap-x-6 gap-y-3 text-sm">
            <div v-if="operation.start_location">
              <span class="text-text-muted">Start</span>
              <div class="font-semibold text-horizon-white">{{ operation.start_location }}</div>
            </div>
            <div v-if="operation.operation_location">
              <span class="text-text-muted">Location</span>
              <div class="font-semibold text-horizon-white">{{ operation.operation_location }}</div>
            </div>
            <div v-if="operation.branch">
              <span class="text-text-muted">Branch</span>
              <div class="flex items-center gap-2 font-semibold text-horizon-white">
                <img v-if="branchLogoSrc(operation.branch)" :src="branchLogoSrc(operation.branch)" class="h-4 w-4 object-contain" loading="lazy" />
                {{ formatTitle(operation.branch) }}
              </div>
            </div>
            <div v-if="operation.squadron_name">
              <span class="text-text-muted">Squadron</span>
              <div class="font-semibold text-horizon-white">{{ operation.squadron_name }}</div>
            </div>
            <div v-if="operation.gameplay_type || operation.type">
              <span class="text-text-muted">Type</span>
              <div class="font-semibold text-horizon-white">{{ formatTitle(operation.gameplay_type ?? operation.type) }}</div>
            </div>
            <div>
              <span class="text-text-muted">Comms</span>
              <div class="font-semibold text-horizon-white">{{ formatTitle(operation.operation_strictness ?? 'normal') }}</div>
            </div>
          </div>
        </div>

        <!-- Roles -->
        <div class="space-y-4">
          <div class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">
            Slots ({{ participantsList.length }} total)
          </div>

          <div v-if="(operation.slots || []).length" class="space-y-4">
            <div v-for="slotName in operation.slots" :key="slotName" class="space-y-2">
              <div class="flex items-center justify-between text-sm">
                <span class="font-semibold text-horizon-white">{{ slotName }}</span>
                <span class="text-xs text-text-muted">{{ (participantsBySlotSafe[slotName] || []).length }}</span>
              </div>
              <div class="space-y-1">
                <div v-for="p in participantsBySlotSafe[slotName] || []" :key="p.id" class="flex items-center justify-between gap-2 py-1">
                  <div class="flex min-w-0 items-center gap-2">
                    <img v-if="participantAvatar(p)" :src="participantAvatar(p)" alt="" class="h-6 w-6 shrink-0 rounded object-cover" loading="lazy" />
                    <div v-else class="flex h-6 w-6 shrink-0 items-center justify-center rounded bg-white/[0.05] text-[10px] font-bold text-horizon-white">{{ participantInitial(p) }}</div>
                    <span class="truncate text-sm text-horizon-white">{{ participantName(p) }}</span>
                  </div>
                  <span class="shrink-0 text-xs text-text-muted">{{ formatTitle(p.attendance_status) }}</span>
                </div>
                <div v-if="!(participantsBySlotSafe[slotName] || []).length" class="py-1 text-sm text-text-muted italic">Empty</div>
              </div>
            </div>
          </div>

          <div v-else class="text-sm text-text-secondary">No slots defined.</div>

          <div v-if="unassignedParticipantsSafe.length" class="border-t border-white/[0.055] pt-4">
            <div class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted mb-2">No Role</div>
            <div class="space-y-1">
              <div v-for="p in unassignedParticipantsSafe" :key="p.id" class="flex items-center justify-between gap-2 py-1">
                <div class="flex min-w-0 items-center gap-2">
                  <img v-if="participantAvatar(p)" :src="participantAvatar(p)" alt="" class="h-6 w-6 shrink-0 rounded object-cover" loading="lazy" />
                  <div v-else class="flex h-6 w-6 shrink-0 items-center justify-center rounded bg-white/[0.05] text-[10px] font-bold text-horizon-white">{{ participantInitial(p) }}</div>
                  <span class="truncate text-sm text-horizon-white">{{ participantName(p) }}</span>
                </div>
                <span class="shrink-0 text-xs text-text-muted">{{ formatTitle(p.attendance_status) }}</span>
              </div>
            </div>
          </div>
        </div>
      </main>

      <aside class="space-y-6">
        <!-- Calendar -->
        <div v-if="canAddToCalendar" class="space-y-2">
          <div class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Calendar</div>
          <HorizonSelect v-model="calendarChoice" :options="calendarOptions" size="sm" />
        </div>

        <!-- Your status -->
        <div class="space-y-3">
          <div class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Your Status</div>

          <template v-if="currentParticipant">
            <div class="flex items-center gap-2">
              <span class="text-lg font-semibold text-horizon-white">Joined</span>
              <span class="text-xs text-text-secondary">as {{ currentParticipant.slot ?? 'No Role' }}</span>
            </div>

            <div class="space-y-2">
              <HorizonSelect v-model="joinForm.slot" :options="slotOptions" size="sm" />
              <div class="flex gap-2">
                <HorizonButton variant="primary" size="sm" class="flex-1" @click="updateSlot" :disabled="joinProcessing">
                  {{ joinProcessing ? '…' : 'Update' }}
                </HorizonButton>
                <HorizonButton variant="outline" size="sm" @click="askLeave" :disabled="joinProcessing">Leave</HorizonButton>
              </div>
            </div>
          </template>

          <template v-else>
            <div class="text-lg font-semibold text-horizon-white">Not Joined</div>

            <div class="space-y-2">
              <HorizonSelect v-model="joinForm.slot" :options="slotOptions" size="sm" placeholder="Role (optional)" />
              <HorizonButton variant="primary" size="sm" class="w-full" @click="join" :disabled="joinProcessing">
                {{ joinProcessing ? '…' : 'Join' }}
              </HorizonButton>
            </div>
          </template>
        </div>

        <!-- Quick facts -->
        <div class="space-y-2 text-sm">
          <div class="flex justify-between gap-2">
            <span class="text-text-muted">Status</span>
            <span class="font-semibold text-horizon-white">{{ formatTitle(operation.status) }}</span>
          </div>
          <div class="flex justify-between gap-2">
            <span class="text-text-muted">Visibility</span>
            <span class="font-semibold text-horizon-white">{{ formatTitle(operation.visibility ?? 'open') }}</span>
          </div>
          <div class="flex justify-between gap-2">
            <span class="text-text-muted">Participants</span>
            <span class="font-semibold text-horizon-white">{{ participantsList.length }}</span>
          </div>
          <div class="flex justify-between gap-2">
            <span class="text-text-muted">Creator</span>
            <span class="font-semibold text-horizon-white">{{ operation.creator?.rsi_handle ?? operation.creator?.display_name ?? 'TBD' }}</span>
          </div>
        </div>

        <!-- Participants -->
        <div class="space-y-2">
          <div class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">
            Participants ({{ participantsList.length }})
          </div>

          <div class="max-h-64 space-y-1 overflow-y-auto pr-1">
            <div
              v-for="p in participantsList"
              :key="p.id"
              class="flex items-center justify-between gap-2 py-1.5"
            >
              <div class="flex min-w-0 items-center gap-2">
                <img
                  v-if="participantAvatar(p)"
                  :src="participantAvatar(p)"
                  alt=""
                  class="h-7 w-7 shrink-0 rounded-md object-cover"
                  loading="lazy"
                />
                <div
                  v-else
                  class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-white/[0.05] text-[10px] font-bold text-horizon-white"
                >
                  {{ participantInitial(p) }}
                </div>
                <div class="min-w-0">
                  <div class="truncate text-sm text-horizon-white">
                    {{ participantName(p) }}
                  </div>
                </div>
              </div>
              <span class="shrink-0 text-xs text-text-muted">{{ p.slot ?? '—' }}</span>
            </div>

            <div v-if="!participantsList.length" class="py-2 text-sm text-text-secondary">
              No participants yet.
            </div>
          </div>
        </div>
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







