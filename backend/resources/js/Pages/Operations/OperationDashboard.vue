<script setup>
import { computed, ref, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { Ziggy } from '../../ziggy'

import HorizonContainer from '@/Components/HorizonContainer.vue'
import HorizonButton from '@/Components/HorizonButton.vue'
import HorizonConfirmDialog from '@/Components/HorizonConfirmDialog.vue'
import HorizonInput from '@/Components/HorizonInput.vue'
import HorizonSelect from '@/Components/HorizonSelect.vue'
import OperationDrawer from '@/Pages/Operations/Components/OperationDrawer.vue'
import MissionEditorForm from '@/Pages/Operations/Components/MissionEditorForm.vue'
import OperationModal from '@/Pages/Operations/Components/OperationModal.vue'
import MissionShowPanel from '@/Pages/Operations/Components/MissionShowPanel.vue'
import { canCreateOperation as userCanCreateOperation, isDirectorLike as userIsDirectorLike } from '@/auth'
import { getHighestOrgRoleSlug, getOrgRoleColor } from '@/roleColors'

const page = usePage()

const props = defineProps({
  operations: {
    type: [Array, Object],
    required: true,
  },
  filters: {
    type: Object,
    default: () => ({}),
  },
})

const todayLabel = computed(() => {
  return new Date().toLocaleDateString(undefined, {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  })
})

const createDrawerOpen = ref(false)
const createEditorSquadronId = ref(null)
const editorPrefillTemplateId = ref(null)

const templates = computed(() => page.props?.operationTemplates ?? [])
const activeOperation = computed(() => page.props?.activeOperation ?? null)
const editingOperation = computed(() => page.props?.editingOperation ?? null)
const drawerOpen = computed(() => createDrawerOpen.value || editingOperation.value !== null)
const editingMission = computed(() => editingOperation.value?.mission ?? null)
const editorSquadronId = computed(() =>
  editingOperation.value?.squadronId ?? createEditorSquadronId.value
)

const createTemplateId = ref('')
const transitionProcessingIds = ref(new Set())

const startConfirmDialog = ref(null)
const pendingStartOperation = ref(null)

const completeConfirmDialog = ref(null)
const pendingCompleteOperation = ref(null)
const pendingCompleteOutcome = ref(null)

const cancelConfirmDialog = ref(null)
const pendingCancelOperation = ref(null)

const completionOutcomeByOpId = ref({})

const completionOutcomeOptions = [
  { label: 'Success', value: 'success' },
  { label: 'Failed', value: 'failed' },
]

const startConfirmMessage = computed(() => {
  const op = pendingStartOperation.value

  if (!op) {
    return 'You are about to start this operation.'
  }

  return `You are about to mark "${op.title}" as In Progress.`
})

const completeConfirmMessage = computed(() => {
  const op = pendingCompleteOperation.value

  if (!op) {
    return 'You are about to complete this operation.'
  }

  return `Mark "${op.title}" as completed?`
})

function isTransitionProcessing(operationId) {
  return transitionProcessingIds.value.has(Number(operationId))
}

function setTransitionProcessing(operationId, value) {
  const id = Number(operationId)
  const next = new Set(transitionProcessingIds.value)

  if (value) {
    next.add(id)
  } else {
    next.delete(id)
  }

  transitionProcessingIds.value = next
}

const templateOptions = computed(() => {
  return (templates.value ?? []).map(template => {
    const scopeLabel = template.scope === 'personal'
      ? 'Personal'
      : template.scope === 'squadron'
        ? 'Squadron'
        : 'Global'

    return {
      label: `${scopeLabel}: ${template.name}`,
      value: template.id,
    }
  })
})

function handleTemplateSaved(template) {
  const newId = template?.id

  if (newId) {
    createTemplateId.value = newId
  }
}

function handleTemplateUpdated(template) {
  const id = template?.id

  if (id) {
    createTemplateId.value = id
  }
}

function handleTemplateDeleted(payload) {
  const deletedId = payload?.id

  if (deletedId && Number(createTemplateId.value) === Number(deletedId)) {
    createTemplateId.value = ''
  }
}

const modalHeaderOperation = computed(() => {
  return activeOperation.value?.operation ?? null
})

function operationTitlePrefix(kind) {
  switch (kind) {
    case 'squadron_training': return 'Squadron Training'
    case 'wing_training': return 'Wing Training'
    default: return ''
  }
}

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

function operationDisplayTitle(op) {
  const title = op?.title ?? ''
  const prefix = operationTitlePrefix(op?.operation_type ?? op?.operation_kind)

  return prefix ? `${prefix}: ${title}` : title
}

const operationsPaginator = computed(() => {
  return Array.isArray(props.operations) ? null : props.operations
})

const operationsList = computed(() => {
  if (Array.isArray(props.operations)) {
    return props.operations ?? []
  }

  return props.operations?.data ?? []
})

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
  if (!url) return

  router.get(url, {}, {
    preserveScroll: true,
    preserveState: true,
  })
}

