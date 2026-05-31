<script setup>
import { computed, ref } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { Ziggy } from '../../ziggy'

import HorizonButton from '@/Components/HorizonButton.vue'
import HorizonConfirmDialog from '@/Components/HorizonConfirmDialog.vue'
import HorizonContainer from '@/Components/HorizonContainer.vue'
import MissionEditorForm from '@/Pages/Operations/Components/MissionEditorForm.vue'
import MissionShowPanel from '@/Pages/Operations/Components/MissionShowPanel.vue'
import OperationAccordion from '@/Pages/Operations/Components/OperationAccordion.vue'
import OperationDrawer from '@/Pages/Operations/Components/OperationDrawer.vue'
import OperationModal from '@/Pages/Operations/Components/OperationModal.vue'
import { canCreateOperation as userCanCreateOperation, isDirectorLike as userIsDirectorLike } from '@/auth'

const props = defineProps({
  operations: {
    type: [Array, Object],
    required: true,
  },
})

const page = usePage()

const user = computed(() => page.props.auth?.user ?? null)
const activeOperation = computed(() => page.props?.activeOperation ?? null)
const editingOperation = computed(() => page.props?.editingOperation ?? null)

const canUseOfficerCommands = computed(() => {
  return userCanCreateOperation(user.value)
})

const isDirectorLike = computed(() => {
  return userIsDirectorLike(user.value)
})

const createDrawerOpen = ref(false)
const createEditorSquadronId = ref(null)

const drawerOpen = computed(() => {
  return createDrawerOpen.value || editingOperation.value !== null
})

const editingMission = computed(() => {
  return editingOperation.value?.mission ?? null
})

const editorSquadronId = computed(() => {
  return editingOperation.value?.squadronId ?? createEditorSquadronId.value
})

const modalHeaderOperation = computed(() => {
  return activeOperation.value?.operation ?? null
})

const operationsPaginator = computed(() => {
  return Array.isArray(props.operations) ? null : props.operations
})

const operationsList = computed(() => {
  if (Array.isArray(props.operations)) {
    return props.operations ?? []
  }

  return props.operations?.data ?? []
})

const todayLabel = computed(() => {
  return new Date().toLocaleDateString(undefined, {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  })
})

const userSquadronId = computed(() => {
  const currentUser = user.value

  if (!currentUser?.squadrons?.length) return null

  const lieutenantSquadron = currentUser.squadrons.find((squadron) => squadron.pivot?.role === 'lieutenant')
  if (lieutenantSquadron) return lieutenantSquadron.id

  const leaderSquadron = currentUser.squadrons.find((squadron) => squadron.pivot?.role === 'leader')
  if (leaderSquadron) return leaderSquadron.id

  return currentUser.squadrons[0]?.id ?? null
})

const sortedOperations = computed(() => {
  const now = new Date()

  return [...(operationsList.value ?? [])].sort((a, b) => {
    const aDate = parseDate(a.starts_at)
    const bDate = parseDate(b.starts_at)

    if (!aDate && !bDate) return 0
    if (!aDate) return 1
    if (!bDate) return -1

    const aIsUpcoming = aDate.getTime() >= now.getTime()
    const bIsUpcoming = bDate.getTime() >= now.getTime()

    if (aIsUpcoming && !bIsUpcoming) return -1
    if (!aIsUpcoming && bIsUpcoming) return 1

    if (aIsUpcoming && bIsUpcoming) {
      return aDate.getTime() - bDate.getTime()
    }

    return bDate.getTime() - aDate.getTime()
  })
})

const transitionProcessingIds = ref(new Set())

const startConfirmDialog = ref(null)
const pendingStartOperation = ref(null)

const cancelConfirmDialog = ref(null)
const pendingCancelOperation = ref(null)

const completeDialogOpen = ref(false)
const pendingCompleteOperation = ref(null)

const startConfirmMessage = computed(() => {
  const op = pendingStartOperation.value

  if (!op) {
    return 'You are about to start this operation.'
  }

  return `You are about to mark "${op.title}" as In Progress.`
})

