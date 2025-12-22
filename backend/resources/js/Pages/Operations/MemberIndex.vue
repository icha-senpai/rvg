<template>
  <HorizonContainer class="space-y-10">

    <div class="mx-auto max-w-5xl space-y-6">

    <!-- HEADER -->
    <HorizonSectionHeader
      label="Operations"
      title="Your Available Operations"
    />

    <div class="hz-caption hz-text-muted">
      Today: {{ todayLabel }}
    </div>

    <!-- LIST -->
    <div class="space-y-4">
      <OperationAccordion
        v-for="op in sortedOperations"
        :key="op.id"
        :operation="op"
      />
    </div>

    <div
      v-if="operationsPaginator && operationsPaginator.last_page > 1"
      class="pt-8 flex items-center justify-between"
    >
      <HorizonButton
        size="sm"
        variant="ghost"
        :disabled="!operationsPaginator.prev_page_url"
        @click="goToUrl(operationsPaginator.prev_page_url)"
      >
        Prev
      </HorizonButton>

      <div class="hz-caption hz-text-muted">
        Page {{ operationsPaginator.current_page }} of {{ operationsPaginator.last_page }}
      </div>

      <HorizonButton
        size="sm"
        variant="ghost"
        :disabled="!operationsPaginator.next_page_url"
        @click="goToUrl(operationsPaginator.next_page_url)"
      >
        Next
      </HorizonButton>
    </div>

    </div>

  </HorizonContainer>
</template>

<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import { Ziggy } from '../../ziggy';
import OperationAccordion from '@/Pages/Operations/Components/OperationAccordion.vue';
import HorizonButton from '@/Components/HorizonButton.vue';
import HorizonContainer from '@/Components/HorizonContainer.vue';
import HorizonSectionHeader from '@/Components/HorizonSectionHeader.vue';

const todayLabel = computed(() => {
  return new Date().toLocaleDateString(undefined, {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
});

const props = defineProps({
  operations: {
    type: [Array, Object],
    required: true,
  },
});

const operationsPaginator = computed(() => {
  return Array.isArray(props.operations) ? null : props.operations;
});

const operationsList = computed(() => {
  if (Array.isArray(props.operations)) {
    return props.operations ?? [];
  }

  return props.operations?.data ?? [];
});

function goToPage(pageNumber) {
  if (!pageNumber || pageNumber < 1) return;

  router.get(
    route('operations.member', {}, Ziggy),
    { page: pageNumber },
    { preserveScroll: true, preserveState: true }
  );
}

function goToUrl(url) {
  if (!url) return;

  router.get(url, {}, { preserveScroll: true, preserveState: true });
}

// Most recent start time first
const sortedOperations = computed(() => {
  return [...(operationsList.value ?? [])].sort((a, b) => {
    const aDate = parseDate(a.starts_at);
    const bDate = parseDate(b.starts_at);

    if (!aDate && !bDate) return 0;
    if (!aDate) return 1; 
    if (!bDate) return -1;

    return bDate.getTime() - aDate.getTime();
  });
});
console.log("Raw starts_at:", operationsList.value.map(o => o.starts_at));
console.log("Parsed starts_at:", operationsList.value.map(o => parseDate(o.starts_at)));
function parseDate(value) {
  if (!value) return null;

  // Normalize format for JS (remove microseconds, ensure Z)
  let v = value.replace(/\.\d+Z$/, 'Z').replace(/\.\d+$/, 'Z');

  // If no timezone exists, assume UTC
  if (!v.endsWith('Z') && !v.includes('+')) v = v + 'Z';

  const d = new Date(v);
  return isNaN(d.getTime()) ? null : d;
}


</script>