function visitDashboard(query, only = ['operations', 'activeOperation', 'editingOperation']) {
  router.get(route('operations.index', {}, Ziggy), query, {
    only,
    preserveScroll: true,
    preserveState: true,
  })
}

function openCreateDrawer() {
  createDrawerOpen.value = true
  createEditorSquadronId.value = userSquadronId.value
  editorPrefillTemplateId.value = null

  const query = {
    ...getCurrentQueryParams(),
  }

  delete query.edit

  visitDashboard(query, ['editingOperation'])
}

function openCreateDrawerFromTemplate() {
  const id = createTemplateId.value
  if (!id) return

  const template = (templates.value ?? []).find(item => Number(item?.id) === Number(id))

  createDrawerOpen.value = true
  createEditorSquadronId.value = template?.squadron_id ?? userSquadronId.value
  editorPrefillTemplateId.value = id

  const query = {
    ...getCurrentQueryParams(),
  }

  delete query.edit

  visitDashboard(query, ['editingOperation'])
}

function openEditDrawer(op) {
  createDrawerOpen.value = false
  createEditorSquadronId.value = null
  editorPrefillTemplateId.value = null

  const query = {
    ...getCurrentQueryParams(),
    edit: op.id,
  }

  delete query.operation

  visitDashboard(query, ['editingOperation'])
}

function closeDrawer() {
  createDrawerOpen.value = false
  createEditorSquadronId.value = null
  editorPrefillTemplateId.value = null

  if (!editingOperation.value) return

  const query = {
    ...getCurrentQueryParams(),
  }

  delete query.edit

  visitDashboard(query, ['editingOperation'])
}

function handleDrawerSaved(payload) {
  const operationId = payload?.id
  if (!operationId) return

  createDrawerOpen.value = false
  createEditorSquadronId.value = null
  editorPrefillTemplateId.value = null

  const query = {
    ...getCurrentQueryParams(),
    operation: operationId,
  }

  delete query.edit

  visitDashboard(query)
}

function handleDrawerDeleted() {
  createDrawerOpen.value = false
  createEditorSquadronId.value = null
  editorPrefillTemplateId.value = null

  const query = {
    ...getCurrentQueryParams(),
  }

  delete query.edit

  visitDashboard(query)
}

function openViewModal(op) {
  const query = {
    ...getCurrentQueryParams(),
    operation: op.id,
  }

  delete query.edit

  visitDashboard(query, ['activeOperation'])
}

function closeViewModal() {
  const query = {
    ...getCurrentQueryParams(),
  }

  delete query.operation

  visitDashboard(query, ['activeOperation'])
}

/* ----------------------------------------
   User + permissions
---------------------------------------- */

const user = computed(() => page.props.auth?.user ?? null)

const userSquadronId = computed(() => {
  const u = user.value
  if (!u?.squadrons?.length) return null

  const lt = u.squadrons.find(squadron => squadron.pivot?.role === 'lieutenant')
  if (lt) return lt.id

  const leader = u.squadrons.find(squadron => squadron.pivot?.role === 'leader')
  if (leader) return leader.id

  return u.squadrons[0]?.id ?? null
})

const isDirectorLike = computed(() => {
  return userIsDirectorLike(user.value)
})

const canCreateOperation = computed(() => {
  return userCanCreateOperation(user.value)
})

