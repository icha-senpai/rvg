<script setup>
import { computed, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { Ziggy } from '../ziggy'

import HorizonBadge from '@/Components/HorizonBadge.vue'
import HorizonButton from '@/Components/HorizonButton.vue'
import HorizonFormBlock from '@/Components/HorizonFormBlock.vue'
import HorizonInput from '@/Components/HorizonInput.vue'

const aarDraftStore = new Map()

const emit = defineEmits(['attendance-draft-change'])

const props = defineProps({
  operation: {
    type: Object,
    required: true,
  },
  verifiedMembers: {
    type: Array,
    default: () => [],
  },
  canManage: {
    type: Boolean,
    default: false,
  },
  reloadOnly: {
    type: Array,
    default: () => [],
  },
  compact: {
    type: Boolean,
    default: false,
  },
})

const reportDraft = ref('')
const attendanceDraft = ref([])
const noShowDraft = ref([])
const signedOffEarlyDraft = ref([])
const excusedDraft = ref([])
const memberSearch = ref('')
const noShowSearch = ref('')
const signedOffSearch = ref('')
const excusedSearch = ref('')
const saving = ref(false)
const attendanceLocked = computed(() => !!props.operation?.operation_settlement?.locked_attendance)
const canEditAttendance = computed(() => props.canManage && !attendanceLocked.value)

function aarDraftKey() {
  return `aar:${props.operation?.id ?? 'unknown'}`
}

function currentAarSignature() {
  return JSON.stringify([
    props.operation?.id ?? null,
    props.operation?.after_action_report ?? '',
    props.operation?.completion_outcome ?? null,
    props.operation?.after_action_report_updated_at ?? null,
    props.operation?.after_action_attendance ?? [],
    props.operation?.after_action_no_show ?? [],
    props.operation?.after_action_signed_off_early ?? [],
    props.operation?.after_action_excused ?? [],
  ])
}

const signedUpUsers = computed(() => {
  const seen = new Set()

  return (props.operation?.participants ?? [])
    .map(participant => participant?.user ?? null)
    .filter(user => user?.id != null)
    .filter((user) => {
      const id = Number(user.id)

      if (!Number.isFinite(id) || seen.has(id)) {
        return false
      }

      seen.add(id)

      return true
    })
})

const signedUpUserIds = computed(() => {
  return new Set(signedUpUsers.value.map(user => Number(user.id)))
})

const signedUpUserLookup = computed(() => {
  return new Map(signedUpUsers.value.map(user => [Number(user.id), user]))
})

const attendanceUsers = computed(() => {
  const lookup = new Map((props.verifiedMembers ?? []).map(user => [Number(user.id), user]))
  const roster = props.operation?.after_action_attendance ?? []

  return roster.map(user => lookup.get(Number(user.id)) ?? user)
})

const attendanceUserIds = computed(() => {
  return attendanceUsers.value.map(user => Number(user.id))
})

const attendanceLookup = computed(() => {
  return new Map(attendanceUsers.value.map(user => [Number(user.id), user]))
})

const noShowUsers = computed(() => {
  const lookup = new Map((props.verifiedMembers ?? []).map(user => [Number(user.id), user]))
  const roster = props.operation?.after_action_no_show ?? []

  return roster.map(user => lookup.get(Number(user.id)) ?? user)
})

const noShowUserIds = computed(() => {
  return noShowUsers.value.map(user => Number(user.id))
})

const noShowLookup = computed(() => {
  return new Map(noShowUsers.value.map(user => [Number(user.id), user]))
})

const signedOffEarlyUsers = computed(() => {
  const lookup = new Map((props.verifiedMembers ?? []).map(user => [Number(user.id), user]))
  const roster = props.operation?.after_action_signed_off_early ?? []

  return roster.map(user => lookup.get(Number(user.id)) ?? user)
})

const signedOffEarlyUserIds = computed(() => {
  return signedOffEarlyUsers.value.map(user => Number(user.id))
})

const signedOffEarlyLookup = computed(() => {
  return new Map(signedOffEarlyUsers.value.map(user => [Number(user.id), user]))
})

const excusedUsers = computed(() => {
  const lookup = new Map((props.verifiedMembers ?? []).map(user => [Number(user.id), user]))
  const roster = props.operation?.after_action_excused ?? []

  return roster.map(user => lookup.get(Number(user.id)) ?? user)
})

const excusedUserIds = computed(() => {
  return excusedUsers.value.map(user => Number(user.id))
})

const excusedLookup = computed(() => {
  return new Map(excusedUsers.value.map(user => [Number(user.id), user]))
})

const filteredVerifiedMembers = computed(() => {
  const search = memberSearch.value.trim().toLowerCase()
  const selectedIds = new Set([
    ...attendanceDraft.value.map(id => Number(id)),
    ...noShowDraft.value.map(id => Number(id)),
    ...signedOffEarlyDraft.value.map(id => Number(id)),
    ...excusedDraft.value.map(id => Number(id)),
  ])

  return (props.verifiedMembers ?? [])
    .filter(user => !selectedIds.has(Number(user.id)))
    .filter((user) => {
      if (!search) return true

      const haystack = [
        user?.rsi_handle,
        user?.discord_name,
        user?.name,
        String(user?.id ?? ''),
      ]
        .filter(Boolean)
        .join(' ')
        .toLowerCase()

      return haystack.includes(search)
    })
    .slice(0, 8)
})

const filteredNoShowMembers = computed(() => {
  const search = noShowSearch.value.trim().toLowerCase()
  const noShowIds = new Set(noShowDraft.value.map(id => Number(id)))
  const signedOffIds = new Set(signedOffEarlyDraft.value.map(id => Number(id)))
  const excusedIds = new Set(excusedDraft.value.map(id => Number(id)))

  return signedUpUsers.value
    .filter(Boolean)
    .filter(user => !noShowIds.has(Number(user.id)))
    .filter(user => !signedOffIds.has(Number(user.id)))
    .filter(user => !excusedIds.has(Number(user.id)))
    .filter((user) => {
      if (!search) return true

      const haystack = [
        user?.rsi_handle,
        user?.discord_name,
        user?.name,
        String(user?.id ?? ''),
      ]
        .filter(Boolean)
        .join(' ')
        .toLowerCase()

      return haystack.includes(search)
    })
    .slice(0, 8)
})

const filteredSignedOffMembers = computed(() => {
  const search = signedOffSearch.value.trim().toLowerCase()
  const signedOffIds = new Set(signedOffEarlyDraft.value.map(id => Number(id)))
  const noShowIds = new Set(noShowDraft.value.map(id => Number(id)))
  const excusedIds = new Set(excusedDraft.value.map(id => Number(id)))

  return signedUpUsers.value
    .filter(Boolean)
    .filter(user => !signedOffIds.has(Number(user.id)))
    .filter(user => !noShowIds.has(Number(user.id)))
    .filter(user => !excusedIds.has(Number(user.id)))
    .filter((user) => {
      if (!search) return true

      const haystack = [
        user?.rsi_handle,
        user?.discord_name,
        user?.name,
        String(user?.id ?? ''),
      ]
        .filter(Boolean)
        .join(' ')
        .toLowerCase()

      return haystack.includes(search)
    })
    .slice(0, 8)
})

const filteredExcusedMembers = computed(() => {
  const search = excusedSearch.value.trim().toLowerCase()
  const excusedIds = new Set(excusedDraft.value.map(id => Number(id)))
  const noShowIds = new Set(noShowDraft.value.map(id => Number(id)))
  const signedOffIds = new Set(signedOffEarlyDraft.value.map(id => Number(id)))

  return signedUpUsers.value
    .filter(Boolean)
    .filter(user => !excusedIds.has(Number(user.id)))
    .filter(user => !noShowIds.has(Number(user.id)))
    .filter(user => !signedOffIds.has(Number(user.id)))
    .filter((user) => {
      if (!search) return true

      const haystack = [
        user?.rsi_handle,
        user?.discord_name,
        user?.name,
        String(user?.id ?? ''),
      ]
        .filter(Boolean)
        .join(' ')
        .toLowerCase()

      return haystack.includes(search)
    })
    .slice(0, 8)
})

const hasChanges = computed(() => {
  const existingIds = attendanceUserIds.value
  const existingNoShowIds = noShowUserIds.value
  const existingSignedOffIds = signedOffEarlyUserIds.value
  const existingExcusedIds = excusedUserIds.value

  if ((reportDraft.value ?? '') !== (props.operation?.after_action_report ?? '')) {
    return true
  }

  if (attendanceDraft.value.length !== existingIds.length) {
    return true
  }

  if (noShowDraft.value.length !== existingNoShowIds.length) {
    return true
  }

  if (signedOffEarlyDraft.value.length !== existingSignedOffIds.length) {
    return true
  }

  if (excusedDraft.value.length !== existingExcusedIds.length) {
    return true
  }

  if (attendanceDraft.value.some((id, index) => Number(id) !== Number(existingIds[index]))) {
    return true
  }

  if (noShowDraft.value.some((id, index) => Number(id) !== Number(existingNoShowIds[index]))) {
    return true
  }

  if (signedOffEarlyDraft.value.some((id, index) => Number(id) !== Number(existingSignedOffIds[index]))) {
    return true
  }

  return excusedDraft.value.some((id, index) => Number(id) !== Number(existingExcusedIds[index]))
})

const updatedAtLabel = computed(() => {
  const value = props.operation?.after_action_report_updated_at
  if (!value) return null

  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return null

  return new Intl.DateTimeFormat('en-GB', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hour12: true,
  }).format(date)
})

