<script setup>
import { computed, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { Ziggy } from '../ziggy'

import HorizonBadge from '@/Components/HorizonBadge.vue'
import HorizonButton from '@/Components/HorizonButton.vue'
import HorizonFormBlock from '@/Components/HorizonFormBlock.vue'
import HorizonInput from '@/Components/HorizonInput.vue'
import HorizonSelect from '@/Components/HorizonSelect.vue'
import {
  buildEvenSplitAmounts,
  calculateDistributableAmount,
  calculateHorizonReserve,
  calculateReserveBaseAmount,
  normalizeWholeNumberField,
  parsePositiveWholeNumber,
  wholeNumber,
} from '@/operationPayoutMath'

const settlementDraftStore = new Map()

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

function settlementDraftKey() {
  return `settlement:${props.operation?.id ?? 'unknown'}`
}

function currentSettlementSignature() {
  return JSON.stringify([
    props.operation?.id ?? null,
    settlement.value?.updated_at ?? null,
    settlement.value?.finalized_at ?? null,
    settlement.value?.money_rows ?? [],
    settlement.value?.loot_rows ?? [],
  ])
}

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
    const amount = wholeNumber(row?.amount ?? 0)
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
  return wholeNumber(presetAmount.value) ?? 0
})

const presetHorizonReserveValue = computed(() => {
  return calculateHorizonReserve(presetAmountValue.value)
})

const presetDistributableValue = computed(() => {
  return calculateDistributableAmount(presetAmountValue.value)
})

const attendeeSplitPreview = computed(() => {
  if (!participantTargets.value.length || presetDistributableValue.value <= 0) {
    return null
  }

  return Math.floor(presetDistributableValue.value / participantTargets.value.length)
})

