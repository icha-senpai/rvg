<script setup>
import HorizonContainer from '@/Components/HorizonContainer.vue'
import HorizonPanel from '@/Components/HorizonPanel.vue'
import HorizonButton from '@/Components/HorizonButton.vue'
import ProgressPill from '@/Components/ProgressPill.vue'
import HorizonSelect from '@/Components/HorizonSelect.vue'

import { ref, reactive, computed, watch } from 'vue'
import axios from 'axios'
import { route } from 'ziggy-js'
import { Ziggy } from '../../../ziggy'

import { usePage } from '@inertiajs/vue3'

// ----------------------
// EMITS
// ----------------------
const emit = defineEmits(['close', 'refresh'])

// ----------------------
// PROPS
// ----------------------
const props = defineProps({
  operation: Object,
  participants: Array,
  participantsBySlot: Object,
  unassignedParticipants: Array,
  currentParticipant: Object,
})

const operation = props.operation
const currentParticipant = computed(() => props.currentParticipant ?? null)

const page = usePage()
const user = computed(() => page.props.auth?.user ?? null)

const canManageOperation = computed(() => {
  const op = operation
  if (!user.value) return false

  const roles = user.value?.roles ?? []
  const isDirectorLike = roles.some(r => r?.slug === 'director' || r?.slug === 'tech_director')
  if (isDirectorLike) return true

  const squadronId = op?.squadron?.id
  if (!squadronId) return false

  const membership = user.value?.squadrons?.find(s => s.id === squadronId)
  if (!membership) return false

  const membershipStatus = membership.pivot?.membership_status
  if (membershipStatus && membershipStatus !== 'active') return false

  const role = membership.pivot?.role
  return role === 'leader' || role === 'lieutenant'
})

const calendarChoice = ref('')
const calendarOptions = [
  { label: 'Add to Calendar', value: '' },
  { label: 'Apple / Outlook / Proton (.ics)', value: 'ics' },
  { label: 'Google Calendar', value: 'google' },
  { label: 'Outlook Web', value: 'outlook' },
]

const canAddToCalendar = computed(() => !!operation?.starts_at)

function toCalendarUtcStamp(d) {
  if (!d) return null
  return d.toISOString().replace(/[-:]/g, '').replace(/\.\d{3}Z$/i, 'Z')
}

