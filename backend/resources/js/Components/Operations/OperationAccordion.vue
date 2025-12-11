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

      <div class="hz-caption text-horizon-offwhite">
        {{ formatDate(operation.starts_at) }}
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

      <div class="grid grid-cols-2 gap-6">

        <!-- START -->
        <div>
          <div class="hz-section-label">Start Time</div>
          <div class="hz-title-sm text-horizon-offwhite">
            {{ formatDate(operation.starts_at) }}
          </div>
        </div>

        <div>
          <div class="hz-section-label">Sign up Deadline</div>
          <div class="hz-title-sm text-horizon-offwhite">
            {{ asText(operation.rsvp_deadline) }}
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
  if (!value) return 'TBD';
  return new Date(value).toLocaleString();
}

function asText(value) {
  if (!value) return 'TBD';
  return new Date(value).toLocaleString();
}
</script>
