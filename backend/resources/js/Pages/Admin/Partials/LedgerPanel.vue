<script setup>
import { computed, ref } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

import HorizonButton from '@/Components/HorizonButton.vue'
import HorizonConfirmDialog from '@/Components/HorizonConfirmDialog.vue'
import HorizonDateTimePicker from '@/Components/HorizonDateTimePicker.vue'

const props = defineProps({
  ledger: {
    type: Object,
    default: () => ({
      feature: {
        enabled: false,
        preview_user_ids: [],
      },
      currentWipe: null,
      counts: {},
      wipeCycles: [],
    }),
  },
})
const page = usePage()

const wipeTypeOptions = [
  'unknown',
  'none',
  'partial',
  'full',
  'economy',
  'inventory',
  'ships',
  'reputation',
]

const createWipeForm = useForm({
  name: '',
  star_citizen_version: '',
  wipe_type: 'unknown',
  started_at: '',
  notes: '',
})
const editCycleForm = useForm({
  name: '',
  star_citizen_version: '',
  wipe_type: 'unknown',
  started_at: '',
})

const currentWipeLabel = computed(() => props.ledger?.currentWipe?.name ?? 'Not set')
const flashSuccess = computed(() => page.props.flash?.success ?? null)
const confirmDialog = ref(null)
const pendingConfirmation = ref(null)
const editingCycleId = ref(null)
const savedCycleId = ref(null)
const analytics = computed(() => props.ledger?.analytics ?? {})

const chartPalette = [
  { solid: 'rgba(125, 211, 252, 0.96)' },
  { solid: 'rgba(96, 165, 250, 0.96)' },
  { solid: 'rgba(45, 212, 191, 0.96)' },
  { solid: 'rgba(251, 191, 36, 0.96)' },
  { solid: 'rgba(248, 113, 113, 0.96)' },
  { solid: 'rgba(196, 181, 253, 0.96)' },
]

const leadershipCards = computed(() => [
  {
    label: 'Net Position',
    value: formatSignedMoney(analytics.value?.snapshot?.net_position ?? 0),
    detail: 'Current cycle across all ledgers',
  },
  {
    label: 'Income',
    value: formatMoney(analytics.value?.snapshot?.income ?? 0),
    detail: `${formatCount(analytics.value?.snapshot?.transaction_count ?? 0)} transactions logged`,
  },
  {
    label: 'Expenses',
    value: formatMoney(analytics.value?.snapshot?.expenses ?? 0),
    detail: `${formatCount(analytics.value?.snapshot?.trade_count ?? 0)} trade runs recorded`,
  },
  {
    label: 'Assets',
    value: formatMoney((analytics.value?.snapshot?.inventory_value ?? 0) + (analytics.value?.snapshot?.fleet_value ?? 0)),
    detail: `${formatCount((analytics.value?.snapshot?.inventory_count ?? 0) + (analytics.value?.snapshot?.ship_count ?? 0))} tracked records`,
  },
])

const ownershipSummaries = computed(() => analytics.value?.ownership ?? [])
const cycleProfitRows = computed(() => normalizeRows(analytics.value?.reports?.cycle_profit_loss ?? [], { limit: 6 }))
const incomeSourceRows = computed(() => normalizeRows(analytics.value?.reports?.income_by_source ?? [], { limit: 5, aggregateRemainder: true }))
const expenseSourceRows = computed(() => normalizeRows(analytics.value?.reports?.expenses_by_source ?? [], { limit: 5, aggregateRemainder: true }))
const tradeCommodityRows = computed(() => normalizeRows(analytics.value?.reports?.trade_profit_by_commodity ?? [], { limit: 5, aggregateRemainder: true }))
const inventoryCategoryRows = computed(() => normalizeRows(analytics.value?.reports?.inventory_value_by_category ?? [], { limit: 5, aggregateRemainder: true }))
const shipStatusRows = computed(() => normalizeRows(analytics.value?.reports?.ship_summary_by_status ?? [], { limit: 6 }))

function toLocalInputValue(value) {
  if (!value) return ''

  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return ''

  const localDate = new Date(date.getTime() - date.getTimezoneOffset() * 60000)
  return localDate.toISOString().slice(0, 16)
}

function submitWipeCycle() {
  createWipeForm.post(route('admin.ledger.wipes.store'), {
    preserveScroll: true,
    onSuccess: () => {
      createWipeForm.reset('name', 'star_citizen_version', 'started_at', 'notes')
      createWipeForm.wipe_type = 'unknown'
    },
  })
}

