<script setup>
import { computed, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { Ziggy } from '../ziggy'

import HorizonButton from '@/Components/HorizonButton.vue'
import HorizonInput from '@/Components/HorizonInput.vue'
import HorizonSelect from '@/Components/HorizonSelect.vue'

const props = defineProps({
  operation: {
    type: Object,
    required: true,
  },
  sharedLootOptions: {
    type: Object,
    default: null,
  },
  attendanceDraftMembers: {
    type: Array,
    default: () => [],
  },
  reloadOnly: {
    type: Array,
    default: () => [],
  },
  compact: {
    type: Boolean,
    default: false,
  },
})

const moneyRows = ref([])
const lootRows = ref([])
const saving = ref(false)
const finalizing = ref(false)
const reopening = ref(false)
const presetAmount = ref('')
const reserveSquadronKey = ref('')

const settlement = computed(() => props.operation?.operation_settlement ?? {})
const canManage = computed(() => !!settlement.value?.permissions?.can_manage)
const isFinalized = computed(() => !!settlement.value?.is_finalized)
const attendanceLocked = computed(() => !!settlement.value?.locked_attendance)
const settlementActivity = computed(() => settlement.value?.activity ?? {})

const recipientOptions = computed(() => {
  const baseOptions = (settlement.value?.eligible_recipients ?? []).map(recipient => ({
    value: recipient.key,
    label: recipient.label,
  }))

  const optionMap = new Map(baseOptions.map(option => [option.value, option]))

  for (const member of props.attendanceDraftMembers ?? []) {
    const memberId = Number(member?.id)
    if (!Number.isFinite(memberId)) continue

    const key = `member:${memberId}`

    if (!optionMap.has(key)) {
      optionMap.set(key, {
        value: key,
        label: member?.rsi_handle ?? member?.discord_name ?? member?.name ?? `Member #${memberId}`,
      })
    }
  }

  return Array.from(optionMap.values())
})

const lootOptionGroups = computed(() => props.sharedLootOptions ?? settlement.value?.loot_options ?? {
  commodities: [],
  items: [],
  components: [],
})

const participantTargets = computed(() => {
  const targets = new Map()

  for (const recipient of settlement.value?.eligible_recipients ?? []) {
    if (recipient?.recipient_type !== 'member') continue

    targets.set(recipient.key, {
      key: recipient.key,
      label: recipient.label,
    })
  }

  for (const member of props.attendanceDraftMembers ?? []) {
    const memberId = Number(member?.id)
    if (!Number.isFinite(memberId)) continue

    const key = `member:${memberId}`

    if (!targets.has(key)) {
      targets.set(key, {
        key,
        label: member?.rsi_handle ?? member?.discord_name ?? member?.name ?? `Member #${memberId}`,
      })
    }
  }

  return Array.from(targets.values())
})

const squadronTargets = computed(() => {
  return (settlement.value?.eligible_recipients ?? [])
    .filter(recipient => recipient?.recipient_type === 'squadron')
    .map(recipient => ({
      value: recipient.key,
      label: recipient.label,
      squadronId: recipient.recipient_squadron_id ?? null,
    }))
})

const assignedMoneyTotal = computed(() => {
  return moneyRows.value.reduce((total, row) => {
    const amount = Number(row?.amount ?? 0)
    return total + (Number.isFinite(amount) ? amount : 0)
  }, 0)
})

const assignedRecipientCount = computed(() => {
  const keys = new Set()

  for (const row of [...moneyRows.value, ...lootRows.value]) {
    const key = selectedRecipientKey(row)
    if (key) keys.add(key)
  }

  return keys.size
})

const unassignedRowCount = computed(() => {
  return [...moneyRows.value, ...lootRows.value]
    .filter(row => !selectedRecipientKey(row))
    .length
})

const presetAmountValue = computed(() => {
  const amount = Number(presetAmount.value)
  return Number.isFinite(amount) ? amount : 0
})

const attendeeSplitPreview = computed(() => {
  if (!participantTargets.value.length || presetAmountValue.value <= 0) {
    return null
  }

  return presetAmountValue.value / participantTargets.value.length
})

const horizonReservePreview = computed(() => {
  if (presetAmountValue.value <= 0) {
    return null
  }

  return presetAmountValue.value * 0.1
})

const presetCoverageDelta = computed(() => {
  if (presetAmountValue.value <= 0) {
    return null
  }

  return presetAmountValue.value - assignedMoneyTotal.value
})

const finalizedAtLabel = computed(() => {
  const value = settlementActivity.value?.finalized_at ?? settlement.value?.finalized_at
  if (!value) return null

  return formatDateLabel(value)
})

const hasChanges = computed(() => {
  return JSON.stringify(serializeRows(moneyRows.value, lootRows.value))
    !== JSON.stringify(serializeRows(
      settlement.value?.money_rows ?? [],
      settlement.value?.loot_rows ?? [],
    ))
})

const settlementActivityRows = computed(() => {
  const rows = [
    {
      label: 'Last saved',
      value: formatDateLabel(settlementActivity.value?.updated_at ?? settlement.value?.updated_at),
      detail: 'Most recent draft or finalize change',
    },
    {
      label: 'Finalized',
      value: finalizedAtLabel.value,
      detail: settlementActivity.value?.finalized_by ? `by ${settlementActivity.value.finalized_by}` : 'Not finalized yet',
    },
    {
      label: 'Last reopened',
      value: formatDateLabel(settlementActivity.value?.reopened_at),
      detail: settlementActivity.value?.reopened_by ? `by ${settlementActivity.value.reopened_by}` : 'No reopen recorded',
    },
  ]

  return rows.filter(row => row.value || row.detail)
})

watch(
  () => [
    props.operation?.id,
    settlement.value?.updated_at,
    settlement.value?.finalized_at,
    JSON.stringify(settlement.value?.money_rows ?? []),
    JSON.stringify(settlement.value?.loot_rows ?? []),
  ],
  () => {
    moneyRows.value = (settlement.value?.money_rows ?? []).map(cloneMoneyRow)
    lootRows.value = (settlement.value?.loot_rows ?? []).map(cloneLootRow)
  },
  { immediate: true },
)

watch(
  squadronTargets,
  (targets) => {
    if (!targets.length) {
      reserveSquadronKey.value = ''
      return
    }

    if (!targets.some(target => target.value === reserveSquadronKey.value)) {
      reserveSquadronKey.value = targets[0].value
    }
  },
  { immediate: true },
)

function createMoneyRow() {
  return {
    row_key: `money-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
    recipient_type: null,
    recipient_user_id: null,
    recipient_squadron_id: null,
    amount: '',
    notes: '',
  }
}

function createLootRow() {
  return {
    row_key: `loot-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
    source_type: 'commodity',
    uex_reference_id: '',
    recipient_type: null,
    recipient_user_id: null,
    recipient_squadron_id: null,
    quantity: '',
    unit_label: '',
    notes: '',
  }
}

function cloneMoneyRow(row) {
  return {
    row_key: row?.row_key ?? createMoneyRow().row_key,
    recipient_type: row?.recipient_type ?? null,
    recipient_user_id: row?.recipient_user_id ?? null,
    recipient_squadron_id: row?.recipient_squadron_id ?? null,
    amount: row?.amount ?? '',
    notes: row?.notes ?? '',
    recipient_label: row?.recipient_label ?? null,
  }
}

function cloneLootRow(row) {
  return {
    row_key: row?.row_key ?? createLootRow().row_key,
    source_type: row?.source_type ?? 'commodity',
    uex_reference_id: row?.uex_reference_id ?? '',
    recipient_type: row?.recipient_type ?? null,
    recipient_user_id: row?.recipient_user_id ?? null,
    recipient_squadron_id: row?.recipient_squadron_id ?? null,
    quantity: row?.quantity ?? '',
    unit_label: row?.unit_label ?? '',
    notes: row?.notes ?? '',
    reference_label: row?.reference_label ?? null,
    recipient_label: row?.recipient_label ?? null,
  }
}

function serializeRows(currentMoneyRows, currentLootRows) {
  return {
    money_rows: currentMoneyRows.map(row => ({
      row_key: row.row_key,
      recipient_type: row.recipient_type,
      recipient_user_id: row.recipient_user_id,
      recipient_squadron_id: row.recipient_squadron_id,
      amount: row.amount === '' ? null : row.amount,
      notes: row.notes || null,
    })),
    loot_rows: currentLootRows.map(row => ({
      row_key: row.row_key,
      source_type: row.source_type,
      uex_reference_id: row.uex_reference_id === '' ? null : row.uex_reference_id,
      recipient_type: row.recipient_type,
      recipient_user_id: row.recipient_user_id,
      recipient_squadron_id: row.recipient_squadron_id,
      quantity: row.quantity === '' ? null : row.quantity,
      unit_label: row.unit_label || null,
      notes: row.notes || null,
    })),
  }
}

function selectedRecipientKey(row) {
  if (row.recipient_type === 'member' && row.recipient_user_id) {
    return `member:${row.recipient_user_id}`
  }

  if (row.recipient_type === 'squadron' && row.recipient_squadron_id) {
    return `squadron:${row.recipient_squadron_id}`
  }

  if (row.recipient_type === 'organization') {
    return 'organization'
  }

  return null
}

function applyRecipient(row, key) {
  if (!key) {
    row.recipient_type = null
    row.recipient_user_id = null
    row.recipient_squadron_id = null
    return
  }

  if (String(key).startsWith('member:')) {
    row.recipient_type = 'member'
    row.recipient_user_id = Number(String(key).split(':')[1] ?? 0) || null
    row.recipient_squadron_id = null
    return
  }

  if (String(key).startsWith('squadron:')) {
    row.recipient_type = 'squadron'
    row.recipient_user_id = null
    row.recipient_squadron_id = Number(String(key).split(':')[1] ?? 0) || null
    return
  }

  row.recipient_type = String(key)
  row.recipient_user_id = null
  row.recipient_squadron_id = null
}

function lootOptionsFor(sourceType) {
  switch (sourceType) {
    case 'item':
      return lootOptionGroups.value.items ?? []
    case 'component':
      return lootOptionGroups.value.components ?? []
    default:
      return lootOptionGroups.value.commodities ?? []
  }
}

function notifyError(message) {
  window.hzNotifyError?.({
    message,
  })
}

function formatMoney(amount) {
  return `${new Intl.NumberFormat('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(Number(amount || 0))} aUEC`
}

function formatDateLabel(value) {
  if (!value) return null

  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return null

  return new Intl.DateTimeFormat('en-GB', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hour12: true,
  }).format(date)
}