function canManageOperation(op) {
  if (isDirectorLike.value) return true

  const squadronId = op?.squadron?.id

  if (!squadronId) {
    const creatorId = op?.creator?.id ?? op?.created_by ?? null

    if (!creatorId || Number(creatorId) !== Number(user.value?.id)) {
      return false
    }

    return canCreateOperation.value
  }

  const squadronLeaderId = op?.squadron?.leader?.id

  if (squadronLeaderId && squadronLeaderId === user.value?.id) {
    return true
  }

  const creatorId = op?.creator?.id ?? op?.created_by ?? null
  const membership = user.value?.squadrons?.find(squadron => Number(squadron?.id) === Number(squadronId))
  if (!membership) return false

  const membershipStatus = membership.pivot?.membership_status
  if (membershipStatus && membershipStatus !== 'active') return false

  if (creatorId && Number(creatorId) === Number(user.value?.id)) {
    return canCreateOperation.value
  }

  const role = membership.pivot?.role

  return role === 'leader' || role === 'lieutenant'
}

function creatorNameColor(op) {
  const creator = op?.creator ?? null
  const slug = getHighestOrgRoleSlug(creator?.roles, creator?.rank)

  return getOrgRoleColor(slug)
}

function creatorName(op) {
  return op?.creator?.rsi_handle
    ?? op?.creator?.display_name
    ?? op?.creator?.name
    ?? 'TBD'
}

function creatorAvatar(op) {
  return op?.creator?.discord_avatar
    ?? op?.creator?.avatar
    ?? null
}

function creatorInitial(op) {
  return String(creatorName(op) ?? 'C').slice(0, 1).toUpperCase()
}

/* ----------------------------------------
   Lifecycle actions
---------------------------------------- */

function askStartOperation(op) {
  pendingStartOperation.value = op
  startConfirmDialog.value?.show()
}

function confirmStartOperation({ close, finish }) {
  const op = pendingStartOperation.value

  if (!op?.id) {
    finish()
    return
  }

  setTransitionProcessing(op.id, true)

  router.post(route('operations.start', op.id, Ziggy), {}, {
    preserveScroll: true,
    preserveState: true,
    only: ['operations', 'activeOperation'],

    onSuccess: () => {
      close()
      pendingStartOperation.value = null
    },

    onError: () => {
      window.hzNotifyError({ message: 'Failed to start operation.' })
      finish()
    },

    onFinish: () => {
      setTransitionProcessing(op.id, false)
    },
  })
}

function askCompleteOperation(op, outcome) {
  if (!op?.id) return
  if (isTransitionProcessing(op.id)) return
  if (!outcome) return

  pendingCompleteOperation.value = op
  pendingCompleteOutcome.value = outcome
  completeConfirmDialog.value?.show()
}

function confirmCompleteOperation({ close, finish }) {
  const op = pendingCompleteOperation.value
  const outcome = pendingCompleteOutcome.value

  if (!op?.id || !outcome) {
    finish()
    return
  }

  setTransitionProcessing(op.id, true)

  router.post(route('operations.complete', op.id, Ziggy), {
    outcome,
  }, {
    preserveScroll: true,
    preserveState: true,
    only: ['operations', 'activeOperation'],

    onSuccess: () => {
      close()
      pendingCompleteOperation.value = null
      pendingCompleteOutcome.value = null
    },

    onError: () => {
      window.hzNotifyError({ message: 'Failed to complete operation.' })
      finish()
    },

    onFinish: () => {
      setTransitionProcessing(op.id, false)
    },
  })
}

function askCancelOperation(op) {
  if (!op?.id) return
  if (isTransitionProcessing(op.id)) return

  pendingCancelOperation.value = op
  cancelConfirmDialog.value?.show()
}

function confirmCancelOperation({ close, finish, text }) {
  const op = pendingCancelOperation.value

  if (!op?.id) {
    finish()
    return
  }

  setTransitionProcessing(op.id, true)

  router.post(route('operations.cancel', op.id, Ziggy), {
    reason: text || null,
  }, {
    preserveScroll: true,
    preserveState: true,
    only: ['operations', 'activeOperation'],

    onSuccess: () => {
      close()
      pendingCancelOperation.value = null
    },

    onError: () => {
      window.hzNotifyError({ message: 'Failed to cancel operation.' })
      finish()
    },

    onFinish: () => {
      setTransitionProcessing(op.id, false)
    },
  })
}

/* ----------------------------------------
   Filtering
---------------------------------------- */

const statusFilters = [
  { label: 'Active', value: 'active' },
  { label: 'All', value: 'all' },
  { label: 'Draft', value: 'draft' },
  { label: 'Published', value: 'published' },
  { label: 'In Progress', value: 'in_progress' },
  { label: 'Completed', value: 'completed' },
  { label: 'Canceled', value: 'canceled' },
]

