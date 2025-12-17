<template>
  <HorizonContainer>



    <!-- MAIN PAGE (single column, screenshot style) -->
    <section class="space-y-7">

      <!-- Header + Create button -->
      <div class="flex items-center justify-between gap-4">
        <HorizonSectionHeader
          label="Operations"
          title="Operations Board"
        />

        <HorizonButton
          v-if="canCreateOperation && userSquadronId"
          variant="primary"
          size="md"
          @click="$inertia.visit(route('operations.create', { squadron: userSquadronId }))"
        >
          Create Operation
        </HorizonButton>
      </div>

      <!-- FILTER PANEL (centered, slim like screenshot) -->
      <HorizonPanel class="p-3 rounded-xl space-y-2 max-w-2xl mx-auto">
        
        <!-- FILTER BUTTONS -->
        <div class="flex flex-wrap gap-2">
          <HorizonButton
            v-for="s in statusFilters"
            :key="s.value"
            size="xs"
            :variant="statusFilter === s.value ? 'primary' : 'ghost'"
            @click="statusFilter = s.value"
          >
            {{ s.label }}
          </HorizonButton>
        </div>

        <!-- SEARCH -->
        <div class="flex">
          <div class="m-auto w-full sm:w-lg">
            <HorizonInput
              v-model="search"
              label="Search"
              placeholder="Title, squadron..."
            />
          </div>
        </div>

      </HorizonPanel>

      <!-- OPERATION GRID -->
      <MissionGrid v-if="filteredOperations.length > 0" class="pt-2">
        <MissionCard
          v-for="op in filteredOperations"
          :key="op.id"
          :title="op.title"
          :description="op.description"
          :start="formatDate(op.starts_at)"
          :eta="op.ends_at ? formatDate(op.ends_at) : 'TBD'"
          :status="op.status"
        >
          <div class="mt-6 flex items-start justify-between gap-4">

            <div class="hz-stack-2xs flex-1 min-w-0">
              <div class="hz-caption text-horizon-offwhite wrap-break-word">
                <span class="opacity-70">SQD:</span>
                {{ op.squadron?.name ?? 'TBD' }}
              </div>

              <div class="hz-caption text-horizon-offwhite wrap-break-word">
                <span class="opacity-70">CRE:</span>
                {{ op.creator?.rsi_handle ?? 'TBD' }}
              </div>

              <div class="hz-caption text-horizon-offwhite">
                Strict: {{ op.operation_strictness ?? 'default' }} • VIS: {{ op.visibility ?? 'open' }}
              </div>
            </div>

            <div class="flex gap-2 shrink-0">
              
              <!-- EDIT -->
              <HorizonButton
                v-if="canEdit(op)"
                size="sm"
                variant="primary"
                @click="$inertia.visit(route('operations.edit', op.id))"
              >
                Edit
              </HorizonButton>
              
              <!-- VIEW -->
              <HorizonButton
                size="sm"
                variant="primary"
                @click="$inertia.visit(route('operations.show', op.id))"
              >
                View
              </HorizonButton>

              <!-- DELETE -->
              <HorizonButton
                variant="danger"
                size="sm"
                @click="destroy(op.id)"
              >
                Delete
              </HorizonButton>
            </div>

          </div>
        </MissionCard>
      </MissionGrid>

      <div
        v-if="operationsPaginator && operationsPaginator.last_page > 1"
        class="pt-8 flex items-center justify-between"
      >
        <HorizonButton
          size="sm"
          variant="ghost"
          :disabled="!operationsPaginator.prev_page_url"
          @click="goToPage(operationsPaginator.current_page - 1)"
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
          @click="goToPage(operationsPaginator.current_page + 1)"
        >
          Next
        </HorizonButton>
      </div>

      <!-- EMPTY STATE -->
      <HorizonPanel
        v-if="filteredOperations.length === 0"
        class="text-center py-20 space-y-6 hz-holo-light hz-lift"
      >
        <div class="hz-title-lg text-horizon-white">
          No Operations Found
        </div>

        <p class="hz-caption hz-text-muted">
          Use the operation editor to create the first entry.
        </p>

      </HorizonPanel>

    </section>

  </HorizonContainer>
