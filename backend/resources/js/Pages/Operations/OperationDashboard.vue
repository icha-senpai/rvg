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

        <div v-if="canCreateOperation" class="flex items-center gap-3">
          <div class="w-64">
            <HorizonSelect
              v-model="createTemplateId"
              :options="templateOptions"
            />
          </div>

          <HorizonButton
            variant="ghost"
            size="md"
            :disabled="!createTemplateId"
            @click="openCreateDrawerFromTemplate"
          >
            Create From Template
          </HorizonButton>

          <HorizonButton
            variant="primary"
            size="md"
            @click="openCreateDrawer"
          >
            Create Operation
          </HorizonButton>
        </div>
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
              placeholder="Title, description, ID, squadron, creator..."
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
              <div class="mt-6 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">

                <div class="hz-stack-2xs flex-1 min-w-0">
                  <div class="hz-caption text-horizon-offwhite">
                    <span class="opacity-70">ID:</span>
                    #{{ op.id }}
                  </div>

                  <div class="hz-caption text-horizon-offwhite">
                    <span class="opacity-70">SQD:</span>
                    {{ op.squadron?.name ?? 'TBD' }}
                  </div>

                  <div class="hz-caption text-horizon-offwhite">
                    <span class="opacity-70">CRE:</span>
                    <span :style="creatorNameColor(op) ? { color: creatorNameColor(op) } : undefined">
                      {{ op.creator?.rsi_handle ?? 'TBD' }}
                    </span>
                  </div>

                  <div class="hz-caption text-horizon-offwhite">
                    Comms: {{ formatEnumLabel(op.operation_strictness, 'default') }} • VIS: {{ formatEnumLabel(op.visibility, 'open') }}
                  </div>
                </div>

                <div class="flex flex-col gap-2 w-full sm:w-auto sm:items-end">
                  <div class="flex flex-wrap gap-2 w-full sm:w-auto sm:justify-end">
                    <HorizonButton
                      v-if="canManageOperation(op)"
                      size="sm"
                      variant="primary"
                      class="w-20"
                      @click="openEditDrawer(op)"
                    >
                      Edit
                    </HorizonButton>

                    <HorizonButton
                      size="sm"
                      variant="primary"
                      class="w-20"
                      @click="openViewModal(op)"
                    >
                      View
                    </HorizonButton>
                  </div>

                  <div class="flex flex-wrap gap-2 w-full sm:w-auto sm:justify-end">
                    <HorizonButton
                      v-if="canManageOperation(op) && op.status === 'published'"
                      size="sm"
                      variant="primary"
                      class="w-20 hover:bg-(--color-state-success)! hover:border-(--color-state-success)!"
                      :disabled="isTransitionProcessing(op.id)"
                      @click="startOperation(op)"
                    >
                      Start
                    </HorizonButton>

                    <div
                      v-if="canManageOperation(op) && op.status === 'in_progress'"
                      class="w-28"
                    >
                      <HorizonSelect
                        v-model="completionOutcomeByOpId[op.id]"
                        :options="completionOutcomeOptions"
                      />
                      <HorizonButton
                        size="sm"
                        variant="primary"
                        class="mt-2 w-full hover:bg-(--color-state-success)! hover:border-(--color-state-success)!"
                        :disabled="isTransitionProcessing(op.id) || !completionOutcomeByOpId[op.id]"
                        @click="completeOperation(op, completionOutcomeByOpId[op.id])"
                      >
                        End
                      </HorizonButton>
                    </div>

                    <HorizonButton
                      v-if="canManageOperation(op) && ['published', 'in_progress'].includes(op.status)"
                      size="sm"
                      variant="danger"
                      class="w-20"
                      :disabled="isTransitionProcessing(op.id)"
                      @click="cancelOperation(op)"
                    >
                      Cancel
                    </HorizonButton>
                  </div>
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
        :prefill-template-id="editorPrefillTemplateId"
        @template-saved="handleTemplateSaved"
        @template-updated="handleTemplateUpdated"
        @template-deleted="handleTemplateDeleted"
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
            {{ (modalHeaderOperation?.operation_type ?? modalHeaderOperation?.operation_kind ?? 'operation') === 'operation' ? 'Operation' : 'Operation' }}
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
import { computed, ref, watch, onMounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import axios from 'axios'
import { route } from 'ziggy-js';
import { Ziggy } from '../../ziggy';

import HorizonContainer from '@/Components/HorizonContainer.vue';
import HorizonButton from '@/Components/HorizonButton.vue';
import HorizonInput from '@/Components/HorizonInput.vue';
import HorizonPanel from '@/Components/HorizonPanel.vue';
import HorizonSectionHeader from '@/Components/HorizonSectionHeader.vue';
import HorizonSelect from '@/Components/HorizonSelect.vue'
import MissionGrid from '@/Components/MissionGrid.vue';
import MissionCard from '@/Components/MissionCard.vue';
import OperationDrawer from '@/Pages/Operations/Components/OperationDrawer.vue'
import MissionEditorForm from '@/Pages/Operations/Components/MissionEditorForm.vue'
import OperationModal from '@/Pages/Operations/Components/OperationModal.vue'
import MissionShowPanel from '@/Pages/Operations/Components/MissionShowPanel.vue'

import { getHighestOrgRoleSlug, getOrgRoleColor } from '@/roleColors'

const page = usePage();
const props = defineProps({
  operations: {
    type: [Array, Object],
    required: true,
  },
  filters: {
    type: Object,
    default: () => ({}),
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
const editorPrefillTemplateId = ref(null)
const editHydrating = ref(false)
const editHydrationError = ref(null)

const templates = ref([])
const templatesLoading = ref(false)
const createTemplateId = ref('')

const transitionProcessingIds = ref(new Set())

const completionOutcomeByOpId = ref({})
const completionOutcomeOptions = [
  { label: 'Success', value: 'success' },
  { label: 'Failed', value: 'failed' },
]

function isTransitionProcessing(operationId) {
  return transitionProcessingIds.value.has(Number(operationId))
}

function setTransitionProcessing(operationId, value) {
  const id = Number(operationId)
  const next = new Set(transitionProcessingIds.value)
  if (value) next.add(id)
  else next.delete(id)
  transitionProcessingIds.value = next
}

const templateOptions = computed(() => {
  return (templates.value ?? []).map(t => {
    const scopeLabel = t.scope === 'personal'
      ? 'Personal'
      : t.scope === 'squadron'
        ? 'Squadron'
        : 'Global'

    return {
      label: `${scopeLabel}: ${t.name}`,
      value: t.id,
    }
  })
})

async function fetchTemplates() {
  if (templatesLoading.value) return
  templatesLoading.value = true

  try {
    const { data } = await axios.get('/api/v1/operation-templates', {
      headers: {
        Accept: 'application/json',
      },
    })

    templates.value = Array.isArray(data?.payload?.templates)
      ? data.payload.templates
      : []
  } catch (err) {
    const status = err?.response?.status ?? null
    if (status !== 401 && status !== 419) {
      console.error('Failed to load templates', err)
    }
  } finally {
    templatesLoading.value = false
  }
}

async function handleTemplateSaved(template) {
  const newId = template?.id
  await fetchTemplates()
  if (newId) {
    createTemplateId.value = newId
  }
}

async function handleTemplateUpdated(template) {
  const id = template?.id
  await fetchTemplates()
  if (id) {
    createTemplateId.value = id
  }
}

async function handleTemplateDeleted(payload) {
  const deletedId = payload?.id
  await fetchTemplates()

  if (deletedId && Number(createTemplateId.value) === Number(deletedId)) {
    createTemplateId.value = ''
  }
}

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
  const prefix = operationTitlePrefix(op?.operation_type ?? op?.operation_kind)
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

function getCurrentQueryParams() {
  const url = new URL(window.location.href)
  const out = {}

  for (const [key, value] of url.searchParams.entries()) {
    out[key] = value
  }

  return out
}

function refreshOperations({ resetPage = false } = {}) {
  const query = {
    ...getCurrentQueryParams(),
    status: statusFilter.value,
    search: search.value,
  }

  if (resetPage) {
    delete query.page
  }

  if (!query.search) delete query.search
  if (!query.status) delete query.status

  router.get(route('operations.index', {}, Ziggy), query, {
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
  editorPrefillTemplateId.value = null
  editHydrating.value = false
  editHydrationError.value = null
  drawerOpen.value = true
}

function openCreateDrawerFromTemplate() {
  const id = createTemplateId.value
  if (!id) return

  const template = (templates.value ?? []).find(t => Number(t?.id) === Number(id))

  editingMission.value = null
  editorSquadronId.value = template?.squadron_id ?? userSquadronId.value
  editorPrefillTemplateId.value = id
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
  editorPrefillTemplateId.value = null
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

  const roles = user.value?.roles ?? [];
  const officerRoleSlugs = ['lieutenant', 'cit', 'commander', 'wing_commander', 'admiral', 'grand_admiral'];
  if (roles.some(r => officerRoleSlugs.includes(r?.slug))) return true;

  return false;
});

onMounted(() => {
  if (canCreateOperation.value) {
    fetchTemplates()
  }
})

function canManageOperation(op) {
  if (isDirectorLike.value) return true;

  const squadronId = op?.squadron?.id;
  if (!squadronId) {
    const creatorId = op?.creator?.id ?? op?.created_by ?? null
    if (!creatorId || Number(creatorId) !== Number(user.value?.id)) {
      return false
    }

    return canCreateOperation.value
  }

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

function creatorNameColor(op) {
  const creator = op?.creator ?? null
  const slug = getHighestOrgRoleSlug(creator?.roles, creator?.rank)
  return getOrgRoleColor(slug)
}

async function startOperation(op) {
  if (!op?.id) return
  if (isTransitionProcessing(op.id)) return
  if (!confirm('Start this operation?')) return

  setTransitionProcessing(op.id, true)
  try {
    await axios.post(route('operations.start', op.id, Ziggy), {})
    refreshOperations()
  } catch (err) {
    const status = err?.response?.status ?? null
    if (status !== 401 && status !== 419) {
      console.error(err)
      window.hzNotifyError({ message: 'Failed to start operation.' })
    }
  } finally {
    setTransitionProcessing(op.id, false)
  }
}

async function completeOperation(op, outcome) {
  if (!op?.id) return
  if (isTransitionProcessing(op.id)) return
  if (!outcome) return
  if (!confirm('Mark this operation as completed?')) return

  setTransitionProcessing(op.id, true)
  try {
    await axios.post(route('operations.complete', op.id, Ziggy), {
      outcome,
    })
    refreshOperations()
  } catch (err) {
    const status = err?.response?.status ?? null
    if (status !== 401 && status !== 419) {
      console.error(err)
      window.hzNotifyError({ message: 'Failed to complete operation.' })
    }
  } finally {
    setTransitionProcessing(op.id, false)
  }
}

async function cancelOperation(op) {
  if (!op?.id) return
  if (isTransitionProcessing(op.id)) return

  const reason = prompt('Cancellation reason (optional):')
  if (reason === null) return
  if (!confirm('Cancel this operation?')) return

  setTransitionProcessing(op.id, true)
  try {
    await axios.post(route('operations.cancel', op.id, Ziggy), {
      reason: reason || null,
    })
    refreshOperations()
  } catch (err) {
    const status = err?.response?.status ?? null
    if (status !== 401 && status !== 419) {
      console.error(err)
      window.hzNotifyError({ message: 'Failed to cancel operation.' })
    }
  } finally {
    setTransitionProcessing(op.id, false)
  }
}

/* ----------------------------------------
   FILTERING
---------------------------------------- */

const statusFilters = [
  { label: 'Active', value: 'active' },
  { label: 'All', value: 'all' },
  { label: 'Draft', value: 'draft' },
  { label: 'Published', value: 'published' },
  { label: 'In Progress', value: 'in_progress' },
  { label: 'Completed', value: 'completed' },
  { label: 'Canceled', value: 'canceled' },
];

const statusFilter = ref(props.filters?.status ?? 'active');
const search = ref(props.filters?.search ?? '');

const filteredOperations = computed(() => {
  return operationsList.value.filter(op => {
    const matchesStatus =
      statusFilter.value === 'all'
        ? true
        : statusFilter.value === 'active'
          ? ['published', 'in_progress'].includes(op.status)
          : op.status === statusFilter.value;

    const q = search.value.toLowerCase();
    const matchesSearch =
      !q ||
      String(op.id).toLowerCase().includes(q) ||
      op.title.toLowerCase().includes(q) ||
      (op.description || '').toLowerCase().includes(q) ||
      (op.squadron?.name ?? '').toLowerCase().includes(q) ||
      (op.creator?.rsi_handle ?? '').toLowerCase().includes(q);

    return matchesStatus && matchesSearch;
  });
});

let filterRefreshTimeout = null
function queueRefreshOperations() {
  if (filterRefreshTimeout) {
    clearTimeout(filterRefreshTimeout)
  }

  filterRefreshTimeout = setTimeout(() => {
    refreshOperations({ resetPage: true })
  }, 220)
}

watch(statusFilter, () => {
  queueRefreshOperations()
})

watch(search, () => {
  queueRefreshOperations()
})

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

function formatEnumLabel(value, fallback) {
  const raw = value ?? fallback
  const normalized = String(raw)
    .replace(/[_-]+/g, ' ')
    .replace(/\s+/g, ' ')
    .trim()

  if (!normalized) return ''

  return normalized
    .split(' ')
    .map(w => (w ? w[0].toUpperCase() + w.slice(1).toLowerCase() : ''))
    .join(' ')
}

function formatDate(value) {
  if (!value) return 'TBD';
  return String(value);
}
</script>