const statusFilter = ref(props.filters?.status ?? 'active')
const search = ref(props.filters?.search ?? '')

const filteredOperations = computed(() => {
  return operationsList.value.filter(op => {
    const matchesStatus =
      statusFilter.value === 'all'
        ? true
        : statusFilter.value === 'active'
          ? ['published', 'in_progress'].includes(op.status)
          : op.status === statusFilter.value

    const q = search.value.toLowerCase()

    const matchesSearch =
      !q ||
      String(op.id).toLowerCase().includes(q) ||
      (op.title ?? '').toLowerCase().includes(q) ||
      (op.description ?? '').toLowerCase().includes(q) ||
      (op.squadron?.name ?? '').toLowerCase().includes(q) ||
      (op.creator?.rsi_handle ?? '').toLowerCase().includes(q)

    return matchesStatus && matchesSearch
  })
})

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
   Stats
---------------------------------------- */

const activeCount = computed(() =>
  operationsList.value.filter(op =>
    ['published', 'in_progress'].includes(op.status)
  ).length
)

const draftCount = computed(() =>
  operationsList.value.filter(op => op.status === 'draft').length
)

const completedCount = computed(() =>
  operationsList.value.filter(op => op.status === 'completed').length
)

const plannedCount = computed(() =>
  operationsList.value.filter(op => op.status === 'published').length
)

const operationsTotalCount = computed(() => {
  return operationsPaginator.value?.total ?? operationsList.value.length
})

const statusFilterLabel = computed(() => {
  return statusFilters.find(item => item.value === statusFilter.value)?.label ?? 'All'
})

/* ----------------------------------------
   Display helpers
---------------------------------------- */

function formatEnumLabel(value, fallback) {
  const raw = value ?? fallback

  const normalized = String(raw)
    .replace(/[_-]+/g, ' ')
    .replace(/\s+/g, ' ')
    .trim()

  if (!normalized) return ''

  return normalized
    .split(' ')
    .map(word => (word ? word[0].toUpperCase() + word.slice(1).toLowerCase() : ''))
    .join(' ')
}

function toDate(value) {
  if (!value) return null

  let normalized = String(value).trim()

  normalized = normalized.replace(' ', 'T')
  normalized = normalized.replace(/\.(\d{3})\d+Z$/i, '.$1Z')
  normalized = normalized.replace(/\.(\d{3})\d+$/i, '.$1')

  const hasTimezone = /([zZ]|[+-]\d{2}:\d{2})$/.test(normalized)
  if (!hasTimezone) normalized = `${normalized}Z`

  const date = new Date(normalized)

  return Number.isNaN(date.getTime()) ? null : date
}

function formatDate(value) {
  const date = toDate(value)

  if (!date) return 'TBD'

  const dateText = new Intl.DateTimeFormat('en-GB', {
    weekday: 'short',
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  }).format(date)

  const timeText = new Intl.DateTimeFormat('en-GB', {
    hour: '2-digit',
    minute: '2-digit',
    hour12: true,
  }).format(date)

  return `${dateText} ${timeText}`
}

function statusCardClass(status) {
  switch (status) {
    case 'draft':
      return 'border-white/10 bg-white/[0.035]'
    case 'published':
      return 'border-[color:var(--horizon-sunset-blue)]/30 bg-[color:var(--horizon-sunset-blue)]/10'
    case 'in_progress':
      return 'border-emerald-300/25 bg-emerald-300/10'
    case 'completed':
      return 'border-[color:var(--horizon-sunset-indigo)]/30 bg-[color:var(--horizon-sunset-indigo)]/10'
    case 'canceled':
      return 'border-red-300/25 bg-red-300/10'
    default:
      return 'border-white/10 bg-white/[0.035]'
  }
}
</script>