function parsePresetAmount() {
  const amount = Number(presetAmount.value)
  return Number.isFinite(amount) && amount > 0 ? amount : null
}

function createRecipientMoneyRow(recipientKey, amount = '', notes = '') {
  const row = createMoneyRow()
  applyRecipient(row, recipientKey)
  row.amount = amount
  row.notes = notes
  return row
}

function createRecipientLootRow(recipientKey) {
  const row = createLootRow()
  applyRecipient(row, recipientKey)
  return row
}

function addParticipantPayout(recipientKey) {
  moneyRows.value = [...moneyRows.value, createRecipientMoneyRow(recipientKey)]
}

function addParticipantLoot(recipientKey) {
  lootRows.value = [...lootRows.value, createRecipientLootRow(recipientKey)]
}

function splitPresetAmountAcrossAttendance() {
  const amount = parsePresetAmount()
  const recipients = participantTargets.value

  if (!amount) {
    notifyError('Enter a positive amount before splitting it across attendance.')
    return
  }

  if (!recipients.length) {
    notifyError('No final attendance members are available for an even split yet.')
    return
  }

  const totalCents = Math.round(amount * 100)
  const baseCents = Math.floor(totalCents / recipients.length)
  let remainderCents = totalCents - (baseCents * recipients.length)

  const newRows = recipients.map((recipient) => {
    const cents = baseCents + (remainderCents > 0 ? 1 : 0)
    remainderCents = Math.max(0, remainderCents - 1)

    return createRecipientMoneyRow(
      recipient.key,
      (cents / 100).toFixed(2),
      'Even attendee split',
    )
  })

  moneyRows.value = [...moneyRows.value, ...newRows]
}