const completeOutcomeMessage = computed(() => {
  const op = pendingCompleteOperation.value

  if (!op) {
    return 'How should this operation be closed?'
  }

  return `How should "${op.title}" be closed?`
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

function parseDate(value) {
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

function goToUrl(url) {
  if (!url) return

  router.get(url, {}, {
    preserveScroll: true,
    preserveState: true,
  })
}

function getCurrentQueryParams() {
  const url = new URL(window.location.href)
  const out = {}

  for (const [key, value] of url.searchParams.entries()) {
    out[key] = value
  }

  return out
}

function visitMemberPage(query, only = ['operations', 'activeOperation', 'editingOperation']) {
  router.get(route('operations.member', {}, Ziggy), query, {
    only,
    preserveScroll: true,
    preserveState: true,
  })
}

function openCreateDrawer() {
  createDrawerOpen.value = true
  createEditorSquadronId.value = userSquadronId.value

  const query = {
    ...getCurrentQueryParams(),
  }

  delete query.edit
  delete query.operation

  visitMemberPage(query, ['activeOperation', 'editingOperation'])
}

function openEditDrawer(op) {
  createDrawerOpen.value = false
  createEditorSquadronId.value = null

  const query = {
    ...getCurrentQueryParams(),
    edit: op.id,
  }

  delete query.operation

  visitMemberPage(query, ['activeOperation', 'editingOperation'])
}

function closeDrawer() {
  createDrawerOpen.value = false
  createEditorSquadronId.value = null

  if (!editingOperation.value) return

  const query = {
    ...getCurrentQueryParams(),
  }

  delete query.edit

  visitMemberPage(query, ['editingOperation'])
}

function handleDrawerSaved() {
  closeDrawer()
}

function handleDrawerDeleted() {
  closeDrawer()
}

function openViewModal(op) {
  const query = {
    ...getCurrentQueryParams(),
    operation: op.id,
  }

  delete query.edit

  visitMemberPage(query, ['activeOperation'])
}

function closeViewModal() {
  const query = {
    ...getCurrentQueryParams(),
  }

  delete query.operation

  visitMemberPage(query, ['activeOperation'])
}

function canManageOperation(op) {
  if (isDirectorLike.value) return true

  const squadronId = op?.squadron?.id

  if (!squadronId) {
    const creatorId = op?.creator?.id ?? op?.created_by ?? null

    if (!creatorId || Number(creatorId) !== Number(user.value?.id)) {
      return false
    }

    return canUseOfficerCommands.value
  }

  const squadronLeaderId = op?.squadron?.leader?.id

  if (squadronLeaderId && squadronLeaderId === user.value?.id) {
    return true
  }

  const creatorId = op?.creator?.id ?? op?.created_by ?? null
  const membership = user.value?.squadrons?.find((squadron) => Number(squadron?.id) === Number(squadronId))

  if (!membership) return false

  const membershipStatus = membership.pivot?.membership_status
  if (membershipStatus && membershipStatus !== 'active') return false

  if (creatorId && Number(creatorId) === Number(user.value?.id)) {
    return canUseOfficerCommands.value
  }

  const role = membership.pivot?.role

  return role === 'leader' || role === 'lieutenant'
}

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

function askStartOperation(op) {
  if (!op?.id || isTransitionProcessing(op.id)) return

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
      window.hzNotifyError?.({ message: 'Failed to start operation.' })
      finish()
    },
    onFinish: () => {
      setTransitionProcessing(op.id, false)
    },
  })
}

function askCancelOperation(op) {
  if (!op?.id || isTransitionProcessing(op.id)) return

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
    reason: text,
  }, {
    preserveScroll: true,
    preserveState: true,
    only: ['operations', 'activeOperation'],
    onSuccess: () => {
      close()
      pendingCancelOperation.value = null
    },
    onError: () => {
      window.hzNotifyError?.({ message: 'Failed to cancel operation.' })
      finish()
    },
    onFinish: () => {
      setTransitionProcessing(op.id, false)
    },
  })
}

function openCompleteDialog(op) {
  if (!op?.id || isTransitionProcessing(op.id)) return

  pendingCompleteOperation.value = op
  completeDialogOpen.value = true
}

function closeCompleteDialog() {
  completeDialogOpen.value = false
  pendingCompleteOperation.value = null
}

function submitCompleteOperation(outcome) {
  const op = pendingCompleteOperation.value

  if (!op?.id || !outcome) return

  setTransitionProcessing(op.id, true)

  router.post(route('operations.complete', op.id, Ziggy), {
    outcome,
  }, {
    preserveScroll: true,
    preserveState: true,
    only: ['operations', 'activeOperation'],
    onSuccess: () => {
      closeCompleteDialog()
    },
    onError: () => {
      window.hzNotifyError?.({ message: 'Failed to complete operation.' })
    },
    onFinish: () => {
      setTransitionProcessing(op.id, false)
    },
  })
}
</script>

