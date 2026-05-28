<template>
  <div class="hz-card-glow hz-shadow-soft hz-stack">

    <!-- Header -->
    <div class="flex justify-between items-start">
      <h3 class="hz-title-lg text-horizon-white">
        {{ title }}
      </h3>

      <div class="px-3 py-1 rounded-full bg-[var(--horizon-sunset-blue)] text-[var(--color-horizon-offwhite)] text-xs font-semibold">
        {{ status }}
      </div>
    </div>
    <div class="border-t border-[color:var(--horizon-sunset-blue)] my-3"></div>
    <!-- DESCRIPTION -->
    <p class="text-base hz-text-soft leading-relaxed">
      {{ description }}
    </p>

    <!-- META: START -->
    <div class="grid grid-cols-2 gap-4 mt-3">
      <div class="hz-stack-sm">
        <div class="hz-section-label">Start</div>

        <div class="hz-caption text-horizon-offwhite">
          <!-- UTC -->
          <div>{{ startUTC }}</div>

          <!-- LOCAL -->
          <div class="text-horizon-offwhite text-xs opacity-70">
            {{ startLocal }} (local)
          </div>
        </div>
      </div>
    </div>

    <!-- Divider -->
    <div class="border-t border-[color:var(--horizon-sunset-blue)] my-3"></div>

    <!-- Slot (footer/actions) -->
    <slot />

  </div>
</template>


<script setup>
import { computed } from 'vue';

const props = defineProps({
  title: String,
  description: String,
  start: String,
  status: String,
});

/* ============================
   FORMATTERS
   ============================ */


function formatUTC(dt) {
  const d = toDate(dt);
  if (!d) return 'N/A';

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

/* LOCAL FORMAT — FOR DISPLAY ONLY (SAFE) */
function formatLocal(dt) {
  const d = toDate(dt);
  if (!d) return 'N/A';

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

/* COMPUTED VALUES */
const startUTC   = computed(() => formatUTC(props.start));
const startLocal = computed(() => formatLocal(props.start));

</script>










