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
      v-if="viewingOperation"
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

      <div v-if="viewLoading" class="p-10 text-center">
        <div class="hz-caption hz-text-muted">
          Loading operation details…
        </div>
      </div>

      <div v-else-if="viewError" class="p-10 text-center text-red-400">
        {{ viewError }}
      </div>

      <MissionShowPanel
        v-else
        :operation="viewData.operation"
        :participants="viewData.participants"
        :participants-by-slot="viewData.participantsBySlot"
        :unassigned-participants="viewData.unassignedParticipants"
        :current-participant="viewData.currentParticipant"
        @refresh="reloadViewData"
      />
    </OperationModal>


  </HorizonContainer>
</template>

<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import { Ziggy } from '../../ziggy';
import axios from 'axios'
import OperationAccordion from '@/Pages/Operations/Components/OperationAccordion.vue';
import HorizonButton from '@/Components/HorizonButton.vue';
import HorizonContainer from '@/Components/HorizonContainer.vue';
import HorizonSectionHeader from '@/Components/HorizonSectionHeader.vue';
import OperationModal from '@/Pages/Operations/Components/OperationModal.vue'
import MissionShowPanel from '@/Pages/Operations/Components/MissionShowPanel.vue'

const viewingOperation = ref(null)
const viewData = ref(null)
const viewLoading = ref(false)
const viewError = ref(null)

const modalHeaderOperation = computed(() => {
  return viewData.value?.operation ?? viewingOperation.value
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

async function openViewModal(op) {
  viewingOperation.value = op
  viewLoading.value = true
  viewError.value = null
  viewData.value = null

  try {
    const { data } = await axios.get(
      route('operations.showData', op.id)
    )
    viewData.value = data
  } catch (e) {
    const status = e?.response?.status ?? null

    if (status === 401 || status === 419) {
      closeViewModal()
      return
    }

    console.error(e)
    viewError.value = 'Failed to load operation.'
  } finally {
    viewLoading.value = false
  }
}

function closeViewModal() {
  viewingOperation.value = null
  viewData.value = null
  viewError.value = null
}

async function reloadViewData() {
  if (!viewingOperation.value) return

  viewLoading.value = true
  viewError.value = null

  try {
    const { data } = await axios.get(
      route('operations.showData', viewingOperation.value.id)
    )
    viewData.value = data
  } catch (err) {
    const status = err?.response?.status ?? null

    if (status === 401 || status === 419) {
      closeViewModal()
      return
    }

    console.error(err)
    viewError.value = 'Failed to refresh operation data.'
  } finally {
    viewLoading.value = false
  }
}

</script>