function defaultReportTemplate(outcome) {
  const outcomeLabel = outcome === 'failed' ? 'Failure' : 'Success'

  return `Outcome: ${outcomeLabel}

Summary:

Objectives completed:

Attendance notes:

Lessons learned:

Follow-up actions:`
}

function persistAarDraft(signature = currentAarSignature()) {
  aarDraftStore.set(aarDraftKey(), {
    signature,
    reportDraft: reportDraft.value,
    attendanceDraft: [...attendanceDraft.value],
    noShowDraft: [...noShowDraft.value],
    signedOffEarlyDraft: [...signedOffEarlyDraft.value],
    excusedDraft: [...excusedDraft.value],
    memberSearch: memberSearch.value,
    noShowSearch: noShowSearch.value,
    signedOffSearch: signedOffSearch.value,
    excusedSearch: excusedSearch.value,
  })
}

watch(
  () => [
    props.operation?.id,
    props.operation?.after_action_report,
    props.operation?.completion_outcome,
    props.operation?.after_action_report_updated_at,
    JSON.stringify(props.operation?.after_action_attendance ?? []),
    JSON.stringify(props.operation?.after_action_no_show ?? []),
    JSON.stringify(props.operation?.after_action_signed_off_early ?? []),
    JSON.stringify(props.operation?.after_action_excused ?? []),
  ],
  () => {
    const nextSignature = currentAarSignature()
    const storedDraft = aarDraftStore.get(aarDraftKey())

    if (storedDraft?.signature === nextSignature) {
      reportDraft.value = storedDraft.reportDraft ?? reportDraft.value
      attendanceDraft.value = Array.isArray(storedDraft.attendanceDraft) ? [...storedDraft.attendanceDraft] : attendanceDraft.value
      noShowDraft.value = Array.isArray(storedDraft.noShowDraft) ? [...storedDraft.noShowDraft] : noShowDraft.value
      signedOffEarlyDraft.value = Array.isArray(storedDraft.signedOffEarlyDraft) ? [...storedDraft.signedOffEarlyDraft] : signedOffEarlyDraft.value
      excusedDraft.value = Array.isArray(storedDraft.excusedDraft) ? [...storedDraft.excusedDraft] : excusedDraft.value
      memberSearch.value = storedDraft.memberSearch ?? memberSearch.value
      noShowSearch.value = storedDraft.noShowSearch ?? noShowSearch.value
      signedOffSearch.value = storedDraft.signedOffSearch ?? signedOffSearch.value
      excusedSearch.value = storedDraft.excusedSearch ?? excusedSearch.value
      return
    }

    const existingReport = String(props.operation?.after_action_report ?? '')

    reportDraft.value = existingReport.trim()
      ? existingReport
      : defaultReportTemplate(props.operation?.completion_outcome)

    attendanceDraft.value = attendanceUserIds.value
    noShowDraft.value = noShowUserIds.value
    signedOffEarlyDraft.value = signedOffEarlyUserIds.value
    excusedDraft.value = excusedUserIds.value
    memberSearch.value = ''
    noShowSearch.value = ''
    signedOffSearch.value = ''
    excusedSearch.value = ''
    persistAarDraft(nextSignature)
  },
  { immediate: true }
)