<template>
  <HorizonContainer class="py-8 md:py-10">
    <div class="mx-auto max-w-6xl space-y-6">
      <section class="hz-surface-welcome relative overflow-hidden rounded-[2rem] border border-white/[0.055]">
        <div class="pointer-events-none absolute inset-0 opacity-40">
          <div class="absolute left-8 top-0 h-px w-48 bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-blue)] to-transparent"></div>
          <div class="absolute bottom-0 right-10 h-px w-64 bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-magenta)] to-transparent"></div>
        </div>

        <div class="relative p-6">
          <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-start">
            <div class="pt-4 md:pt-5">
              <div class="text-xs font-bold uppercase tracking-[0.28em] text-[color:var(--horizon-text-secondary)]">
                Horizon Operations Command
              </div>

              <h1 class="mt-2 text-3xl font-black tracking-tight text-horizon-white md:text-5xl">
                Operations
              </h1>

              <p class="mt-3 max-w-3xl text-sm text-text-secondary md:text-base">
                Review active briefings, upcoming missions, squadron trainings, meetings, and field operations.
              </p>
            </div>

            <div class="flex flex-col items-start gap-3 lg:items-end">
              <div class="rounded-2xl border border-white/[0.055] bg-white/[0.035] px-4 py-3 text-right">
                <div class="text-xs font-bold uppercase tracking-[0.2em] text-text-muted">
                  Today
                </div>
                <div class="mt-1 text-sm font-semibold text-horizon-white">
                  {{ todayLabel }}
                </div>
              </div>

              <HorizonButton
                v-if="canUseOfficerCommands"
                variant="primary"
                size="md"
                class="min-w-[12rem]"
                @click="openCreateDrawer"
              >
                Create Operation
              </HorizonButton>
            </div>
          </div>
        </div>

        <div class="relative border-t border-white/[0.055] p-4 md:p-5">
          <div v-if="sortedOperations.length" class="space-y-4">
            <OperationAccordion
              v-for="op in sortedOperations"
              :key="op.id"
              :operation="op"
              @view="openViewModal"
            >
              <template
                v-if="canUseOfficerCommands && canManageOperation(op)"
                #actions
              >
                <div class="flex flex-wrap items-center justify-between gap-3">
                  <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
                    Officer Tools
                  </div>

                  <div class="flex flex-wrap gap-2">
                    <HorizonButton
                      variant="ghost"
                      size="sm"
                      @click.stop="openEditDrawer(op)"
                    >
                      Edit
                    </HorizonButton>

                    <HorizonButton
                      v-if="op.status === 'published'"
                      variant="primary"
                      size="sm"
                      :disabled="isTransitionProcessing(op.id)"
                      @click.stop="askStartOperation(op)"
                    >
                      {{ isTransitionProcessing(op.id) ? 'Starting…' : 'Start' }}
                    </HorizonButton>

                    <HorizonButton
                      v-if="op.status === 'in_progress'"
                      variant="primary"
                      size="sm"
                      :disabled="isTransitionProcessing(op.id)"
                      @click.stop="openCompleteDialog(op)"
                    >
                      {{ isTransitionProcessing(op.id) ? 'Completing…' : 'Complete' }}
                    </HorizonButton>

                    <HorizonButton
                      v-if="['published', 'in_progress'].includes(op.status)"
                      variant="ghost"
                      size="sm"
                      class="border-red-300/25! bg-red-300/10! text-red-100! hover:bg-red-300/15!"
                      :disabled="isTransitionProcessing(op.id)"
                      @click.stop="askCancelOperation(op)"
                    >
                      Cancel
                    </HorizonButton>
                  </div>
                </div>
              </template>
            </OperationAccordion>
          </div>

          <div
            v-else
            class="hz-surface-welcome rounded-[1.5rem] border border-dashed border-white/[0.075] p-8 text-center"
          >
            <div class="text-sm font-bold uppercase tracking-[0.22em] text-text-muted">
              No Operations Found
            </div>
            <p class="mx-auto mt-2 max-w-xl text-sm text-text-secondary">
              There are no operations in this view yet. Once missions are published, they will appear here.
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
          :key="`${activeOperation.operation.id}-${activeOperation.operation.status}-${activeOperation.operation.after_action_report_updated_at ?? 'na'}`"
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
    ref="cancelConfirmDialog"
    title="Cancel Operation"
    confirm-label="Cancel Operation"
    cancel-label="Back"
    variant="danger"
    message="This action cannot be undone."
    :close-on-confirm="false"
    :requires-text-input="true"
    text-input-label="Cancellation reason:"
    text-input-placeholder="Enter reason..."
    @confirm="confirmCancelOperation"
  />

  <div
    v-if="completeDialogOpen"
    class="hz-overlay flex items-center justify-center p-4"
    style="z-index: 110;"
    @click.self="closeCompleteDialog"
  >
    <div
      class="hz-modal hz-shadow-deep hz-stack hz-animate-pop w-full"
      style="max-width: 30rem; max-height: 85vh; overflow-y: auto;"
      role="dialog"
      aria-modal="true"
    >
      <div class="hz-row gap-3">
        <div class="text-2xl text-[var(--color-state-success)]">
          ✓
        </div>

        <div class="hz-title-md">
          Complete Operation
        </div>
      </div>

      <div class="hz-body hz-text-soft whitespace-pre-line">
        {{ completeOutcomeMessage }}
      </div>

      <div class="hz-row justify-end gap-2 pt-2">
        <HorizonButton
          variant="secondary"
          size="sm"
          :disabled="pendingCompleteOperation && isTransitionProcessing(pendingCompleteOperation.id)"
          @click="closeCompleteDialog"
        >
          Back
        </HorizonButton>

        <HorizonButton
          variant="ghost"
          size="sm"
          class="border-red-300/25! bg-red-300/10! text-red-100! hover:bg-red-300/15!"
          :disabled="pendingCompleteOperation && isTransitionProcessing(pendingCompleteOperation.id)"
          @click="submitCompleteOperation('failed')"
        >
          End Failed
        </HorizonButton>

        <HorizonButton
          variant="primary"
          size="sm"
          :disabled="pendingCompleteOperation && isTransitionProcessing(pendingCompleteOperation.id)"
          @click="submitCompleteOperation('success')"
        >
          End Success
        </HorizonButton>
      </div>
    </div>
  </div>
</template>