function startRenameCycle(wipe) {
  editingCycleId.value = wipe.id
  savedCycleId.value = null
  editCycleForm.clearErrors()
  editCycleForm.name = wipe.name ?? ''
  editCycleForm.star_citizen_version = wipe.star_citizen_version ?? ''
  editCycleForm.wipe_type = wipe.wipe_type ?? 'unknown'
  editCycleForm.started_at = toLocalInputValue(wipe.started_at)
}

function cancelRenameCycle() {
  editingCycleId.value = null
  editCycleForm.clearErrors()
  editCycleForm.reset()
}

function submitRenameCycle(id) {
  editCycleForm.post(route('admin.ledger.wipes.rename', id), {
    preserveScroll: true,
    onSuccess: () => {
      savedCycleId.value = id
      cancelRenameCycle()
    },
  })
}

function activateWipeCycle(id) {
  pendingConfirmation.value = {
    title: 'Set Current Cycle',
    message: 'Set this cycle as the current live ledger cycle?',
    confirmLabel: 'Set Current',
    variant: 'warning',
    action: ({ close, finish }) => {
      useForm({}).post(route('admin.ledger.wipes.activate', id), {
        preserveScroll: true,
        onSuccess: () => {
          close()
        },
        onFinish: () => {
          finish()
        },
      })
    },
  }

  confirmDialog.value?.show()
}

function closeWipeCycle(id) {
  pendingConfirmation.value = {
    title: 'Close Cycle',
    message: 'Close this cycle now? If no current cycle remains, Horizon will create a fresh one automatically.',
    confirmLabel: 'Close Cycle',
    variant: 'danger',
    action: ({ close, finish }) => {
      useForm({}).post(route('admin.ledger.wipes.close', id), {
        preserveScroll: true,
        onSuccess: () => {
          close()
        },
        onFinish: () => {
          finish()
        },
      })
    },
  }

  confirmDialog.value?.show()
}

function handleConfirmDialogConfirm(controls) {
  pendingConfirmation.value?.action?.(controls)
}

function normalizeRows(rows, options = {}) {
  const {
    limit = 6,
    aggregateRemainder = false,
  } = options

  const cleanedRows = (rows ?? [])
    .map((row) => ({
      label: String(row?.label ?? 'Unknown'),
      value: Number(row?.value ?? 0),
    }))
    .filter((row) => row.label && Number.isFinite(row.value) && row.value !== 0)

  if (!cleanedRows.length) {
    return []
  }

  const trimmedRows = limit > 0 ? cleanedRows.slice(0, limit) : [...cleanedRows]
  const remainderRows = limit > 0 ? cleanedRows.slice(limit) : []

  if (aggregateRemainder && remainderRows.length) {
    trimmedRows.push({
      label: 'Other',
      value: remainderRows.reduce((total, row) => total + row.value, 0),
    })
  }

  const total = trimmedRows.reduce((sum, row) => sum + Math.abs(row.value), 0)

  return trimmedRows.map((row, index) => ({
    ...row,
    color: chartPalette[index % chartPalette.length].solid,
    share: total > 0 ? Math.abs(row.value) / total : 0,
  }))
}

function formatMoney(value) {
  const amount = Number(value ?? 0)
  return `${amount.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 })} aUEC`
}

function formatSignedMoney(value) {
  const amount = Number(value ?? 0)

  if (amount > 0) return `+${formatMoney(amount)}`
  if (amount < 0) return `-${formatMoney(Math.abs(amount))}`

  return formatMoney(0)
}

function formatCount(value) {
  return Number(value ?? 0).toLocaleString('en-US')
}

function formatCompactNumber(value) {
  const amount = Number(value ?? 0)

  return new Intl.NumberFormat('en-US', {
    notation: 'compact',
    maximumFractionDigits: amount >= 100000 ? 1 : 0,
  }).format(amount)
}

function buildBarHeight(value, maxValue) {
  const safeMax = Math.max(Number(maxValue ?? 0), 1)
  const height = Math.max((Math.abs(Number(value ?? 0)) / safeMax) * 100, 10)

  return `${Math.min(height, 100)}%`
}

function buildBarWidth(share) {
  const width = Math.max(Number(share ?? 0) * 100, 8)

  return `${Math.min(width, 100)}%`
}

function buildDonutStyle(rows) {
  if (!rows?.length) {
    return {
      background: 'conic-gradient(rgba(148, 163, 184, 0.14) 0deg 360deg)',
    }
  }

  let currentAngle = 0

  const slices = rows.map((row) => {
    const start = currentAngle
    const size = row.share * 360
    const end = start + size
    currentAngle = end

    return `${row.color} ${start}deg ${end}deg`
  })

  return {
    background: `conic-gradient(${slices.join(', ')})`,
  }
}
</script>