watch(
  () => [
    reportDraft.value,
    JSON.stringify(attendanceDraft.value),
    JSON.stringify(noShowDraft.value),
    JSON.stringify(signedOffEarlyDraft.value),
    JSON.stringify(excusedDraft.value),
    memberSearch.value,
    noShowSearch.value,
    signedOffSearch.value,
    excusedSearch.value,
  ],
  () => {
    persistAarDraft()
  }
)

watch(
  () => [
    props.operation?.id,
    JSON.stringify(attendanceDraft.value),
    JSON.stringify(props.verifiedMembers ?? []),
    JSON.stringify(props.operation?.after_action_attendance ?? []),
  ],
  () => {
    emit('attendance-draft-change', attendanceDraft.value
      .map((userId) => attendanceUser(userId))
      .filter(Boolean))
  },
  { immediate: true }
)

function memberName(user) {
  return user?.rsi_handle ?? user?.discord_name ?? user?.name ?? `User #${user?.id ?? 'Unknown'}`
}

function memberAvatar(user) {
  return user?.discord_avatar ?? null
}

function memberInitial(user) {
  return String(memberName(user)).slice(0, 1).toUpperCase()
}

function attendanceUser(userId) {
  return signedUpUserLookup.value.get(Number(userId))
    ?? attendanceLookup.value.get(Number(userId))
    ?? (props.verifiedMembers ?? []).find(user => Number(user.id) === Number(userId))
    ?? null
}

function noShowUser(userId) {
  return signedUpUserLookup.value.get(Number(userId))
    ?? noShowLookup.value.get(Number(userId))
    ?? (props.verifiedMembers ?? []).find(user => Number(user.id) === Number(userId))
    ?? null
}

function signedOffEarlyUser(userId) {
  return signedUpUserLookup.value.get(Number(userId))
    ?? signedOffEarlyLookup.value.get(Number(userId))
    ?? (props.verifiedMembers ?? []).find(user => Number(user.id) === Number(userId))
    ?? null
}

function excusedUser(userId) {
  return signedUpUserLookup.value.get(Number(userId))
    ?? excusedLookup.value.get(Number(userId))
    ?? (props.verifiedMembers ?? []).find(user => Number(user.id) === Number(userId))
    ?? null
}

function addAttendance(userId) {
  const id = Number(userId)
  if (!Number.isFinite(id)) return
  if (attendanceDraft.value.includes(id)) return

  noShowDraft.value = noShowDraft.value.filter(existingId => Number(existingId) !== id)
  signedOffEarlyDraft.value = signedOffEarlyDraft.value.filter(existingId => Number(existingId) !== id)
  excusedDraft.value = excusedDraft.value.filter(existingId => Number(existingId) !== id)
  attendanceDraft.value = [...attendanceDraft.value, id]
  memberSearch.value = ''
}

