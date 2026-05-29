<script setup>
import { computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { Ziggy } from '../../ziggy'

import OperationAccordion from '@/Pages/Operations/Components/OperationAccordion.vue'
import HorizonButton from '@/Components/HorizonButton.vue'
import HorizonContainer from '@/Components/HorizonContainer.vue'
import OperationModal from '@/Pages/Operations/Components/OperationModal.vue'
import MissionShowPanel from '@/Pages/Operations/Components/MissionShowPanel.vue'

const props = defineProps({
  operations: {
    type: [Array, Object],
    required: true,
  },
})

const page = usePage()

const activeOperation = computed(() => page.props?.activeOperation ?? null)

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

const operationStats = computed(() => {
  const list = operationsList.value ?? []

  return {
    total: list.length,
    joined: list.filter(op => {
      if (typeof op.joined_by_me === 'boolean') return op.joined_by_me
      return Number(op.joined_by_me ?? 0) > 0
    }).length,
    upcoming: list.filter(op => {
      const date = parseDate(op.starts_at)
      return date && date.getTime() >= Date.now()
    }).length,
  }
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

  let v = String(value).trim()
  v = v.replace(' ', 'T')
  v = v.replace(/\.(\d{3})\d+Z$/i, '.$1Z')
  v = v.replace(/\.(\d{3})\d+$/i, '.$1')

  const hasTimezone = /([zZ]|[+-]\d{2}:\d{2})$/.test(v)
  if (!hasTimezone) v = `${v}Z`

  const d = new Date(v)
  return Number.isNaN(d.getTime()) ? null : d
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

<template>
  <HorizonContainer class="py-8 md:py-10">
    <div class="mx-auto max-w-6xl space-y-8">
      <!-- Command header -->
      <section class="hz-surface-welcome relative overflow-hidden rounded-[2rem] border border-white/[0.055] p-6 ">
        <div class="pointer-events-none absolute inset-0 opacity-40">
          <div class="absolute left-8 top-0 h-px w-48 bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-blue)] to-transparent"></div>
          <div class="absolute bottom-0 right-10 h-px w-64 bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-magenta)] to-transparent"></div>
        </div>

        <div class="relative grid gap-6 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
          <div>
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

          <div class="rounded-2xl border border-white/[0.055] bg-white/[0.035] px-4 py-3 text-right">
            <div class="text-xs font-bold uppercase tracking-[0.2em] text-text-muted">
              Today
            </div>
            <div class="mt-1 text-sm font-semibold text-horizon-white">
              {{ todayLabel }}
            </div>
          </div>
        </div>
      </section>

      <!-- Status strip -->
      <section class="hz-surface-welcome grid overflow-hidden rounded-3xl border border-white/[0.055] md:grid-cols-3">
        <div class="border-l border-white/[0.055] p-5 first:border-l-0">
          <div class="text-xs font-bold uppercase tracking-[0.2em] text-text-muted">
            Listed
          </div>
          <div class="mt-2 text-3xl font-black text-horizon-white">
            {{ operationStats.total }}
          </div>
          <div class="mt-1 text-sm text-text-secondary">
            Operations in this view
          </div>
        </div>

        <div class="border-l border-white/[0.055] p-5 first:border-l-0">
          <div class="text-xs font-bold uppercase tracking-[0.2em] text-text-muted">
            Upcoming
          </div>
          <div class="mt-2 text-3xl font-black text-horizon-white">
            {{ operationStats.upcoming }}
          </div>
          <div class="mt-1 text-sm text-text-secondary">
            Still ahead of current time
          </div>
        </div>

        <div class="border-l border-white/[0.055] p-5 first:border-l-0">
          <div class="text-xs font-bold uppercase tracking-[0.2em] text-text-muted">
            Joined
          </div>
          <div class="mt-2 text-3xl font-black text-horizon-white">
            {{ operationStats.joined }}
          </div>
          <div class="mt-1 text-sm text-text-secondary">
            Missions you are signed up for
          </div>
        </div>
      </section>

      <!-- Operations list shell -->
      <section class="hz-surface-welcome rounded-[2rem] border border-white/[0.055] p-4 md:p-5">
        <div class="mb-5 flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
          <div>
            <div class="text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--horizon-text-secondary)]">
              Mission Board
            </div>

            <h2 class="mt-1 text-xl font-black text-horizon-white">
              Operation Briefings
            </h2>

            <p class="mt-1 text-sm text-text-secondary">
              Upcoming operations sort first. Completed or past operations fall behind them.
            </p>
          </div>

          <div class="hz-surface-welcome rounded-full border border-white/[0.055] px-3 py-1 text-xs font-bold uppercase tracking-[0.16em] text-text-muted">
            {{ sortedOperations.length }} visible
          </div>
        </div>

        <div v-if="sortedOperations.length" class="space-y-4">
          <OperationAccordion
            v-for="op in sortedOperations"
            :key="op.id"
            :operation="op"
            @view="openViewModal"
          />
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
      </section>

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
</template>







