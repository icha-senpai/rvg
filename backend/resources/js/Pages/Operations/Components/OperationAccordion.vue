<script setup>
import { computed, ref } from 'vue'

import HorizonButton from '@/Components/HorizonButton.vue'
import ProgressPill from '@/Components/ProgressPill.vue'
import { getHighestOrgRoleSlug, getOrgRoleColor } from '@/roleColors'

const emit = defineEmits(['view'])

const props = defineProps({
  operation: {
    type: Object,
    required: true,
  },
})

const open = ref(false)

const operationKind = computed(() => {
  return props.operation?.operation_type ?? props.operation?.operation_kind ?? 'operation'
})

const displayTitle = computed(() => {
  const title = props.operation?.title ?? 'Untitled Operation'
  const prefix = operationTitlePrefix(operationKind.value)

  return prefix ? `${prefix}: ${title}` : title
})

const statusLabel = computed(() => {
  return formatTitle(props.operation?.status ?? 'published')
})

const isJoinedByMe = computed(() => {
  const op = props.operation
  if (!op) return false

  if (typeof op.joined_by_me === 'boolean') return op.joined_by_me
  return Number(op.joined_by_me ?? 0) > 0
})

const joinedCount = computed(() => {
  const op = props.operation
  if (!op) return 0

  if (typeof op.participants_count === 'number') return op.participants_count
  if (Array.isArray(op.participants)) return op.participants.length

  return 0
})

const creatorName = computed(() => {
  return props.operation?.creator?.rsi_handle
    ?? props.operation?.creator?.display_name
    ?? props.operation?.creator?.name
    ?? 'TBD'
})

const creatorAvatar = computed(() => {
  return props.operation?.creator?.discord_avatar
    ?? props.operation?.creator?.avatar
    ?? null
})

const creatorInitial = computed(() => {
  return String(creatorName.value ?? 'M').slice(0, 1).toUpperCase()
})

const creatorColor = computed(() => {
  const creator = props.operation?.creator ?? null
  const slug = getHighestOrgRoleSlug(creator?.roles, creator?.rank)

  return getOrgRoleColor(slug)
})

const branchLogoSrc = computed(() => {
  const branch = props.operation?.branch ?? ''

  switch (branch) {
    case 'defence': return '/images/Horizon_Defence_Logo.png'
    case 'frontiers': return '/images/Horizon_Frontiers_Logo.png'
    case 'industries': return '/images/Horizon_Industries_logo.png'
    case 'lifelines': return '/images/Horizon_Lifeline_logo.png'
    default: return null
  }
})

const branchLogoAlt = computed(() => {
  const branch = props.operation?.branch ?? ''
  return branch ? `${branchLabel(branch)} branch logo` : ''
})

const startsAt = computed(() => toDate(props.operation?.starts_at))
const rsvpDeadline = computed(() => toDate(props.operation?.rsvp_deadline))

const isUpcoming = computed(() => {
  if (!startsAt.value) return false
  return startsAt.value.getTime() >= Date.now()
})

const timingBadgeLabel = computed(() => {
  if (!startsAt.value) return 'Unscheduled'
  return isUpcoming.value ? 'Upcoming' : 'Past'
})

const collapsedMetaParts = computed(() => {
  const op = props.operation
  if (!op) return []

  const parts = []

  parts.push(formatLocal(op.starts_at))
  parts.push(`${joinedCount.value} joined`)

  if (op.visibility) {
    parts.push(formatTitle(op.visibility))
  }

  return parts
    .filter(part => part && String(part).trim() !== '')
    .map(part => String(part))
})

