<template>
  <HorizonContainer class="space-y-10">

    <div class="mx-auto max-w-5xl space-y-6">

      <!-- HEADER -->
      <HorizonSectionHeader
        label=""
        title="Operations"
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
          @view="openViewModal"
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

    <OperationModal
      v-if="activeOperation"
      @close="closeViewModal"
    >
      <template #header>
        <div class="hz-stack-xs">
          <div class="hz-section-label">
            {{ operationKindLabel(modalHeaderOperation?.operation_type ?? modalHeaderOperation?.operation_kind) }}
          </div>
          <div class="hz-title-md text-horizon-white">
            {{ operationDisplayTitle(modalHeaderOperation) }}
          </div>
        </div>
      </template>

      <MissionShowPanel
        :operation="activeOperation.operation"
        :participants="activeOperation.participants"
        :participants-by-slot="activeOperation.participantsBySlot"
        :unassigned-participants="activeOperation.unassignedParticipants"
        :current-participant="activeOperation.currentParticipant"
      />
    </OperationModal>


  </HorizonContainer>
</template>

<script setup>
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import { Ziggy } from '../../ziggy';
import OperationAccordion from '@/Pages/Operations/Components/OperationAccordion.vue';
import HorizonButton from '@/Components/HorizonButton.vue';
import HorizonContainer from '@/Components/HorizonContainer.vue';
import HorizonSectionHeader from '@/Components/HorizonSectionHeader.vue';
import OperationModal from '@/Pages/Operations/Components/OperationModal.vue'
import MissionShowPanel from '@/Pages/Operations/Components/MissionShowPanel.vue'

const page = usePage()
const activeOperation = computed(() => page.props?.activeOperation ?? null)

const modalHeaderOperation = computed(() => {
  return activeOperation.value?.operation ?? null
})

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

function operationTitlePrefix(kind) {
  switch (kind) {
    case 'squadron_training': return 'Squadron Training'
    case 'wing_training': return 'Wing Training'
    default: return ''
  }
}

function operationDisplayTitle(op) {
  const title = op?.title ?? ''
  const prefix = operationTitlePrefix(op?.operation_type ?? op?.operation_kind)
  return prefix ? `${prefix}: ${title}` : title
}

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

function goToUrl(url) {
  if (!url) return;

  router.get(url, {}, { preserveScroll: true, preserveState: true });
}

function getCurrentQueryParams() {
  const url = new URL(window.location.href)
  const out = {}

  for (const [key, value] of url.searchParams.entries()) {
    out[key] = value
  }

  return out
}

// Most recent start time first
const sortedOperations = computed(() => {
  const now = new Date();

  return [...(operationsList.value ?? [])].sort((a, b) => {
    const aDate = parseDate(a.starts_at);
    const bDate = parseDate(b.starts_at);

    if (!aDate && !bDate) return 0;
    if (!aDate) return 1; 
    if (!bDate) return -1;

    const aIsUpcoming = aDate.getTime() >= now.getTime();
    const bIsUpcoming = bDate.getTime() >= now.getTime();

    if (aIsUpcoming && !bIsUpcoming) return -1;
    if (!aIsUpcoming && bIsUpcoming) return 1;

    if (aIsUpcoming && bIsUpcoming) {
      return aDate.getTime() - bDate.getTime();
    }

    return bDate.getTime() - aDate.getTime();
  });
});
function parseDate(value) {
  if (!value) return null;

  // Normalize format for JS (remove microseconds, ensure Z)
  let v = value.replace(/\.\d+Z$/, 'Z').replace(/\.\d+$/, 'Z');

  // If no timezone exists, assume UTC
  if (!v.endsWith('Z') && !v.includes('+')) v = v + 'Z';

  const d = new Date(v);
  return isNaN(d.getTime()) ? null : d;
}

function openViewModal(op) {
  const query = {
    ...getCurrentQueryParams(),
    operation: op.id,
  }

  router.get(route('operations.member', {}, Ziggy), query, {
    only: ['activeOperation'],
    preserveScroll: true,
    preserveState: true,
  })
}

function closeViewModal() {
  const query = {
    ...getCurrentQueryParams(),
  }

  delete query.operation

  router.get(route('operations.member', {}, Ziggy), query, {
    only: ['activeOperation'],
    preserveScroll: true,
    preserveState: true,
  })
}

</script>