</template>



<script setup>
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import { Ziggy } from '../../ziggy';

import HorizonContainer from '@/Components/HorizonContainer.vue';
import HorizonButton from '@/Components/HorizonButton.vue';
import HorizonInput from '@/Components/HorizonInput.vue';
import HorizonPanel from '@/Components/HorizonPanel.vue';
import HorizonSectionHeader from '@/Components/HorizonSectionHeader.vue';
import MissionGrid from '@/Components/MissionGrid.vue';
import MissionCard from '@/Components/MissionCard.vue';


const page = usePage();
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
    route('operations.index', {}, Ziggy),
    { page: pageNumber },
    { preserveScroll: true, preserveState: true }
  );
}

/* ----------------------------------------
   USER + PERMISSIONS
---------------------------------------- */

const user = computed(() => page.props.auth?.user ?? null);

const userSquadronId = computed(() => {
  const u = user.value;
  if (!u?.squadrons?.length) return null;

  const lt = u.squadrons.find(s => s.pivot?.role === 'lieutenant');
  if (lt) return lt.id;

  const leader = u.squadrons.find(s => s.pivot?.role === 'leader');
  if (leader) return leader.id;

  return u.squadrons[0]?.id ?? null;
});

const can = computed(() => page.props.auth?.can ?? {});

const isSquadronLeader = computed(() =>
  user.value?.squadrons?.some(s => s.pivot?.role === 'leader')
);

const isSquadronLieutenant = computed(() =>
  user.value?.squadrons?.some(s => s.pivot?.role === 'lieutenant')
);

const canCreateOperation = computed(() => {
  if (can.value['operation.create']) return true;
  if (can.value['operation.host.small']) return true;
  if (can.value['operation.host.medium']) return true;
  if (can.value['operation.host.large']) return true;
  if (can.value['operation.host.org']) return true;
  if (isSquadronLeader.value) return true;
  if (isSquadronLieutenant.value) return true;
  return false;
});

// EDIT permission
function canEdit(op) {
  if (can.value['operation.update']) return true;
  if (op.created_by === user.value?.id) return true;
  if (isSquadronLeader.value) return true;
  return false;
}

function destroy(operationId) {
  if (!confirm('Delete this operation?')) return;

  router.delete(route('operations.destroy', operationId, Ziggy), {
    preserveScroll: true,
  });
}

/* ----------------------------------------
   FILTERING
---------------------------------------- */

const statusFilters = [
  { label: 'All', value: 'all' },
  { label: 'Draft', value: 'draft' },
  { label: 'Published', value: 'published' },
  { label: 'In Progress', value: 'in_progress' },
  { label: 'Completed', value: 'completed' },
  { label: 'Canceled', value: 'canceled' },
];

const statusFilter = ref('all');
const search = ref('');

const filteredOperations = computed(() => {
  return operationsList.value.filter(op => {
    const matchesStatus =
      statusFilter.value === 'all' || op.status === statusFilter.value;

    const q = search.value.toLowerCase();
    const matchesSearch =
      !q ||
      op.title.toLowerCase().includes(q) ||
      (op.description || '').toLowerCase().includes(q);

    return matchesStatus && matchesSearch;
  });
});

/* ----------------------------------------
   STATS
---------------------------------------- */
const activeCount = computed(() =>
  operationsList.value.filter(op =>
    ['published', 'in_progress'].includes(op.status)
  ).length
);
const draftCount = computed(() =>
  operationsList.value.filter(op => op.status === 'draft').length
);
const completedCount = computed(() =>
  operationsList.value.filter(op => op.status === 'completed').length
);
const plannedCount = computed(() =>
  operationsList.value.filter(op => op.status === 'published').length
);

const operationsTotalCount = computed(() => {
  return operationsPaginator.value?.total ?? operationsList.value.length;
});

const statusFilterLabel = computed(
  () => statusFilters.find(s => s.value === statusFilter.value)?.label ?? 'All'
);

// UTIL
function formatDate(value) {
  if (!value) return 'TBD';
  return String(value);
}
</script>
