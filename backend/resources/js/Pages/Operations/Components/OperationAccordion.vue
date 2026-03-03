<template>
<div class="max-w-6xl mx-auto">
  <HorizonPanel
    class="p-6 rounded-xl cursor-pointer !border !border-[color:var(--horizon-sunset-blue)]"
    @click="open = !open"
  >
    <!-- TITLE ROW -->
    <div class="flex justify-between items-start gap-4">
      <div class="min-w-0">
        <div class="hz-section-label">
          {{ operationKindLabel(operation.operation_type ?? operation.operation_kind) }}
        </div>
        <h3 class="hz-title-md text-horizon-white">
          {{ displayTitle }}
        </h3>
      </div>

      <div class="shrink-0 self-center flex items-center gap-3">
        <ProgressPill v-if="isJoinedByMe" variant="green">
          Joined
        </ProgressPill>

        <div class="text-horizon-offwhite opacity-70">
          <svg
            class="h-12 w-12 transition-transform duration-200"
            :class="open ? 'rotate-180' : 'rotate-0'"
            viewBox="0 0 20 20"
            fill="currentColor"
            aria-hidden="true"
          >
            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
          </svg>
        </div>
      </div>
    </div>

    <!-- COLLAPSED PREVIEW -->
    <div class="hz-caption text-horizon-offwhite mt-2" v-if="!open">
      <div class="flex items-center flex-wrap">
        <template v-if="operation.branch">
          <span class="inline-flex items-center gap-2">
            <img
              v-if="branchLogoSrc"
              :src="branchLogoSrc"
              :alt="branchLogoAlt"
              class="h-5 w-5 rounded-full object-contain border border-bg-hover"
            />
            <span>{{ branchLabel(operation.branch) }}</span>
          </span>
          <span v-if="collapsedMetaParts.length" class="opacity-70 px-2">•</span>
        </template>

        <template v-for="(part, idx) in collapsedMetaParts" :key="`${operation?.id ?? 'op'}_meta_${idx}`">
          <span>{{ part }}</span>
          <span v-if="idx < collapsedMetaParts.length - 1" class="opacity-70 px-2">•</span>
        </template>

        <span v-if="collapsedMetaParts.length" class="opacity-70 px-2">•</span>

        <span class="inline-flex items-center gap-2">
          <img
            v-if="creatorAvatar"
            :src="creatorAvatar"
            alt=""
            class="h-5 w-5 rounded-full object-cover border border-bg-hover"
          />
          <span
            v-else
            class="h-5 w-5 rounded-full bg-bg-hover border border-bg-hover flex items-center justify-center text-[10px] font-semibold text-horizon-white"
          >
            {{ creatorInitial }}
          </span>
          <span :style="creatorColor ? { color: creatorColor } : undefined">{{ creatorName }}</span>
        </span>
      </div>
    </div>

    <!-- EXPANDED DETAILS -->
    <div v-if="open" class="mt-6 space-y-4">

      <div v-if="isJoinedByMe" class="hz-caption text-horizon-offwhite">
        <ProgressPill variant="green">
          Joined
        </ProgressPill>
      </div>

      <div class="space-y-1">
        <div class="hz-section-label">Description</div>
        <p class="hz-caption leading-relaxed whitespace-pre-line">
          {{ operation.description }}
        </p>
      </div>

      <div class="hz-caption text-horizon-offwhite flex items-center flex-wrap">
        <template v-if="operation.branch">
          <span class="opacity-70">Branch:</span>
          <span class="inline-flex items-center gap-2">
            <img
              v-if="branchLogoSrc"
              :src="branchLogoSrc"
              :alt="branchLogoAlt"
              class="h-5 w-5 rounded-full object-contain border border-bg-hover"
            />
            <span>{{ branchLabel(operation.branch) }}</span>
          </span>
          <span class="opacity-70 px-2">•</span>
        </template>

        <span class="opacity-70">Creator:</span>
        <span class="inline-flex items-center gap-2 ml-2">
          <img
            v-if="creatorAvatar"
            :src="creatorAvatar"
            alt=""
            class="h-5 w-5 rounded-full object-cover border border-bg-hover"
          />
          <span
            v-else
            class="h-5 w-5 rounded-full bg-bg-hover border border-bg-hover flex items-center justify-center text-[10px] font-semibold text-horizon-white"
          >
            {{ creatorInitial }}
          </span>
          <span :style="creatorColor ? { color: creatorColor } : undefined">{{ creatorName }}</span>
        </span>
      </div>

      <div class="grid grid-cols-2 gap-6">

        <!-- START -->
        <div>
          <div class="hz-section-label">Start Time</div>
          <div class="hz-title-sm text-horizon-offwhite">
            <div>{{ formatDate(operation.starts_at) }}</div>
            <div class="hz-caption text-horizon-offwhite opacity-70">
              {{ formatLocal(operation.starts_at) }} (local)
            </div>
          </div>
        </div>

        <div>
          <div class="hz-section-label">Sign up Deadline</div>
          <div class="hz-title-sm text-horizon-offwhite">
            <div v-if="operation.rsvp_deadline">
              <div>{{ asText(operation.rsvp_deadline) }}</div>
              <div class="hz-caption text-horizon-offwhite opacity-70">
                {{ formatLocal(operation.rsvp_deadline) }} (local)
              </div>
            </div>
            <div v-else>
              No sign up deadline set
            </div>
          </div>
        </div>

      </div>

      <div class="hz-caption">
        Comms Strictness: {{ formatFirstLetter(operation.operation_strictness ?? 'normal') }}
        <br />
        Visibility: {{ formatFirstLetter(operation.visibility ?? 'open') }}

      </div>

      <HorizonButton
        variant="primary"
        size="sm"
        class="mt-4"
        @click.stop="emit('view', operation)"
      >
        View Full Operation
      </HorizonButton>

    </div>
  </HorizonPanel>
