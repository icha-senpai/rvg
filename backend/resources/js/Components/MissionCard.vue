<template>
  <div class="hz-card-glow hz-shadow-soft hz-stack">

    <!-- Header -->
    <div class="flex justify-between items-start">
      <h3 class="hz-title-lg text-horizon-white">
        {{ title }}
      </h3>

      <div class="px-3 py-1 rounded-full bg-[var(--color-horizon-offwhite)] text-[var(--color-horizon-alloy)] text-xs font-semibold">
        {{ status }}
      </div>
    </div>

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
    <div class="border-t border-horizon-offwhite my-3"></div>

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

/* PURE UTC FORMAT — NO TIMEZONE CONVERSION */
function formatUTC(dt) {
  if (!dt) return 'N/A';

  // Remove timezone suffix + milliseconds
  const clean = dt.replace('Z', '').replace('+00:00', '');

  const date = clean.slice(0, 10);  // YYYY-MM-DD
  const time = clean.slice(11, 16); // HH:MM

  return `${date} ${time} UTC`;
}

/* LOCAL FORMAT — FOR DISPLAY ONLY (SAFE) */
function formatLocal(dt) {
  if (!dt) return 'N/A';

  const d = new Date(dt); // Only for display, never used in editor

  return d.toLocaleString(undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
}

/* COMPUTED VALUES */
const startUTC   = computed(() => formatUTC(props.start));
const startLocal = computed(() => formatLocal(props.start));

</script>