function addSquadronReserveRow() {
  const squadronKey = reserveSquadronKey.value || squadronTargets.value[0]?.value || ''

  if (!squadronKey) {
    notifyError('Choose a squadron target before adding a reserve row.')
    return
  }

  const amount = parsePresetAmount()

  moneyRows.value = [
    ...moneyRows.value,
    createRecipientMoneyRow(
      squadronKey,
      amount ? amount.toFixed(2) : '',
      'Squadron reserve',
    ),
  ]
}

function reserveTenPercentForHorizon() {
  const moneyBase = moneyRows.value.reduce((total, row) => {
    const amount = Number(row?.amount ?? 0)

    if (!Number.isFinite(amount) || amount <= 0 || row?.recipient_type === 'organization') {
      return total
    }

    return total + amount
  }, 0)

  const baseAmount = moneyBase > 0 ? moneyBase : parsePresetAmount()

  if (!baseAmount) {
    notifyError('Add payout amounts first, or enter an amount in the preset field before setting aside Horizon’s 10 percent share.')
    return
  }

  const horizonAmount = (Math.round((baseAmount * 0.10) * 100) / 100).toFixed(2)
  const organizationIndex = moneyRows.value.findIndex(row => row?.recipient_type === 'organization')

  if (organizationIndex === -1) {
    moneyRows.value = [
      ...moneyRows.value,
      createRecipientMoneyRow('organization', horizonAmount, '10% Horizon reserve'),
    ]
    return
  }

  moneyRows.value = moneyRows.value.map((row, index) => {
    if (index !== organizationIndex) return row

    return {
      ...row,
      amount: horizonAmount,
      notes: row.notes || '10% Horizon reserve',
    }
  })
}