function removeAttendance(userId) {
  attendanceDraft.value = attendanceDraft.value.filter(id => Number(id) !== Number(userId))
}

function addNoShow(userId) {
  const id = Number(userId)
  if (!Number.isFinite(id)) return
  if (noShowDraft.value.includes(id)) return

  attendanceDraft.value = attendanceDraft.value.filter(existingId => Number(existingId) !== id)
  signedOffEarlyDraft.value = signedOffEarlyDraft.value.filter(existingId => Number(existingId) !== id)
  excusedDraft.value = excusedDraft.value.filter(existingId => Number(existingId) !== id)
  noShowDraft.value = [...noShowDraft.value, id]
  noShowSearch.value = ''
}

function removeNoShow(userId) {
  noShowDraft.value = noShowDraft.value.filter(id => Number(id) !== Number(userId))
}

function addSignedOffEarly(userId) {
  const id = Number(userId)
  if (!Number.isFinite(id)) return
  if (signedOffEarlyDraft.value.includes(id)) return

  attendanceDraft.value = attendanceDraft.value.filter(existingId => Number(existingId) !== id)
  noShowDraft.value = noShowDraft.value.filter(existingId => Number(existingId) !== id)
  excusedDraft.value = excusedDraft.value.filter(existingId => Number(existingId) !== id)
  signedOffEarlyDraft.value = [...signedOffEarlyDraft.value, id]
  signedOffSearch.value = ''
}

function removeSignedOffEarly(userId) {
  signedOffEarlyDraft.value = signedOffEarlyDraft.value.filter(id => Number(id) !== Number(userId))
}

function addExcused(userId) {
  const id = Number(userId)
  if (!Number.isFinite(id)) return
  if (excusedDraft.value.includes(id)) return

  attendanceDraft.value = attendanceDraft.value.filter(existingId => Number(existingId) !== id)
  noShowDraft.value = noShowDraft.value.filter(existingId => Number(existingId) !== id)
  signedOffEarlyDraft.value = signedOffEarlyDraft.value.filter(existingId => Number(existingId) !== id)
  excusedDraft.value = [...excusedDraft.value, id]
  excusedSearch.value = ''
}

function removeExcused(userId) {
  excusedDraft.value = excusedDraft.value.filter(id => Number(id) !== Number(userId))
}

function moveNoShowToSignedOffEarly(userId) {
  removeNoShow(userId)
  addSignedOffEarly(userId)
}

function moveSignedOffEarlyToNoShow(userId) {
  removeSignedOffEarly(userId)
  addNoShow(userId)
}

function moveNoShowToExcused(userId) {
  removeNoShow(userId)
  addExcused(userId)
}

function moveExcusedToNoShow(userId) {
  removeExcused(userId)
  addNoShow(userId)
}

function saveAfterActionReport() {
  if (!props.operation?.id || saving.value || !props.canManage) return

  saving.value = true

  router.put(route('operations.aar.update', props.operation.id, Ziggy), {
    after_action_report: reportDraft.value,
    attendance_user_ids: attendanceDraft.value,
    no_show_user_ids: noShowDraft.value,
    signed_off_early_user_ids: signedOffEarlyDraft.value,
    excused_user_ids: excusedDraft.value,
  }, {
    preserveScroll: true,
    preserveState: true,
    only: props.reloadOnly,
    onError: (errors) => {
      const firstError = Object.values(errors ?? {})[0]
      const message = Array.isArray(firstError) ? firstError[0] : firstError

      window.hzNotifyError?.({
        message: message ?? 'Failed to update the After Action Report.',
      })
    },
    onFinish: () => {
      saving.value = false
    },
  })
}
</script>