const operationAccentClass = computed(() => {
  switch (operationKind.value) {
    case 'squadron_training':
      return {
        border: 'border-[color:var(--horizon-sunset-blue)]/35',
        glow: 'rgba(30,64,175,0.16)',
        chip: 'border-[color:var(--horizon-sunset-blue)]/30 bg-[color:var(--horizon-sunset-blue)]/10',
      }

    case 'wing_training':
      return {
        border: 'border-[color:var(--horizon-sunset-indigo)]/35',
        glow: 'rgba(67,56,202,0.16)',
        chip: 'border-[color:var(--horizon-sunset-indigo)]/30 bg-[color:var(--horizon-sunset-indigo)]/10',
      }

    case 'roleplay':
      return {
        border: 'border-[color:var(--horizon-sunset-magenta)]/35',
        glow: 'rgba(192,38,211,0.16)',
        chip: 'border-[color:var(--horizon-sunset-magenta)]/30 bg-[color:var(--horizon-sunset-magenta)]/10',
      }

    case 'meeting':
      return {
        border: 'border-white/15',
        glow: 'rgba(255,255,255,0.08)',
        chip: 'border-white/15 bg-white/[0.04]',
      }

    case 'event':
      return {
        border: 'border-[color:var(--horizon-sunset-pink)]/35',
        glow: 'rgba(255,61,129,0.16)',
        chip: 'border-[color:var(--horizon-sunset-pink)]/30 bg-[color:var(--horizon-sunset-pink)]/10',
      }

    default:
      return {
        border: 'border-[color:var(--horizon-sunset-blue)]/35',
        glow: 'rgba(30,64,175,0.16)',
        chip: 'border-[color:var(--horizon-sunset-blue)]/30 bg-[color:var(--horizon-sunset-blue)]/10',
      }
  }
})

function toggleOpen() {
  open.value = !open.value
}

function viewOperation() {
  emit('view', props.operation)
}

function operationTitlePrefix(kind) {
  switch (kind) {
    case 'squadron_training': return 'Squadron Training'
    case 'wing_training': return 'Wing Training'
    default: return ''
  }
}

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

function branchLabel(branch) {
  switch (branch) {
    case 'industries': return 'Industries'
    case 'defence': return 'Defence'
    case 'frontiers': return 'Frontiers'
    case 'lifelines': return 'Lifelines'
    default: return formatTitle(branch)
  }
}

function formatTitle(value) {
  const raw = String(value ?? '').trim()
  if (!raw) return ''

  return raw
    .replace(/[_-]+/g, ' ')
    .split(' ')
    .map(word => word ? word.charAt(0).toUpperCase() + word.slice(1) : '')
    .join(' ')
}