function addMoneyRow() {
  moneyRows.value = [...moneyRows.value, createMoneyRow()]
}

function removeMoneyRow(rowKey) {
  moneyRows.value = moneyRows.value.filter(row => row.row_key !== rowKey)
}

function addLootRow() {
  lootRows.value = [...lootRows.value, createLootRow()]
}

function removeLootRow(rowKey) {
  lootRows.value = lootRows.value.filter(row => row.row_key !== rowKey)
}

function updateLootType(row, value) {
  row.source_type = value
  row.uex_reference_id = ''
}

function saveDraft() {
  if (!props.operation?.id || saving.value || !canManage.value || isFinalized.value) return

  saving.value = true

  router.put(route('operations.settlement.update', props.operation.id, Ziggy), serializeRows(moneyRows.value, lootRows.value), {
    preserveScroll: true,
    preserveState: true,
    only: props.reloadOnly,
    onError: showFirstError,
    onFinish: () => {
      saving.value = false
    },
  })
}

function finalizeSettlement() {
  if (!props.operation?.id || finalizing.value || !canManage.value || isFinalized.value) return

  finalizing.value = true

  router.post(route('operations.settlement.finalize', props.operation.id, Ziggy), serializeRows(moneyRows.value, lootRows.value), {
    preserveScroll: true,
    preserveState: true,
    only: props.reloadOnly,
    onError: showFirstError,
    onFinish: () => {
      finalizing.value = false
    },
  })
}

function reopenSettlement() {
  if (!props.operation?.id || reopening.value || !canManage.value || !isFinalized.value) return

  reopening.value = true

  router.post(route('operations.settlement.reopen', props.operation.id, Ziggy), {}, {
    preserveScroll: true,
    preserveState: true,
    only: props.reloadOnly,
    onError: showFirstError,
    onFinish: () => {
      reopening.value = false
    },
  })
}

function exportSettlement() {
  if (!props.operation?.id || !isFinalized.value) return

  window.location.assign(route('operations.settlement.export', props.operation.id, Ziggy))
}

function showFirstError(errors) {
  const firstError = Object.values(errors ?? {})[0]
  const message = Array.isArray(firstError) ? firstError[0] : firstError

  notifyError(message ?? 'That operation settlement update could not be saved.')
}
</script>

