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

const props = defineProps({
  operation: {
    type: Object,
    required: true,
  },
  fundsPrep: {
    type: Object,
    default: () => ({}),
  },
  runtimeParticipants: {
    type: Array,
    default: () => [],
  },
})

const moneyRows = ref([])
const saving = ref(false)
const presetAmount = ref('')
const reserveSquadronKey = ref('')
const prepOpen = ref(false)

const prep = computed(() => props.fundsPrep ?? {})
const editable = computed(() => ['draft', 'published', 'in_progress'].includes(props.operation?.status ?? ''))
const recipientOptions = computed(() => {
  return (prep.value?.eligible_recipients ?? []).map(option => ({
    ...option,
    value: option?.value ?? option?.key,
    label: option?.label ?? 'Unknown recipient',
  }))
})
const recipientOptionMap = computed(() => {
  return new Map(recipientOptions.value.map(option => [option.value, option]))
})
const squadronTargets = computed(() => {
  return recipientOptions.value.filter(option => option?.recipient_type === 'squadron')
})
const participantTargets = computed(() => {
  const activeParticipantKeys = new Set(
    (props.runtimeParticipants ?? [])
      .filter((participant) => participant?.runtime_status !== 'signed_off_before_start')
      .filter((participant) => participant?.runtime_status !== 'no_show')
      .map((participant) => {
        const userId = Number(participant?.user?.id)
        return Number.isFinite(userId) ? `member:${userId}` : null
      })
      .filter(Boolean),
  )

  return recipientOptions.value.filter((option) => activeParticipantKeys.has(option.value))
})
const prepActivity = computed(() => prep.value?.activity ?? {})
const prepUpdatedLabel = computed(() => formatDateLabel(prepActivity.value?.updated_at))
const assignedMoneyTotal = computed(() => {
  return moneyRows.value.reduce((total, row) => {
    const amount = wholeNumber(row?.amount ?? 0)
    return total + (Number.isFinite(amount) ? amount : 0)
  }, 0)
})
const presetAmountValue = computed(() => wholeNumber(presetAmount.value) ?? 0)
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
const unassignedRowCount = computed(() => {
  return moneyRows.value.filter(row => !selectedRecipientKey(row)).length
})