function formatUTC(dt) {
  if (!dt) return 'TBD'

  const d = toDate(dt)
  if (!d) return 'TBD'

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
  if (!d) return 'TBD'

  const date = new Intl.DateTimeFormat('en-GB', {
    weekday: 'short',
    day: 'numeric',
    month: 'short',
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
</script>

<template>
  <article
    class="group relative overflow-hidden rounded-[1.75rem] border bg-[linear-gradient(135deg,rgba(30,64,175,0.10),var(--horizon-void-700)_42%,var(--horizon-void-900))] transition duration-200 hover:-translate-y-0.5 hover:border-[color:var(--horizon-sunset-magenta)]/40"
    :class="operationAccentClass.border"
    :style="{ boxShadow: `0 0 32px ${operationAccentClass.glow}` }"
  >
    <div class="pointer-events-none absolute inset-0 opacity-0 transition duration-200 group-hover:opacity-100">
      <div class="absolute inset-x-8 top-0 h-px bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-blue)] to-transparent"></div>
      <div class="absolute inset-x-10 bottom-0 h-px bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-magenta)] to-transparent"></div>
    </div>

    <button
      type="button"
      class="relative block w-full p-5 text-left md:p-6"
      @click="toggleOpen"
    >
      <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
        <div class="min-w-0 flex-1">
          <div class="flex flex-wrap items-center gap-2">
            <span
              class="rounded-full border px-3 py-1 text-[11px] font-bold uppercase tracking-[0.18em] text-[color:var(--horizon-text-primary)]"
              :class="operationAccentClass.chip"
            >
              {{ operationKindLabel(operationKind) }}
            </span>

            <span
              class="rounded-full border px-3 py-1 text-[11px] font-bold uppercase tracking-[0.18em]"
              :class="isUpcoming
                ? 'border-emerald-300/25 bg-emerald-300/10 text-emerald-100'
                : 'border-white/10 bg-white/[0.04] text-text-secondary'"
            >
              {{ timingBadgeLabel }}
            </span>

            <span class="rounded-full border border-white/10 bg-white/[0.035] px-3 py-1 text-[11px] font-bold uppercase tracking-[0.18em] text-text-muted">
              {{ statusLabel }}
            </span>

            <ProgressPill v-if="isJoinedByMe" variant="green">
              Joined
            </ProgressPill>
          </div>

          <h3 class="mt-4 text-2xl font-black tracking-tight text-horizon-white md:text-3xl">
            {{ displayTitle }}
          </h3>

          <p
            v-if="operation.description"
            class="mt-2 line-clamp-2 max-w-3xl text-sm leading-6 text-text-secondary"
          >
            {{ operation.description }}
          </p>

          <div class="mt-4 flex flex-wrap items-center gap-x-3 gap-y-2 text-sm text-text-secondary">
            <span
              v-if="operation.branch"
              class="inline-flex items-center gap-2"
            >
              <img
                v-if="branchLogoSrc"
                :src="branchLogoSrc"
                :alt="branchLogoAlt"
                class="h-5 w-5 rounded-full border border-white/10 object-contain"
                loading="lazy"
              />
              <span>{{ branchLabel(operation.branch) }}</span>
            </span>

            <span
              v-if="operation.branch && collapsedMetaParts.length"
              class="text-text-muted"
            >
              •
            </span>

            <template
              v-for="(part, idx) in collapsedMetaParts"
              :key="`${operation?.id ?? 'op'}_meta_${idx}`"
            >
              <span>{{ part }}</span>
              <span
                v-if="idx < collapsedMetaParts.length - 1"
                class="text-text-muted"
              >
                •
              </span>
            </template>

            <span
              v-if="collapsedMetaParts.length"
              class="text-text-muted"
            >
              •
            </span>

            <span class="inline-flex items-center gap-2">
              <img
                v-if="creatorAvatar"
                :src="creatorAvatar"
                alt=""
                class="h-5 w-5 rounded-full border border-white/10 object-cover"
                loading="lazy"
              />

              <span
                v-else
                class="flex h-5 w-5 items-center justify-center rounded-full border border-white/10 bg-white/[0.04] text-[10px] font-black text-horizon-white"
              >
                {{ creatorInitial }}
              </span>

              <span :style="creatorColor ? { color: creatorColor } : undefined">
                {{ creatorName }}
              </span>
            </span>
          </div>
        </div>

        <div class="flex shrink-0 items-center justify-between gap-3 lg:justify-end">
          <div class="hidden rounded-2xl border border-white/10 bg-white/[0.035] px-4 py-3 text-right sm:block">
            <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
              Participants
            </div>
            <div class="mt-1 text-2xl font-black text-horizon-white">
              {{ joinedCount }}
            </div>
          </div>

          <div class="flex h-11 w-11 items-center justify-center rounded-xl border border-white/10 bg-white/[0.035] text-text-secondary transition group-hover:text-horizon-white">
            <svg
              class="h-6 w-6 transition-transform duration-200"
              :class="open ? 'rotate-180' : 'rotate-0'"
              viewBox="0 0 20 20"
              fill="currentColor"
              aria-hidden="true"
            >
              <path
                fill-rule="evenodd"
                d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z"
                clip-rule="evenodd"
              />
            </svg>
          </div>
        </div>
      </div>
    </button>

    <div
      v-if="open"
      class="relative border-t border-white/10 px-5 pb-5 md:px-6 md:pb-6"
    >
      <div class="grid gap-4 pt-5 lg:grid-cols-[minmax(0,1fr)_18rem]">
        <div class="space-y-4">
          <section class="rounded-2xl border border-white/10 bg-white/[0.025] p-4">
            <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
              Mission Briefing
            </div>

            <p
              v-if="operation.description"
              class="mt-2 whitespace-pre-line text-sm leading-7 text-text-secondary"
            >
              {{ operation.description }}
            </p>

            <p
              v-else
              class="mt-2 text-sm text-text-secondary"
            >
              No briefing has been posted for this operation yet.
            </p>
          </section>

          <section class="grid gap-4 md:grid-cols-2">
            <div class="rounded-2xl border border-[color:var(--horizon-sunset-blue)]/20 bg-[color:var(--horizon-sunset-blue)]/10 p-4">
              <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
                Start Time
              </div>

              <div class="mt-2 text-sm font-semibold text-horizon-white">
                {{ formatUTC(operation.starts_at) }}
              </div>

              <div class="mt-1 text-xs text-text-secondary">
                {{ formatLocal(operation.starts_at) }} local
              </div>
            </div>

            <div class="rounded-2xl border border-[color:var(--horizon-sunset-magenta)]/20 bg-[color:var(--horizon-sunset-magenta)]/10 p-4">
              <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
                Sign Up Deadline
              </div>

              <template v-if="rsvpDeadline">
                <div class="mt-2 text-sm font-semibold text-horizon-white">
                  {{ formatUTC(operation.rsvp_deadline) }}
                </div>

                <div class="mt-1 text-xs text-text-secondary">
                  {{ formatLocal(operation.rsvp_deadline) }} local
                </div>
              </template>

              <div
                v-else
                class="mt-2 text-sm text-text-secondary"
              >
                No sign up deadline set.
              </div>
            </div>
          </section>
        </div>

        <aside class="space-y-4">
          <section class="rounded-2xl border border-white/10 bg-white/[0.025] p-4">
            <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
              Mission Tags
            </div>

            <div class="mt-3 flex flex-wrap gap-2">
              <span class="rounded-full border border-white/10 bg-white/[0.035] px-3 py-1 text-xs font-semibold text-text-secondary">
                {{ formatTitle(operation.visibility ?? 'open') }}
              </span>

              <span class="rounded-full border border-white/10 bg-white/[0.035] px-3 py-1 text-xs font-semibold text-text-secondary">
                {{ formatTitle(operation.operation_strictness ?? 'normal') }} Comms
              </span>

              <span
                v-if="operation.gameplay_type || operation.type"
                class="rounded-full border border-white/10 bg-white/[0.035] px-3 py-1 text-xs font-semibold text-text-secondary"
              >
                {{ formatTitle(operation.gameplay_type ?? operation.type) }}
              </span>
            </div>
          </section>

          <section class="rounded-2xl border border-white/10 bg-white/[0.025] p-4">
            <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
              Creator
            </div>

            <div class="mt-3 flex items-center gap-3">
              <img
                v-if="creatorAvatar"
                :src="creatorAvatar"
                alt=""
                class="h-10 w-10 rounded-xl border border-white/10 object-cover"
                loading="lazy"
              />

              <span
                v-else
                class="flex h-10 w-10 items-center justify-center rounded-xl border border-white/10 bg-white/[0.04] text-sm font-black text-horizon-white"
              >
                {{ creatorInitial }}
              </span>

              <div class="min-w-0">
                <div
                  class="truncate font-semibold"
                  :style="creatorColor ? { color: creatorColor } : undefined"
                >
                  {{ creatorName }}
                </div>

                <div class="text-xs uppercase tracking-wide text-text-muted">
                  Operation Creator
                </div>
              </div>
            </div>
          </section>

          <HorizonButton
            variant="primary"
            size="sm"
            class="w-full"
            @click.stop="viewOperation"
          >
            View Full Operation
          </HorizonButton>
        </aside>
      </div>
    </div>
  </article>
</template>