<template>
  <section
    v-if="operation?.status === 'completed'"
    :class="compact
      ? 'rounded-[1.25rem] border border-white/[0.055] bg-black/10 p-4'
      : 'hz-surface-welcome rounded-[1.75rem] border border-white/[0.055] p-4 md:p-5'"
  >
    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
      <div>
        <div class="text-xs font-bold uppercase tracking-[0.2em] text-text-muted">
          Operation Settlement
        </div>

        <h3 class="mt-1 text-xl font-black text-horizon-white">
          Payouts And Loot
        </h3>

        <p class="mt-2 max-w-3xl text-sm text-text-secondary">
          Record money payouts and UEX-backed loot, then finalize them into the member, squadron, or Horizon ledgers.
        </p>
      </div>

      <div class="flex flex-wrap gap-2">
        <HorizonButton
          v-if="isFinalized"
          type="button"
          size="sm"
          variant="ghost"
          @click="exportSettlement"
        >
          Export CSV
        </HorizonButton>

        <div class="rounded-full border border-white/[0.055] bg-white/[0.024] px-3 py-1 text-xs font-semibold text-text-secondary">
          {{ moneyRows.length }} payout rows
        </div>

        <div class="rounded-full border border-white/[0.055] bg-white/[0.024] px-3 py-1 text-xs font-semibold text-text-secondary">
          {{ lootRows.length }} loot rows
        </div>

        <div
          class="rounded-full border px-3 py-1 text-xs font-semibold"
          :class="isFinalized
            ? 'border-emerald-300/25 bg-emerald-300/10 text-horizon-white'
            : 'border-white/[0.055] bg-white/[0.024] text-text-secondary'"
        >
          {{ isFinalized ? 'Finalized' : 'Draft' }}
        </div>
      </div>
    </div>

    <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
      <div class="rounded-[1rem] border border-white/[0.055] bg-black/10 px-4 py-3">
        <div class="text-[10px] font-bold uppercase tracking-[0.16em] text-text-muted">Assigned Money</div>
        <div class="mt-2 text-lg font-black text-horizon-white">{{ formatMoney(assignedMoneyTotal) }}</div>
      </div>

      <div class="rounded-[1rem] border border-white/[0.055] bg-black/10 px-4 py-3">
        <div class="text-[10px] font-bold uppercase tracking-[0.16em] text-text-muted">Recipients</div>
        <div class="mt-2 text-lg font-black text-horizon-white">{{ assignedRecipientCount }}</div>
      </div>

      <div class="rounded-[1rem] border border-white/[0.055] bg-black/10 px-4 py-3">
        <div class="text-[10px] font-bold uppercase tracking-[0.16em] text-text-muted">Payout Rows</div>
        <div class="mt-2 text-lg font-black text-horizon-white">{{ moneyRows.length }}</div>
      </div>

      <div class="rounded-[1rem] border border-white/[0.055] bg-black/10 px-4 py-3">
        <div class="text-[10px] font-bold uppercase tracking-[0.16em] text-text-muted">Loot Rows</div>
        <div class="mt-2 text-lg font-black text-horizon-white">{{ lootRows.length }}</div>
      </div>

      <div class="rounded-[1rem] border border-white/[0.055] bg-black/10 px-4 py-3">
        <div class="text-[10px] font-bold uppercase tracking-[0.16em] text-text-muted">Needs Attention</div>
        <div class="mt-2 text-lg font-black text-horizon-white">{{ unassignedRowCount }}</div>
      </div>
    </div>

    <div
      v-if="attendanceLocked"
      class="mt-4 rounded-[1rem] border border-emerald-300/20 bg-emerald-300/8 px-4 py-3 text-sm text-text-secondary"
    >
      Final attendance is locked while this settlement is finalized.
      <span v-if="finalizedAtLabel"> Finalized {{ finalizedAtLabel }}.</span>
    </div>

    <div class="mt-4 rounded-[1.25rem] border border-white/[0.055] bg-black/10 p-4">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Settlement Activity</div>
          <div class="mt-1 text-sm text-text-secondary">
            Latest settlement timestamps for this operation without leaving the AAR flow.
          </div>
        </div>
        <div
          class="rounded-full border border-white/[0.055] bg-white/[0.024] px-3 py-1 text-[10px] font-bold uppercase tracking-[0.16em] text-text-secondary"
        >
          {{ isFinalized ? 'Live in ledgers' : 'Still in draft' }}
        </div>
      </div>

      <div class="mt-4 grid gap-3 md:grid-cols-3">
        <div
          v-for="row in settlementActivityRows"
          :key="row.label"
          class="rounded-[1rem] border border-white/[0.055] bg-white/[0.024] px-4 py-3"
        >
          <div class="text-[10px] font-bold uppercase tracking-[0.16em] text-text-muted">{{ row.label }}</div>
          <div class="mt-2 text-sm font-semibold text-horizon-white">
            {{ row.value ?? 'Not yet' }}
          </div>
          <div class="mt-1 text-xs text-text-secondary">{{ row.detail }}</div>
        </div>
      </div>
    </div>

    <div v-if="canManage && !isFinalized" class="mt-4 grid gap-4 xl:grid-cols-[minmax(0,1.2fr)_minmax(0,0.8fr)]">
      <div class="rounded-[1.25rem] border border-white/[0.055] bg-black/10 p-4">
        <div class="flex items-center justify-between gap-3">
          <div>
            <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Attendance Quick Add</div>
            <div class="mt-1 text-sm text-text-secondary">Drop in payout or loot rows for the final attendee list without re-picking the same recipients.</div>
          </div>
          <div class="rounded-full border border-white/[0.055] bg-white/[0.024] px-3 py-1 text-[10px] font-bold uppercase tracking-[0.16em] text-text-secondary">
            {{ participantTargets.length }} attendees
          </div>
        </div>

        <div v-if="participantTargets.length" class="mt-4 grid gap-3 lg:grid-cols-2">
          <div
            v-for="participant in participantTargets"
            :key="participant.key"
            class="rounded-[1rem] border border-white/[0.055] bg-white/[0.024] px-3 py-3"
          >
            <div class="text-sm font-semibold text-horizon-white">{{ participant.label }}</div>
            <div class="mt-3 flex flex-wrap gap-2">
              <HorizonButton
                type="button"
                size="sm"
                variant="ghost"
                @click="addParticipantPayout(participant.key)"
              >
                Add Payout
              </HorizonButton>
              <HorizonButton
                type="button"
                size="sm"
                variant="ghost"
                @click="addParticipantLoot(participant.key)"
              >
                Add Loot
              </HorizonButton>
            </div>
          </div>
        </div>

        <div
          v-else
          class="mt-4 rounded-[1rem] border border-dashed border-white/15 bg-white/[0.024] px-4 py-4 text-sm text-text-secondary"
        >
          Final attendance will show up here once the AAR roster is filled in.
        </div>
      </div>

      <div class="rounded-[1.25rem] border border-white/[0.055] bg-black/10 p-4">
        <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Settlement Helpers</div>
        <div class="mt-1 text-sm text-text-secondary">
          Use one amount field for quick attendee splits, reserve rows, and Horizon’s 10 percent share.
        </div>

        <div class="mt-4 space-y-3">
          <HorizonInput
            v-model="presetAmount"
            type="number"
            step="0.01"
            min="0"
            placeholder="Preset amount"
          />

          <HorizonSelect
            v-if="squadronTargets.length"
            v-model="reserveSquadronKey"
            :options="squadronTargets"
            placeholder="Choose squadron reserve target"
            searchable
            search-placeholder="Search operation squadrons"
          />

          <div class="flex flex-wrap gap-2">
            <HorizonButton
              type="button"
              size="sm"
              variant="ghost"
              @click="splitPresetAmountAcrossAttendance"
            >
              Split To Attendance
            </HorizonButton>
            <HorizonButton
              type="button"
              size="sm"
              variant="ghost"
              :disabled="!squadronTargets.length"
              @click="addSquadronReserveRow"
            >
              Add Squadron Reserve
            </HorizonButton>
            <HorizonButton
              type="button"
              size="sm"
              variant="ghost"
              @click="reserveTenPercentForHorizon"
            >
              Reserve 10% For Horizon
            </HorizonButton>
          </div>

          <div class="rounded-[1rem] border border-white/[0.055] bg-white/[0.024] px-4 py-3">
            <div class="text-[10px] font-bold uppercase tracking-[0.16em] text-text-muted">Helper Math</div>
            <div class="mt-3 grid gap-3 sm:grid-cols-3">
              <div>
                <div class="text-[10px] font-bold uppercase tracking-[0.14em] text-text-muted">Attendance Split</div>
                <div class="mt-1 text-sm font-semibold text-horizon-white">
                  {{ attendeeSplitPreview !== null ? formatMoney(attendeeSplitPreview) : 'Add an amount' }}
                </div>
                <div class="mt-1 text-xs text-text-secondary">
                  {{ participantTargets.length ? `${participantTargets.length} attendees in the current AAR roster.` : 'No final attendees available yet.' }}
                </div>
              </div>

              <div>
                <div class="text-[10px] font-bold uppercase tracking-[0.14em] text-text-muted">Horizon Reserve</div>
                <div class="mt-1 text-sm font-semibold text-horizon-white">
                  {{ horizonReservePreview !== null ? formatMoney(horizonReservePreview) : 'Add an amount' }}
                </div>
                <div class="mt-1 text-xs text-text-secondary">
                  10 percent of the helper amount.
                </div>
              </div>

              <div>
                <div class="text-[10px] font-bold uppercase tracking-[0.14em] text-text-muted">Preset Coverage</div>
                <div class="mt-1 text-sm font-semibold text-horizon-white">
                  {{ presetCoverageDelta !== null ? formatMoney(presetCoverageDelta) : 'Add an amount' }}
                </div>
                <div class="mt-1 text-xs text-text-secondary">
                  Remaining helper amount after current payout rows.
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="mt-5 grid gap-4 xl:grid-cols-2">
      <div class="hz-surface-welcome rounded-[1.25rem] border border-white/[0.055] p-4">
        <div class="flex items-center justify-between gap-3">
          <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
            Money Payouts
          </div>

          <HorizonButton
            v-if="canManage && !isFinalized"
            type="button"
            size="sm"
            variant="ghost"
            @click="addMoneyRow"
          >
            Add Row
          </HorizonButton>
        </div>

        <div v-if="moneyRows.length" class="mt-4 space-y-3">
          <div
            v-for="row in moneyRows"
            :key="row.row_key"
            class="rounded-[1rem] border border-white/[0.055] bg-black/10 p-4"
          >
            <template v-if="canManage && !isFinalized">
              <div class="grid gap-3 md:grid-cols-2">
                <HorizonSelect
                  :model-value="selectedRecipientKey(row)"
                  :options="recipientOptions"
                  placeholder="Send payout to..."
                  :searchable="true"
                  search-placeholder="Search recipients..."
                  @update:model-value="value => applyRecipient(row, value)"
                />

                <HorizonInput
                  v-model="row.amount"
                  type="number"
                  step="0.01"
                  min="0"
                  placeholder="Amount"
                />
              </div>

              <HorizonInput
                v-model="row.notes"
                type="textarea"
                rows="3"
                class="mt-3"
                placeholder="Optional payout note..."
              />

              <div class="mt-3 flex justify-end">
                <HorizonButton
                  type="button"
                  size="sm"
                  variant="danger"
                  @click="removeMoneyRow(row.row_key)"
                >
                  Remove
                </HorizonButton>
              </div>
            </template>

            <template v-else>
              <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <div class="text-sm font-semibold text-horizon-white">
                    {{ row.recipient_label ?? 'Unassigned destination' }}
                  </div>

                  <div v-if="row.notes" class="mt-2 whitespace-pre-line text-sm text-text-secondary">
                    {{ row.notes }}
                  </div>
                </div>

                <div class="text-lg font-black text-horizon-white">
                  {{ row.amount ?? '0' }} aUEC
                </div>
              </div>
            </template>
          </div>
        </div>

        <div
          v-else
          class="mt-4 rounded-[1rem] border border-dashed border-white/15 bg-black/10 px-4 py-5 text-sm text-text-secondary"
        >
          No payout rows yet.
        </div>
      </div>

      <div class="hz-surface-welcome rounded-[1.25rem] border border-white/[0.055] p-4">
        <div class="flex items-center justify-between gap-3">
          <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
            Loot Distribution
          </div>

          <HorizonButton
            v-if="canManage && !isFinalized"
            type="button"
            size="sm"
            variant="ghost"
            @click="addLootRow"
          >
            Add Row
          </HorizonButton>
        </div>

        <div v-if="lootRows.length" class="mt-4 space-y-3">
          <div
            v-for="row in lootRows"
            :key="row.row_key"
            class="rounded-[1rem] border border-white/[0.055] bg-black/10 p-4"
          >
            <template v-if="canManage && !isFinalized">
              <div class="grid gap-3 md:grid-cols-2">
                <HorizonSelect
                  :model-value="row.source_type"
                  :options="[
                    { value: 'commodity', label: 'Commodity' },
                    { value: 'item', label: 'Item' },
                    { value: 'component', label: 'Component' },
                  ]"
                  placeholder="Loot type"
                  @update:model-value="value => updateLootType(row, value)"
                />

                <HorizonSelect
                  v-model="row.uex_reference_id"
                  :options="lootOptionsFor(row.source_type)"
                  placeholder="Pick synced UEX loot..."
                  :searchable="true"
                  search-placeholder="Search synced loot..."
                />
              </div>

              <div class="mt-3 grid gap-3 md:grid-cols-3">
                <HorizonInput
                  v-model="row.quantity"
                  type="number"
                  step="0.0001"
                  min="0"
                  placeholder="Quantity"
                />

                <HorizonInput
                  v-model="row.unit_label"
                  placeholder="Unit label"
                />

                <HorizonSelect
                  :model-value="selectedRecipientKey(row)"
                  :options="recipientOptions"
                  placeholder="Send loot to..."
                  :searchable="true"
                  search-placeholder="Search recipients..."
                  @update:model-value="value => applyRecipient(row, value)"
                />
              </div>

              <HorizonInput
                v-model="row.notes"
                type="textarea"
                rows="3"
                class="mt-3"
                placeholder="Optional loot note..."
              />

              <div class="mt-3 flex justify-end">
                <HorizonButton
                  type="button"
                  size="sm"
                  variant="danger"
                  @click="removeLootRow(row.row_key)"
                >
                  Remove
                </HorizonButton>
              </div>
            </template>

            <template v-else>
              <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <div class="text-sm font-semibold text-horizon-white">
                    {{ row.reference_label ?? 'Unknown synced loot' }}
                  </div>

                  <div class="mt-1 text-sm text-text-secondary">
                    {{ row.quantity ?? '0' }} {{ row.unit_label || 'units' }} to {{ row.recipient_label ?? 'Unassigned destination' }}
                  </div>

                  <div v-if="row.notes" class="mt-2 whitespace-pre-line text-sm text-text-secondary">
                    {{ row.notes }}
                  </div>
                </div>

                <div class="rounded-full border border-white/[0.055] bg-white/[0.024] px-3 py-1 text-xs font-semibold text-text-secondary">
                  {{ row.source_type ?? 'loot' }}
                </div>
              </div>
            </template>
          </div>
        </div>

        <div
          v-else
          class="mt-4 rounded-[1rem] border border-dashed border-white/15 bg-black/10 px-4 py-5 text-sm text-text-secondary"
        >
          No loot rows yet.
        </div>
      </div>
    </div>

    <div
      v-if="canManage"
      class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-[1rem] border border-white/[0.055] bg-black/10 px-4 py-4"
    >
      <div class="text-sm text-text-secondary">
        Draft rows stay editable until you finalize them into the ledgers.
      </div>

      <div class="flex flex-wrap gap-2">
        <HorizonButton
          v-if="!isFinalized"
          type="button"
          size="sm"
          variant="ghost"
          :disabled="saving || !hasChanges"
          @click="saveDraft"
        >
          {{ saving ? 'Saving…' : 'Save Draft' }}
        </HorizonButton>

        <HorizonButton
          v-if="!isFinalized"
          type="button"
          size="sm"
          variant="primary"
          :disabled="finalizing"
          @click="finalizeSettlement"
        >
          {{ finalizing ? 'Finalizing…' : 'Finalize Settlement' }}
        </HorizonButton>

        <HorizonButton
          v-else
          type="button"
          size="sm"
          variant="danger"
          :disabled="reopening"
          @click="reopenSettlement"
        >
          {{ reopening ? 'Reopening…' : 'Reopen Settlement' }}
        </HorizonButton>
      </div>
    </div>
  </section>
</template>