<template>
  <section
    v-if="operation?.status === 'completed'"
    :class="compact
      ? 'hz-surface-welcome rounded-[1.25rem] border border-white/[0.055] p-4'
      : 'hz-surface-welcome rounded-[1.75rem] border border-white/[0.055] p-4 md:p-5'"
  >
    <div v-if="!compact" class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
      <div>
        <div class="text-xs font-bold uppercase tracking-[0.2em] text-text-muted">
          After Action
        </div>

        <h3 class="mt-1 text-xl font-black text-horizon-white">
          After Action Report
        </h3>

        <p class="mt-2 max-w-3xl text-sm text-text-secondary">
          Capture what happened, who actually attended, and the follow-up takeaways for this operation.
        </p>
      </div>

      <HorizonBadge variant="muted">
        {{ attendanceDraft.length }} attended · {{ noShowDraft.length }} no-show · {{ excusedDraft.length }} excused · {{ signedOffEarlyDraft.length }} signed off early
      </HorizonBadge>
    </div>

    <div :class="compact ? 'grid gap-4 xl:grid-cols-[minmax(0,1fr)_22rem]' : 'mt-5 grid gap-4 xl:grid-cols-[minmax(0,1fr)_22rem]'">
      <div class="space-y-4">
        <div class="hz-surface-welcome rounded-[1.25rem] border border-white/[0.055] p-4">
          <div class="flex items-center justify-between gap-3">
            <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
              Report
            </div>

            <div
              v-if="updatedAtLabel"
              class="text-[11px] font-semibold uppercase tracking-[0.16em] text-text-muted"
            >
              Updated {{ updatedAtLabel }}
            </div>
          </div>

          <HorizonInput
            v-if="canManage"
            v-model="reportDraft"
            type="textarea"
            rows="12"
            class="mt-3"
            placeholder="Write the outcome, lessons learned, logistics notes, and follow-up actions..."
          />

          <div
            v-else
            class="hz-surface-welcome mt-3 min-h-52 rounded-[1rem] border border-white/[0.055] p-4 text-sm leading-7 text-text-secondary"
          >
            <div v-if="operation?.after_action_report" class="whitespace-pre-line">
              {{ operation.after_action_report }}
            </div>

            <div v-else class="italic text-text-muted">
              No After Action Report has been written yet.
            </div>
          </div>
        </div>

        <div class="hz-surface-welcome rounded-[1.25rem] border border-white/[0.055] p-4">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
              Attendance
            </div>

            <div class="text-xs text-text-secondary">
              Signed up: {{ signedUpUsers.length }} · Final attendance: {{ attendanceDraft.length }} · No-show: {{ noShowDraft.length }} · Excused: {{ excusedDraft.length }} · Signed off early: {{ signedOffEarlyDraft.length }}
            </div>
          </div>

          <div class="mt-3 flex flex-wrap gap-2">
            <div
              v-for="userId in attendanceDraft"
              :key="userId"
              class="hz-surface-welcome flex items-center gap-2 rounded-full border border-white/[0.055] px-2.5 py-1.5"
            >
              <img
                v-if="memberAvatar(attendanceUser(userId))"
                :src="memberAvatar(attendanceUser(userId))"
                alt=""
                class="h-7 w-7 rounded-full object-cover"
                loading="lazy"
              />

              <div
                v-else
                class="hz-shell-avatar flex h-7 w-7 items-center justify-center rounded-full text-[11px] font-black"
              >
                {{ memberInitial(attendanceUser(userId)) }}
              </div>

              <div class="min-w-0">
                <div class="truncate text-sm font-semibold text-horizon-white">
                  {{ memberName(attendanceUser(userId)) }}
                </div>
                <div class="text-[11px] uppercase tracking-[0.14em] text-text-muted">
                  {{ signedUpUserIds.has(Number(userId)) ? 'Signed Up' : 'Added Later' }}
                </div>
              </div>

              <button
                v-if="canEditAttendance"
                type="button"
                class="rounded-full border border-white/[0.055] px-2 py-0.5 text-[11px] font-bold uppercase tracking-[0.14em] text-text-secondary hover:text-horizon-white"
                @click="removeAttendance(userId)"
              >
                Remove
              </button>
            </div>
          </div>

          <div
            v-if="!attendanceDraft.length"
            class="hz-surface-welcome hz-divider-subtle mt-3 rounded-[1rem] border border-dashed border-white/15 px-4 py-5 text-sm text-text-secondary"
          >
            No final attendance has been recorded yet.
          </div>

          <div
            v-if="canEditAttendance"
            class="hz-surface-welcome mt-4 space-y-3 rounded-[1rem] border border-white/[0.055] p-4"
          >
            <div class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">
              Add Verified Member
            </div>

            <HorizonInput
              v-model="memberSearch"
              placeholder="Search verified members by handle, Discord, name, or ID..."
            />

            <div class="grid gap-2">
              <button
                v-for="member in filteredVerifiedMembers"
                :key="member.id"
                type="button"
                class="hz-surface-welcome hz-shell-hover flex items-center justify-between gap-3 rounded-[1rem] border border-white/[0.055] px-3 py-2 text-left transition"
                @click="addAttendance(member.id)"
              >
                <div class="flex min-w-0 items-center gap-3">
                  <img
                    v-if="memberAvatar(member)"
                    :src="memberAvatar(member)"
                    alt=""
                    class="h-8 w-8 rounded-full object-cover"
                    loading="lazy"
                  />

                  <div
                    v-else
                    class="hz-shell-avatar flex h-8 w-8 items-center justify-center rounded-full text-xs font-black"
                  >
                    {{ memberInitial(member) }}
                  </div>

                  <div class="min-w-0">
                    <div class="truncate text-sm font-semibold text-horizon-white">
                      {{ memberName(member) }}
                    </div>
                    <div class="text-xs text-text-muted">
                      Member #{{ member.id }}
                    </div>
                  </div>
                </div>

                <span class="text-xs font-bold uppercase tracking-[0.14em] text-text-secondary">
                  Add
                </span>
              </button>
            </div>

            <div
              v-if="memberSearch && !filteredVerifiedMembers.length"
              class="hz-divider-subtle rounded-[1rem] border border-dashed px-4 py-3 text-sm text-text-secondary"
            >
              No verified members matched that search.
            </div>
          </div>

          <div
            v-else-if="attendanceLocked"
            class="mt-4 rounded-[1rem] border border-emerald-300/20 bg-emerald-300/8 px-4 py-3 text-sm text-text-secondary"
          >
            Final attendance is locked because the operation settlement has already been finalized.
          </div>
        </div>

        <div class="hz-surface-welcome rounded-[1.25rem] border border-white/[0.055] p-4">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
              No Show
            </div>

            <div class="text-xs text-text-secondary">
              Signed up but absent: {{ noShowDraft.length }}
            </div>
          </div>

          <div class="mt-3 flex flex-wrap gap-2">
            <div
              v-for="userId in noShowDraft"
              :key="`no-show-${userId}`"
              class="hz-surface-welcome flex items-center gap-2 rounded-full border border-white/[0.055] px-2.5 py-1.5"
            >
              <img
                v-if="memberAvatar(noShowUser(userId))"
                :src="memberAvatar(noShowUser(userId))"
                alt=""
                class="h-7 w-7 rounded-full object-cover"
                loading="lazy"
              />

              <div
                v-else
                class="hz-shell-avatar flex h-7 w-7 items-center justify-center rounded-full text-[11px] font-black"
              >
                {{ memberInitial(noShowUser(userId)) }}
              </div>

              <div class="min-w-0">
                <div class="truncate text-sm font-semibold text-horizon-white">
                  {{ memberName(noShowUser(userId)) }}
                </div>
                <div class="text-[11px] uppercase tracking-[0.14em] text-text-muted">
                  Signed Up · No Show
                </div>
              </div>

              <button
                v-if="canEditAttendance"
                type="button"
                class="rounded-full border border-white/[0.055] px-2 py-0.5 text-[11px] font-bold uppercase tracking-[0.14em] text-text-secondary hover:text-horizon-white"
                @click="moveNoShowToExcused(userId)"
              >
                Move To Excused
              </button>

              <button
                v-if="canEditAttendance"
                type="button"
                class="rounded-full border border-white/[0.055] px-2 py-0.5 text-[11px] font-bold uppercase tracking-[0.14em] text-text-secondary hover:text-horizon-white"
                @click="moveNoShowToSignedOffEarly(userId)"
              >
                Move To Signed Off
              </button>

              <button
                v-if="canEditAttendance"
                type="button"
                class="rounded-full border border-white/[0.055] px-2 py-0.5 text-[11px] font-bold uppercase tracking-[0.14em] text-text-secondary hover:text-horizon-white"
                @click="removeNoShow(userId)"
              >
                Remove
              </button>
            </div>
          </div>

          <div
            v-if="!noShowDraft.length"
            class="hz-surface-welcome hz-divider-subtle mt-3 rounded-[1rem] border border-dashed border-white/15 px-4 py-5 text-sm text-text-secondary"
          >
            No no-shows have been recorded yet.
          </div>

          <div
            v-if="canEditAttendance"
            class="hz-surface-welcome mt-4 space-y-3 rounded-[1rem] border border-white/[0.055] p-4"
          >
            <div class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">
              Add No Show
            </div>

            <HorizonInput
              v-model="noShowSearch"
              placeholder="Search signed-up members by handle, Discord, name, or ID..."
            />

            <div class="grid gap-2">
              <button
                v-for="member in filteredNoShowMembers"
                :key="`no-show-member-${member.id}`"
                type="button"
                class="hz-surface-welcome hz-shell-hover flex items-center justify-between gap-3 rounded-[1rem] border border-white/[0.055] px-3 py-2 text-left transition"
                @click="addNoShow(member.id)"
              >
                <div class="flex min-w-0 items-center gap-3">
                  <img
                    v-if="memberAvatar(member)"
                    :src="memberAvatar(member)"
                    alt=""
                    class="h-8 w-8 rounded-full object-cover"
                    loading="lazy"
                  />

                  <div
                    v-else
                    class="hz-shell-avatar flex h-8 w-8 items-center justify-center rounded-full text-xs font-black"
                  >
                    {{ memberInitial(member) }}
                  </div>

                  <div class="min-w-0">
                    <div class="truncate text-sm font-semibold text-horizon-white">
                      {{ memberName(member) }}
                    </div>
                    <div class="text-xs text-text-muted">
                      Member #{{ member.id }}
                    </div>
                  </div>
                </div>

                <span class="text-xs font-bold uppercase tracking-[0.14em] text-text-secondary">
                  Add
                </span>
              </button>
            </div>

            <div
              v-if="noShowSearch && !filteredNoShowMembers.length"
              class="hz-divider-subtle rounded-[1rem] border border-dashed px-4 py-3 text-sm text-text-secondary"
            >
              No signed-up members matched that search.
            </div>
          </div>

          <div
            v-else-if="attendanceLocked"
            class="mt-4 rounded-[1rem] border border-emerald-300/20 bg-emerald-300/8 px-4 py-3 text-sm text-text-secondary"
          >
            No-show tracking is locked until the settlement is reopened.
          </div>
        </div>

        <div class="hz-surface-welcome rounded-[1.25rem] border border-white/[0.055] p-4">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
              Excused
            </div>

            <div class="text-xs text-text-secondary">
              Signed up but excused: {{ excusedDraft.length }}
            </div>
          </div>

          <div class="mt-3 flex flex-wrap gap-2">
            <div
              v-for="userId in excusedDraft"
              :key="`excused-${userId}`"
              class="hz-surface-welcome flex items-center gap-2 rounded-full border border-white/[0.055] px-2.5 py-1.5"
            >
              <img
                v-if="memberAvatar(excusedUser(userId))"
                :src="memberAvatar(excusedUser(userId))"
                alt=""
                class="h-7 w-7 rounded-full object-cover"
                loading="lazy"
              />

              <div
                v-else
                class="hz-shell-avatar flex h-7 w-7 items-center justify-center rounded-full text-[11px] font-black"
              >
                {{ memberInitial(excusedUser(userId)) }}
              </div>

              <div class="min-w-0">
                <div class="truncate text-sm font-semibold text-horizon-white">
                  {{ memberName(excusedUser(userId)) }}
                </div>
                <div class="text-[11px] uppercase tracking-[0.14em] text-text-muted">
                  Signed Up · Excused
                </div>
              </div>

              <button
                v-if="canEditAttendance"
                type="button"
                class="rounded-full border border-white/[0.055] px-2 py-0.5 text-[11px] font-bold uppercase tracking-[0.14em] text-text-secondary hover:text-horizon-white"
                @click="moveExcusedToNoShow(userId)"
              >
                Move To No Show
              </button>

              <button
                v-if="canEditAttendance"
                type="button"
                class="rounded-full border border-white/[0.055] px-2 py-0.5 text-[11px] font-bold uppercase tracking-[0.14em] text-text-secondary hover:text-horizon-white"
                @click="removeExcused(userId)"
              >
                Remove
              </button>
            </div>
          </div>

          <div
            v-if="!excusedDraft.length"
            class="hz-surface-welcome hz-divider-subtle mt-3 rounded-[1rem] border border-dashed border-white/15 px-4 py-5 text-sm text-text-secondary"
          >
            No excused members have been recorded yet.
          </div>

          <div
            v-if="canEditAttendance"
            class="hz-surface-welcome mt-4 space-y-3 rounded-[1rem] border border-white/[0.055] p-4"
          >
            <div class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">
              Add Excused
            </div>

            <HorizonInput
              v-model="excusedSearch"
              placeholder="Search signed-up members by handle, Discord, name, or ID..."
            />

            <div class="grid gap-2">
              <button
                v-for="member in filteredExcusedMembers"
                :key="`excused-member-${member.id}`"
                type="button"
                class="hz-surface-welcome hz-shell-hover flex items-center justify-between gap-3 rounded-[1rem] border border-white/[0.055] px-3 py-2 text-left transition"
                @click="addExcused(member.id)"
              >
                <div class="flex min-w-0 items-center gap-3">
                  <img
                    v-if="memberAvatar(member)"
                    :src="memberAvatar(member)"
                    alt=""
                    class="h-8 w-8 rounded-full object-cover"
                    loading="lazy"
                  />

                  <div
                    v-else
                    class="hz-shell-avatar flex h-8 w-8 items-center justify-center rounded-full text-xs font-black"
                  >
                    {{ memberInitial(member) }}
                  </div>

                  <div class="min-w-0">
                    <div class="truncate text-sm font-semibold text-horizon-white">
                      {{ memberName(member) }}
                    </div>
                    <div class="text-xs text-text-muted">
                      Member #{{ member.id }}
                    </div>
                  </div>
                </div>

                <span class="text-xs font-bold uppercase tracking-[0.14em] text-text-secondary">
                  Add
                </span>
              </button>
            </div>

            <div
              v-if="excusedSearch && !filteredExcusedMembers.length"
              class="hz-divider-subtle rounded-[1rem] border border-dashed px-4 py-3 text-sm text-text-secondary"
            >
              No signed-up members matched that search.
            </div>
          </div>

          <div
            v-else-if="attendanceLocked"
            class="mt-4 rounded-[1rem] border border-emerald-300/20 bg-emerald-300/8 px-4 py-3 text-sm text-text-secondary"
          >
            Excused tracking is locked until the settlement is reopened.
          </div>
        </div>

        <div class="hz-surface-welcome rounded-[1.25rem] border border-white/[0.055] p-4">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
              Signed Off Early
            </div>

            <div class="text-xs text-text-secondary">
              Signed up but backed out before start: {{ signedOffEarlyDraft.length }}
            </div>
          </div>

          <div class="mt-3 flex flex-wrap gap-2">
            <div
              v-for="userId in signedOffEarlyDraft"
              :key="`signed-off-${userId}`"
              class="hz-surface-welcome flex items-center gap-2 rounded-full border border-white/[0.055] px-2.5 py-1.5"
            >
              <img
                v-if="memberAvatar(signedOffEarlyUser(userId))"
                :src="memberAvatar(signedOffEarlyUser(userId))"
                alt=""
                class="h-7 w-7 rounded-full object-cover"
                loading="lazy"
              />

              <div
                v-else
                class="hz-shell-avatar flex h-7 w-7 items-center justify-center rounded-full text-[11px] font-black"
              >
                {{ memberInitial(signedOffEarlyUser(userId)) }}
              </div>

              <div class="min-w-0">
                <div class="truncate text-sm font-semibold text-horizon-white">
                  {{ memberName(signedOffEarlyUser(userId)) }}
                </div>
                <div class="text-[11px] uppercase tracking-[0.14em] text-text-muted">
                  Signed Up · Signed Off Early
                </div>
              </div>

              <button
                v-if="canEditAttendance"
                type="button"
                class="rounded-full border border-white/[0.055] px-2 py-0.5 text-[11px] font-bold uppercase tracking-[0.14em] text-text-secondary hover:text-horizon-white"
                @click="moveSignedOffEarlyToNoShow(userId)"
              >
                Move To No Show
              </button>

              <button
                v-if="canEditAttendance"
                type="button"
                class="rounded-full border border-white/[0.055] px-2 py-0.5 text-[11px] font-bold uppercase tracking-[0.14em] text-text-secondary hover:text-horizon-white"
                @click="removeSignedOffEarly(userId)"
              >
                Remove
              </button>
            </div>
          </div>

          <div
            v-if="!signedOffEarlyDraft.length"
            class="hz-surface-welcome hz-divider-subtle mt-3 rounded-[1rem] border border-dashed border-white/15 px-4 py-5 text-sm text-text-secondary"
          >
            No signed-off-early members have been recorded yet.
          </div>

          <div
            v-if="canEditAttendance"
            class="hz-surface-welcome mt-4 space-y-3 rounded-[1rem] border border-white/[0.055] p-4"
          >
            <div class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">
              Add Signed Off Early
            </div>

            <HorizonInput
              v-model="signedOffSearch"
              placeholder="Search signed-up members by handle, Discord, name, or ID..."
            />

            <div class="grid gap-2">
              <button
                v-for="member in filteredSignedOffMembers"
                :key="`signed-off-member-${member.id}`"
                type="button"
                class="hz-surface-welcome hz-shell-hover flex items-center justify-between gap-3 rounded-[1rem] border border-white/[0.055] px-3 py-2 text-left transition"
                @click="addSignedOffEarly(member.id)"
              >
                <div class="flex min-w-0 items-center gap-3">
                  <img
                    v-if="memberAvatar(member)"
                    :src="memberAvatar(member)"
                    alt=""
                    class="h-8 w-8 rounded-full object-cover"
                    loading="lazy"
                  />

                  <div
                    v-else
                    class="hz-shell-avatar flex h-8 w-8 items-center justify-center rounded-full text-xs font-black"
                  >
                    {{ memberInitial(member) }}
                  </div>

                  <div class="min-w-0">
                    <div class="truncate text-sm font-semibold text-horizon-white">
                      {{ memberName(member) }}
                    </div>
                    <div class="text-xs text-text-muted">
                      Member #{{ member.id }}
                    </div>
                  </div>
                </div>

                <span class="text-xs font-bold uppercase tracking-[0.14em] text-text-secondary">
                  Add
                </span>
              </button>
            </div>

            <div
              v-if="signedOffSearch && !filteredSignedOffMembers.length"
              class="hz-divider-subtle rounded-[1rem] border border-dashed px-4 py-3 text-sm text-text-secondary"
            >
              No signed-up members matched that search.
            </div>
          </div>

          <div
            v-else-if="attendanceLocked"
            class="mt-4 rounded-[1rem] border border-emerald-300/20 bg-emerald-300/8 px-4 py-3 text-sm text-text-secondary"
          >
            Signed-off-early tracking is locked until the settlement is reopened.
          </div>
        </div>
      </div>

      <aside class="space-y-4">
        <div class="hz-surface-welcome rounded-[1.25rem] border border-white/[0.055] p-4">
          <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
            Roster Snapshot
          </div>

          <div class="mt-3 grid gap-3">
            <HorizonFormBlock label="Signed Up" label-class="text-[11px] tracking-[0.14em]">
              <div class="text-2xl font-black text-horizon-white">
                {{ signedUpUsers.length }}
              </div>
            </HorizonFormBlock>

            <HorizonFormBlock label="Final Attendance" label-class="text-[11px] tracking-[0.14em]">
              <div class="text-2xl font-black text-horizon-white">
                {{ attendanceDraft.length }}
              </div>
            </HorizonFormBlock>

            <HorizonFormBlock label="No Show" panel-class="border-red-300/15" label-class="text-[11px] tracking-[0.14em]">
              <div class="text-2xl font-black text-horizon-white">
                {{ noShowDraft.length }}
              </div>
            </HorizonFormBlock>

            <HorizonFormBlock label="Excused" panel-class="border-amber-300/15" label-class="text-[11px] tracking-[0.14em]">
              <div class="text-2xl font-black text-horizon-white">
                {{ excusedDraft.length }}
              </div>
            </HorizonFormBlock>

            <HorizonFormBlock label="Signed Off Early" panel-class="border-amber-300/15" label-class="text-[11px] tracking-[0.14em]">
              <div class="text-2xl font-black text-horizon-white">
                {{ signedOffEarlyDraft.length }}
              </div>
            </HorizonFormBlock>
          </div>
        </div>

        <div
          v-if="canManage"
          class="hz-surface-welcome rounded-[1.25rem] border border-white/[0.055] p-4"
        >
          <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
            Actions
          </div>

          <p class="mt-2 text-sm text-text-secondary">
            Save the written report, attendance, no-show, excused, and signed-off-early rosters together.
          </p>

          <HorizonButton
            variant="primary"
            size="sm"
            class="mt-4 w-full"
            :disabled="saving || !hasChanges"
            @click="saveAfterActionReport"
          >
            {{ saving ? 'Saving…' : 'Save AAR' }}
          </HorizonButton>
        </div>
      </aside>
    </div>
  </section>
</template>