watch(
  () => JSON.stringify(prep.value?.money_rows ?? []),
  () => {
    moneyRows.value = (prep.value?.money_rows ?? []).map(cloneMoneyRow)
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
    row_key: `prep-money-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
    recipient_type: null,
    recipient_user_id: null,
    recipient_squadron_id: null,
    amount: '',
    notes: '',
    recipient_label: null,
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
  const option = recipientOptionMap.value.get(key)

  if (!option) {
    row.recipient_type = null
    row.recipient_user_id = null
    row.recipient_squadron_id = null
    row.recipient_label = null
    return
  }

  row.recipient_type = option.recipient_type ?? null
  row.recipient_user_id = option.recipient_user_id ?? null
  row.recipient_squadron_id = option.recipient_squadron_id ?? null
  row.recipient_label = option.label ?? null
}

function createRecipientMoneyRow(recipientKey, amount = '', notes = '') {
  const row = createMoneyRow()
  applyRecipient(row, recipientKey)
  row.amount = amount
  row.notes = notes
  return row
}

function addRecipientPayout(recipientKey) {
  moneyRows.value = [...moneyRows.value, createRecipientMoneyRow(recipientKey)]
}

function addMoneyRow() {
  moneyRows.value = [...moneyRows.value, createMoneyRow()]
}

function removeMoneyRow(rowKey) {
  moneyRows.value = moneyRows.value.filter(row => row.row_key !== rowKey)
}

function parsePresetAmount() {
  return parsePositiveWholeNumber(presetAmount.value)
}

function splitPresetAmountAcrossAttendance() {
  const amount = parsePresetAmount()
  const recipients = participantTargets.value

  if (!amount) {
    notifyError('Enter a positive amount before splitting it across the live roster.')
    return
  }

  if (!recipients.length) {
    notifyError('No live roster members are available for an even split yet.')
    return
  }

  const splitAmounts = buildEvenSplitAmounts(amount, recipients.length)

  if (!splitAmounts.length) {
    notifyError('The helper amount is too small to split after Horizon’s 10 percent reserve.')
    return
  }

  const newRows = recipients.map((recipient, index) => {
    return createRecipientMoneyRow(
      recipient.value,
      String(splitAmounts[index] ?? 0),
      'Run-tool prep split',
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
      'Run-tool squadron reserve',
    ),
  ]
}

function reserveTenPercentForHorizon() {
  const baseAmount = calculateReserveBaseAmount(presetAmount.value, moneyRows.value)

  if (!baseAmount) {
    notifyError('Add payout amounts first, or enter an amount before setting aside Horizon’s 10 percent share.')
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

function normalizeInputs() {
  presetAmount.value = normalizeWholeNumberField(presetAmount.value)
  moneyRows.value = moneyRows.value.map(row => ({
    ...row,
    amount: normalizeWholeNumberField(row.amount),
  }))
}

function serializeRows() {
  return {
    money_rows: moneyRows.value.map(row => ({
      row_key: row.row_key,
      recipient_type: row.recipient_type,
      recipient_user_id: row.recipient_user_id,
      recipient_squadron_id: row.recipient_squadron_id,
      amount: row.amount === '' ? null : wholeNumber(row.amount),
      notes: row.notes || null,
    })),
  }
}

function savePrep() {
  if (!props.operation?.id || saving.value || !editable.value) return

  normalizeInputs()
  saving.value = true

  router.put(route('operations.run.funds-prep.update', props.operation.id, Ziggy), serializeRows(), {
    preserveScroll: true,
    preserveState: true,
    onError: showFirstError,
    onFinish: () => {
      saving.value = false
    },
  })
}

function formatMoney(amount) {
  return `${new Intl.NumberFormat('en-US', {
    maximumFractionDigits: 0,
  }).format(wholeNumber(amount || 0) ?? 0)} aUEC`
}

function formatDateLabel(value) {
  if (!value) return null

  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return null

  return new Intl.DateTimeFormat('en-US', {
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  }).format(date)
}

function notifyError(message) {
  window.hzNotifyError?.({ message })
}

function showFirstError(errors) {
  const firstError = Object.values(errors ?? {})[0]
  const message = Array.isArray(firstError) ? firstError[0] : firstError

  notifyError(message ?? 'That funds prep update could not be saved.')
}

function togglePrepOpen() {
  prepOpen.value = !prepOpen.value
}
</script>

<template>
  <section
    v-if="prep?.is_available && operation?.status !== 'completed' && operation?.status !== 'canceled'"
    class="hz-surface-welcome rounded-[1.5rem] border border-white/[0.055] p-5"
  >
    <button
      type="button"
      class="flex w-full items-start justify-between gap-4 text-left"
      :aria-expanded="prepOpen"
      @click="togglePrepOpen"
    >
      <div>
        <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Funds Prep</div>
        <div class="mt-2 text-lg font-black text-horizon-white">Build the payout draft before final settlement</div>
        <p class="mt-3 max-w-4xl text-sm text-text-secondary">
          These money rows are a run-tool head start only. When the operation is completed, Horizon seeds the final settlement draft from this prep and flags any recipients that no longer match final attendance.
        </p>
      </div>

      <div class="flex shrink-0 flex-wrap items-center justify-end gap-2">
        <HorizonBadge variant="muted">
          {{ moneyRows.length }} prep rows
        </HorizonBadge>
        <HorizonBadge variant="muted">
          {{ formatMoney(assignedMoneyTotal) }}
        </HorizonBadge>
        <div class="hz-surface-welcome flex h-9 w-9 items-center justify-center rounded-full border border-white/[0.055] text-sm font-bold text-horizon-white transition-transform duration-200" :class="prepOpen ? 'rotate-180' : ''">
          ⌄
        </div>
      </div>
    </button>

    <div v-if="prepOpen" class="mt-4 space-y-4">
      <div class="grid gap-3 sm:grid-cols-3">
        <HorizonFormBlock label="Prep Saved" label-class="text-[10px] tracking-[0.16em]" panel-class="rounded-[1rem] px-4 py-3" :helper="prepActivity?.updated_by ?? 'No saved prep draft yet.'">
          <div class="text-sm font-semibold text-horizon-white">{{ prepUpdatedLabel ?? 'Not yet' }}</div>
        </HorizonFormBlock>

        <HorizonFormBlock label="Live Recipients" label-class="text-[10px] tracking-[0.16em]" panel-class="rounded-[1rem] px-4 py-3" helper="Current live-roster members available for quick splits.">
          <div class="text-sm font-semibold text-horizon-white">{{ participantTargets.length }}</div>
        </HorizonFormBlock>

        <HorizonFormBlock label="Needs Review" label-class="text-[10px] tracking-[0.16em]" panel-class="rounded-[1rem] px-4 py-3" helper="Rows without a destination yet.">
          <div class="text-sm font-semibold text-horizon-white">{{ unassignedRowCount }}</div>
        </HorizonFormBlock>
      </div>

      <div class="space-y-4">
      <div class="hz-surface-welcome rounded-[1.25rem] border border-white/[0.055] p-4">
        <div class="flex items-center justify-between gap-3">
          <div>
            <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Live Roster Quick Add</div>
            <div class="mt-1 text-sm text-text-secondary">Drop payout rows onto the people currently in the run flow.</div>
          </div>
          <HorizonBadge variant="muted" size="xs" uppercase>
            {{ participantTargets.length }} members
          </HorizonBadge>
        </div>

        <div v-if="participantTargets.length" class="mt-4 grid gap-3 lg:grid-cols-2">
          <div
            v-for="participant in participantTargets"
            :key="participant.value"
            class="hz-surface-welcome rounded-[1rem] border border-white/[0.055] px-3 py-3"
          >
            <div class="text-sm font-semibold text-horizon-white">{{ participant.label }}</div>
            <div class="mt-3 flex flex-wrap gap-2">
              <HorizonButton
                type="button"
                size="sm"
                variant="ghost"
                @click="addRecipientPayout(participant.value)"
              >
                Add Payout
              </HorizonButton>
            </div>
          </div>
        </div>

        <div
          v-else
          class="hz-surface-welcome hz-divider-subtle mt-4 rounded-[1rem] border border-dashed border-white/15 px-4 py-4 text-sm text-text-secondary"
        >
          Nobody on the live roster is available for quick-add payouts yet.
        </div>
      </div>

      <div class="hz-surface-welcome rounded-[1.25rem] border border-white/[0.055] p-4">
        <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Prep Helpers</div>
        <div class="mt-1 text-sm text-text-secondary">
          Treat the helper amount as the total pot. Horizon’s 10 percent comes off the top before live-roster splits.
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
            <HorizonButton type="button" size="sm" variant="ghost" @click="splitPresetAmountAcrossAttendance">
              Split To Live Roster
            </HorizonButton>
            <HorizonButton type="button" size="sm" variant="ghost" :disabled="!squadronTargets.length" @click="addSquadronReserveRow">
              Add Squadron Reserve
            </HorizonButton>
            <HorizonButton type="button" size="sm" variant="ghost" @click="reserveTenPercentForHorizon">
              Reserve 10% For Horizon
            </HorizonButton>
          </div>

          <div class="hz-surface-welcome rounded-[1rem] border border-white/[0.055] px-4 py-3">
            <div class="text-[10px] font-bold uppercase tracking-[0.16em] text-text-muted">Helper Math</div>
            <div class="mt-3 grid gap-3 sm:grid-cols-2">
              <HorizonFormBlock label="Live Split" label-class="text-[10px] tracking-[0.14em]" panel-class="rounded-[0.9rem] bg-white/[0.02] px-3 py-3">
                <div class="text-sm font-semibold text-horizon-white">
                  {{ attendeeSplitPreview !== null ? formatMoney(attendeeSplitPreview) : 'Add an amount' }}
                </div>
                <div class="mt-1 text-xs text-text-secondary">
                  {{ participantTargets.length ? `${participantTargets.length} active recipients right now after Horizon's reserve.` : 'No live roster members available yet.' }}
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
            </div>
          </div>
        </div>
      </div>
      </div>

      <div class="hz-surface-welcome rounded-[1.25rem] border border-white/[0.055] p-4">
        <div class="flex items-center justify-between gap-3">
          <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Prep Money Rows</div>

          <HorizonButton type="button" size="sm" variant="ghost" @click="addMoneyRow">
            Add Row
          </HorizonButton>
        </div>

        <div v-if="moneyRows.length" class="mt-4 space-y-3">
          <div
            v-for="row in moneyRows"
            :key="row.row_key"
            class="hz-surface-welcome rounded-[1rem] border border-white/[0.055] p-4"
          >
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
              placeholder="Optional prep note..."
            />

            <div class="mt-3 flex justify-end">
              <HorizonButton type="button" size="sm" variant="danger" @click="removeMoneyRow(row.row_key)">
                Remove
              </HorizonButton>
            </div>
          </div>
        </div>

        <div
          v-else
          class="hz-surface-welcome hz-divider-subtle mt-4 rounded-[1rem] border border-dashed border-white/15 px-4 py-5 text-sm text-text-secondary"
        >
          No funds prep rows yet.
        </div>
      </div>

      <div class="hz-surface-welcome flex flex-wrap items-center justify-between gap-3 rounded-[1rem] border border-white/[0.055] px-4 py-4">
        <div class="text-sm text-text-secondary">
          Save this early payout draft now, then finish the real settlement after the op closes.
        </div>

        <HorizonButton
          type="button"
          size="sm"
          variant="primary"
          :disabled="saving || !editable"
          @click="savePrep"
        >
          {{ saving ? 'Saving…' : 'Save Funds Prep' }}
        </HorizonButton>
      </div>
    </div>
  </section>
</template>
