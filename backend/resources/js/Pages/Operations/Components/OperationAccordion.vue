<script setup>
import { computed } from 'vue'

import ProgressPill from '@/Components/ProgressPill.vue'
import { getHighestOrgRoleSlug, getOrgRoleColor } from '@/roleColors'

const emit = defineEmits(['view'])

const props = defineProps({
  operation: {
    type: Object,
    required: true,
  },
})

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
        chip: 'border-white/[0.055] bg-white/[0.042]',
      }

    case 'wing_training':
      return {
        border: 'border-white/[0.055]',
        glow: 'rgba(67,56,202,0.16)',
        chip: 'border-white/[0.055] bg-white/[0.042]',
      }

    case 'roleplay':
      return {
        border: 'border-white/[0.055]',
        glow: 'rgba(192,38,211,0.16)',
        chip: 'border-white/[0.055] bg-white/[0.042]',
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
        chip: 'border-white/[0.055] bg-white/[0.042]',
      }
  }
})

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
    class="group relative overflow-hidden rounded-[1.75rem] border bg-[rgba(21,25,42,0.64)] transition duration-200 hover:-translate-y-0.5 hover:border-white/[0.12] hover:bg-[rgba(27,32,53,0.68)] hover:shadow-[0_10px_28px_rgb(0_0_0/0.22)]"
    :class="operationAccentClass.border"
  >
    <div class="pointer-events-none absolute inset-0 opacity-0 transition duration-200 group-hover:opacity-100">
      <div class="absolute inset-x-8 top-0 h-px bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-blue)] to-transparent"></div>
      <div class="absolute inset-x-10 bottom-0 h-px bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-magenta)] to-transparent"></div>
    </div>

    <button
      type="button"
      class="relative block w-full p-5 text-left md:p-6 cursor-pointer group"
      @click="viewOperation"
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
                : 'border-transparent bg-white/[0.042] shadow-none text-text-secondary'"
            >
              {{ timingBadgeLabel }}
            </span>

            <span class="rounded-full border-transparent bg-white/[0.042] px-3 py-1 text-[11px] font-bold uppercase tracking-[0.18em] text-text-muted shadow-none">
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
                class="h-5 w-5 rounded-full border-transparent object-contain"
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
                class="h-5 w-5 rounded-full border-transparent object-cover"
                loading="lazy"
              />

              <span
                v-else
                class="flex h-5 w-5 items-center justify-center rounded-full border-transparent bg-white/[0.042] text-[10px] font-black text-horizon-white shadow-none"
              >
                {{ creatorInitial }}
              </span>

              <span :style="creatorColor ? { color: creatorColor } : undefined">
                {{ creatorName }}
              </span>
            </span>
          </div>
        </div>

        <div class="flex shrink-0 items-center gap-3">
          <div class="hidden text-right sm:block">
            <div class="text-xs font-bold uppercase tracking-[0.24em] text-text-muted">
              {{ joinedCount }} joined
            </div>
          </div>

          <div class="rounded-xl border border-white/[0.055] bg-white/[0.024] px-4 py-2 text-sm font-semibold text-text-secondary transition group-hover:border-white/[0.12] group-hover:bg-white/[0.042] group-hover:text-horizon-white">
            View
          </div>
        </div>
      </div>
    </button>
  </article>
</template>







