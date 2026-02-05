<template>
  <HorizonContainer class="space-y-10">

    <!-- MAIN PAGE (single column, screenshot style) -->
    <section class="mx-auto max-w-5xl space-y-7">

      <!-- Header + Create button -->
      <div class="flex items-center justify-between gap-4">
        <HorizonSectionHeader
          label=""
          title="Operations Dashboard"
        />

        <HorizonButton
          v-if="canCreateOperation"
          variant="primary"
          size="md"
          @click="openCreateDrawer"
        >
          Create Operation
        </HorizonButton>
      </div>

      <!-- FILTER PANEL -->
      <HorizonPanel class="p-3 rounded-xl space-y-2 max-w-2xl mx-auto border-(--horizon-sunset-blue)">

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
              placeholder="Title, description..."
            />
          </div>
        </div>

      </HorizonPanel>

      <!-- Today label -->
      <div class="hz-caption hz-text-muted">
        Today: {{ todayLabel }}
      </div>

      <!-- OPERATION GRID -->
      <div v-if="filteredOperations.length > 0" class="pt-2">
        <div class="rounded-2xl border border-(--horizon-sunset-blue) p-4">
          <MissionGrid class="lg:grid-cols-2!">
            <MissionCard
              v-for="op in filteredOperations"
              :key="op.id"
              :title="operationDisplayTitle(op)"
              :description="op.description"
              :start="formatDate(op.starts_at)"
              :eta="op.ends_at ? formatDate(op.ends_at) : 'TBD'"
              :status="op.status"
            >
              <div class="mt-6 flex items-start justify-between gap-4">

                <div class="hz-stack-2xs flex-1 min-w-0">
                  <div class="hz-caption text-horizon-offwhite">
                    <span class="opacity-70">SQD:</span>
                    {{ op.squadron?.name ?? 'TBD' }}
                  </div>

                  <div class="hz-caption text-horizon-offwhite">
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
                    v-if="canManageOperation(op)"
                    size="sm"
                    variant="primary"
                    @click="openEditDrawer(op)"
                  >
                    Edit
                  </HorizonButton>

                  <!-- VIEW -->
                  <HorizonButton
                    size="sm"
                    variant="primary"
                    @click="openViewModal(op)"
                  >
                    View
                  </HorizonButton>

                  <!-- DELETE -->
                  <HorizonButton
                    v-if="canManageOperation(op)"
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
        </div>
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

    <OperationDrawer
      v-if="drawerOpen"
      @close="closeDrawer"
    >
      <!-- HEADER -->
      <template #header>
        <div class="hz-stack-xs">
          <div class="hz-section-label">
            {{ editingMission ? 'Edit Operation' : 'New Operation' }}
          </div>
          <div class="hz-title-md text-horizon-white">
            {{ editingMission ? editingMission.title : 'Create Operation' }}
          </div>
        </div>
      </template>

      <!-- BODY -->
      <div
        v-if="editHydrating"
        class="p-10 text-center"
      >
        <div class="hz-caption hz-text-muted">
          Loading editor…
        </div>
      </div>

      <div
        v-else-if="editHydrationError"
        class="p-10 text-center text-red-400"
      >
        {{ editHydrationError }}
      </div>

      <MissionEditorForm
        v-else
        :embedded="true"
        :mission="editingMission"
        :squadron-id="editorSquadronId"
        @cancel="closeDrawer"
        @deleted="handleDrawerDeleted"
        @saved="handleDrawerSaved"
      />
    </OperationDrawer>

    <!-- MISSION VIEW MODAL -->
    <OperationModal
      v-if="viewingOperation"
      @close="closeViewModal"
    >
      <template #header>
        <div class="hz-stack-xs">
          <div class="hz-section-label">
            {{ (modalHeaderOperation?.operation_kind ?? 'operation') === 'operation' ? 'Operation' : 'Operation' }}
          </div>
          <div class="hz-title-md text-horizon-white">
            {{ operationDisplayTitle(modalHeaderOperation) }}
          </div>
        </div>
      </template>

      <!-- LOADING -->
      <div v-if="viewLoading" class="p-10 text-center">
        <div class="hz-caption hz-text-muted">
          Loading operation details…
        </div>
      </div>

      <!-- ERROR -->
      <div v-else-if="viewError" class="p-10 text-center text-red-400">
        {{ viewError }}
      </div>

      <!-- CONTENT (guarded so we don't render until data exists) -->
      <div v-else-if="!viewData" class="p-10 text-center">
        <div class="hz-caption hz-text-muted">
          Preparing…
        </div>
      </div>

      <MissionShowPanel
        v-else
        :operation="viewData.operation"
        :participants="viewData.participants"
        :participants-by-slot="viewData.participantsBySlot"
        :unassigned-participants="viewData.unassignedParticipants"
        :current-participant="viewData.currentParticipant"
        @refresh="handleViewRefreshed"
      />
    </OperationModal>

  </HorizonContainer>
</template>

<script setup>
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import axios from 'axios'
import { route } from 'ziggy-js';
import { Ziggy } from '../../ziggy';

import HorizonContainer from '@/Components/HorizonContainer.vue';
import HorizonButton from '@/Components/HorizonButton.vue';
import HorizonInput from '@/Components/HorizonInput.vue';
import HorizonPanel from '@/Components/HorizonPanel.vue';
import HorizonSectionHeader from '@/Components/HorizonSectionHeader.vue';
import MissionGrid from '@/Components/MissionGrid.vue';
import MissionCard from '@/Components/MissionCard.vue';
import OperationDrawer from '@/Pages/Operations/Components/OperationDrawer.vue'
import MissionEditorForm from '@/Pages/Operations/Components/MissionEditorForm.vue'
import OperationModal from '@/Pages/Operations/Components/OperationModal.vue'
import MissionShowPanel from '@/Pages/Operations/Components/MissionShowPanel.vue'

const page = usePage();
const props = defineProps({
  operations: {
    type: [Array, Object],
    required: true,
  },
});

const todayLabel = computed(() => {
  return new Date().toLocaleDateString(undefined, {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
});

const drawerOpen = ref(false)
const editingMission = ref(null)
const editorSquadronId = ref(null)
const editHydrating = ref(false)
const editHydrationError = ref(null)

/* ----------------------
   VIEW MODAL STATE
---------------------- */
const viewingOperation = ref(null)
const viewData = ref(null)
const viewLoading = ref(false)
const viewError = ref(null)

const modalHeaderOperation = computed(() => {
  return viewData.value?.operation ?? viewingOperation.value
})

function operationTitlePrefix(kind) {
  switch (kind) {
    case 'squadron_training': return 'Squadron Training'
    case 'wing_training': return 'Wing Training'
    default: return ''
  }
}

function operationDisplayTitle(op) {
  const title = op?.title ?? ''
  const prefix = operationTitlePrefix(op?.operation_kind)
  return prefix ? `${prefix}: ${title}` : title
}

const operationsPaginator = computed(() => {
  return Array.isArray(props.operations) ? null : props.operations;
});

const operationsList = computed(() => {
  if (Array.isArray(props.operations)) {
    return props.operations ?? [];
  }

  return props.operations?.data ?? [];
});

function refreshOperations() {
  if (typeof router.reload === 'function') {
    router.reload({
      only: ['operations'],
      preserveScroll: true,
      preserveState: true,
    })
    return
  }

  router.get(route('operations.index', {}, Ziggy), {}, {
    only: ['operations'],
    preserveScroll: true,
    preserveState: true,
  })
}

function goToUrl(url) {
  if (!url) return;

  router.get(url, {}, { preserveScroll: true, preserveState: true });
}

function openCreateDrawer() {
  editingMission.value = null
  editorSquadronId.value = userSquadronId.value
  editHydrating.value = false
  editHydrationError.value = null
  drawerOpen.value = true
}

async function openEditDrawer(op) {
  editingMission.value = op
  editorSquadronId.value = op.squadron?.id ?? null
  editHydrating.value = true
  editHydrationError.value = null
  drawerOpen.value = true

  try {
    const { data } = await axios.get(
      route('operations.editData', op.id, Ziggy)
    )

    editingMission.value = data?.payload?.mission ?? null
    editorSquadronId.value = data?.payload?.squadronId ?? null
  } catch (err) {
    const status = err?.response?.status ?? null

    if (status === 401 || status === 419) {
      closeDrawer()
      return
    }

    console.error(err)
    editHydrationError.value = 'Failed to load editor data.'
  } finally {
    editHydrating.value = false
  }
}

function closeDrawer() {
  drawerOpen.value = false
  editingMission.value = null
  editorSquadronId.value = null
  editHydrating.value = false
  editHydrationError.value = null
}

function handleDrawerSaved(payload) {
  const operationId = payload?.id
  closeDrawer()

  refreshOperations()

  if (!operationId) return

  setTimeout(() => {
    openViewModal({ id: operationId })
  }, 160)
}

function handleDrawerDeleted() {
  closeDrawer()
  refreshOperations()
}

function handleViewRefreshed() {
  if (viewingOperation.value) {
    openViewModal(viewingOperation.value)
  }

  refreshOperations()
}

/* ----------------------
   LAZY LOAD VIEW MODAL
---------------------- */
async function openViewModal(op) {
  viewingOperation.value = op
  viewLoading.value = true
  viewError.value = null
  viewData.value = null

  try {
    const { data } = await axios.get(
      route('operations.showData', op.id, Ziggy)
    )

    viewData.value = data
    viewingOperation.value = data?.operation ?? viewingOperation.value
  } catch (err) {
    const status = err?.response?.status ?? null

    if (status === 401 || status === 419) {
      closeViewModal()
      return
    }

    console.error(err)
    viewError.value = 'Failed to load operation data.'
  } finally {
    viewLoading.value = false
  }
}

function closeViewModal() {
  viewingOperation.value = null
  viewData.value = null
  viewError.value = null
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

const isDirectorLike = computed(() => {
  const roles = user.value?.roles ?? [];
  return roles.some(r => r?.slug === 'director' || r?.slug === 'tech_director');
});

const canCreateOperation = computed(() => {
  if (isDirectorLike.value) return true;
  if ((user.value?.rank_level ?? 0) >= 3) return true;

  const roles = user.value?.roles ?? [];
  const highCommandRoleSlugs = ['commander_staff', 'wing_commander', 'admiral', 'grand_admiral'];
  if (roles.some(r => highCommandRoleSlugs.includes(r?.slug))) return true;

  if (!userSquadronId.value) return false;

  if (can.value['operation.create']) return true;
  if (can.value['operation.host.small']) return true;
  if (can.value['operation.host.medium']) return true;
  if (can.value['operation.host.large']) return true;
  if (can.value['operation.host.org']) return true;
  if (isSquadronLeader.value) return true;
  if (isSquadronLieutenant.value) return true;
  return false;
});

function canManageOperation(op) {
  if (isDirectorLike.value) return true;

  const squadronId = op?.squadron?.id;
  if (!squadronId) return false;

  const squadronLeaderId = op?.squadron?.leader?.id;
  if (squadronLeaderId && squadronLeaderId === user.value?.id) {
    return true;
  }

  const membership = user.value?.squadrons?.find(s => s.id === squadronId);
  if (!membership) return false;

  const membershipStatus = membership.pivot?.membership_status;
  if (membershipStatus && membershipStatus !== 'active') return false;

  const role = membership.pivot?.role;
  return role === 'leader' || role === 'lieutenant';
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

const statusFilter = ref('published');
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
