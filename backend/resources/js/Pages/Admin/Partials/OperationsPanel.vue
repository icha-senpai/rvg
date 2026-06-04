<script setup>
import { computed, ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

import AfterActionReportSection from '@/Components/AfterActionReportSection.vue'
import HorizonInput from '@/Components/HorizonInput.vue'
import OperationSettlementSection from '@/Components/OperationSettlementSection.vue'

const props = defineProps({
  operations: {
    type: Array,
    default: () => [],
  },
  canceledOperations: {
    type: Array,
    default: () => [],
  },
  verifiedMembers: {
    type: Array,
    default: () => [],
  },
  operationSettlementLootOptions: {
    type: Object,
    default: () => ({
      commodities: [],
      items: [],
      components: [],
    }),
  },
})

const search = ref('')
const openOperationId = ref(null)
const activeOperationsSection = ref('aar')
const attendanceDraftMembersByOperationId = ref({})

const currentSection = computed(() => {
  if (activeOperationsSection.value === 'cancellations') {
    return {
      eyebrow: 'Canceled Operations',
      title: 'Cancellation Reasons',
      description: 'Review recently canceled operations and the recorded reason for each cancellation.',
      emptyTitle: 'No Canceled Operations',
      emptyDescription: 'Canceled operations with a recorded reason will show up here for admin review.',
      searchPlaceholder: 'Search by operation, creator, squadron, reason, or ID...',
    }
  }

  return {
    eyebrow: 'Completed Operations',
    title: 'After Action Reports',
    description: 'Review finished operations, correct final attendance, and update the written AAR from the admin command surface.',
    emptyTitle: 'No Completed Operations',
    emptyDescription: 'Finished operations with a success or failed outcome will show up here for AAR maintenance.',
    searchPlaceholder: 'Search by operation, creator, squadron, outcome, or ID...',
  }
})

const filteredOperations = computed(() => {
  const needle = search.value.trim().toLowerCase()

  if (!needle) {
    return props.operations ?? []
  }

  return (props.operations ?? []).filter((operation) => {
    const haystack = [
      operation?.title,
      operation?.description,
      operation?.completion_outcome,
      operation?.creator?.rsi_handle,
      operation?.creator?.discord_name,
      operation?.squadron?.name,
      String(operation?.id ?? ''),
    ]
      .filter(Boolean)
      .join(' ')
      .toLowerCase()

    return haystack.includes(needle)
  })
})

const filteredCanceledOperations = computed(() => {
  const needle = search.value.trim().toLowerCase()

  if (!needle) {
    return props.canceledOperations ?? []
  }

  return (props.canceledOperations ?? []).filter((operation) => {
    const haystack = [
      operation?.title,
      operation?.description,
      operation?.cancellation_reason,
      operation?.creator?.rsi_handle,
      operation?.creator?.discord_name,
      operation?.squadron?.name,
      String(operation?.id ?? ''),
    ]
      .filter(Boolean)
      .join(' ')
      .toLowerCase()

    return haystack.includes(needle)
  })
})

function formatOutcome(value) {
  if (!value) return 'Completed'
  return value === 'failed' ? 'Failed' : 'Success'
}

function formatDate(value) {
  if (!value) return 'No date set'

  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return String(value)

  return new Intl.DateTimeFormat('en-GB', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hour12: true,
  }).format(date)
}

function creatorName(operation) {
  return operation?.creator?.rsi_handle
    ?? operation?.creator?.discord_name
    ?? operation?.creator?.name
    ?? 'Unknown'
}

function toggleOperation(operationId) {
  openOperationId.value = Number(openOperationId.value) === Number(operationId)
    ? null
    : Number(operationId)
}

function updateAttendanceDraftMembers(operationId, members = []) {
  const id = Number(operationId)
  if (!Number.isFinite(id)) return

  attendanceDraftMembersByOperationId.value = {
    ...attendanceDraftMembersByOperationId.value,
    [id]: members,
  }
}

function attendanceDraftMembers(operation) {
  const id = Number(operation?.id)

  if (!Number.isFinite(id)) {
    return []
  }

  return attendanceDraftMembersByOperationId.value[id] ?? operation?.after_action_attendance ?? []
}

function selectOperationsSection(section) {
  activeOperationsSection.value = section

  if (section !== 'aar') {
    openOperationId.value = null
  }
}
</script>