<template>
  <div class="space-y-5">
    <HorizonConfirmDialog
      ref="confirmDialog"
      :title="pendingConfirmation?.title ?? 'Confirm Action'"
      :message="pendingConfirmation?.message ?? ''"
      :confirm-label="pendingConfirmation?.confirmLabel ?? 'Confirm'"
      :variant="pendingConfirmation?.variant ?? 'warning'"
      @confirm="handleConfirmDialogConfirm"
    />

    <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_auto] xl:items-center">
      <div>
        <h3 class="text-xl font-black text-horizon-white">
          Cycle Controls
        </h3>

        <p class="mt-1 max-w-3xl text-sm text-text-secondary">
          Manage the live cycle, rollout state, and leadership readouts from one place.
        </p>
      </div>

      <div class="hz-surface-soft rounded-[1.25rem] px-4 py-3 text-right">
        <div class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">
          Current Cycle
        </div>

        <div class="mt-1 text-2xl font-black text-horizon-white">
          {{ currentWipeLabel }}
        </div>
      </div>
    </div>

    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
      <div class="hz-surface-soft rounded-[1.25rem] p-4">
        <div class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Feature Flag</div>
        <div class="mt-2 text-lg font-black text-horizon-white">
          {{ ledger.feature?.enabled ? 'Enabled' : 'Preview Only' }}
        </div>
        <div class="mt-1 text-sm text-text-secondary">
          Preview users: {{ ledger.feature?.preview_user_ids?.length ?? 0 }}
        </div>
      </div>

      <div class="hz-surface-soft rounded-[1.25rem] p-4">
        <div class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Transactions</div>
        <div class="mt-2 text-lg font-black text-horizon-white">{{ ledger.counts?.transactions ?? 0 }}</div>
        <div class="mt-1 text-sm text-text-secondary">Recorded ledger entries.</div>
      </div>

      <div class="hz-surface-soft rounded-[1.25rem] p-4">
        <div class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Trades</div>
        <div class="mt-2 text-lg font-black text-horizon-white">{{ ledger.counts?.trades ?? 0 }}</div>
        <div class="mt-1 text-sm text-text-secondary">Commodity runs logged so far.</div>
      </div>

      <div class="hz-surface-soft rounded-[1.25rem] p-4">
        <div class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Assets</div>
        <div class="mt-2 text-lg font-black text-horizon-white">
          {{ (ledger.counts?.inventory_items ?? 0) + (ledger.counts?.ship_assets ?? 0) }}
        </div>
        <div class="mt-1 text-sm text-text-secondary">Inventory and ship records combined.</div>
      </div>
    </section>

    <section class="hz-surface-soft rounded-[1.75rem] p-5">
      <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Leadership Snapshot</div>
          <h4 class="mt-1 text-xl font-black text-horizon-white">Current cycle command economy</h4>
        </div>

        <p class="max-w-2xl text-sm text-text-secondary">
          A shared readout for leadership without pushing all of this extra reporting into the member ledgers.
        </p>
      </div>

      <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div
          v-for="card in leadershipCards"
          :key="card.label"
          class="hz-surface-soft rounded-[1.25rem] px-4 py-4"
        >
          <div class="text-[11px] font-bold uppercase tracking-[0.18em] text-text-muted">{{ card.label }}</div>
          <div class="mt-2 text-2xl font-black text-horizon-white">{{ card.value }}</div>
          <div class="mt-2 text-sm text-text-secondary">{{ card.detail }}</div>
        </div>
      </div>
    </section>

    <section class="grid gap-4 xl:grid-cols-3">
      <article
        v-for="scope in ownershipSummaries"
        :key="scope.key"
        class="hz-surface-soft rounded-[1.5rem] p-5"
      >
        <div class="flex items-start justify-between gap-3">
          <div>
            <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">{{ scope.label }}</div>
            <div class="mt-1 text-sm text-text-secondary">{{ scope.description }}</div>
          </div>

          <div class="rounded-full border border-white/[0.055] bg-white/[0.03] px-3 py-1 text-[11px] font-bold uppercase tracking-[0.14em] text-horizon-white">
            {{ formatCount(scope.account_count) }} ledgers
          </div>
        </div>

        <div class="mt-4 grid gap-3 sm:grid-cols-2">
          <div class="hz-surface-deep rounded-[1.1rem] px-4 py-3">
            <div class="text-[11px] font-bold uppercase tracking-[0.14em] text-text-muted">Net Position</div>
            <div class="mt-2 text-lg font-black text-horizon-white">{{ formatSignedMoney(scope.cards?.net_position ?? 0) }}</div>
          </div>

          <div class="hz-surface-deep rounded-[1.1rem] px-4 py-3">
            <div class="text-[11px] font-bold uppercase tracking-[0.14em] text-text-muted">Trade Profit</div>
            <div class="mt-2 text-lg font-black text-horizon-white">{{ formatMoney(scope.cards?.trade_profit ?? 0) }}</div>
          </div>

          <div class="hz-surface-deep rounded-[1.1rem] px-4 py-3">
            <div class="text-[11px] font-bold uppercase tracking-[0.14em] text-text-muted">Inventory Value</div>
            <div class="mt-2 text-lg font-black text-horizon-white">{{ formatMoney(scope.cards?.inventory_value ?? 0) }}</div>
          </div>

          <div class="hz-surface-deep rounded-[1.1rem] px-4 py-3">
            <div class="text-[11px] font-bold uppercase tracking-[0.14em] text-text-muted">Fleet Value</div>
            <div class="mt-2 text-lg font-black text-horizon-white">{{ formatMoney(scope.cards?.fleet_value ?? 0) }}</div>
          </div>
        </div>

        <div class="mt-4 text-xs text-text-secondary">
          {{ formatCount(scope.record_count) }} records tracked in this slice for the current cycle.
        </div>
      </article>
    </section>

    <section class="grid gap-5 xl:grid-cols-2">
      <div class="hz-surface-soft rounded-[1.75rem] p-5">
        <div class="flex items-center justify-between gap-3">
          <div>
            <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Cycle Profit / Loss</div>
            <div class="mt-1 text-sm text-text-secondary">How each cycle has landed across the whole command economy.</div>
          </div>

          <div class="rounded-full border border-white/[0.055] bg-white/[0.03] px-3 py-1 text-[11px] font-bold uppercase tracking-[0.14em] text-horizon-white">
            {{ cycleProfitRows.length }} cycles
          </div>
        </div>

        <div v-if="cycleProfitRows.length" class="mt-5 grid gap-5 lg:grid-cols-[minmax(0,1.1fr)_18rem]">
          <div class="hz-surface-deep rounded-[1.4rem] p-4">
            <div class="flex h-56 items-end gap-3">
              <div
                v-for="row in cycleProfitRows"
                :key="`cycle-${row.label}`"
                class="flex min-w-0 flex-1 flex-col justify-end"
              >
                <div class="px-1 pb-2 text-center text-[11px] font-bold tracking-[0.04em]" :class="row.value >= 0 ? 'text-emerald-200' : 'text-rose-200'">
                  {{ formatCompactNumber(Math.abs(row.value)) }}
                </div>
                <div
                  class="w-full rounded-t-[1rem]"
                  :style="{
                    height: buildBarHeight(row.value, Math.max(...cycleProfitRows.map((entry) => Math.abs(entry.value)), 1)),
                    background: row.value >= 0
                      ? 'linear-gradient(180deg, rgba(52, 211, 153, 0.95), rgba(16, 185, 129, 0.6))'
                      : 'linear-gradient(180deg, rgba(251, 113, 133, 0.92), rgba(244, 63, 94, 0.55))',
                  }"
                />
                <div class="mt-3 text-center text-[11px] font-bold uppercase tracking-[0.14em] text-text-muted">
                  {{ row.label }}
                </div>
              </div>
            </div>
          </div>

          <div class="space-y-3">
            <div
              v-for="row in cycleProfitRows"
              :key="`cycle-list-${row.label}`"
              class="hz-surface-deep rounded-[1.1rem] px-4 py-3"
            >
              <div class="flex items-center justify-between gap-3">
                <div class="text-sm font-bold text-horizon-white">{{ row.label }}</div>
                <div class="text-sm font-semibold" :class="row.value >= 0 ? 'text-emerald-200' : 'text-rose-200'">
                  {{ formatSignedMoney(row.value) }}
                </div>
              </div>
              <div class="mt-2 h-2 overflow-hidden rounded-full bg-white/6">
                <div
                  class="h-full rounded-full"
                  :style="{ width: buildBarWidth(row.share), backgroundColor: row.value >= 0 ? 'rgba(52, 211, 153, 0.88)' : 'rgba(251, 113, 133, 0.88)' }"
                />
              </div>
            </div>
          </div>
        </div>

        <div v-else class="hz-surface-deep mt-5 rounded-[1.4rem] px-4 py-8 text-sm text-text-secondary">
          No cycle trend data is available yet.
        </div>
      </div>

      <div class="hz-surface-soft rounded-[1.75rem] p-5">
        <div class="flex items-center justify-between gap-3">
          <div>
            <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Income By Source</div>
            <div class="mt-1 text-sm text-text-secondary">What is feeding the current cycle economy right now.</div>
          </div>

          <div class="rounded-full border border-white/[0.055] bg-white/[0.03] px-3 py-1 text-[11px] font-bold uppercase tracking-[0.14em] text-horizon-white">
            {{ formatMoney(analytics.snapshot?.income ?? 0) }}
          </div>
        </div>

        <div v-if="incomeSourceRows.length" class="mt-5 grid gap-5 lg:grid-cols-[15rem_minmax(0,1fr)] lg:items-center">
          <div class="flex items-center justify-center">
            <div class="relative flex h-44 w-44 items-center justify-center rounded-full border border-white/[0.055] p-3">
              <div class="absolute inset-3 rounded-full" :style="buildDonutStyle(incomeSourceRows)" />
              <div class="absolute inset-[2.35rem] rounded-full bg-[color:var(--color-surface-deep)] ring-1 ring-white/5" />
              <div class="relative text-center">
                <div class="text-[11px] font-bold uppercase tracking-[0.18em] text-text-muted">Income</div>
                <div class="mt-2 text-2xl font-black text-horizon-white">{{ formatCompactNumber(analytics.snapshot?.income ?? 0) }}</div>
                <div class="text-xs text-text-secondary">aUEC tracked</div>
              </div>
            </div>
          </div>

          <div class="space-y-3">
            <div
              v-for="row in incomeSourceRows"
              :key="`income-${row.label}`"
              class="hz-surface-deep rounded-[1.1rem] px-4 py-3"
            >
              <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                  <span class="h-3 w-3 rounded-full" :style="{ backgroundColor: row.color }" />
                  <div class="text-sm font-bold text-horizon-white">{{ row.label }}</div>
                </div>
                <div class="text-sm text-text-secondary">{{ formatMoney(row.value) }}</div>
              </div>
              <div class="mt-2 h-2 overflow-hidden rounded-full bg-white/6">
                <div class="h-full rounded-full" :style="{ width: buildBarWidth(row.share), backgroundColor: row.color }" />
              </div>
            </div>
          </div>
        </div>

        <div v-else class="hz-surface-deep mt-5 rounded-[1.4rem] px-4 py-8 text-sm text-text-secondary">
          No income-source data is available yet.
        </div>
      </div>

      <div class="hz-surface-soft rounded-[1.75rem] p-5">
        <div class="flex items-center justify-between gap-3">
          <div>
            <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Expense By Source</div>
            <div class="mt-1 text-sm text-text-secondary">Where spending pressure is landing during the current cycle.</div>
          </div>

          <div class="rounded-full border border-white/[0.055] bg-white/[0.03] px-3 py-1 text-[11px] font-bold uppercase tracking-[0.14em] text-horizon-white">
            {{ formatMoney(analytics.snapshot?.expenses ?? 0) }}
          </div>
        </div>

        <div v-if="expenseSourceRows.length" class="mt-5 space-y-3">
          <div
            v-for="row in expenseSourceRows"
            :key="`expense-${row.label}`"
            class="hz-surface-deep rounded-[1.1rem] px-4 py-4"
          >
            <div class="flex items-center justify-between gap-3">
              <div class="text-sm font-bold text-horizon-white">{{ row.label }}</div>
              <div class="text-sm text-text-secondary">{{ formatMoney(row.value) }}</div>
            </div>
            <div class="mt-3 h-3 overflow-hidden rounded-full bg-white/6">
              <div class="h-full rounded-full" :style="{ width: buildBarWidth(row.share), backgroundColor: row.color }" />
            </div>
            <div class="mt-2 text-[11px] font-bold uppercase tracking-[0.14em] text-text-muted">
              {{ Math.round(row.share * 100) }}% of current cycle expenses
            </div>
          </div>
        </div>

        <div v-else class="hz-surface-deep mt-5 rounded-[1.4rem] px-4 py-8 text-sm text-text-secondary">
          No expense-source data is available yet.
        </div>
      </div>

      <div class="hz-surface-soft rounded-[1.75rem] p-5">
        <div class="flex items-center justify-between gap-3">
          <div>
            <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Trade Profit By Commodity</div>
            <div class="mt-1 text-sm text-text-secondary">The strongest current-cycle trade performers.</div>
          </div>

          <div class="rounded-full border border-white/[0.055] bg-white/[0.03] px-3 py-1 text-[11px] font-bold uppercase tracking-[0.14em] text-horizon-white">
            {{ formatMoney(analytics.snapshot?.trade_profit ?? 0) }}
          </div>
        </div>

        <div v-if="tradeCommodityRows.length" class="mt-5 space-y-3">
          <div
            v-for="row in tradeCommodityRows"
            :key="`trade-${row.label}`"
            class="hz-surface-deep rounded-[1.1rem] px-4 py-4"
          >
            <div class="flex items-center justify-between gap-3">
              <div class="text-sm font-bold text-horizon-white">{{ row.label }}</div>
              <div class="text-sm text-cyan-100">{{ formatMoney(row.value) }}</div>
            </div>
            <div class="mt-3 h-3 overflow-hidden rounded-full bg-white/6">
              <div
                class="h-full rounded-full"
                :style="{ width: buildBarWidth(row.share), background: `linear-gradient(90deg, ${row.color}, rgba(255, 255, 255, 0.65))` }"
              />
            </div>
          </div>
        </div>

        <div v-else class="hz-surface-deep mt-5 rounded-[1.4rem] px-4 py-8 text-sm text-text-secondary">
          No trade profit data is available yet.
        </div>
      </div>

      <div class="hz-surface-soft rounded-[1.75rem] p-5">
        <div class="flex items-center justify-between gap-3">
          <div>
            <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Inventory Value By Category</div>
            <div class="mt-1 text-sm text-text-secondary">Where the stored value is sitting right now.</div>
          </div>

          <div class="rounded-full border border-white/[0.055] bg-white/[0.03] px-3 py-1 text-[11px] font-bold uppercase tracking-[0.14em] text-horizon-white">
            {{ formatMoney(analytics.snapshot?.inventory_value ?? 0) }}
          </div>
        </div>

        <div v-if="inventoryCategoryRows.length" class="mt-5 grid gap-5 lg:grid-cols-[15rem_minmax(0,1fr)] lg:items-center">
          <div class="flex items-center justify-center">
            <div class="relative flex h-44 w-44 items-center justify-center rounded-full border border-white/[0.055] p-3">
              <div class="absolute inset-3 rounded-full" :style="buildDonutStyle(inventoryCategoryRows)" />
              <div class="absolute inset-[2.35rem] rounded-full bg-[color:var(--color-surface-deep)] ring-1 ring-white/5" />
              <div class="relative text-center">
                <div class="text-[11px] font-bold uppercase tracking-[0.18em] text-text-muted">Inventory</div>
                <div class="mt-2 text-2xl font-black text-horizon-white">{{ formatCompactNumber(analytics.snapshot?.inventory_value ?? 0) }}</div>
                <div class="text-xs text-text-secondary">aUEC value</div>
              </div>
            </div>
          </div>

          <div class="space-y-3">
            <div
              v-for="row in inventoryCategoryRows"
              :key="`inventory-${row.label}`"
              class="hz-surface-deep rounded-[1.1rem] px-4 py-3"
            >
              <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                  <span class="h-3 w-3 rounded-full" :style="{ backgroundColor: row.color }" />
                  <div class="text-sm font-bold text-horizon-white">{{ row.label }}</div>
                </div>
                <div class="text-sm text-text-secondary">{{ formatMoney(row.value) }}</div>
              </div>
              <div class="mt-2 h-2 overflow-hidden rounded-full bg-white/6">
                <div class="h-full rounded-full" :style="{ width: buildBarWidth(row.share), backgroundColor: row.color }" />
              </div>
            </div>
          </div>
        </div>

        <div v-else class="hz-surface-deep mt-5 rounded-[1.4rem] px-4 py-8 text-sm text-text-secondary">
          No inventory category breakdown is available yet.
        </div>
      </div>

      <div class="hz-surface-soft rounded-[1.75rem] p-5">
        <div class="flex items-center justify-between gap-3">
          <div>
            <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Fleet Status Mix</div>
            <div class="mt-1 text-sm text-text-secondary">A quick read on the current tracked ship state mix.</div>
          </div>

          <div class="rounded-full border border-white/[0.055] bg-white/[0.03] px-3 py-1 text-[11px] font-bold uppercase tracking-[0.14em] text-horizon-white">
            {{ formatCount(analytics.snapshot?.ship_count ?? 0) }} ships
          </div>
        </div>

        <div v-if="shipStatusRows.length" class="mt-5 space-y-3">
          <div
            v-for="row in shipStatusRows"
            :key="`ship-status-${row.label}`"
            class="hz-surface-deep rounded-[1.1rem] px-4 py-4"
          >
            <div class="flex items-center justify-between gap-3">
              <div class="text-sm font-bold text-horizon-white">{{ row.label }}</div>
              <div class="text-sm text-text-secondary">{{ formatCount(row.value) }}</div>
            </div>
            <div class="mt-3 h-3 overflow-hidden rounded-full bg-white/6">
              <div class="h-full rounded-full" :style="{ width: buildBarWidth(row.share), backgroundColor: row.color }" />
            </div>
          </div>
        </div>

        <div v-else class="hz-surface-deep mt-5 rounded-[1.4rem] px-4 py-8 text-sm text-text-secondary">
          No ship status mix is available yet.
        </div>
      </div>
    </section>

    <section class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_24rem]">
      <div class="hz-surface-soft rounded-[1.75rem] p-5">
        <div class="flex items-center justify-between gap-3">
          <div>
            <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Cycle History</div>
            <h4 class="mt-1 text-xl font-black text-horizon-white">Cycles</h4>
          </div>

          <div class="rounded-full border border-white/[0.055] bg-white/[0.03] px-3 py-1 text-xs font-bold text-horizon-white">
            {{ ledger.wipeCycles?.length ?? 0 }} total
          </div>
        </div>

        <div class="mt-4 grid gap-3">
          <article
            v-for="wipe in ledger.wipeCycles ?? []"
            :key="wipe.id"
            class="hz-surface-deep rounded-[1.25rem] p-4"
          >
            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
              <div>
                <div class="flex flex-wrap items-center gap-2">
                  <template v-if="editingCycleId === wipe.id">
                    <div class="grid w-full max-w-2xl gap-3 md:grid-cols-2">
                      <div class="md:col-span-2">
                        <label class="mb-1 block text-[11px] font-bold uppercase tracking-[0.14em] text-text-muted">
                          Cycle Name
                        </label>
                        <input v-model="editCycleForm.name" type="text" class="hz-input" />
                        <div v-if="editCycleForm.errors.name" class="mt-2 text-xs text-rose-200">
                          {{ editCycleForm.errors.name }}
                        </div>
                      </div>

                      <div>
                        <label class="mb-1 block text-[11px] font-bold uppercase tracking-[0.14em] text-text-muted">
                          Version
                        </label>
                        <input v-model="editCycleForm.star_citizen_version" type="text" class="hz-input" placeholder="4.1.1" />
                        <div v-if="editCycleForm.errors.star_citizen_version" class="mt-2 text-xs text-rose-200">
                          {{ editCycleForm.errors.star_citizen_version }}
                        </div>
                      </div>

                      <div>
                        <label class="mb-1 block text-[11px] font-bold uppercase tracking-[0.14em] text-text-muted">
                          Cycle Type
                        </label>
                        <select v-model="editCycleForm.wipe_type" class="hz-input">
                          <option v-for="option in wipeTypeOptions" :key="option" :value="option">
                            {{ option }}
                          </option>
                        </select>
                        <div v-if="editCycleForm.errors.wipe_type" class="mt-2 text-xs text-rose-200">
                          {{ editCycleForm.errors.wipe_type }}
                        </div>
                      </div>

                      <div class="md:col-span-2">
                        <HorizonDateTimePicker
                          v-model="editCycleForm.started_at"
                          label="Start Time"
                          clearable
                          show-now
                        />
                        <div v-if="editCycleForm.errors.started_at" class="mt-2 text-xs text-rose-200">
                          {{ editCycleForm.errors.started_at }}
                        </div>
                      </div>
                    </div>
                  </template>
                  <div v-else class="text-lg font-black text-horizon-white">
                    {{ wipe.name }}
                  </div>

                  <span
                    class="rounded-full border px-3 py-1 text-[11px] font-bold uppercase tracking-[0.14em]"
                    :class="wipe.is_current
                      ? 'border-emerald-300/35 bg-emerald-300/10 text-emerald-100'
                      : 'border-white/[0.055] bg-[color:var(--color-surface-soft)] text-text-secondary'"
                  >
                    {{ wipe.is_current ? 'Current' : 'Archived' }}
                  </span>
                </div>

                <div v-if="editingCycleId !== wipe.id" class="mt-2 text-sm text-text-secondary">
                  {{ wipe.star_citizen_version || 'Version pending' }} • {{ wipe.wipe_type }}
                </div>

                <div v-if="editingCycleId !== wipe.id" class="mt-1 text-xs text-text-muted">
                  Started {{ wipe.started_at ? new Date(wipe.started_at).toLocaleString() : 'unknown' }}
                  <span v-if="wipe.ended_at">• Ended {{ new Date(wipe.ended_at).toLocaleString() }}</span>
                </div>

                <div v-else class="mt-3 text-xs text-text-secondary">
                  Update the live cycle details here without creating a brand-new cycle.
                </div>

                <p
                  v-if="wipe.notes && editingCycleId !== wipe.id"
                  class="mt-3 text-sm text-text-secondary"
                >
                  {{ wipe.notes }}
                </p>

                <div
                  v-if="savedCycleId === wipe.id && flashSuccess === 'Current cycle updated.'"
                  class="mt-3 inline-flex rounded-full border border-emerald-300/30 bg-emerald-300/10 px-3 py-1 text-[11px] font-bold uppercase tracking-[0.14em] text-emerald-100"
                >
                  Cycle details saved
                </div>
              </div>

              <div class="flex flex-wrap gap-2">
                <template v-if="editingCycleId === wipe.id">
                  <HorizonButton
                    size="sm"
                    variant="secondary"
                    :disabled="editCycleForm.processing"
                    @click="cancelRenameCycle"
                  >
                    Cancel
                  </HorizonButton>

                  <HorizonButton
                    size="sm"
                    :disabled="editCycleForm.processing"
                    @click="submitRenameCycle(wipe.id)"
                  >
                    Save Cycle
                  </HorizonButton>
                </template>

                <HorizonButton
                  v-else-if="wipe.is_current"
                  size="sm"
                  variant="ghost"
                  @click="startRenameCycle(wipe)"
                >
                  Edit Cycle
                </HorizonButton>

                <HorizonButton
                  v-if="!wipe.is_current && editingCycleId !== wipe.id"
                  size="sm"
                  variant="ghost"
                  @click="activateWipeCycle(wipe.id)"
                >
                  Set Current
                </HorizonButton>

                <HorizonButton
                  v-if="wipe.is_current && editingCycleId !== wipe.id"
                  size="sm"
                  variant="danger"
                  @click="closeWipeCycle(wipe.id)"
                >
                  Close Cycle
                </HorizonButton>
              </div>
            </div>
          </article>
        </div>
      </div>

      <section class="hz-surface-soft rounded-[1.75rem] p-5">
        <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">New Cycle</div>
        <h4 class="mt-1 text-xl font-black text-horizon-white">Start Next Cycle</h4>
        <p class="mt-2 text-sm text-text-secondary">
          Creating a new cycle automatically closes the current one and moves new Ledger records onto the fresh cycle.
        </p>

        <form class="mt-4 space-y-3" @submit.prevent="submitWipeCycle">
          <div>
            <label class="mb-1 block text-xs font-bold uppercase tracking-[0.14em] text-text-muted">
              Name
            </label>
            <input v-model="createWipeForm.name" type="text" class="hz-input" placeholder="4.1.1 Live" />
          </div>

          <div>
            <label class="mb-1 block text-xs font-bold uppercase tracking-[0.14em] text-text-muted">
              Star Citizen Version
            </label>
            <input v-model="createWipeForm.star_citizen_version" type="text" class="hz-input" placeholder="4.1.1" />
          </div>

          <div>
            <label class="mb-1 block text-xs font-bold uppercase tracking-[0.14em] text-text-muted">
              Cycle Type
            </label>
            <select v-model="createWipeForm.wipe_type" class="hz-input">
              <option v-for="option in wipeTypeOptions" :key="option" :value="option">
                {{ option }}
              </option>
            </select>
          </div>

          <HorizonDateTimePicker
            v-model="createWipeForm.started_at"
            label="Start Time"
            clearable
            show-now
          />

          <div>
            <label class="mb-1 block text-xs font-bold uppercase tracking-[0.14em] text-text-muted">
              Notes
            </label>
            <textarea v-model="createWipeForm.notes" class="hz-input min-h-28 resize-y" placeholder="Patch notes, cycle scope, or rollout context..."></textarea>
          </div>

          <div class="flex justify-end">
            <HorizonButton type="submit" :disabled="createWipeForm.processing">
              Create Current Cycle
            </HorizonButton>
          </div>
        </form>
      </section>
    </section>
  </div>
</template>
