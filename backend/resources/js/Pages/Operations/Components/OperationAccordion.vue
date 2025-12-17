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
        <p class="hz-caption leading-relaxed">
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

  const clean = String(dt).replace('Z', '').replace('+00:00', '');
  const date = clean.slice(0, 10);
  const time = clean.slice(11, 16);

  return `${date} ${time} UTC`;
}

function formatLocal(dt) {
  if (!dt) return 'TBD';

  const d = new Date(dt);
  if (Number.isNaN(d.getTime())) return String(dt);

  return d.toLocaleString(undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
}
</script>