<template>
  <div class="space-y-5">
    <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_auto] xl:items-end">
      <div>
        <div class="text-xs font-bold uppercase tracking-[0.2em] text-text-muted">
          {{ currentSection.eyebrow }}
        </div>

        <h3 class="mt-1 text-2xl font-black text-horizon-white">
          {{ currentSection.title }}
        </h3>

        <p class="mt-2 max-w-3xl text-sm text-text-secondary">
          {{ currentSection.description }}
        </p>
      </div>

      <div class="xl:min-w-[20rem]">
        <HorizonInput
          v-model="search"
          :placeholder="currentSection.searchPlaceholder"
        />
      </div>
    </div>

    <div class="relative border-t border-white/[0.055] px-0 pb-0 pt-4">
      <div class="flex flex-wrap gap-2">
        <button
          type="button"
          class="rounded-full border px-4 py-2 text-xs font-bold uppercase tracking-[0.16em] transition"
          :class="activeOperationsSection === 'aar'
            ? 'border-emerald-300/35 bg-emerald-300/10 text-horizon-white'
            : 'border-white/[0.055] bg-white/[0.024] text-text-secondary hover:bg-white/[0.04] hover:text-horizon-white'"
          @click="selectOperationsSection('aar')"
        >
          AAR
        </button>

        <button
          type="button"
          class="rounded-full border px-4 py-2 text-xs font-bold uppercase tracking-[0.16em] transition"
          :class="activeOperationsSection === 'cancellations'
            ? 'border-red-300/35 bg-red-300/10 text-horizon-white'
            : 'border-white/[0.055] bg-white/[0.024] text-text-secondary hover:bg-white/[0.04] hover:text-horizon-white'"
          @click="selectOperationsSection('cancellations')"
        >
          Cancellations
        </button>
      </div>
    </div>

    <template v-if="activeOperationsSection === 'aar'">
      <div
        v-if="!filteredOperations.length"
        class="rounded-[1.5rem] border border-dashed border-white/15 bg-white/[0.02] p-8 text-center"
      >
        <div class="text-sm font-bold uppercase tracking-[0.2em] text-text-muted">
          {{ currentSection.emptyTitle }}
        </div>

        <p class="mx-auto mt-2 max-w-xl text-sm text-text-secondary">
          {{ currentSection.emptyDescription }}
        </p>
      </div>

      <article
        v-for="operation in filteredOperations"
        :key="`${operation.id}-${operation.after_action_report_updated_at ?? 'na'}`"
        class="hz-surface-welcome rounded-[1.75rem] border border-white/[0.055] p-4 md:p-5"
      >
        <div
          class="cursor-pointer"
          tabindex="0"
          role="button"
          @click="toggleOperation(operation.id)"
          @keydown.enter.prevent="toggleOperation(operation.id)"
          @keydown.space.prevent="toggleOperation(operation.id)"
        >
          <div class="flex flex-col gap-4 border-b border-white/[0.055] pb-4 md:flex-row md:items-start md:justify-between">
            <div>
              <div class="flex flex-wrap items-center gap-2 text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
                <span>#{{ operation.id }}</span>
                <span class="text-text-secondary">•</span>
                <span>{{ formatOutcome(operation.completion_outcome) }}</span>
              </div>

              <h4 class="mt-2 text-2xl font-black text-horizon-white">
                {{ operation.title }}
              </h4>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 md:min-w-[24rem]">
              <div class="rounded-[1rem] border border-white/[0.055] bg-white/[0.024] px-4 py-3">
                <div class="text-xs uppercase tracking-[0.14em] text-text-muted">Creator</div>
                <div class="mt-1 text-sm font-semibold text-horizon-white">{{ creatorName(operation) }}</div>
              </div>

              <div class="rounded-[1rem] border border-white/[0.055] bg-white/[0.024] px-4 py-3">
                <div class="text-xs uppercase tracking-[0.14em] text-text-muted">Completed</div>
                <div class="mt-1 text-sm font-semibold text-horizon-white">{{ formatDate(operation.ends_at ?? operation.starts_at) }}</div>
              </div>
            </div>
          </div>
        </div>

        <div class="mt-4 flex flex-wrap gap-2">
          <span class="rounded-full border border-white/[0.055] bg-white/[0.024] px-3 py-1 text-xs font-semibold text-text-secondary">
            {{ operation.squadron?.name ?? 'Global Operation' }}
          </span>

          <button
            type="button"
            class="rounded-full border border-white/[0.055] bg-white/[0.024] px-3 py-1 text-xs font-bold text-horizon-white transition hover:bg-white/[0.04]"
            @click.stop="toggleOperation(operation.id)"
          >
            {{ Number(openOperationId) === Number(operation.id) ? 'Close AAR' : 'Open AAR' }}
          </button>

          <Link
            :href="route('operations.show', operation.id)"
            class="rounded-full border border-[color:var(--horizon-sunset-blue)]/35 bg-white/[0.042] px-3 py-1 text-xs font-bold text-horizon-white transition hover:bg-[color:var(--horizon-sunset-blue)]/15"
            @click.stop
          >
            Open Operation
          </Link>
        </div>

        <div v-if="Number(openOperationId) === Number(operation.id)" class="mt-5 space-y-4">
          <AfterActionReportSection
            :operation="operation"
            :verified-members="verifiedMembers"
            :can-manage="!!operation?.permissions?.can_manage_aar"
            :reload-only="['operations']"
            :compact="true"
            @attendance-draft-change="updateAttendanceDraftMembers(operation.id, $event)"
          />

              <OperationSettlementSection
                :operation="operation"
                :shared-loot-options="operationSettlementLootOptions"
                :attendance-draft-members="attendanceDraftMembers(operation)"
                :reload-only="['operations']"
                :compact="true"
              />
        </div>
      </article>
    </template>

    <template v-else>
      <div
        v-if="!filteredCanceledOperations.length"
        class="rounded-[1.5rem] border border-dashed border-white/15 bg-white/[0.02] p-8 text-center"
      >
        <div class="text-sm font-bold uppercase tracking-[0.2em] text-text-muted">
          {{ currentSection.emptyTitle }}
        </div>

        <p class="mx-auto mt-2 max-w-xl text-sm text-text-secondary">
          {{ currentSection.emptyDescription }}
        </p>
      </div>

      <article
        v-for="operation in filteredCanceledOperations"
        :key="`canceled-${operation.id}`"
        class="rounded-[1.75rem] border border-red-300/15 bg-red-300/5 p-4 md:p-5"
      >
        <div class="flex flex-col gap-4 border-b border-red-300/15 pb-4 md:flex-row md:items-start md:justify-between">
          <div>
            <div class="flex flex-wrap items-center gap-2 text-xs font-bold uppercase tracking-[0.18em] text-red-200/75">
              <span>#{{ operation.id }}</span>
              <span class="text-red-200/45">•</span>
              <span>Canceled</span>
            </div>

            <h4 class="mt-2 text-2xl font-black text-horizon-white">
              {{ operation.title }}
            </h4>
          </div>

          <div class="grid gap-3 sm:grid-cols-2 md:min-w-[24rem]">
            <div class="rounded-[1rem] border border-white/[0.055] bg-white/[0.024] px-4 py-3">
              <div class="text-xs uppercase tracking-[0.14em] text-text-muted">Creator</div>
              <div class="mt-1 text-sm font-semibold text-horizon-white">{{ creatorName(operation) }}</div>
            </div>

            <div class="rounded-[1rem] border border-white/[0.055] bg-white/[0.024] px-4 py-3">
              <div class="text-xs uppercase tracking-[0.14em] text-text-muted">Canceled</div>
              <div class="mt-1 text-sm font-semibold text-horizon-white">{{ formatDate(operation.ends_at ?? operation.starts_at) }}</div>
            </div>
          </div>
        </div>

        <div class="mt-4 flex flex-wrap gap-2">
          <span class="rounded-full border border-white/[0.055] bg-white/[0.024] px-3 py-1 text-xs font-semibold text-text-secondary">
            {{ operation.squadron?.name ?? 'Global Operation' }}
          </span>

          <Link
            :href="route('operations.show', operation.id)"
            class="rounded-full border border-[color:var(--horizon-sunset-blue)]/35 bg-white/[0.042] px-3 py-1 text-xs font-bold text-horizon-white transition hover:bg-[color:var(--horizon-sunset-blue)]/15"
          >
            Open Operation
          </Link>
        </div>

        <div class="mt-4 rounded-[1rem] border border-red-300/15 bg-black/10 p-4">
          <div class="text-xs font-bold uppercase tracking-[0.16em] text-red-200/75">
            Cancellation Reason
          </div>

          <div class="mt-2 whitespace-pre-line text-sm leading-7 text-text-secondary">
            {{ operation.cancellation_reason }}
          </div>
        </div>
      </article>
    </template>
  </div>
</template>