</div>
</template>

<script setup>
import { computed, ref } from 'vue';
import HorizonPanel from '@/Components/HorizonPanel.vue';
import HorizonButton from '@/Components/HorizonButton.vue';
import ProgressPill from '@/Components/ProgressPill.vue'

import { getHighestOrgRoleSlug, getOrgRoleColor } from '@/roleColors'

const emit = defineEmits(['view'])
const props = defineProps({
  operation: Object,
});

function operationTitlePrefix(kind) {
  switch (kind) {
    case 'squadron_training': return 'Squadron Training';
    case 'wing_training': return 'Wing Training';
    default: return '';
  }
}

const displayTitle = computed(() => {
  const title = props.operation?.title ?? '';
  const prefix = operationTitlePrefix(props.operation?.operation_type ?? props.operation?.operation_kind);
  return prefix ? `${prefix}: ${title}` : title;
});

const open = ref(false);

const isJoinedByMe = computed(() => {
  const op = props.operation
  if (!op) return false

  if (typeof op.joined_by_me === 'boolean') return op.joined_by_me
  return Number(op.joined_by_me ?? 0) > 0
})

const joinedCount = computed(() => {
  const op = props.operation;
  if (!op) return 0;
  if (typeof op.participants_count === 'number') return op.participants_count;
  if (Array.isArray(op.participants)) return op.participants.length;
  return 0;
});

const creatorName = computed(() => {
  return props.operation?.creator?.rsi_handle ?? 'TBD';
});

const creatorAvatar = computed(() => {
  return props.operation?.creator?.discord_avatar ?? null;
});

const creatorInitial = computed(() => {
  return String(creatorName.value ?? 'M').slice(0, 1).toUpperCase();
});

const creatorColor = computed(() => {
  const creator = props.operation?.creator ?? null
  const slug = getHighestOrgRoleSlug(creator?.roles, creator?.rank)
  return getOrgRoleColor(slug)
})

