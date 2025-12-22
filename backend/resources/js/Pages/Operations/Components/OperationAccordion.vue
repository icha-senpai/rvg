<template>
<div class="max-w-6xl mx-auto">
  <HorizonPanel
    class="p-6 rounded-xl cursor-pointer"
    @click="open = !open"
  >
    <!-- TITLE ROW -->
    <div class="flex justify-between items-center">
      <h3 class="hz-title-md text-horizon-white">
        {{ operation.title }}
      </h3>

      <div class="hz-caption text-horizon-offwhite text-right">
        <div>{{ formatDate(operation.starts_at) }}</div>
        <div class="text-horizon-offwhite text-xs opacity-70">
          {{ formatLocal(operation.starts_at) }} (local)
        </div>
      </div>
    </div>

    <!-- COLLAPSED PREVIEW -->
    <p class="hz-text-soft mt-2" v-if="!open">
      {{ truncate(operation.description, 140) }}
    </p>

    <!-- EXPANDED DETAILS -->
    <div v-if="open" class="mt-6 space-y-4">

      <div class="space-y-1">
        <div class="hz-section-label">Description</div>
        <p class="hz-caption leading-relaxed whitespace-pre-line">
          {{ operation.description }}
        </p>
      </div>

      <div class="hz-caption text-horizon-offwhite">
        <span class="opacity-70">Squadron:</span>
        {{ operation.squadron?.name ?? 'TBD' }}
        <span class="opacity-70">• Creator:</span>
        {{ operation.creator?.rsi_handle ?? 'TBD' }}
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
              TBD
            </div>
          </div>
        </div>

      </div>

      <div class="hz-caption">
        Strictness: {{ operation.operation_strictness ?? 'N/A' }}
        <br />
        Visibility: {{ operation.visibility ?? 'open' }}

      </div>

      <HorizonButton
        variant="primary"
        size="sm"
        class="mt-4"
        @click.stop="$inertia.visit(route('operations.show', operation.id))"
      >
        View Full Operation
      </HorizonButton>

    </div>
  </HorizonPanel>
</div>
</template>

<script setup>
import { ref } from 'vue';
import HorizonPanel from '@/Components/HorizonPanel.vue';
import HorizonButton from '@/Components/HorizonButton.vue';

const props = defineProps({
  operation: Object,
});

const open = ref(false);

function truncate(text, length) {
  if (!text) return '';
  return text.length > length ? text.slice(0, length) + '…' : text;
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