const calendarBody = computed(() => {
  const parts = [
    operation?.description,
    operation?.notes,
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
  if (!operation?.id) return null
  return route('operations.calendar', operation.id, Ziggy)
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

/* ============================================================
   FORMATTER
============================================================ */
function asText(v) {
  return v ? String(v) : 'TBD'
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

/* ============================================================
   STATUS PILL VARIANT
============================================================ */
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

/* ============================================================
   JOIN FORM + PREFILL
============================================================ */
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
const transitionProcessing = ref(false)

async function startOperation() {
  if (transitionProcessing.value) return
  if (!confirm('Start this operation?')) return

  transitionProcessing.value = true

  try {
    await axios.post(route('operations.start', operation.id, Ziggy), {})
    emit('refresh')
  } catch (err) {
    console.error(err)
    alert('Failed to start operation.')
  } finally {
    transitionProcessing.value = false
  }
}

async function completeOperation() {
  if (transitionProcessing.value) return
  if (!confirm('Mark this operation as completed?')) return

  transitionProcessing.value = true

  try {
    await axios.post(route('operations.complete', operation.id, Ziggy), {})
    emit('refresh')
  } catch (err) {
    console.error(err)
    alert('Failed to complete operation.')
  } finally {
    transitionProcessing.value = false
  }
}

async function cancelOperation() {
  if (transitionProcessing.value) return

  const reason = prompt('Cancellation reason (optional):')
  if (reason === null) return
  if (!confirm('Cancel this operation?')) return

  transitionProcessing.value = true

  try {
    await axios.post(route('operations.cancel', operation.id, Ziggy), {
      reason: reason || null,
    })
    emit('refresh')
  } catch (err) {
    console.error(err)
    alert('Failed to cancel operation.')
  } finally {
    transitionProcessing.value = false
  }
}

/* ============================================================
   JOIN / LEAVE / UPDATE SLOT (NO NAVIGATION)
   Uses axios so backend redirects do NOT move the browser.
============================================================ */
async function join() {
  if (joinProcessing.value) return
  joinProcessing.value = true

  try {
    await axios.post(
      route('operations.join', operation.id, Ziggy),
      {
        slot: joinForm.slot,
        notes: joinForm.notes,
        operation_role_id: null,
      }
    )

    // Tell parent: re-fetch showData so modal updates.
    emit('refresh')
  } catch (err) {
    const status = err?.response?.status ?? null

    if (status === 401 || status === 419) {
      return
    }

    console.error(err)
    alert('Failed to join operation.')
  } finally {
    joinProcessing.value = false
  }
}

async function leave() {
  if (!confirm('Leave this operation?')) return
  if (joinProcessing.value) return
  joinProcessing.value = true

  try {
    await axios.post(
      route('operations.leave', operation.id, Ziggy),
      {}
    )

    emit('refresh')
  } catch (err) {
    const status = err?.response?.status ?? null

    if (status === 401 || status === 419) {
      return
    }

    console.error(err)
    alert('Failed to leave operation.')
  } finally {
    joinProcessing.value = false
  }
}

async function updateSlot() {
  if (!currentParticipant.value) return
  if (joinProcessing.value) return
  joinProcessing.value = true

  try {
    await axios.post(
      route('operations.participants.slot', {
        operation: operation.id,
        participant: currentParticipant.value.id,
      }, Ziggy),
      {
        slot: joinForm.slot,
        operation_role_id: null,
      }
    )

    emit('refresh')
  } catch (err) {
    const status = err?.response?.status ?? null

    if (status === 401 || status === 419) {
      return
    }

    console.error(err)
    alert('Failed to update role.')
  } finally {
    joinProcessing.value = false
  }
}
</script>

<template>
  <HorizonContainer class="space-y-10">

    <!-- HEADER -->
    <div class="mx-auto max-w-5xl flex items-center justify-between mb-4">
      <div class="hz-stack-sm">
        <div class="hz-section-label">
          {{ operation.operation_kind === 'mission' ? 'Operation' : 'Operation' }}
        </div>

        <h1 class="hz-title-lg text-horizon-white">
          {{ operation.title }}
        </h1>
      </div>

      <div class="flex items-center gap-3">
        <ProgressPill :variant="statusVariant">
          {{ operation.status }}
        </ProgressPill>

        <div v-if="canAddToCalendar" class="w-56">
          <HorizonSelect
            v-model="calendarChoice"
            :options="calendarOptions"
          />
        </div>
      </div>
    </div>

    <HorizonPanel
      v-if="canManageOperation"
      class="rounded-xl shadow-lg"
    >
      <div class="hz-section-label mb-3">Operation Controls</div>

      <div class="flex gap-3 flex-wrap">
        <HorizonButton
          v-if="operation.status === 'published'"
          variant="primary"
          @click="startOperation"
          :disabled="transitionProcessing"
        >
          Start
        </HorizonButton>

        <HorizonButton
          v-if="operation.status === 'in_progress'"
          variant="primary"
          @click="completeOperation"
          :disabled="transitionProcessing"
        >
          End
        </HorizonButton>

        <HorizonButton
          v-if="['published', 'in_progress'].includes(operation.status)"
          variant="danger"
          @click="cancelOperation"
          :disabled="transitionProcessing"
        >
          Cancel
        </HorizonButton>
      </div>
    </HorizonPanel>

    <!-- MAIN GRID -->
    <div class="mb-20 mx-auto max-w-5xl space-y-10">

      <section class="space-y-6">

        <!-- META PANEL -->
        <HorizonPanel class="rounded-xl shadow-lg">
          <div class="grid md:grid-cols-2 gap-6">

            <div class="hz-stack-xs">
              <div class="hz-section-label">Squadron</div>
              <div class="hz-body-strong">
                {{ operation.squadron?.name ?? 'TBD' }}
              </div>
            </div>

            <div class="hz-stack-xs">
              <div class="hz-section-label">Creator</div>
              <div class="hz-body-strong">
                {{ operation.creator?.rsi_handle ?? 'TBD' }}
              </div>
            </div>

            <div class="hz-stack-xs">
              <div class="hz-section-label">Time Window</div>
              <div class="hz-body-strong">
                <div>
                  {{ formatUTC(operation.starts_at) }}
                </div>
                <div class="hz-caption text-horizon-offwhite opacity-70">
                  {{ formatLocal(operation.starts_at) }} (local)
                </div>

                <div class="mt-2" v-if="operation.ends_at">
                  <div>
                    {{ formatUTC(operation.ends_at) }}
                  </div>
                  <div class="hz-caption text-horizon-offwhite opacity-70">
                    {{ formatLocal(operation.ends_at) }} (local)
                  </div>
                </div>
              </div>
            </div>

            <div class="hz-stack-xs">
              <div class="hz-section-label">Visibility</div>
              <div class="hz-body-strong">
                {{ operation.visibility ?? 'open' }}
              </div>
            </div>

            <div class="hz-stack-xs">
              <div class="hz-section-label">Comms Strictness</div>
              <div class="hz-body-strong">
                {{ operation.operation_strictness ?? 'normal' }}
              </div>
            </div>

            <div v-if="operation.rsvp_deadline" class="hz-stack-xs">
              <div class="hz-section-label">Sign up Deadline</div>
              <div class="hz-body-strong">
                <div>
                  {{ formatUTC(operation.rsvp_deadline) }}
                </div>
                <div class="hz-caption text-horizon-offwhite opacity-70">
                  {{ formatLocal(operation.rsvp_deadline) }} (local)
                </div>
              </div>
            </div>

            <div v-if="operation.type" class="hz-stack-xs">
              <div class="hz-section-label">Operation Type</div>
              <div class="hz-body-strong">
                {{ operation.type }}
              </div>
            </div>

          </div>
        </HorizonPanel>

        <!-- BRIEFING -->
        <HorizonPanel
          v-if="operation.description"
          class="rounded-xl shadow-lg"
        >
          <div class="hz-section-label mb-2">Operation Briefing</div>
          <p class="hz-body whitespace-pre-line">
            {{ operation.description }}
          </p>
        </HorizonPanel>

        <!-- NOTES -->
        <HorizonPanel
          v-if="operation.notes"
          class="rounded-xl shadow-lg"
        >
          <div class="hz-section-label mb-2">Operation Extended Briefing</div>
          <p class="hz-body whitespace-pre-line">
            {{ operation.notes }}
          </p>
        </HorizonPanel>

        <!-- ROLES -->
        <HorizonPanel class="rounded-xl shadow-lg">
          <div class="hz-section-label mb-3">Roles</div>

          <div
            v-if="(operation.slots || []).length"
            class="flex flex-col gap-4"
          >
            <div
              v-for="slotName in operation.slots"
              :key="slotName"
              class="p-4 rounded-xl bg-bg-elevated/60 hz-inset hz-stack-xs shadow"
            >
              <div class="flex justify-between items-center">
                <div class="hz-body-strong">{{ slotName }}</div>
                <div class="hz-caption opacity-70">
                  {{ (participantsBySlot[slotName] || []).length }} participants
                </div>
              </div>

              <ul class="hz-caption hz-stack-2xs">
                <li
                  v-for="p in participantsBySlot[slotName] || []"
                  :key="p.id"
                >
                  • {{ p.user?.rsi_handle ?? p.user?.display_name ?? p.user?.name ?? 'Unknown' }}
                  <span class="opacity-60">
                    ({{ p.attendance_status }})
                  </span>
                </li>

                <li
                  v-if="!(participantsBySlot[slotName] || []).length"
                  class="opacity-60"
                >
                  No one assigned yet.
                </li>
              </ul>
            </div>
          </div>

          <div v-else class="hz-caption hz-text-muted">
            No roles defined. Participants join as “No Role”.
          </div>

          <div v-if="unassignedParticipants.length" class="mt-6">
            <div class="hz-section-label mb-1">No Role</div>
            <ul class="hz-caption hz-stack-2xs">
              <li
                v-for="p in unassignedParticipants"
                :key="p.id"
              >
                • {{ p.user?.rsi_handle ?? p.user?.display_name ?? p.user?.name ?? 'Unknown' }}
                <span class="opacity-60">
                  ({{ p.attendance_status }})
                </span>
              </li>
            </ul>
          </div>
        </HorizonPanel>

      </section>

      <section class="space-y-6">

        <!-- USER STATUS -->
        <HorizonPanel class="rounded-xl shadow-lg">
          <div class="hz-section-label mb-3">Your Status</div>

          <template v-if="currentParticipant">
            <p class="hz-body mb-3">
              You are signed up as
              <strong>{{ currentParticipant.slot ?? 'No Role' }}</strong>
              ({{ currentParticipant.attendance_status }}).
            </p>

            <HorizonSelect
              label="Role"
              v-model="joinForm.slot"
              :options="slotOptions"
            />

            <div class="flex gap-3">
              <HorizonButton
                variant="primary"
                class="flex-1"
                @click="updateSlot"
                :disabled="joinProcessing"
              >
                Update Role
              </HorizonButton>

              <HorizonButton
                variant="outline"
                class="flex-1"
                @click="leave"
                :disabled="joinProcessing"
              >
                Leave Operation
              </HorizonButton>
            </div>
          </template>

          <template v-else>
            <p class="hz-body mb-4">
              Join this {{ operation.operation_kind }} with an optional role.
            </p>

            <div class="hz-stack">
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
                Join Operation
              </HorizonButton>
            </div>
          </template>

        </HorizonPanel>

        <!-- PARTICIPANTS -->
        <HorizonPanel class="rounded-xl shadow-lg">
          <div class="hz-section-label mb-3">Participants</div>

          <p class="hz-body mb-3">
            {{ participants.length }} total.
          </p>

          <ul class="hz-caption max-h-48 overflow-auto hz-stack-2xs">
            <li
              v-for="p in participants"
              :key="p.id"
              class="flex justify-between"
            >
              <span>
                {{ p.user?.rsi_handle ?? p.user?.display_name ?? p.user?.name ?? 'Unknown' }}
                <span class="opacity-60">
                  ({{ p.slot ?? 'No Role' }})
                </span>
              </span>
              <span class="opacity-60">
                {{ p.attendance_status }}
              </span>
            </li>
          </ul>
        </HorizonPanel>

      </section>

    </div>
  </HorizonContainer>
</template>