const collapsedMetaParts = computed(() => {
  const op = props.operation;
  if (!op) return [];

  const parts = [];
  parts.push(formatLocal(op.starts_at));
  parts.push(`${joinedCount.value} joined`);

  return parts
    .filter(p => p && String(p).trim() !== '')
    .map(p => String(p));
});

const branchLogoSrc = computed(() => {
  const branch = props.operation?.branch ?? '';
  switch (branch) {
    case 'defence': return '/images/Horizon_Defence_Logo.png';
    case 'frontiers': return '/images/Horizon_Frontiers_Logo.png';
    case 'industries': return '/images/Horizon_Industries_logo.png';
    case 'lifelines': return '/images/Horizon_Lifeline_logo.png';
    default: return null;
  }
});

const branchLogoAlt = computed(() => {
  const branch = props.operation?.branch ?? '';
  return branch ? `${branchLabel(branch)} branch logo` : '';
});

function truncate(text, length) {
  if (!text) return '';
  return text.length > length ? text.slice(0, length) + '…' : text;
}

function operationKindLabel(kind) {
  switch (kind) {
    case 'operation': return 'Operation';
    case 'squadron_training': return 'Squadron Training';
    case 'wing_training': return 'Wing Training';
    case 'roleplay': return 'Roleplay';
    case 'meeting': return 'Meeting';
    case 'event': return 'Event';
    default: return 'Operation';
  }
}

function branchLabel(branch) {
  switch (branch) {
    case 'industries': return 'Industries';
    case 'defence': return 'Defence';
    case 'frontiers': return 'Frontiers';
    case 'lifelines': return 'Lifelines';
    default: return branch ?? '';
  }
}

function formatFirstLetter(value) {
  if (!value) return '';
  const text = String(value).trim();
  if (!text) return '';
  return text.charAt(0).toUpperCase() + text.slice(1);
}

function formatDate(value) {
  return formatUTC(value);
}

function asText(value) {
  return formatUTC(value);
}

function formatUTC(dt) {
  if (!dt) return 'TBD';

  const d = toDate(dt);
  if (!d) return 'TBD';

  const date = new Intl.DateTimeFormat('en-GB', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
    timeZone: 'UTC',
  }).format(d);

  const time = new Intl.DateTimeFormat('en-GB', {
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
    timeZone: 'UTC',
  }).format(d);

  return `${date} ${time} UTC`;
}

function formatLocal(dt) {
  if (!dt) return 'TBD';

  const d = toDate(dt);
  if (!d) return 'TBD';

  const date = new Intl.DateTimeFormat('en-GB', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  }).format(d);

  const timeParts = new Intl.DateTimeFormat('en-GB', {
    hour: '2-digit',
    minute: '2-digit',
    hour12: true,
  }).formatToParts(d);

  const hour = timeParts.find(p => p.type === 'hour')?.value;
  const minute = timeParts.find(p => p.type === 'minute')?.value;
  const dayPeriod = (timeParts.find(p => p.type === 'dayPeriod')?.value ?? '').toLowerCase();

  const time = hour && minute && dayPeriod
    ? `${hour}:${minute} ${dayPeriod}`
    : new Intl.DateTimeFormat('en-GB', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: true,
      }).format(d);

  return `${date} ${time}`;
}

function toDate(value) {
  if (!value) return null;

  let v = String(value).trim();
  v = v.replace(' ', 'T');

  v = v.replace(/\.(\d{3})\d+Z$/i, '.$1Z');
  v = v.replace(/\.(\d{3})\d+$/i, '.$1');

  const hasTimezone = /([zZ]|[+-]\d{2}:\d{2})$/.test(v);
  if (!hasTimezone) v = `${v}Z`;

  const d = new Date(v);
  return Number.isNaN(d.getTime()) ? null : d;
}
</script>