const horizonReservePreview = computed(() => {
  if (presetAmountValue.value <= 0) {
    return null
  }

  return presetHorizonReserveValue.value
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

function persistSettlementDraft(signature = currentSettlementSignature()) {
  settlementDraftStore.set(settlementDraftKey(), {
    signature,
    moneyRows: moneyRows.value.map(cloneMoneyRow),
    lootRows: lootRows.value.map(cloneLootRow),
    presetAmount: presetAmount.value,
    reserveSquadronKey: reserveSquadronKey.value,
  })
}

watch(
  () => [
    props.operation?.id,
    settlement.value?.updated_at,
    settlement.value?.finalized_at,
    JSON.stringify(settlement.value?.money_rows ?? []),
    JSON.stringify(settlement.value?.loot_rows ?? []),
  ],
  () => {
    const nextSignature = currentSettlementSignature()
    const storedDraft = settlementDraftStore.get(settlementDraftKey())

    if (storedDraft?.signature === nextSignature) {
      moneyRows.value = Array.isArray(storedDraft.moneyRows) ? storedDraft.moneyRows.map(cloneMoneyRow) : moneyRows.value
      lootRows.value = Array.isArray(storedDraft.lootRows) ? storedDraft.lootRows.map(cloneLootRow) : lootRows.value
      presetAmount.value = storedDraft.presetAmount ?? presetAmount.value
      reserveSquadronKey.value = storedDraft.reserveSquadronKey ?? reserveSquadronKey.value
      return
    }

    moneyRows.value = (settlement.value?.money_rows ?? []).map(cloneMoneyRow)
    lootRows.value = (settlement.value?.loot_rows ?? []).map(cloneLootRow)
    presetAmount.value = ''
    persistSettlementDraft(nextSignature)
  },
  { immediate: true },
)

watch(
  () => [
    JSON.stringify(moneyRows.value),
    JSON.stringify(lootRows.value),
    presetAmount.value,
    reserveSquadronKey.value,
  ],
  () => {
    persistSettlementDraft()
  }
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
    amount: normalizeWholeNumberField(row?.amount),
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
    quantity: normalizeWholeNumberField(row?.quantity),
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
      amount: row.amount === '' ? null : wholeNumber(row.amount),
      notes: row.notes || null,
    })),
    loot_rows: currentLootRows.map(row => ({
      row_key: row.row_key,
      source_type: row.source_type,
      uex_reference_id: row.uex_reference_id === '' ? null : row.uex_reference_id,
      recipient_type: row.recipient_type,
      recipient_user_id: row.recipient_user_id,
      recipient_squadron_id: row.recipient_squadron_id,
      quantity: row.quantity === '' ? null : wholeNumber(row.quantity),
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
    maximumFractionDigits: 0,
  }).format(wholeNumber(amount || 0) ?? 0)} aUEC`
}

function formatWholeNumber(value) {
  return new Intl.NumberFormat('en-US', {
    maximumFractionDigits: 0,
  }).format(wholeNumber(value || 0) ?? 0)
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
  return parsePositiveWholeNumber(presetAmount.value)
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

function normalizeSettlementInputs() {
  presetAmount.value = normalizeWholeNumberField(presetAmount.value)
  moneyRows.value = moneyRows.value.map(row => ({
    ...row,
    amount: normalizeWholeNumberField(row.amount),
  }))
  lootRows.value = lootRows.value.map(row => ({
    ...row,
    quantity: normalizeWholeNumberField(row.quantity),
  }))
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

  const splitAmounts = buildEvenSplitAmounts(amount, recipients.length)

  if (!splitAmounts.length) {
    notifyError('The helper amount is too small to split after Horizon’s 10 percent reserve.')
    return
  }

  const newRows = recipients.map((recipient, index) => {
    return createRecipientMoneyRow(
      recipient.key,
      String(splitAmounts[index] ?? 0),
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
      amount ? String(amount) : '',
      'Squadron reserve',
    ),
  ]
}

function reserveTenPercentForHorizon() {
  const baseAmount = calculateReserveBaseAmount(presetAmount.value, moneyRows.value)

  if (!baseAmount) {
    notifyError('Add payout amounts first, or enter an amount in the preset field before setting aside Horizon’s 10 percent share.')
    return
  }

  const horizonAmount = String(calculateHorizonReserve(baseAmount))
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

  normalizeSettlementInputs()
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

  normalizeSettlementInputs()
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
      ? 'hz-surface-welcome rounded-[1.25rem] border border-white/[0.055] p-4'
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

        <HorizonBadge variant="muted">
          {{ moneyRows.length }} payout rows
        </HorizonBadge>

        <HorizonBadge variant="muted">
          {{ lootRows.length }} loot rows
        </HorizonBadge>

        <HorizonBadge
          :variant="isFinalized ? 'success' : 'muted'"
        >
          {{ isFinalized ? 'Finalized' : 'Draft' }}
        </HorizonBadge>
      </div>
    </div>

    <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
      <HorizonFormBlock label="Assigned Money" label-class="text-[10px] tracking-[0.16em]" panel-class="rounded-[1rem] px-4 py-3">
        <div class="text-lg font-black text-horizon-white">{{ formatMoney(assignedMoneyTotal) }}</div>
      </HorizonFormBlock>

      <HorizonFormBlock label="Recipients" label-class="text-[10px] tracking-[0.16em]" panel-class="rounded-[1rem] px-4 py-3">
        <div class="text-lg font-black text-horizon-white">{{ assignedRecipientCount }}</div>
      </HorizonFormBlock>

      <HorizonFormBlock label="Payout Rows" label-class="text-[10px] tracking-[0.16em]" panel-class="rounded-[1rem] px-4 py-3">
        <div class="text-lg font-black text-horizon-white">{{ moneyRows.length }}</div>
      </HorizonFormBlock>

      <HorizonFormBlock label="Loot Rows" label-class="text-[10px] tracking-[0.16em]" panel-class="rounded-[1rem] px-4 py-3">
        <div class="text-lg font-black text-horizon-white">{{ lootRows.length }}</div>
      </HorizonFormBlock>

      <HorizonFormBlock label="Needs Attention" label-class="text-[10px] tracking-[0.16em]" panel-class="rounded-[1rem] px-4 py-3">
        <div class="text-lg font-black text-horizon-white">{{ unassignedRowCount }}</div>
      </HorizonFormBlock>
    </div>

    <div
      v-if="attendanceLocked"
      class="mt-4 rounded-[1rem] border border-emerald-300/20 bg-emerald-300/8 px-4 py-3 text-sm text-text-secondary"
    >
      Final attendance is locked while this settlement is finalized.
      <span v-if="finalizedAtLabel"> Finalized {{ finalizedAtLabel }}.</span>
    </div>

    <div class="hz-surface-welcome mt-4 rounded-[1.25rem] border border-white/[0.055] p-4">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Settlement Activity</div>
          <div class="mt-1 text-sm text-text-secondary">
            Latest settlement timestamps for this operation without leaving the AAR flow.
          </div>
        </div>
        <HorizonBadge
          variant="muted"
          size="xs"
          uppercase
        >
          {{ isFinalized ? 'Live in ledgers' : 'Still in draft' }}
        </HorizonBadge>
      </div>

      <div class="mt-4 grid gap-3 md:grid-cols-3">
        <div
          v-for="row in settlementActivityRows"
          :key="row.label"
          class="hz-surface-welcome rounded-[1rem] border border-white/[0.055] px-4 py-3"
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
      <div class="hz-surface-welcome rounded-[1.25rem] border border-white/[0.055] p-4">
        <div class="flex items-center justify-between gap-3">
          <div>
            <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Attendance Quick Add</div>
            <div class="mt-1 text-sm text-text-secondary">Drop in payout or loot rows for the final attendee list without re-picking the same recipients.</div>
          </div>
          <HorizonBadge variant="muted" size="xs" uppercase>
            {{ participantTargets.length }} attendees
          </HorizonBadge>
        </div>

        <div v-if="participantTargets.length" class="mt-4 grid gap-3 lg:grid-cols-2">
          <div
            v-for="participant in participantTargets"
            :key="participant.key"
            class="hz-surface-welcome rounded-[1rem] border border-white/[0.055] px-3 py-3"
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
          class="hz-surface-welcome hz-divider-subtle mt-4 rounded-[1rem] border border-dashed border-white/15 px-4 py-4 text-sm text-text-secondary"
        >
          Final attendance will show up here once the AAR roster is filled in.
        </div>
      </div>

      <div class="hz-surface-welcome rounded-[1.25rem] border border-white/[0.055] p-4">
        <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Settlement Helpers</div>
        <div class="mt-1 text-sm text-text-secondary">
          Treat the helper amount as the total pot. Horizon’s 10 percent comes off the top before attendee splits.
        </div>

        <div class="mt-4 space-y-3">
          <HorizonInput
            v-model="presetAmount"
            type="number"
            step="1"
            min="0"
            placeholder="Preset amount"
            @blur="presetAmount = normalizeWholeNumberField(presetAmount)"
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

          <div class="hz-surface-welcome rounded-[1rem] border border-white/[0.055] px-4 py-3">
            <div class="text-[10px] font-bold uppercase tracking-[0.16em] text-text-muted">Helper Math</div>
            <div class="mt-3 grid gap-3 sm:grid-cols-3">
              <HorizonFormBlock label="Attendance Split" label-class="text-[10px] tracking-[0.14em]" panel-class="rounded-[0.9rem] bg-white/[0.02] px-3 py-3">
                <div class="text-sm font-semibold text-horizon-white">
                  {{ attendeeSplitPreview !== null ? formatMoney(attendeeSplitPreview) : 'Add an amount' }}
                </div>
                <div class="mt-1 text-xs text-text-secondary">
                  {{ participantTargets.length ? `${participantTargets.length} attendees in the current AAR roster after Horizon's reserve.` : 'No final attendees available yet.' }}
                </div>
              </HorizonFormBlock>

              <HorizonFormBlock label="Horizon Reserve" label-class="text-[10px] tracking-[0.14em]" panel-class="rounded-[0.9rem] bg-white/[0.02] px-3 py-3">
                <div class="text-sm font-semibold text-horizon-white">
                  {{ horizonReservePreview !== null ? formatMoney(horizonReservePreview) : 'Add an amount' }}
                </div>
                <div class="mt-1 text-xs text-text-secondary">
                  10 percent of the helper amount.
                </div>
              </HorizonFormBlock>

              <HorizonFormBlock label="Preset Coverage" label-class="text-[10px] tracking-[0.14em]" panel-class="rounded-[0.9rem] bg-white/[0.02] px-3 py-3">
                <div class="text-sm font-semibold text-horizon-white">
                  {{ presetCoverageDelta !== null ? formatMoney(presetCoverageDelta) : 'Add an amount' }}
                </div>
                <div class="mt-1 text-xs text-text-secondary">
                  Remaining helper amount after current payout rows.
                </div>
              </HorizonFormBlock>
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
            class="hz-surface-welcome rounded-[1rem] border border-white/[0.055] p-4"
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
                  step="1"
                  min="0"
                  placeholder="Amount"
                  @blur="row.amount = normalizeWholeNumberField(row.amount)"
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
                  {{ formatMoney(row.amount ?? '0') }}
                </div>
              </div>
            </template>
          </div>
        </div>

        <div
          v-else
          class="hz-surface-welcome hz-divider-subtle mt-4 rounded-[1rem] border border-dashed border-white/15 px-4 py-5 text-sm text-text-secondary"
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
            class="hz-surface-welcome rounded-[1rem] border border-white/[0.055] p-4"
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
                  step="1"
                  min="0"
                  placeholder="Quantity"
                  @blur="row.quantity = normalizeWholeNumberField(row.quantity)"
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
                    {{ formatWholeNumber(row.quantity ?? '0') }} {{ row.unit_label || 'units' }} to {{ row.recipient_label ?? 'Unassigned destination' }}
                  </div>

                  <div v-if="row.notes" class="mt-2 whitespace-pre-line text-sm text-text-secondary">
                    {{ row.notes }}
                  </div>
                </div>

                <HorizonBadge variant="muted">
                  {{ row.source_type ?? 'loot' }}
                </HorizonBadge>
              </div>
            </template>
          </div>
        </div>

        <div
          v-else
          class="hz-surface-welcome hz-divider-subtle mt-4 rounded-[1rem] border border-dashed border-white/15 px-4 py-5 text-sm text-text-secondary"
        >
          No loot rows yet.
        </div>
      </div>
    </div>

    <div
      v-if="canManage"
      class="hz-surface-welcome mt-4 flex flex-wrap items-center justify-between gap-3 rounded-[1rem] border border-white/[0.055] px-4 py-4"
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