<template>
  <HorizonContainer class="py-8 md:py-10">
    <div class="mx-auto max-w-6xl space-y-8">
      <!-- Command header -->
      <section class="relative z-30 overflow-visible rounded-[2rem] border border-[color:var(--horizon-sunset-indigo)]/45 bg-[radial-gradient(circle_at_top_left,var(--horizon-glow-blue),transparent_34%),radial-gradient(circle_at_top_right,var(--horizon-glow-magenta),transparent_32%),linear-gradient(135deg,var(--horizon-void-600),var(--horizon-void-900))] p-6 shadow-[0_0_48px_rgba(67,56,202,0.18)]">
        <div class="pointer-events-none absolute inset-0 opacity-40">
          <div class="absolute left-8 top-0 h-px w-48 bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-blue)] to-transparent"></div>
          <div class="absolute bottom-0 right-10 h-px w-64 bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-magenta)] to-transparent"></div>
        </div>

        <div class="relative grid gap-6 xl:grid-cols-[minmax(0,1fr)_auto] xl:items-end">
          <div class="min-w-0">
            <div class="text-xs font-bold uppercase tracking-[0.28em] text-[color:var(--horizon-text-secondary)]">
              Horizon Officer Command
            </div>

            <h1 class="mt-2 text-3xl font-black tracking-tight text-horizon-white md:text-5xl">
              Operations Dashboard
            </h1>

            <p class="mt-3 max-w-3xl text-sm text-text-secondary md:text-base">
              Create, edit, start, complete, cancel, and inspect Horizon operations from one command board.
            </p>

            <div class="mt-4 flex flex-wrap gap-2">
              <span class="rounded-full border border-[color:var(--horizon-sunset-blue)]/30 bg-[color:var(--horizon-sunset-blue)]/10 px-3 py-1 text-xs font-semibold text-[color:var(--horizon-text-primary)]">
                {{ operationsTotalCount }} Total
              </span>

              <span class="rounded-full border border-[color:var(--horizon-sunset-magenta)]/30 bg-[color:var(--horizon-sunset-magenta)]/10 px-3 py-1 text-xs font-semibold text-[color:var(--horizon-text-primary)]">
                {{ statusFilterLabel }} View
              </span>

              <span class="rounded-full border border-white/10 bg-white/[0.035] px-3 py-1 text-xs font-semibold text-text-secondary">
                {{ todayLabel }}
              </span>
            </div>
          </div>

          <div v-if="canCreateOperation" class="relative z-[9999] grid gap-3 xl:min-w-[34rem] xl:grid-cols-[minmax(0,1fr)_auto]">
            <div class="min-w-0">
              <HorizonSelect
                v-model="createTemplateId"
                :options="templateOptions"
              />
            </div>

            <div class="flex flex-col gap-2 sm:flex-row xl:flex-col">
              <HorizonButton
                variant="ghost"
                size="md"
                :disabled="!createTemplateId"
                class="w-full whitespace-nowrap"
                @click="openCreateDrawerFromTemplate"
              >
                From Template
              </HorizonButton>

              <HorizonButton
                variant="primary"
                size="md"
                class="w-full whitespace-nowrap"
                @click="openCreateDrawer"
              >
                Create Operation
              </HorizonButton>
            </div>
          </div>
        </div>
      </section>

      <!-- Stats strip -->
      <section class="grid gap-4 md:grid-cols-4">
        <div class="rounded-[1.5rem] border border-[color:var(--horizon-sunset-blue)]/25 bg-[linear-gradient(135deg,rgba(30,64,175,0.14),rgba(255,255,255,0.025))] p-5 shadow-[0_0_24px_rgba(30,64,175,0.10)]">
          <div class="text-xs font-bold uppercase tracking-[0.2em] text-text-muted">
            Active
          </div>
          <div class="mt-2 text-3xl font-black text-horizon-white">
            {{ activeCount }}
          </div>
          <div class="mt-1 text-sm text-text-secondary">
            Published or live
          </div>
        </div>

        <div class="rounded-[1.5rem] border border-white/10 bg-white/[0.035] p-5">
          <div class="text-xs font-bold uppercase tracking-[0.2em] text-text-muted">
            Drafts
          </div>
          <div class="mt-2 text-3xl font-black text-horizon-white">
            {{ draftCount }}
          </div>
          <div class="mt-1 text-sm text-text-secondary">
            In planning
          </div>
        </div>

        <div class="rounded-[1.5rem] border border-[color:var(--horizon-sunset-indigo)]/25 bg-[linear-gradient(135deg,rgba(67,56,202,0.14),rgba(255,255,255,0.025))] p-5 shadow-[0_0_24px_rgba(67,56,202,0.10)]">
          <div class="text-xs font-bold uppercase tracking-[0.2em] text-text-muted">
            Published
          </div>
          <div class="mt-2 text-3xl font-black text-horizon-white">
            {{ plannedCount }}
          </div>
          <div class="mt-1 text-sm text-text-secondary">
            Ready to run
          </div>
        </div>

        <div class="rounded-[1.5rem] border border-[color:var(--horizon-sunset-magenta)]/25 bg-[radial-gradient(circle_at_top_right,var(--horizon-glow-magenta),transparent_46%),rgba(255,255,255,0.035)] p-5">
          <div class="text-xs font-bold uppercase tracking-[0.2em] text-text-muted">
            Completed
          </div>
          <div class="mt-2 text-3xl font-black text-horizon-white">
            {{ completedCount }}
          </div>
          <div class="mt-1 text-sm text-text-secondary">
            Archived results
          </div>
        </div>
      </section>

      <!-- Filters -->
      <section class="rounded-[2rem] border border-[color:var(--horizon-sunset-blue)]/25 bg-[color:var(--horizon-void-700)]/70 p-5 shadow-[0_0_32px_rgba(30,64,175,0.10)]">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
          <div class="min-w-0">
            <div class="text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--horizon-text-secondary)]">
              Command Filters
            </div>

            <h2 class="mt-1 text-xl font-black text-horizon-white">
              Operation Registry
            </h2>

            <p class="mt-1 text-sm text-text-secondary">
              Filter by lifecycle state or search by title, description, ID, squadron, or creator.
            </p>
          </div>

          <div class="w-full lg:max-w-md">
            <HorizonInput
              v-model="search"
              label="Search"
              placeholder="Title, description, ID, squadron, creator..."
            />
          </div>
        </div>

        <div class="mt-5 flex flex-wrap gap-2">
          <HorizonButton
            v-for="status in statusFilters"
            :key="status.value"
            size="xs"
            :variant="statusFilter === status.value ? 'primary' : 'ghost'"
            @click="statusFilter = status.value"
          >
            {{ status.label }}
          </HorizonButton>
        </div>
      </section>

      <!-- Operation command cards -->
      <section class="rounded-[2rem] border border-[color:var(--horizon-sunset-blue)]/25 bg-[color:var(--horizon-void-700)]/70 p-4 shadow-[0_0_32px_rgba(30,64,175,0.10)] md:p-5">
        <div class="mb-5 flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
          <div>
            <div class="text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--horizon-text-secondary)]">
              Command Board
            </div>

            <h2 class="mt-1 text-xl font-black text-horizon-white">
              {{ filteredOperations.length }} Visible Operations
            </h2>
          </div>

          <div class="rounded-full border border-white/10 bg-white/[0.035] px-3 py-1 text-xs font-bold uppercase tracking-[0.16em] text-text-muted">
            {{ statusFilterLabel }}
          </div>
        </div>

        <div v-if="filteredOperations.length" class="grid gap-4 xl:grid-cols-2">
          <article
            v-for="op in filteredOperations"
            :key="op.id"
            class="group relative overflow-visible rounded-[1.75rem] border bg-[linear-gradient(135deg,rgba(30,64,175,0.10),var(--horizon-void-700)_42%,var(--horizon-void-900))] p-5 shadow-[0_0_28px_rgba(30,64,175,0.10)] transition duration-200 hover:-translate-y-0.5 hover:border-[color:var(--horizon-sunset-magenta)]/40 hover:shadow-[0_0_42px_rgba(192,38,211,0.14)]"
            :class="statusCardClass(op.status)"
          >
            <div class="pointer-events-none absolute inset-0 opacity-0 transition duration-200 group-hover:opacity-100">
              <div class="absolute inset-x-8 top-0 h-px bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-blue)] to-transparent"></div>
              <div class="absolute inset-x-10 bottom-0 h-px bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-magenta)] to-transparent"></div>
            </div>

            <div class="relative space-y-5">
              <div class="flex flex-wrap items-center gap-2">
                <span class="rounded-full border border-[color:var(--horizon-sunset-blue)]/25 bg-[color:var(--horizon-sunset-blue)]/10 px-3 py-1 text-[11px] font-bold uppercase tracking-[0.18em] text-[color:var(--horizon-text-primary)]">
                  #{{ op.id }}
                </span>

                <span class="rounded-full border border-white/10 bg-white/[0.035] px-3 py-1 text-[11px] font-bold uppercase tracking-[0.18em] text-text-secondary">
                  {{ operationKindLabel(op.operation_type ?? op.operation_kind) }}
                </span>

                <span class="rounded-full border border-white/10 bg-white/[0.035] px-3 py-1 text-[11px] font-bold uppercase tracking-[0.18em] text-text-secondary">
                  {{ formatEnumLabel(op.status, 'draft') }}
                </span>
              </div>

              <div>
                <h3 class="text-2xl font-black tracking-tight text-horizon-white">
                  {{ operationDisplayTitle(op) }}
                </h3>

                <p
                  v-if="op.description"
                  class="mt-2 line-clamp-2 text-sm leading-6 text-text-secondary"
                >
                  {{ op.description }}
                </p>

                <p
                  v-else
                  class="mt-2 text-sm leading-6 text-text-secondary"
                >
                  No description provided.
                </p>
              </div>

              <div class="grid gap-3 md:grid-cols-2">
                <div class="rounded-2xl border border-white/10 bg-white/[0.025] p-3">
                  <div class="text-xs uppercase tracking-wide text-text-muted">
                    Starts
                  </div>
                  <div class="mt-1 text-sm font-semibold text-horizon-white">
                    {{ formatDate(op.starts_at) }}
                  </div>
                </div>

                <div class="rounded-2xl border border-white/10 bg-white/[0.025] p-3">
                  <div class="text-xs uppercase tracking-wide text-text-muted">
                    Ends
                  </div>
                  <div class="mt-1 text-sm font-semibold text-horizon-white">
                    {{ op.ends_at ? formatDate(op.ends_at) : 'TBD' }}
                  </div>
                </div>
              </div>

              <div class="grid gap-3 md:grid-cols-2">
                <div class="rounded-2xl border border-white/10 bg-white/[0.025] p-3">
                  <div class="text-xs uppercase tracking-wide text-text-muted">
                    Squadron
                  </div>
                  <div class="mt-1 truncate text-sm font-semibold text-horizon-white">
                    {{ op.squadron?.name ?? 'Global / TBD' }}
                  </div>
                </div>

                <div class="rounded-2xl border border-white/10 bg-white/[0.025] p-3">
                  <div class="text-xs uppercase tracking-wide text-text-muted">
                    Creator
                  </div>

                  <div class="mt-2 flex min-w-0 items-center gap-2">
                    <img
                      v-if="creatorAvatar(op)"
                      :src="creatorAvatar(op)"
                      alt=""
                      class="h-7 w-7 shrink-0 rounded-lg object-cover"
                      loading="lazy"
                    />

                    <div
                      v-else
                      class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-white/[0.06] text-xs font-black text-horizon-white"
                    >
                      {{ creatorInitial(op) }}
                    </div>

                    <span
                      class="truncate text-sm font-semibold"
                      :style="creatorNameColor(op) ? { color: creatorNameColor(op) } : undefined"
                    >
                      {{ creatorName(op) }}
                    </span>
                  </div>
                </div>
              </div>

              <div class="flex flex-wrap gap-2">
                <span class="rounded-full border border-white/10 bg-white/[0.035] px-3 py-1 text-xs font-semibold text-text-secondary">
                  {{ formatEnumLabel(op.operation_strictness, 'default') }} Comms
                </span>

                <span class="rounded-full border border-white/10 bg-white/[0.035] px-3 py-1 text-xs font-semibold text-text-secondary">
                  {{ formatEnumLabel(op.visibility, 'open') }} Visibility
                </span>
              </div>

              <div class="flex flex-col gap-3 border-t border-white/10 pt-4">
                <div class="flex flex-wrap gap-2">
                  <HorizonButton
                    v-if="canManageOperation(op)"
                    size="sm"
                    variant="ghost"
                    class="min-w-24"
                    @click="openEditDrawer(op)"
                  >
                    Edit
                  </HorizonButton>

                  <HorizonButton
                    size="sm"
                    variant="primary"
                    class="min-w-24"
                    @click="openViewModal(op)"
                  >
                    View
                  </HorizonButton>

                  <HorizonButton
                    v-if="canManageOperation(op) && op.status === 'published'"
                    size="sm"
                    variant="primary"
                    class="min-w-24 hover:bg-[color:var(--color-state-success)]! hover:border-[color:var(--color-state-success)]!"
                    :disabled="isTransitionProcessing(op.id)"
                    @click="askStartOperation(op)"
                  >
                    {{ isTransitionProcessing(op.id) ? 'Starting…' : 'Start' }}
                  </HorizonButton>

                  <HorizonButton
                    v-if="canManageOperation(op) && ['published', 'in_progress'].includes(op.status)"
                    size="sm"
                    variant="danger"
                    class="min-w-24"
                    :disabled="isTransitionProcessing(op.id)"
                    @click="askCancelOperation(op)"
                  >
                    Cancel
                  </HorizonButton>
                </div>

                <div
                  v-if="canManageOperation(op) && op.status === 'in_progress'"
                  class="grid gap-2 rounded-2xl border border-emerald-300/20 bg-emerald-300/5 p-3 sm:grid-cols-2"
                >
                  <HorizonButton
                    size="sm"
                    variant="primary"
                    class="w-full hover:bg-[color:var(--color-state-success)]! hover:border-[color:var(--color-state-success)]!"
                    :disabled="isTransitionProcessing(op.id)"
                    @click="askCompleteOperation(op, 'success')"
                  >
                    {{ isTransitionProcessing(op.id) ? 'Ending…' : 'End Success' }}
                  </HorizonButton>

                  <HorizonButton
                    size="sm"
                    variant="ghost"
                    class="w-full border-red-300/25! bg-red-300/10! text-red-100! hover:bg-red-300/15!"
                    :disabled="isTransitionProcessing(op.id)"
                    @click="askCompleteOperation(op, 'failed')"
                  >
                    {{ isTransitionProcessing(op.id) ? 'Ending…' : 'End Failed' }}
                  </HorizonButton>
                </div>
              </div>
            </div>
          </article>
        </div>

        <div
          v-else
          class="rounded-[1.5rem] border border-dashed border-white/15 bg-white/[0.025] p-10 text-center"
        >
          <div class="text-2xl font-black text-horizon-white">
            No Operations Found
          </div>

          <p class="mx-auto mt-2 max-w-xl text-sm text-text-secondary">
            Adjust the filters or create the first operation for this command view.
          </p>
        </div>

        <div
          v-if="operationsPaginator && operationsPaginator.last_page > 1"
          class="mt-6 flex items-center justify-between border-t border-white/10 pt-5"
        >
          <HorizonButton
            size="sm"
            variant="ghost"
            :disabled="!operationsPaginator.prev_page_url"
            @click="goToUrl(operationsPaginator.prev_page_url)"
          >
            Prev
          </HorizonButton>

          <div class="text-xs font-semibold uppercase tracking-[0.16em] text-text-muted">
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
      </section>

      <OperationDrawer
        v-if="drawerOpen"
        @close="closeDrawer"
      >
        <template #header>
          <div>
            <div class="text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--horizon-text-secondary)]">
              {{ editingMission ? 'Edit Operation' : 'New Operation' }}
            </div>

            <div class="mt-1 text-2xl font-black text-horizon-white">
              {{ editingMission ? editingMission.title : 'Create Operation' }}
            </div>
          </div>
        </template>

        <MissionEditorForm
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

      <OperationModal
        v-if="activeOperation"
        @close="closeViewModal"
      >
        <template #header>
          <div class="min-w-0">
            <div class="text-xs font-bold uppercase tracking-[0.22em] text-[color:var(--horizon-text-secondary)]">
              {{ operationKindLabel(modalHeaderOperation?.operation_type ?? modalHeaderOperation?.operation_kind) }}
            </div>

            <div class="mt-1 truncate text-2xl font-black text-horizon-white">
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
    </div>
  </HorizonContainer>

  <HorizonConfirmDialog
    ref="startConfirmDialog"
    title="Start Operation"
    confirm-label="Start Operation"
    cancel-label="Back"
    variant="success"
    :message="startConfirmMessage"
    :close-on-confirm="false"
    @confirm="confirmStartOperation"
  />

  <HorizonConfirmDialog
    ref="completeConfirmDialog"
    title="Complete Operation"
    confirm-label="Complete"
    cancel-label="Back"
    variant="success"
    :message="completeConfirmMessage"
    :close-on-confirm="false"
    @confirm="confirmCompleteOperation"
  />

  <HorizonConfirmDialog
    ref="cancelConfirmDialog"
    title="Cancel Operation"
    confirm-label="Cancel Operation"
    cancel-label="Back"
    variant="danger"
    message="This action cannot be undone."
    :close-on-confirm="false"
    :requires-text-input="true"
    text-input-label="Cancellation reason (optional):"
    text-input-placeholder="Enter reason..."
    @confirm="confirmCancelOperation"
  />
</template>