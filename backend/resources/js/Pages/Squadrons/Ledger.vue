<script setup>
import { computed, ref, watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

import HorizonContainer from '@/Components/HorizonContainer.vue'
import HorizonButton from '@/Components/HorizonButton.vue'
import HorizonConfirmDialog from '@/Components/HorizonConfirmDialog.vue'
import HorizonDateTimePicker from '@/Components/HorizonDateTimePicker.vue'
import HorizonSelect from '@/Components/HorizonSelect.vue'
import { notifyErrorFromErrors } from '@/errors'

const props = defineProps({
  squadron: {
    type: Object,
    default: null,
  },
  ledger: {
    type: Object,
    required: true,
  },
  permissions: {
    type: Object,
    default: () => ({}),
  },
  context: {
    type: Object,
    default: () => ({
      mode: 'personal',
    }),
  },
  filters: {
    type: Object,
    default: () => ({
      wipe: 'current',
    }),
  },
})

const activeTab = ref('overview')
const wipeFilter = ref(props.filters?.wipe ?? props.ledger?.activeWipeFilter ?? 'current')

const currentWipe = computed(() => props.ledger?.currentWipe ?? null)
const selectedWipe = computed(() => props.ledger?.selectedWipe ?? null)
const wipeCycles = computed(() => props.ledger?.wipeCycles ?? [])
const references = computed(() => props.ledger?.references ?? {})
const cycleSummaries = computed(() => props.ledger?.overview?.cycleSummaries ?? [])
const cycleComparison = computed(() => props.ledger?.overview?.cycleComparison ?? null)
const isSquadronLedger = computed(() => props.context?.mode === 'squadron')
const isOrganizationLedger = computed(() => props.context?.mode === 'organization')
const canEditLedger = computed(() => (isSquadronLedger.value || isOrganizationLedger.value)
  ? props.permissions?.can_edit_ledger === true
  : true)
const isHistoryView = computed(() => wipeFilter.value === 'all' || Boolean(selectedWipe.value && !selectedWipe.value.is_current))
const canEditCurrentView = computed(() => canEditLedger.value && !isHistoryView.value)
const ledgerEyebrow = computed(() => {
  if (isSquadronLedger.value) return 'Squadron Ledger'
  if (isOrganizationLedger.value) return 'Organization Ledger'
  return 'Horizon Ledger'
})
const ledgerTitle = computed(() => {
  if (isSquadronLedger.value) return props.squadron?.name ?? 'Squadron Accounting'
  if (isOrganizationLedger.value) return 'Horizon Treasury'
  return 'Personal Accounting'
})
const ledgerDescription = computed(() => {
  if (isSquadronLedger.value) {
    return 'Track squadron-owned income, expenses, trade runs, ships, and inventory without mixing in personal member books.'
  }

  if (isOrganizationLedger.value) {
    return 'Track Horizon-wide treasury activity, shared trade runs, org inventory, and org fleet assets in one separated organization ledger.'
  }

  return 'Track your income, expenses, trade runs, ships, and inventory against the current Star Citizen cycle while keeping older cycles archived instead of losing the history.'
})
const settingsHeading = computed(() => {
  if (isSquadronLedger.value) return 'Squadron Defaults'
  if (isOrganizationLedger.value) return 'Organization Defaults'
  return 'Launch Defaults'
})
const tabs = [
  { key: 'overview', label: 'Overview' },
  { key: 'transactions', label: 'Transactions' },
  { key: 'transfers', label: 'Transfers' },
  { key: 'trades', label: 'Trades' },
  { key: 'inventory', label: 'Inventory' },
  { key: 'ships', label: 'Ships' },
  { key: 'reports', label: 'Reports' },
  { key: 'settings', label: 'Settings' },
]
const tabKeys = tabs.map(tab => tab.key)
const ledgerTabStorageKey = computed(() => {
  if (isSquadronLedger.value) {
    return `horizon.ledger.active-tab.squadron.${props.squadron?.id ?? 'current'}`
  }

  if (isOrganizationLedger.value) {
    return 'horizon.ledger.active-tab.organization'
  }

  return 'horizon.ledger.active-tab.personal'
})

const editingTransactionId = ref(null)
const editingTradeId = ref(null)
const editingInventoryItemId = ref(null)
const editingShipId = ref(null)
const suppressInventorySourceReset = ref(false)
const confirmDialog = ref(null)
const pendingConfirmation = ref(null)
const lastAppliedTradeBuyPrice = ref(null)
const lastAppliedTradeSellPrice = ref(null)
const lastAppliedInventoryPurchasePrice = ref(null)
const lastAppliedInventoryEstimatedValue = ref(null)
const lastAppliedInventoryCategory = ref(null)
const lastAppliedInventoryUnitLabel = ref(null)
const lastAppliedShipPurchasePrice = ref(null)

const LEDGER_DEFAULTS_KEY = 'horizon-ledger-defaults-v1'
const DEFAULT_TRADE_UNIT_TYPE = 'SCU'
const rememberedDefaults = ref(readRememberedLedgerDefaults())

const transactionForm = useForm({
  ledger_account_id: props.ledger?.account?.id ?? null,
  wipe_cycle_id: null,
  type: 'income',
  amount: '',
  currency: props.ledger?.account?.currency ?? 'aUEC',
  source_type: '',
  description: '',
  transaction_date: '',
  related_operation_id: '',
  related_uex_type: '',
  related_uex_id: '',
  notes: '',
})

const transferForm = useForm({
  wipe_cycle_id: null,
  destination_type: '',
  destination_squadron_id: '',
  amount: '',
  description: '',
  transaction_date: '',
  notes: '',
})

const inventoryTransferForm = useForm({
  inventory_item_id: '',
  destination_type: '',
  destination_squadron_id: '',
  quantity: '',
  notes: '',
})

const tradeForm = useForm({
  ledger_account_id: props.ledger?.account?.id ?? null,
  wipe_cycle_id: null,
  commodity_uex_id: '',
  quantity: '',
  unit_type: 'SCU',
  buy_price_per_unit: '',
  sell_price_per_unit: '',
  cargo_capacity_used: '',
  trade_date: '',
  notes: '',
})

const inventoryForm = useForm({
  wipe_cycle_id: null,
  source_type: 'item',
  uex_reference_type: 'item',
  uex_reference_id: '',
  custom_name: '',
  category: '',
  quantity: 1,
  unit_label: '',
  location_name: '',
  purchase_price: '',
  estimated_value: '',
  currency: props.ledger?.account?.currency ?? 'aUEC',
  status: 'owned',
  acquired_at: '',
  notes: '',
})

const shipForm = useForm({
  wipe_cycle_id: null,
  vehicle_uex_id: '',
  custom_name: '',
  serial_or_label: '',
  purchase_price: '',
  currency: props.ledger?.account?.currency ?? 'aUEC',
  acquisition_source: '',
  current_location: '',
  status: 'owned',
  acquired_at: '',
  notes: '',
})

const transactionRelatedOptions = computed(() => {
  switch (transactionForm.related_uex_type) {
    case 'commodity':
      return references.value.commodities ?? []
    case 'vehicle':
    case 'ship':
      return references.value.ships ?? []
    case 'item':
    case 'component':
      return references.value.items ?? []
    default:
      return []
  }
})

const inventoryReferenceOptions = computed(() => {
  switch (inventoryForm.source_type) {
    case 'commodity':
      return references.value.commodities ?? []
    case 'component':
    case 'item':
      return references.value.items ?? []
    default:
      return []
  }
})

const cycleSelectOptions = computed(() => wipeCycles.value.map(wipe => ({
  value: String(wipe.id),
  label: wipe.is_current ? `${wipe.name} (Current)` : wipe.name,
})))
const formCycleOptions = computed(() => {
  const current = currentWipe.value ?? wipeCycles.value.find(wipe => wipe.is_current)

  if (! current) {
    return cycleSelectOptions.value.filter(option => option.label.endsWith('(Current)'))
  }

  return [{
    value: String(current.id),
    label: `${current.name} (Current)`,
  }]
})

const transactionRelatedSelectOptions = computed(() => transactionRelatedOptions.value.map(option => ({
  value: String(option.uex_id),
  label: option.name,
})))

const commoditySelectOptions = computed(() => (references.value.commodities ?? []).map(commodity => ({
  value: String(commodity.uex_id),
  label: commodity.name,
})))

const transferTargets = computed(() => props.ledger?.transferTargets ?? [])
const transferInventoryItems = computed(() => props.ledger?.transferInventoryOptions ?? [])
const transferTargetOptions = computed(() => transferTargets.value.map(target => ({
  value: target.key,
  label: target.label,
})))
const transferInventoryItemOptions = computed(() => transferInventoryItems.value.map(item => ({
  value: String(item.id),
  label: `${item.label} (${formatLedgerQuantity(item.quantity)} ${item.unit_label})`,
})))

const inventoryReferenceSelectOptions = computed(() => inventoryReferenceOptions.value.map(option => ({
  value: String(option.uex_id),
  label: option.name,
})))

const shipSelectOptions = computed(() => (references.value.ships ?? []).map(ship => ({
  value: String(ship.uex_id),
  label: ship.name,
})))

const transactionSourceSuggestions = computed(() => rememberedDefaults.value.transactionSourceHistory ?? [])
const shipAcquisitionSourceSuggestions = computed(() => rememberedDefaults.value.shipAcquisitionSourceHistory ?? [])

const tradePricingByCommodityId = computed(() => mapSuggestionsByUexId(references.value.tradePricing ?? []))
const inventoryCommodityValuationsById = computed(() => mapSuggestionsByUexId(references.value.inventoryValuations?.commodities ?? []))
const inventoryItemValuationsById = computed(() => mapSuggestionsByUexId(references.value.inventoryValuations?.items ?? []))
const itemOptionsById = computed(() => mapSuggestionsByUexId(references.value.items ?? []))
const shipPricingByVehicleId = computed(() => mapSuggestionsByUexId(references.value.shipPricing ?? []))
const selectedTransferTargetKey = ref('')
const selectedTransferTarget = computed(() => transferTargets.value.find(target => target.key === selectedTransferTargetKey.value) ?? null)
const canTransferFunds = computed(() => canEditCurrentView.value && transferTargets.value.length > 0)
const selectedInventoryTransferTargetKey = ref('')
const selectedInventoryTransferTarget = computed(() => transferTargets.value.find(target => target.key === selectedInventoryTransferTargetKey.value) ?? null)
const selectedInventoryTransferItem = computed(() => transferInventoryItems.value.find(item => String(item.id) === String(inventoryTransferForm.inventory_item_id ?? '')) ?? null)
const canTransferInventory = computed(() => canEditCurrentView.value && transferTargets.value.length > 0 && transferInventoryItems.value.length > 0)
const openTransferPanels = ref({
  funds: true,
  inventory: false,
})

const selectedTradePricing = computed(() => tradePricingByCommodityId.value[String(tradeForm.commodity_uex_id ?? '')] ?? null)
const selectedInventorySuggestion = computed(() => {
  const referenceId = String(inventoryForm.uex_reference_id ?? '')

  if (!referenceId) {
    return null
  }

  if (inventoryForm.source_type === 'commodity') {
    return inventoryCommodityValuationsById.value[referenceId] ?? null
  }

  if (inventoryForm.source_type === 'item' || inventoryForm.source_type === 'component') {
    return inventoryItemValuationsById.value[referenceId] ?? null
  }

  return null
})
const selectedShipPricing = computed(() => shipPricingByVehicleId.value[String(shipForm.vehicle_uex_id ?? '')] ?? null)

function isTransferPanelOpen(panel) {
  return openTransferPanels.value[panel] === true
}

function toggleTransferPanel(panel) {
  openTransferPanels.value[panel] = !openTransferPanels.value[panel]
}

function readOnlySectionContent(title, noun) {
  if (isHistoryView.value) {
    return {
      heading: `${title} History`,
      body: `This cycle view is archived, so ${noun} are locked. Switch back to the current cycle to add, edit, move, or delete records.`,
    }
  }

  if (isOrganizationLedger.value) {
    return {
      heading: `Organization ${title} History`,
      body: `These shared ${noun} stay visible here, but only org ledger managers can change them.`,
    }
  }

  if (isSquadronLedger.value) {
    return {
      heading: `Squadron ${title} History`,
      body: `These shared ${noun} stay visible here, but only squadron ledger managers can change them.`,
    }
  }

  return {
    heading: `${title} History`,
    body: `This ledger view is read only right now, so ${noun} cannot be changed from here.`,
  }
}
const overviewScopeLabel = computed(() => {
  if (wipeFilter.value === 'all') {
    return 'All Cycles Snapshot'
  }

  if (selectedWipe.value && !selectedWipe.value.is_current) {
    return `${selectedWipe.value.name} Snapshot`
  }

  return 'This Cycle Snapshot'
})
const tradeRouteAssist = computed(() => {
  if (!selectedTradePricing.value) {
    return null
  }

  const buyPrice = normalizeNumericValue(selectedTradePricing.value.buy_price_per_unit)
  const sellPrice = normalizeNumericValue(selectedTradePricing.value.sell_price_per_unit)
  const hasQuantity = !isBlankValue(tradeForm.quantity)
  const quantity = effectiveQuantity(tradeForm.quantity)
  const marginPerUnit = buyPrice !== null && sellPrice !== null
    ? Number((sellPrice - buyPrice).toFixed(2))
    : null
  const estimatedProfit = marginPerUnit !== null && hasQuantity
    ? Number((marginPerUnit * quantity).toFixed(2))
    : null

  return {
    buyPrice,
    buyTerminalName: selectedTradePricing.value.buy_terminal_name,
    sellPrice,
    sellTerminalName: selectedTradePricing.value.sell_terminal_name,
    marginPerUnit,
    estimatedProfit,
    quantity,
  }
})

const wipeBanner = computed(() => {
  if (!selectedWipe.value || selectedWipe.value.is_current) {
    return null
  }

  if (isSquadronLedger.value) {
    return `Viewing archived squadron ledger data from ${selectedWipe.value.name}.`
  }

  if (isOrganizationLedger.value) {
    return `Viewing archived organization ledger data from ${selectedWipe.value.name}.`
  }

  return `Viewing archived Ledger data from ${selectedWipe.value.name}.`
})

const transactionModeLabel = computed(() => (editingTransactionId.value ? 'Edit Transaction' : 'Add Transaction'))
const tradeModeLabel = computed(() => (editingTradeId.value ? 'Edit Trade' : 'Log Trade'))
const inventoryModeLabel = computed(() => (editingInventoryItemId.value ? 'Edit Inventory' : 'Add Inventory'))
const shipModeLabel = computed(() => (editingShipId.value ? 'Edit Ship Asset' : 'Add Ship Asset'))

function ledgerIndexHref(params = {}) {
  if (isOrganizationLedger.value) {
    return route('organization.ledger', params)
  }

  if (isSquadronLedger.value) {
    if (props.squadron?.slug) {
      return route('squadrons.ledger', { squadron: props.squadron.slug, ...params })
    }

    return route('squadrons.ledgerById', { squadron: props.squadron?.id, ...params })
  }

  return route('ledger.index', params)
}

function transactionStoreHref() {
  if (isOrganizationLedger.value) {
    return route('organization.ledger.transactions.store')
  }

  if (isSquadronLedger.value) {
    return route('squadrons.ledger.transactions.store', { squadron: props.squadron?.id })
  }

  return route('ledger.transactions.store')
}

function transactionUpdateHref(id) {
  if (isOrganizationLedger.value) {
    return route('organization.ledger.transactions.update', id)
  }

  if (isSquadronLedger.value) {
    return route('squadrons.ledger.transactions.update', { squadron: props.squadron?.id, transaction: id })
  }

  return route('ledger.transactions.update', id)
}

function transactionDestroyHref(id) {
  if (isOrganizationLedger.value) {
    return route('organization.ledger.transactions.destroy', id)
  }

  if (isSquadronLedger.value) {
    return route('squadrons.ledger.transactions.destroy', { squadron: props.squadron?.id, transaction: id })
  }

  return route('ledger.transactions.destroy', id)
}

function transactionTransferHref() {
  if (isOrganizationLedger.value) {
    return route('organization.ledger.transactions.transfer')
  }

  if (isSquadronLedger.value) {
    return route('squadrons.ledger.transactions.transfer', { squadron: props.squadron?.id })
  }

  return route('ledger.transactions.transfer')
}

function inventoryTransferHref() {
  if (isOrganizationLedger.value) {
    return route('organization.ledger.inventory.transfer')
  }

  if (isSquadronLedger.value) {
    return route('squadrons.ledger.inventory.transfer', { squadron: props.squadron?.id })
  }

  return route('ledger.inventory.transfer')
}

function tradeStoreHref() {
  if (isOrganizationLedger.value) {
    return route('organization.ledger.trades.store')
  }

  if (isSquadronLedger.value) {
    return route('squadrons.ledger.trades.store', { squadron: props.squadron?.id })
  }

  return route('ledger.trades.store')
}

function tradeUpdateHref(id) {
  if (isOrganizationLedger.value) {
    return route('organization.ledger.trades.update', id)
  }

  if (isSquadronLedger.value) {
    return route('squadrons.ledger.trades.update', { squadron: props.squadron?.id, trade: id })
  }

  return route('ledger.trades.update', id)
}

function tradeDestroyHref(id) {
  if (isOrganizationLedger.value) {
    return route('organization.ledger.trades.destroy', id)
  }

  if (isSquadronLedger.value) {
    return route('squadrons.ledger.trades.destroy', { squadron: props.squadron?.id, trade: id })
  }

  return route('ledger.trades.destroy', id)
}

function inventoryStoreHref() {
  if (isOrganizationLedger.value) {
    return route('organization.ledger.inventory.store')
  }

  if (isSquadronLedger.value) {
    return route('squadrons.ledger.inventory.store', { squadron: props.squadron?.id })
  }

  return route('ledger.inventory.store')
}

function inventoryUpdateHref(id) {
  if (isOrganizationLedger.value) {
    return route('organization.ledger.inventory.update', id)
  }

  if (isSquadronLedger.value) {
    return route('squadrons.ledger.inventory.update', { squadron: props.squadron?.id, inventoryItem: id })
  }

  return route('ledger.inventory.update', id)
}

function inventoryDestroyHref(id) {
  if (isOrganizationLedger.value) {
    return route('organization.ledger.inventory.destroy', id)
  }

  if (isSquadronLedger.value) {
    return route('squadrons.ledger.inventory.destroy', { squadron: props.squadron?.id, inventoryItem: id })
  }

  return route('ledger.inventory.destroy', id)
}

function shipStoreHref() {
  if (isOrganizationLedger.value) {
    return route('organization.ledger.ships.store')
  }

  if (isSquadronLedger.value) {
    return route('squadrons.ledger.ships.store', { squadron: props.squadron?.id })
  }

  return route('ledger.ships.store')
}

function shipUpdateHref(id) {
  if (isOrganizationLedger.value) {
    return route('organization.ledger.ships.update', id)
  }

  if (isSquadronLedger.value) {
    return route('squadrons.ledger.ships.update', { squadron: props.squadron?.id, ship: id })
  }

  return route('ledger.ships.update', id)
}

function shipDestroyHref(id) {
  if (isOrganizationLedger.value) {
    return route('organization.ledger.ships.destroy', id)
  }

  if (isSquadronLedger.value) {
    return route('squadrons.ledger.ships.destroy', { squadron: props.squadron?.id, ship: id })
  }

  return route('ledger.ships.destroy', id)
}

watch(
  () => tradeForm.commodity_uex_id,
  () => {
    applyTradeSuggestions()
  }
)

watch(
  ledgerTabStorageKey,
  () => {
    if (typeof window === 'undefined') {
      return
    }

    try {
      const storedTab = window.localStorage.getItem(ledgerTabStorageKey.value)

      if (storedTab && tabKeys.includes(storedTab)) {
        activeTab.value = storedTab
        return
      }
    } catch {
      // Ignore local storage issues and keep the default overview tab.
    }

    activeTab.value = 'overview'
  },
  { immediate: true }
)

watch(activeTab, value => {
  if (typeof window === 'undefined' || !tabKeys.includes(value)) {
    return
  }

  window.localStorage.setItem(ledgerTabStorageKey.value, value)
})

watch(wipeFilter, value => {
  router.visit(ledgerIndexHref(value && value !== 'current' ? { wipe: value } : {}), {
    data: value && value !== 'current' ? { wipe: value } : {},
    preserveState: true,
    preserveScroll: true,
    replace: true,
    only: ['ledger', 'filters', 'flash'],
  })
})

watch(
  () => inventoryForm.source_type,
  value => {
    if (suppressInventorySourceReset.value) {
      return
    }

    inventoryForm.uex_reference_type = value === 'custom' ? '' : value
    inventoryForm.uex_reference_id = ''
  }
)

watch(
  () => selectedTransferTargetKey.value,
  () => {
    if (!selectedTransferTarget.value) {
      transferForm.destination_type = ''
      transferForm.destination_squadron_id = ''
      return
    }

    transferForm.destination_type = selectedTransferTarget.value.type ?? ''
    transferForm.destination_squadron_id = selectedTransferTarget.value.squadron_id ?? ''
  }
)

watch(
  () => selectedInventoryTransferTargetKey.value,
  () => {
    if (!selectedInventoryTransferTarget.value) {
      inventoryTransferForm.destination_type = ''
      inventoryTransferForm.destination_squadron_id = ''
      return
    }

    inventoryTransferForm.destination_type = selectedInventoryTransferTarget.value.type ?? ''
    inventoryTransferForm.destination_squadron_id = selectedInventoryTransferTarget.value.squadron_id ?? ''
  }
)

watch(
  [canTransferFunds, canTransferInventory],
  ([fundsAvailable, inventoryAvailable]) => {
    if (!fundsAvailable) {
      openTransferPanels.value.funds = false
    }

    if (!inventoryAvailable) {
      openTransferPanels.value.inventory = false
    }

    if (!fundsAvailable && inventoryAvailable && !isTransferPanelOpen('inventory')) {
      openTransferPanels.value.inventory = true
    }
  },
  { immediate: true }
)

watch(
  () => inventoryTransferForm.inventory_item_id,
  () => {
    if (!selectedInventoryTransferItem.value) {
      inventoryTransferForm.quantity = ''
      return
    }

    inventoryTransferForm.quantity = selectedInventoryTransferItem.value.quantity
  }
)

watch(
  [
    () => inventoryForm.source_type,
    () => inventoryForm.uex_reference_id,
    () => inventoryForm.quantity,
  ],
  () => {
    applyInventorySuggestions()
  }
)

watch(
  () => shipForm.vehicle_uex_id,
  () => {
    applyShipSuggestions()
  }
)

resetTransactionForm()
resetTransferForm()
resetInventoryTransferForm()
resetTradeForm()
resetInventoryForm()
resetShipForm()

function defaultFormWipeId() {
  return preferredCycleId()
}

function withSuppressedInventorySourceReset(callback) {
  suppressInventorySourceReset.value = true
  callback()
  queueMicrotask(() => {
    suppressInventorySourceReset.value = false
  })
}

function showLedgerFormError(errors, fallbackMessage, title) {
  notifyErrorFromErrors(errors, fallbackMessage, title)
}

function readRememberedLedgerDefaults() {
  if (typeof window === 'undefined') {
    return {}
  }

  try {
    const stored = window.localStorage.getItem(LEDGER_DEFAULTS_KEY)
    return stored ? JSON.parse(stored) : {}
  } catch {
    return {}
  }
}

function persistRememberedLedgerDefaults(nextDefaults) {
  rememberedDefaults.value = {
    ...rememberedDefaults.value,
    ...nextDefaults,
  }

  if (typeof window === 'undefined') {
    return
  }

  window.localStorage.setItem(LEDGER_DEFAULTS_KEY, JSON.stringify(rememberedDefaults.value))
}

function rememberHistoryEntry(items = [], nextValue) {
  const trimmedValue = String(nextValue ?? '').trim()

  if (!trimmedValue) {
    return items
  }

  return [trimmedValue, ...items.filter(item => item !== trimmedValue)].slice(0, 6)
}

function preferredCycleId() {
  const preferredId = rememberedDefaults.value.preferredCycleId

  if (selectedWipe.value?.is_current && wipeFilter.value !== 'all') {
    return String(selectedWipe.value.id)
  }

  if (preferredId && wipeCycles.value.some(wipe => String(wipe.id) === String(preferredId) && wipe.is_current)) {
    return String(preferredId)
  }

  return currentWipe.value?.id ? String(currentWipe.value.id) : null
}

function rememberCommonLedgerDefaults(extraDefaults = {}) {
  persistRememberedLedgerDefaults({
    preferredCycleId: extraDefaults.preferredCycleId ?? preferredCycleId(),
    ...extraDefaults,
  })
}

function mapSuggestionsByUexId(entries = []) {
  return Object.fromEntries(entries.map(entry => [String(entry.uex_id), entry]))
}

function isBlankValue(value) {
  return value === null || value === undefined || String(value).trim() === ''
}

function normalizeNumericValue(value, precision = 2) {
  if (isBlankValue(value)) {
    return null
  }

  const numericValue = Number(value)

  if (Number.isNaN(numericValue)) {
    return null
  }

  return Number(numericValue.toFixed(precision))
}

function valuesMatch(currentValue, suggestedValue, precision = 2) {
  const normalizedCurrent = normalizeNumericValue(currentValue, precision)
  const normalizedSuggested = normalizeNumericValue(suggestedValue, precision)

  return normalizedCurrent !== null
    && normalizedSuggested !== null
    && normalizedCurrent === normalizedSuggested
}

function canReplaceSuggestedNumericValue(currentValue, lastAppliedValue, precision = 2) {
  return isBlankValue(currentValue) || valuesMatch(currentValue, lastAppliedValue, precision)
}

function canReplaceSuggestedTextValue(currentValue, lastAppliedValue) {
  return isBlankValue(currentValue) || String(currentValue) === String(lastAppliedValue ?? '')
}

function formatInputNumber(value, precision = 2) {
  const normalizedValue = normalizeNumericValue(value, precision)

  if (normalizedValue === null) {
    return ''
  }

  return normalizedValue
    .toFixed(precision)
    .replace(/\.0+$/, '')
    .replace(/(\.\d*?)0+$/, '$1')
}

function applySuggestedNumericField(form, field, nextValue, tracker, precision = 2) {
  if (nextValue === null || nextValue === undefined) {
    tracker.value = null
    return
  }

  if (!canReplaceSuggestedNumericValue(form[field], tracker.value, precision)) {
    return
  }

  const formattedValue = formatInputNumber(nextValue, precision)

  form[field] = formattedValue
  tracker.value = formattedValue
}

function applySuggestedTextField(form, field, nextValue, tracker) {
  if (!nextValue) {
    tracker.value = null
    return
  }

  if (!canReplaceSuggestedTextValue(form[field], tracker.value)) {
    return
  }

  form[field] = nextValue
  tracker.value = nextValue
}

function effectiveQuantity(value) {
  const normalizedQuantity = normalizeNumericValue(value, 4)

  if (normalizedQuantity === null || normalizedQuantity <= 0) {
    return 1
  }

  return normalizedQuantity
}

function resetTradeSuggestionTracking() {
  lastAppliedTradeBuyPrice.value = null
  lastAppliedTradeSellPrice.value = null
}

function resetInventorySuggestionTracking() {
  lastAppliedInventoryPurchasePrice.value = null
  lastAppliedInventoryEstimatedValue.value = null
  lastAppliedInventoryCategory.value = null
  lastAppliedInventoryUnitLabel.value = null
}

function resetShipSuggestionTracking() {
  lastAppliedShipPurchasePrice.value = null
}

function applyTradeSuggestions() {
  const suggestion = selectedTradePricing.value

  if (!suggestion) {
    resetTradeSuggestionTracking()
    return
  }

  applySuggestedNumericField(tradeForm, 'buy_price_per_unit', suggestion.buy_price_per_unit, lastAppliedTradeBuyPrice)
  applySuggestedNumericField(tradeForm, 'sell_price_per_unit', suggestion.sell_price_per_unit, lastAppliedTradeSellPrice)
}

function applyInventorySuggestions() {
  if (inventoryForm.source_type === 'custom' || !inventoryForm.uex_reference_id) {
    resetInventorySuggestionTracking()
    return
  }

  const suggestion = selectedInventorySuggestion.value
  const itemOption = itemOptionsById.value[String(inventoryForm.uex_reference_id ?? '')] ?? null
  const quantity = effectiveQuantity(inventoryForm.quantity)
  const categorySuggestion = suggestion?.category
    ?? (inventoryForm.source_type === 'commodity'
      ? 'Commodity'
      : (itemOption?.type || (inventoryForm.source_type === 'component' ? 'Component' : 'Item')))
  const unitLabelSuggestion = suggestion?.unit_label ?? (inventoryForm.source_type === 'commodity' ? 'SCU' : 'units')

  applySuggestedTextField(inventoryForm, 'category', categorySuggestion, lastAppliedInventoryCategory)
  applySuggestedTextField(inventoryForm, 'unit_label', unitLabelSuggestion, lastAppliedInventoryUnitLabel)

  if (!suggestion) {
    return
  }

  if (suggestion.unit_purchase_price !== null && suggestion.unit_purchase_price !== undefined) {
    applySuggestedNumericField(
      inventoryForm,
      'purchase_price',
      suggestion.unit_purchase_price * quantity,
      lastAppliedInventoryPurchasePrice
    )
  }

  if (suggestion.unit_estimated_value !== null && suggestion.unit_estimated_value !== undefined) {
    applySuggestedNumericField(
      inventoryForm,
      'estimated_value',
      suggestion.unit_estimated_value * quantity,
      lastAppliedInventoryEstimatedValue
    )
  }
}

function applyShipSuggestions() {
  const suggestion = selectedShipPricing.value

  if (!suggestion) {
    resetShipSuggestionTracking()
    return
  }

  applySuggestedNumericField(shipForm, 'purchase_price', suggestion.purchase_price, lastAppliedShipPurchasePrice)
}

function resetTransactionForm() {
  editingTransactionId.value = null
  transactionForm.clearErrors()
  transactionForm.reset()
  transactionForm.ledger_account_id = props.ledger?.account?.id ?? null
  transactionForm.wipe_cycle_id = defaultFormWipeId()
  transactionForm.type = 'income'
  transactionForm.amount = ''
  transactionForm.currency = props.ledger?.account?.currency ?? 'aUEC'
  transactionForm.source_type = rememberedDefaults.value.lastTransactionSourceType ?? ''
  transactionForm.description = ''
  transactionForm.transaction_date = ''
  transactionForm.related_operation_id = ''
  transactionForm.related_uex_type = ''
  transactionForm.related_uex_id = ''
  transactionForm.notes = ''
}

function resetTransferForm() {
  transferForm.clearErrors()
  transferForm.reset()
  transferForm.wipe_cycle_id = defaultFormWipeId()
  transferForm.amount = ''
  transferForm.description = ''
  transferForm.transaction_date = ''
  transferForm.notes = ''
  selectedTransferTargetKey.value = transferTargets.value.length === 1
    ? transferTargets.value[0].key
    : ''
  transferForm.destination_type = selectedTransferTarget.value?.type ?? ''
  transferForm.destination_squadron_id = selectedTransferTarget.value?.squadron_id ?? ''
}

function resetInventoryTransferForm() {
  inventoryTransferForm.clearErrors()
  inventoryTransferForm.reset()
  inventoryTransferForm.inventory_item_id = transferInventoryItems.value.length === 1
    ? String(transferInventoryItems.value[0].id)
    : ''
  inventoryTransferForm.quantity = selectedInventoryTransferItem.value?.quantity ?? ''
  inventoryTransferForm.notes = ''
  selectedInventoryTransferTargetKey.value = transferTargets.value.length === 1
    ? transferTargets.value[0].key
    : ''
  inventoryTransferForm.destination_type = selectedInventoryTransferTarget.value?.type ?? ''
  inventoryTransferForm.destination_squadron_id = selectedInventoryTransferTarget.value?.squadron_id ?? ''
}

function resetTradeForm() {
  editingTradeId.value = null
  tradeForm.clearErrors()
  tradeForm.reset()
  resetTradeSuggestionTracking()
  tradeForm.ledger_account_id = props.ledger?.account?.id ?? null
  tradeForm.wipe_cycle_id = defaultFormWipeId()
  tradeForm.commodity_uex_id = ''
  tradeForm.quantity = ''
  tradeForm.unit_type = rememberedDefaults.value.lastTradeUnitType ?? DEFAULT_TRADE_UNIT_TYPE
  tradeForm.buy_price_per_unit = ''
  tradeForm.sell_price_per_unit = ''
  tradeForm.cargo_capacity_used = ''
  tradeForm.trade_date = ''
  tradeForm.notes = ''
}

function resetInventoryForm() {
  editingInventoryItemId.value = null
  inventoryForm.clearErrors()
  inventoryForm.reset()
  resetInventorySuggestionTracking()

  withSuppressedInventorySourceReset(() => {
    inventoryForm.wipe_cycle_id = defaultFormWipeId()
    inventoryForm.source_type = rememberedDefaults.value.lastInventorySourceType ?? 'item'
    inventoryForm.uex_reference_type = inventoryForm.source_type === 'custom' ? '' : inventoryForm.source_type
    inventoryForm.uex_reference_id = ''
  })

  inventoryForm.custom_name = ''
  inventoryForm.category = ''
  inventoryForm.quantity = 1
  inventoryForm.unit_label = ''
  inventoryForm.location_name = ''
  inventoryForm.purchase_price = ''
  inventoryForm.estimated_value = ''
  inventoryForm.currency = props.ledger?.account?.currency ?? 'aUEC'
  inventoryForm.status = 'owned'
  inventoryForm.acquired_at = ''
  inventoryForm.notes = ''
}

function resetShipForm() {
  editingShipId.value = null
  shipForm.clearErrors()
  shipForm.reset()
  resetShipSuggestionTracking()
  shipForm.wipe_cycle_id = defaultFormWipeId()
  shipForm.vehicle_uex_id = ''
  shipForm.custom_name = ''
  shipForm.serial_or_label = ''
  shipForm.purchase_price = ''
  shipForm.currency = props.ledger?.account?.currency ?? 'aUEC'
  shipForm.acquisition_source = rememberedDefaults.value.lastShipAcquisitionSource ?? ''
  shipForm.current_location = ''
  shipForm.status = 'owned'
  shipForm.acquired_at = ''
  shipForm.notes = ''
}

function toLocalInputValue(value) {
  if (!value) return ''

  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return ''

  const localDate = new Date(date.getTime() - date.getTimezoneOffset() * 60000)
  return localDate.toISOString().slice(0, 16)
}

function startTransactionEdit(transaction) {
  editingTransactionId.value = transaction.id
  transactionForm.clearErrors()
  transactionForm.ledger_account_id = transaction.ledger_account_id ?? props.ledger?.account?.id ?? null
  transactionForm.wipe_cycle_id = transaction.wipe_cycle_id ?? defaultFormWipeId()
  transactionForm.type = transaction.type ?? 'income'
  transactionForm.amount = transaction.amount ?? ''
  transactionForm.currency = transaction.currency ?? props.ledger?.account?.currency ?? 'aUEC'
  transactionForm.source_type = transaction.source_type ?? ''
  transactionForm.description = transaction.description ?? ''
  transactionForm.transaction_date = toLocalInputValue(transaction.transaction_date)
  transactionForm.related_operation_id = transaction.related_operation_id ?? ''
  transactionForm.related_uex_type = transaction.related_uex_type ?? ''
  transactionForm.related_uex_id = transaction.related_uex_id ?? ''
  transactionForm.notes = transaction.notes ?? ''
}

function startTradeEdit(trade) {
  editingTradeId.value = trade.id
  tradeForm.clearErrors()
  resetTradeSuggestionTracking()
  tradeForm.ledger_account_id = trade.ledger_account_id ?? props.ledger?.account?.id ?? null
  tradeForm.wipe_cycle_id = trade.wipe_cycle_id ?? defaultFormWipeId()
  tradeForm.commodity_uex_id = trade.commodity_uex_id ?? ''
  tradeForm.quantity = trade.quantity ?? ''
  tradeForm.unit_type = trade.unit_type ?? 'SCU'
  tradeForm.buy_price_per_unit = trade.buy_price_per_unit ?? ''
  tradeForm.sell_price_per_unit = trade.sell_price_per_unit ?? ''
  tradeForm.cargo_capacity_used = trade.cargo_capacity_used ?? ''
  tradeForm.trade_date = toLocalInputValue(trade.trade_date)
  tradeForm.notes = trade.notes ?? ''
}

function startInventoryEdit(item) {
  editingInventoryItemId.value = item.id
  inventoryForm.clearErrors()
  resetInventorySuggestionTracking()

  withSuppressedInventorySourceReset(() => {
    inventoryForm.wipe_cycle_id = item.wipe_cycle_id ?? defaultFormWipeId()
    inventoryForm.source_type = item.source_type ?? 'item'
    inventoryForm.uex_reference_type = item.source_type === 'custom'
      ? ''
      : (item.uex_reference_type ?? item.source_type ?? 'item')
    inventoryForm.uex_reference_id = item.uex_reference_id ?? ''
  })

  inventoryForm.custom_name = item.custom_name ?? ''
  inventoryForm.category = item.category ?? ''
  inventoryForm.quantity = item.quantity ?? 1
  inventoryForm.unit_label = item.unit_label ?? ''
  inventoryForm.location_name = item.location_name ?? ''
  inventoryForm.purchase_price = item.purchase_price ?? ''
  inventoryForm.estimated_value = item.estimated_value ?? ''
  inventoryForm.currency = item.currency ?? props.ledger?.account?.currency ?? 'aUEC'
  inventoryForm.status = item.status ?? 'owned'
  inventoryForm.acquired_at = toLocalInputValue(item.acquired_at)
  inventoryForm.notes = item.notes ?? ''
}

function startShipEdit(ship) {
  editingShipId.value = ship.id
  shipForm.clearErrors()
  resetShipSuggestionTracking()
  shipForm.wipe_cycle_id = ship.wipe_cycle_id ?? defaultFormWipeId()
  shipForm.vehicle_uex_id = ship.vehicle_uex_id ?? ''
  shipForm.custom_name = ship.custom_name ?? ''
  shipForm.serial_or_label = ship.serial_or_label ?? ''
  shipForm.purchase_price = ship.purchase_price ?? ''
  shipForm.currency = ship.currency ?? props.ledger?.account?.currency ?? 'aUEC'
  shipForm.acquisition_source = ship.acquisition_source ?? ''
  shipForm.current_location = ship.current_location ?? ''
  shipForm.status = ship.status ?? 'owned'
  shipForm.acquired_at = toLocalInputValue(ship.acquired_at)
  shipForm.notes = ship.notes ?? ''
}

function duplicateTransaction(transaction) {
  resetTransactionForm()
  transactionForm.wipe_cycle_id = transaction.wipe_cycle_id ?? defaultFormWipeId()
  transactionForm.type = transaction.type ?? 'income'
  transactionForm.amount = transaction.amount ?? ''
  transactionForm.currency = transaction.currency ?? props.ledger?.account?.currency ?? 'aUEC'
  transactionForm.source_type = transaction.source_type ?? ''
  transactionForm.description = transaction.description ?? ''
  transactionForm.transaction_date = toLocalInputValue(transaction.transaction_date)
  transactionForm.related_operation_id = transaction.related_operation_id ?? ''
  transactionForm.related_uex_type = transaction.related_uex_type ?? ''
  transactionForm.related_uex_id = transaction.related_uex_id ?? ''
  transactionForm.notes = transaction.notes ?? ''
}

function duplicateTrade(trade) {
  resetTradeForm()
  tradeForm.wipe_cycle_id = trade.wipe_cycle_id ?? defaultFormWipeId()
  tradeForm.commodity_uex_id = trade.commodity_uex_id ?? ''
  tradeForm.quantity = trade.quantity ?? ''
  tradeForm.unit_type = trade.unit_type ?? DEFAULT_TRADE_UNIT_TYPE
  tradeForm.buy_price_per_unit = trade.buy_price_per_unit ?? ''
  tradeForm.sell_price_per_unit = trade.sell_price_per_unit ?? ''
  tradeForm.cargo_capacity_used = trade.cargo_capacity_used ?? ''
  tradeForm.trade_date = toLocalInputValue(trade.trade_date)
  tradeForm.notes = trade.notes ?? ''
}

function duplicateInventoryItem(item) {
  resetInventoryForm()

  withSuppressedInventorySourceReset(() => {
    inventoryForm.wipe_cycle_id = item.wipe_cycle_id ?? defaultFormWipeId()
    inventoryForm.source_type = item.source_type ?? 'item'
    inventoryForm.uex_reference_type = item.source_type === 'custom'
      ? ''
      : (item.uex_reference_type ?? item.source_type ?? 'item')
    inventoryForm.uex_reference_id = item.uex_reference_id ?? ''
  })

  inventoryForm.custom_name = item.custom_name ?? ''
  inventoryForm.category = item.category ?? ''
  inventoryForm.quantity = item.quantity ?? 1
  inventoryForm.unit_label = item.unit_label ?? ''
  inventoryForm.location_name = item.location_name ?? ''
  inventoryForm.purchase_price = item.purchase_price ?? ''
  inventoryForm.estimated_value = item.estimated_value ?? ''
  inventoryForm.currency = item.currency ?? props.ledger?.account?.currency ?? 'aUEC'
  inventoryForm.status = item.status ?? 'owned'
  inventoryForm.acquired_at = toLocalInputValue(item.acquired_at)
  inventoryForm.notes = item.notes ?? ''
}

function duplicateShip(ship) {
  resetShipForm()
  shipForm.wipe_cycle_id = ship.wipe_cycle_id ?? defaultFormWipeId()
  shipForm.vehicle_uex_id = ship.vehicle_uex_id ?? ''
  shipForm.custom_name = ship.custom_name ?? ''
  shipForm.serial_or_label = ship.serial_or_label ?? ''
  shipForm.purchase_price = ship.purchase_price ?? ''
  shipForm.currency = ship.currency ?? props.ledger?.account?.currency ?? 'aUEC'
  shipForm.acquisition_source = ship.acquisition_source ?? ''
  shipForm.current_location = ship.current_location ?? ''
  shipForm.status = ship.status ?? 'owned'
  shipForm.acquired_at = toLocalInputValue(ship.acquired_at)
  shipForm.notes = ship.notes ?? ''
}

function submitTransaction() {
  const options = {
    preserveScroll: true,
    onError: (errors) => {
      showLedgerFormError(errors, 'Please fill in the required transaction details.', 'Missing Transaction Info')
    },
    onSuccess: () => {
      rememberCommonLedgerDefaults({
        preferredCycleId: transactionForm.wipe_cycle_id,
        lastTransactionSourceType: transactionForm.source_type,
        transactionSourceHistory: rememberHistoryEntry(transactionSourceSuggestions.value, transactionForm.source_type),
      })
      resetTransactionForm()
    },
  }

  if (editingTransactionId.value) {
    transactionForm.put(transactionUpdateHref(editingTransactionId.value), options)
    return
  }

  transactionForm.post(transactionStoreHref(), options)
}

function submitTransfer() {
  const options = {
    preserveScroll: true,
    onError: (errors) => {
      showLedgerFormError(errors, 'Please choose a destination, amount, and reason for the transfer.', 'Missing Transfer Info')
    },
    onSuccess: () => {
      resetTransferForm()
    },
  }

  transferForm.post(transactionTransferHref(), options)
}

function submitInventoryTransfer() {
  const options = {
    preserveScroll: true,
    onError: (errors) => {
      showLedgerFormError(errors, 'Please choose an inventory record, destination, and quantity to move.', 'Missing Inventory Transfer Info')
    },
    onSuccess: () => {
      resetInventoryTransferForm()
    },
  }

  inventoryTransferForm.post(inventoryTransferHref(), options)
}

function submitTrade() {
  const options = {
    preserveScroll: true,
    onError: (errors) => {
      showLedgerFormError(errors, 'Please fill in the required trade details.', 'Missing Trade Info')
    },
    onSuccess: () => {
      rememberCommonLedgerDefaults({
        preferredCycleId: tradeForm.wipe_cycle_id,
        lastTradeUnitType: tradeForm.unit_type || DEFAULT_TRADE_UNIT_TYPE,
      })
      resetTradeForm()
    },
  }

  if (editingTradeId.value) {
    tradeForm.put(tradeUpdateHref(editingTradeId.value), options)
    return
  }

  tradeForm.post(tradeStoreHref(), options)
}

function submitInventory() {
  const options = {
    preserveScroll: true,
    onError: (errors) => {
      showLedgerFormError(errors, 'Please fill in the required inventory details.', 'Missing Inventory Info')
    },
    onSuccess: () => {
      rememberCommonLedgerDefaults({
        preferredCycleId: inventoryForm.wipe_cycle_id,
        lastInventorySourceType: inventoryForm.source_type,
      })
      resetInventoryForm()
    },
  }

  if (editingInventoryItemId.value) {
    inventoryForm.put(inventoryUpdateHref(editingInventoryItemId.value), options)
    return
  }

  inventoryForm.post(inventoryStoreHref(), options)
}

function submitShip() {
  const options = {
    preserveScroll: true,
    onError: (errors) => {
      showLedgerFormError(errors, 'Please fill in the required ship details.', 'Missing Ship Info')
    },
    onSuccess: () => {
      rememberCommonLedgerDefaults({
        preferredCycleId: shipForm.wipe_cycle_id,
        lastShipAcquisitionSource: shipForm.acquisition_source,
        shipAcquisitionSourceHistory: rememberHistoryEntry(shipAcquisitionSourceSuggestions.value, shipForm.acquisition_source),
      })
      resetShipForm()
    },
  }

  if (editingShipId.value) {
    shipForm.put(shipUpdateHref(editingShipId.value), options)
    return
  }

  shipForm.post(shipStoreHref(), options)
}

function deleteLedgerRecord(href, message, onSuccess) {
  pendingConfirmation.value = {
    title: 'Delete Record',
    message,
    confirmLabel: 'Delete',
    variant: 'danger',
    action: ({ close, finish }) => {
      router.delete(href, {
        preserveScroll: true,
        onSuccess: () => {
          onSuccess?.()
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

function formatMoney(value) {
  const amount = Number(value ?? 0)
  return `${amount.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 })} aUEC`
}

function formatSignedMoney(value) {
  const amount = Number(value ?? 0)

  if (amount > 0) {
    return `+${formatMoney(amount)}`
  }

  if (amount < 0) {
    return `-${formatMoney(Math.abs(amount))}`
  }

  return formatMoney(0)
}

function formatCount(value) {
  return Number(value ?? 0).toLocaleString('en-US')
}

function formatLedgerQuantity(value) {
  const quantity = Number(value ?? 0)
  return quantity.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 4 })
}

function formatDate(value) {
  if (!value) return 'Not set'

  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return String(value)

  return date.toLocaleString()
}

function formatLedgerStatus(value) {
  if (!value) return 'Unknown'

  if (value === 'archived_by_wipe') {
    return 'Archived by cycle'
  }

  return String(value)
    .replaceAll('_', ' ')
    .replace(/\b\w/g, character => character.toUpperCase())
}

function comparisonDeltaClass(value) {
  const amount = Number(value ?? 0)

  if (amount > 0) {
    return 'text-emerald-100'
  }

  if (amount < 0) {
    return 'text-rose-100'
  }

  return 'text-text-secondary'
}

function comparisonDeltaLabel(value) {
  const amount = Number(value ?? 0)

  if (amount > 0) {
    return 'Ahead of last cycle'
  }

  if (amount < 0) {
    return 'Behind last cycle'
  }

  return 'Flat against last cycle'
}

function formatCycleRange(cycle) {
  if (!cycle) {
    return 'Cycle timing not set'
  }

  const started = cycle.started_at ? formatDate(cycle.started_at) : 'Unknown start'
  const ended = cycle.ended_at ? formatDate(cycle.ended_at) : 'Still live'

  return `${started} to ${ended}`
}

function tabClass(key) {
  return activeTab.value === key
    ? 'hz-ledger-tab-active'
    : 'hz-ledger-tab-inactive'
}
</script>

<template>
  <HorizonContainer class="py-8 md:py-10">
    <HorizonConfirmDialog
      ref="confirmDialog"
      :title="pendingConfirmation?.title ?? 'Confirm Action'"
      :message="pendingConfirmation?.message ?? ''"
      :confirm-label="pendingConfirmation?.confirmLabel ?? 'Confirm'"
      :variant="pendingConfirmation?.variant ?? 'danger'"
      @confirm="handleConfirmDialogConfirm"
    />

    <div class="hz-ledger-page mx-auto max-w-7xl space-y-6">
      <section class="hz-surface-welcome relative overflow-hidden rounded-[2rem] border border-white/[0.055] p-6">
        <div class="pointer-events-none absolute inset-0 opacity-40">
          <div class="absolute left-8 top-0 h-px w-48 bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-blue)] to-transparent"></div>
          <div class="absolute bottom-0 right-10 h-px w-64 bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-magenta)] to-transparent"></div>
        </div>

        <div class="relative grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-end">
          <div>
            <div class="text-xs font-bold uppercase tracking-[0.28em] text-[color:var(--horizon-text-secondary)]">
              {{ ledgerEyebrow }}
            </div>

            <h1 class="mt-2 text-3xl font-black tracking-tight text-horizon-white md:text-5xl">
              {{ ledgerTitle }}
            </h1>

            <p class="mt-3 max-w-3xl text-sm text-text-secondary md:text-base">
              {{ ledgerDescription }}
            </p>

            <div v-if="isSquadronLedger && squadron" class="mt-4">
              <a
                :href="squadron.slug ? route('squadrons.show', { squadron: squadron.slug }) : `/squadrons/${squadron.id}`"
                class="hz-ledger-panel-soft inline-flex items-center rounded-full px-4 py-2 text-xs font-bold uppercase tracking-[0.16em] text-text-secondary transition hover:text-horizon-white"
              >
                Back To Squadron
              </a>
            </div>
          </div>

          <div class="hz-ledger-panel-soft rounded-[1.5rem] p-4">
            <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
              Active Cycle View
            </div>

            <div class="mt-2 text-2xl font-black text-horizon-white">
              {{ selectedWipe?.name ?? currentWipe?.name ?? 'Current Live' }}
            </div>

            <div class="mt-1 text-sm text-text-secondary">
              {{ selectedWipe?.star_citizen_version || currentWipe?.star_citizen_version || 'Version pending' }}
            </div>
          </div>
        </div>

        <div
          v-if="wipeBanner"
          class="relative mt-5 rounded-[1.25rem] border border-amber-300/25 bg-amber-300/10 px-4 py-3 text-sm text-amber-100"
        >
          {{ wipeBanner }}
        </div>

        <div
          v-if="isHistoryView && canEditLedger"
          class="relative mt-4 rounded-[1.25rem] border border-white/10 bg-white/[0.03] px-4 py-3 text-sm text-text-secondary"
        >
          This cycle view is archived, so ledger history is locked here. Switch back to the current cycle to add, edit, move, or delete records.
        </div>
      </section>

      <section class="hz-ledger-shell rounded-[2rem] p-4 sm:p-5 lg:p-6">
        <div class="space-y-5">
          <section class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            <div class="hz-ledger-panel-soft rounded-[1.5rem] p-4">
              <div class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Estimated Net Balance</div>
              <div class="mt-2 text-2xl font-black text-horizon-white">{{ formatMoney(ledger.overview?.cards?.estimated_balance) }}</div>
            </div>

            <div class="hz-ledger-panel-soft rounded-[1.5rem] p-4">
              <div class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Income This View</div>
              <div class="mt-2 text-2xl font-black text-emerald-100">{{ formatMoney(ledger.overview?.cards?.income) }}</div>
            </div>

            <div class="hz-ledger-panel-soft rounded-[1.5rem] p-4">
              <div class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Expenses This View</div>
              <div class="mt-2 text-2xl font-black text-red-100">{{ formatMoney(ledger.overview?.cards?.expenses) }}</div>
            </div>

            <div class="hz-ledger-panel-soft rounded-[1.5rem] p-4">
              <div class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Trade Profit</div>
              <div class="mt-2 text-2xl font-black text-horizon-white">{{ formatMoney(ledger.overview?.cards?.trade_profit) }}</div>
            </div>
          </section>

      <section class="hz-ledger-tabbar rounded-[1.5rem] p-4">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
          <div class="flex flex-wrap gap-2">
            <button
              v-for="tab in tabs"
              :key="tab.key"
              type="button"
              class="rounded-full border px-4 py-2 text-xs font-bold uppercase tracking-[0.16em] transition duration-200"
              :class="tabClass(tab.key)"
              @click="activeTab = tab.key"
            >
              {{ tab.label }}
            </button>
          </div>

          <div class="w-full lg:w-72">
            <label class="mb-1 block text-xs font-bold uppercase tracking-[0.14em] text-text-muted">
              Cycle Filter
            </label>
            <select v-model="wipeFilter" class="hz-input">
              <option value="current">Current cycle only</option>
              <option value="all">All cycles</option>
              <option
                v-for="wipe in wipeCycles"
                :key="wipe.id"
                :value="String(wipe.id)"
              >
                {{ wipe.name }}
              </option>
            </select>
          </div>
        </div>
      </section>

      <section v-if="activeTab === 'overview'" class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="space-y-5">
          <div class="hz-ledger-panel rounded-[1.75rem] p-5">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
              <div>
                <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">{{ overviewScopeLabel }}</div>
                <div class="mt-1 text-sm text-text-secondary">
                  UEX-backed estimates show up with a label so you can tell what was inferred instead of typed.
                </div>
              </div>
            </div>

            <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-5">
              <div class="hz-ledger-panel-soft rounded-[1.25rem] p-4">
                <div class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Realized Profit</div>
                <div class="mt-2 text-xl font-black text-horizon-white">{{ formatMoney(ledger.overview?.cards?.net_profit) }}</div>
              </div>

              <div class="hz-ledger-panel-soft rounded-[1.25rem] p-4">
                <div class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Trade Spend</div>
                <div class="mt-2 text-xl font-black text-horizon-white">{{ formatMoney(ledger.overview?.cards?.trade_spend) }}</div>
              </div>

              <div class="hz-ledger-panel-soft rounded-[1.25rem] p-4">
                <div class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Commodity Value</div>
                <div class="mt-2 text-xl font-black text-horizon-white">{{ formatMoney(ledger.overview?.cards?.commodity_inventory_value) }}</div>
              </div>

              <div class="hz-ledger-panel-soft rounded-[1.25rem] p-4">
                <div class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Gear And Components</div>
                <div class="mt-2 text-xl font-black text-horizon-white">{{ formatMoney(ledger.overview?.cards?.gear_inventory_value) }}</div>
              </div>

              <div class="hz-ledger-panel-soft rounded-[1.25rem] p-4">
                <div class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Fleet Value</div>
                <div class="mt-2 text-xl font-black text-horizon-white">{{ formatMoney(ledger.overview?.cards?.fleet_value) }}</div>
              </div>
            </div>
          </div>

          <div
            v-if="cycleComparison"
            class="hz-ledger-panel rounded-[1.75rem] p-5"
          >
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
              <div>
                <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Cycle Comparison</div>
                <div class="mt-1 text-lg font-black text-horizon-white">
                  {{ cycleComparison.focus.name }} vs {{ cycleComparison.baseline.name }}
                </div>
                <div class="mt-1 text-sm text-text-secondary">
                  See how this cycle is stacking up against the one right before it.
                </div>
              </div>

              <div class="text-xs text-text-muted">
                {{ formatCycleRange(cycleComparison.focus) }}
              </div>
            </div>

            <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
              <div
                v-for="metric in cycleComparison.metrics ?? []"
                :key="metric.key"
                class="hz-ledger-panel-soft rounded-[1.25rem] p-4"
              >
                <div class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">{{ metric.label }}</div>
                <div class="mt-2 text-lg font-black text-horizon-white">{{ formatMoney(metric.focus_value) }}</div>
                <div class="mt-1 text-xs text-text-secondary">
                  Last cycle: {{ formatMoney(metric.baseline_value) }}
                </div>
                <div class="mt-3 text-sm font-black" :class="comparisonDeltaClass(metric.delta)">
                  {{ formatSignedMoney(metric.delta) }}
                </div>
                <div class="mt-1 text-[11px] uppercase tracking-[0.14em]" :class="comparisonDeltaClass(metric.delta)">
                  {{ comparisonDeltaLabel(metric.delta) }}
                </div>
              </div>
            </div>
          </div>

          <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div class="hz-ledger-panel rounded-[1.5rem] p-4">
              <div class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Inventory Value</div>
              <div class="mt-2 text-xl font-black text-horizon-white">{{ formatMoney(ledger.overview?.cards?.inventory_value) }}</div>
              <div v-if="ledger.overview?.cards?.inventory_estimate_count" class="mt-1 text-xs text-text-secondary">
                {{ formatCount(ledger.overview?.cards?.inventory_estimate_count) }} UEX estimate<span v-if="ledger.overview?.cards?.inventory_estimate_count !== 1">s</span>
              </div>
            </div>

            <div class="hz-ledger-panel rounded-[1.5rem] p-4">
              <div class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Ships Owned</div>
              <div class="mt-2 text-xl font-black text-horizon-white">{{ ledger.overview?.cards?.ships_owned ?? 0 }}</div>
              <div v-if="ledger.overview?.cards?.ship_estimate_count" class="mt-1 text-xs text-text-secondary">
                {{ formatCount(ledger.overview?.cards?.ship_estimate_count) }} UEX price match<span v-if="ledger.overview?.cards?.ship_estimate_count !== 1">es</span>
              </div>
            </div>

            <div class="hz-ledger-panel rounded-[1.5rem] p-4">
              <div class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Top Commodity</div>
              <div class="mt-2 text-xl font-black text-horizon-white">
                {{ ledger.overview?.topCommodityTrades?.[0]?.label ?? 'No trade runs yet' }}
              </div>
              <div v-if="ledger.overview?.topCommodityTrades?.[0]" class="mt-1 text-xs text-text-secondary">
                Profit {{ formatMoney(ledger.overview?.topCommodityTrades?.[0]?.value) }}
              </div>
            </div>
          </div>

          <div class="hz-ledger-panel rounded-[1.75rem] p-5">
            <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Recent Transactions</div>
            <div class="mt-4 space-y-3">
              <article
                v-for="transaction in ledger.overview?.recentTransactions ?? []"
                :key="`overview-transaction-${transaction.id}`"
                class="hz-ledger-panel-soft rounded-[1.25rem] p-4"
              >
                <div class="flex flex-wrap items-center justify-between gap-3">
                  <div>
                    <div class="text-sm font-black text-horizon-white">{{ transaction.description }}</div>
                    <div class="mt-1 text-xs uppercase tracking-[0.14em] text-text-muted">
                      {{ transaction.type }}<span v-if="transaction.source_type"> • {{ transaction.source_type }}</span>
                    </div>
                    <div v-if="transaction.notes" class="mt-3 whitespace-pre-line text-sm text-text-secondary">
                      {{ transaction.notes }}
                    </div>
                  </div>

                  <div class="text-right">
                    <div class="text-sm font-black text-horizon-white">{{ formatMoney(transaction.amount) }}</div>
                    <div class="mt-1 text-xs text-text-muted">{{ formatDate(transaction.transaction_date) }}</div>
                  </div>
                </div>
              </article>
            </div>
          </div>

          <div class="grid gap-4 xl:grid-cols-2">
            <div class="hz-ledger-panel rounded-[1.75rem] p-5">
              <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Recent Activity</div>
              <div class="mt-4 space-y-3">
                <div
                  v-for="activity in ledger.overview?.recentActivity ?? []"
                  :key="activity.id"
                  class="hz-ledger-panel-soft rounded-[1.25rem] px-4 py-3"
                >
                  <div class="text-sm font-black text-horizon-white">
                    {{ activity.title }}
                  </div>
                  <div v-if="activity.detail" class="mt-1 text-xs tracking-[0.04em] text-text-secondary">
                    {{ activity.detail }}
                  </div>
                  <div class="mt-1 text-xs text-text-muted">
                    {{ formatDate(activity.created_at) }}
                  </div>
                </div>
              </div>
            </div>

            <div class="hz-ledger-panel rounded-[1.75rem] p-5">
              <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Recent Trades</div>
              <div class="mt-4 space-y-3">
                <div
                  v-for="trade in ledger.overview?.recentTrades ?? []"
                  :key="trade.id"
                  class="hz-ledger-panel-soft rounded-[1.25rem] px-4 py-3"
                >
                  <div class="text-sm font-black text-horizon-white">{{ trade.commodity || 'Unknown commodity' }}</div>
                  <div class="mt-1 text-xs uppercase tracking-[0.14em] text-text-muted">
                    {{ trade.quantity }} {{ trade.unit_type }} • Profit {{ formatMoney(trade.profit) }}
                  </div>
                  <div v-if="trade.notes" class="mt-3 whitespace-pre-line text-sm text-text-secondary">
                    {{ trade.notes }}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <aside class="space-y-5">
          <div class="hz-ledger-panel rounded-[1.75rem] p-5">
            <div class="flex items-center justify-between gap-3">
              <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Cycle Snapshots</div>
              <div class="text-[11px] uppercase tracking-[0.14em] text-text-muted">
                {{ formatCount(cycleSummaries.length) }} total
              </div>
            </div>

            <div class="mt-4 space-y-3">
              <div
                v-for="cycle in cycleSummaries"
                :key="`overview-cycle-${cycle.id}`"
                class="rounded-[1.25rem] border px-4 py-3"
                :class="cycle.is_current
                  ? 'hz-ledger-panel-soft border-emerald-300/25 bg-emerald-300/8'
                  : 'hz-ledger-panel-soft'"
              >
                <div class="flex items-start justify-between gap-3">
                  <div>
                    <div class="text-sm font-black text-horizon-white">{{ cycle.name }}</div>
                    <div class="mt-1 text-[11px] uppercase tracking-[0.14em] text-text-muted">
                      {{ cycle.star_citizen_version || 'Version pending' }} • {{ cycle.wipe_type }}
                    </div>
                  </div>

                  <span
                    class="rounded-full border px-2 py-1 text-[10px] font-bold uppercase tracking-[0.16em]"
                    :class="cycle.is_current
                      ? 'border-emerald-300/25 bg-emerald-300/10 text-emerald-100'
                      : 'hz-ledger-panel-soft text-text-secondary'"
                  >
                    {{ cycle.is_current ? 'Current' : 'Archived' }}
                  </span>
                </div>

                <div class="mt-2 text-xs text-text-secondary">
                  {{ formatCycleRange(cycle) }}
                </div>

                <div class="mt-3 grid grid-cols-2 gap-3 text-xs">
                  <div>
                    <div class="uppercase tracking-[0.14em] text-text-muted">Net</div>
                    <div class="mt-1 font-black text-horizon-white">{{ formatMoney(cycle.cards?.net_profit) }}</div>
                  </div>
                  <div>
                    <div class="uppercase tracking-[0.14em] text-text-muted">Trade Profit</div>
                    <div class="mt-1 font-black text-horizon-white">{{ formatMoney(cycle.cards?.trade_profit) }}</div>
                  </div>
                  <div>
                    <div class="uppercase tracking-[0.14em] text-text-muted">Inventory</div>
                    <div class="mt-1 font-black text-horizon-white">{{ formatMoney(cycle.cards?.inventory_value) }}</div>
                  </div>
                  <div>
                    <div class="uppercase tracking-[0.14em] text-text-muted">Fleet</div>
                    <div class="mt-1 font-black text-horizon-white">{{ formatMoney(cycle.cards?.fleet_value) }}</div>
                  </div>
                </div>

                <div class="mt-3 flex flex-wrap gap-2 text-[11px] uppercase tracking-[0.14em] text-text-muted">
                  <span>{{ formatCount(cycle.cards?.transaction_count) }} entries</span>
                  <span>{{ formatCount(cycle.cards?.trade_count) }} trades</span>
                  <span>{{ formatCount(cycle.cards?.inventory_count) }} items</span>
                  <span>{{ formatCount(cycle.cards?.ship_count) }} ships</span>
                </div>
              </div>
            </div>
          </div>

          <div class="hz-ledger-panel rounded-[1.75rem] p-5">
            <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Top Commodities This View</div>
            <div class="mt-4 space-y-3">
              <div
                v-for="row in ledger.overview?.topCommodityTrades ?? []"
                :key="`overview-top-commodity-${row.label}`"
                class="hz-ledger-panel-soft rounded-[1.25rem] px-4 py-3"
              >
                <div class="flex items-center justify-between gap-3">
                  <div class="text-sm font-black text-horizon-white">{{ row.label }}</div>
                  <div class="text-sm font-black text-horizon-white">{{ formatMoney(row.value) }}</div>
                </div>
              </div>
            </div>
          </div>
        </aside>
      </section>

      <section v-else-if="activeTab === 'transactions'" class="grid gap-4 xl:grid-cols-[24rem_minmax(0,1fr)]">
        <aside v-if="canEditCurrentView" class="hz-ledger-panel rounded-[1.75rem] p-4 sm:p-5">
          <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">{{ transactionModeLabel }}</div>
          <h3 class="mt-1 text-xl font-black text-horizon-white">Income, Expense, Or Adjustment</h3>
          <p class="mt-2 text-sm text-text-secondary">
            {{ editingTransactionId ? 'Update this ledger entry and keep the original cycle context intact.' : 'Log payouts, refuel bills, repairs, or manual balance adjustments.' }}
          </p>

          <form class="mt-4 space-y-4" @submit.prevent="submitTransaction">
            <HorizonSelect
              v-model="transactionForm.wipe_cycle_id"
              :options="formCycleOptions"
              label="Cycle"
              placeholder="Select cycle"
            />

            <select v-model="transactionForm.type" class="hz-input">
              <option value="income">Income</option>
              <option value="expense">Expense</option>
              <option value="adjustment">Adjustment</option>
            </select>

            <input v-model="transactionForm.amount" type="number" step="0.01" class="hz-input" placeholder="Amount" />
            <input
              v-model="transactionForm.source_type"
              type="text"
              list="transaction-source-suggestions"
              class="hz-input"
              placeholder="Source type, like mining or refuel"
            />
            <datalist id="transaction-source-suggestions">
              <option v-for="source in transactionSourceSuggestions" :key="`transaction-source-${source}`" :value="source" />
            </datalist>
            <input v-model="transactionForm.description" type="text" class="hz-input" placeholder="Description" />
            <HorizonDateTimePicker
              v-model="transactionForm.transaction_date"
              label="Transaction date"
              clearable
              show-now
            />

            <select v-model="transactionForm.related_uex_type" class="hz-input">
              <option value="">No related UEX reference</option>
              <option value="commodity">Commodity</option>
              <option value="item">Item</option>
              <option value="component">Component</option>
              <option value="vehicle">Ship</option>
            </select>

            <HorizonSelect
              v-if="transactionForm.related_uex_type"
              v-model="transactionForm.related_uex_id"
              :options="transactionRelatedSelectOptions"
              label="Related Reference"
              placeholder="Select related reference"
              searchable
              search-placeholder="Search related references"
            />

            <textarea v-model="transactionForm.notes" class="hz-input min-h-28 resize-y" placeholder="Notes"></textarea>

            <div class="flex flex-wrap justify-end gap-2">
              <HorizonButton
                v-if="editingTransactionId"
                type="button"
                variant="secondary"
                :disabled="transactionForm.processing"
                @click="resetTransactionForm"
              >
                Cancel Edit
              </HorizonButton>

              <HorizonButton type="submit" :disabled="transactionForm.processing">
                {{ editingTransactionId ? 'Update Transaction' : 'Save Transaction' }}
              </HorizonButton>
            </div>
          </form>
        </aside>

        <aside v-else class="hz-ledger-panel rounded-[1.75rem] p-4 sm:p-5">
          <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Read Only</div>
          <h3 class="mt-1 text-xl font-black text-horizon-white">{{ readOnlySectionContent('Transaction', 'transactions').heading }}</h3>
          <p class="mt-2 text-sm text-text-secondary">
            {{ readOnlySectionContent('Transaction', 'transactions').body }}
          </p>
        </aside>

        <div class="hz-ledger-panel rounded-[1.75rem] p-4 sm:p-5">
          <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Transaction Log</div>
          <div class="mt-4 space-y-3">
            <article
              v-for="transaction in ledger.transactions ?? []"
              :key="transaction.id"
              class="hz-ledger-panel-soft rounded-[1.25rem] p-4"
              :class="editingTransactionId === transaction.id
                ? 'border-[color:var(--horizon-sunset-blue)]/40'
                : 'border-white/[0.055]'"
            >
              <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div>
                  <div class="text-base font-black text-horizon-white">{{ transaction.description }}</div>
                  <div class="mt-1 text-xs uppercase tracking-[0.14em] text-text-muted">
                    {{ transaction.type }}<span v-if="transaction.source_type"> • {{ transaction.source_type }}</span>
                  </div>
                  <div v-if="transaction.related_reference" class="mt-2 text-sm text-text-secondary">
                    <span v-if="transaction.related_reference">{{ transaction.related_reference }}</span>
                  </div>
                  <div v-if="transaction.notes" class="mt-3 whitespace-pre-line text-sm text-text-secondary">
                    {{ transaction.notes }}
                  </div>
                </div>

                <div class="text-right">
                  <div class="text-base font-black text-horizon-white">{{ formatMoney(transaction.amount) }}</div>
                  <div class="mt-1 text-xs text-text-muted">{{ formatDate(transaction.transaction_date) }}</div>
                </div>
              </div>

              <div v-if="canEditCurrentView" class="mt-4 flex flex-wrap justify-end gap-2">
                <HorizonButton v-if="!transaction.is_transfer" type="button" size="sm" variant="ghost" @click="startTransactionEdit(transaction)">
                  Edit
                </HorizonButton>
                <HorizonButton v-if="!transaction.is_transfer" type="button" size="sm" variant="ghost" @click="duplicateTransaction(transaction)">
                  Duplicate
                </HorizonButton>
                <div
                  v-if="transaction.is_transfer"
                  class="rounded-full border border-amber-300/20 bg-amber-300/10 px-3 py-1 text-[10px] font-bold uppercase tracking-[0.16em] text-amber-100"
                >
                  Locked transfer record
                </div>
                <HorizonButton
                  v-if="!transaction.is_transfer"
                  type="button"
                  size="sm"
                  variant="danger"
                  @click="deleteLedgerRecord(transactionDestroyHref(transaction.id), 'Delete this transaction?', resetTransactionForm)"
                >
                  Delete
                </HorizonButton>
              </div>
            </article>
          </div>
        </div>
      </section>

      <section v-else-if="activeTab === 'transfers'" class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="space-y-4">
          <div v-if="canTransferFunds" class="hz-ledger-panel rounded-[1.75rem] p-4 sm:p-5">
            <button
              type="button"
              class="flex w-full items-start justify-between gap-4 text-left"
              @click="toggleTransferPanel('funds')"
            >
              <div>
                <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Move Funds</div>
                <h3 class="mt-1 text-xl font-black text-horizon-white">Ledger Transfer</h3>
                <p class="mt-2 text-sm text-text-secondary">
                  Send funds out of {{ ledger.account?.name }} and let Horizon write the matching in and out records for you.
                </p>
              </div>

              <div class="hz-ledger-panel-soft mt-1 rounded-full px-3 py-1 text-[10px] font-bold uppercase tracking-[0.16em] text-text-secondary">
                {{ isTransferPanelOpen('funds') ? 'Close' : 'Open' }}
              </div>
            </button>

            <form v-if="isTransferPanelOpen('funds')" class="mt-4 space-y-4" @submit.prevent="submitTransfer">
              <HorizonSelect
                v-model="transferForm.wipe_cycle_id"
                :options="formCycleOptions"
                label="Cycle"
                placeholder="Select cycle"
              />

              <HorizonSelect
                v-model="selectedTransferTargetKey"
                :options="transferTargetOptions"
                label="Send To"
                placeholder="Choose destination ledger"
              />

              <div
                v-if="selectedTransferTarget?.description"
                class="hz-ledger-panel-soft rounded-[1rem] px-3 py-2 text-xs text-text-secondary"
              >
                {{ selectedTransferTarget.description }}
              </div>

              <input v-model="transferForm.amount" type="number" step="0.01" min="0" class="hz-input" placeholder="Amount" />
              <input v-model="transferForm.description" type="text" class="hz-input" placeholder="Reason for this transfer" />
              <HorizonDateTimePicker
                v-model="transferForm.transaction_date"
                label="Transfer date"
                clearable
                show-now
              />
              <textarea v-model="transferForm.notes" class="hz-input min-h-24 resize-y" placeholder="Notes"></textarea>

              <div class="flex flex-wrap justify-end gap-2">
                <HorizonButton
                  type="button"
                  variant="secondary"
                  :disabled="transferForm.processing"
                  @click="resetTransferForm"
                >
                  Reset Transfer
                </HorizonButton>

                <HorizonButton type="submit" :disabled="transferForm.processing">
                  Send Funds
                </HorizonButton>
              </div>
            </form>
          </div>

          <div v-if="canTransferInventory" class="hz-ledger-panel rounded-[1.75rem] p-4 sm:p-5">
            <button
              type="button"
              class="flex w-full items-start justify-between gap-4 text-left"
              @click="toggleTransferPanel('inventory')"
            >
              <div>
                <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Move Inventory</div>
                <h3 class="mt-1 text-xl font-black text-horizon-white">Inventory Transfer</h3>
                <p class="mt-2 text-sm text-text-secondary">
                  Move tracked cargo, components, or gear into another ledger while keeping the original cycle history attached to the item.
                </p>
              </div>

              <div class="hz-ledger-panel-soft mt-1 rounded-full px-3 py-1 text-[10px] font-bold uppercase tracking-[0.16em] text-text-secondary">
                {{ isTransferPanelOpen('inventory') ? 'Close' : 'Open' }}
              </div>
            </button>

            <form v-if="isTransferPanelOpen('inventory')" class="mt-4 space-y-4" @submit.prevent="submitInventoryTransfer">
              <HorizonSelect
                v-model="inventoryTransferForm.inventory_item_id"
                :options="transferInventoryItemOptions"
                label="Inventory Record"
                placeholder="Choose inventory to move"
                searchable
                search-placeholder="Search tracked inventory"
              />

              <HorizonSelect
                v-model="selectedInventoryTransferTargetKey"
                :options="transferTargetOptions"
                label="Send To"
                placeholder="Choose destination ledger"
              />

              <div
                v-if="selectedInventoryTransferTarget?.description"
                class="hz-ledger-panel-soft rounded-[1rem] px-3 py-2 text-xs text-text-secondary"
              >
                {{ selectedInventoryTransferTarget.description }}
              </div>

              <div class="grid gap-3 sm:grid-cols-2">
                <input v-model="inventoryTransferForm.quantity" type="number" step="0.0001" min="0" class="hz-input" placeholder="Quantity to move" />
                <div class="hz-ledger-panel-soft rounded-[1rem] px-3 py-3 text-sm text-text-secondary">
                  <div class="text-[10px] font-bold uppercase tracking-[0.16em] text-text-muted">Available</div>
                  <div class="mt-1 font-black text-horizon-white">
                    {{ selectedInventoryTransferItem ? `${formatLedgerQuantity(selectedInventoryTransferItem.quantity)} ${selectedInventoryTransferItem.unit_label}` : 'Choose an item' }}
                  </div>
                </div>
              </div>

              <textarea v-model="inventoryTransferForm.notes" class="hz-input min-h-24 resize-y" placeholder="Notes"></textarea>

              <div class="flex flex-wrap justify-end gap-2">
                <HorizonButton
                  type="button"
                  variant="secondary"
                  :disabled="inventoryTransferForm.processing"
                  @click="resetInventoryTransferForm"
                >
                  Reset Inventory Move
                </HorizonButton>

                <HorizonButton type="submit" :disabled="inventoryTransferForm.processing">
                  Move Inventory
                </HorizonButton>
              </div>
            </form>
          </div>

          <div v-if="!canTransferFunds && !canTransferInventory" class="hz-ledger-panel rounded-[1.75rem] p-4 sm:p-5">
            <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Transfers Locked</div>
            <h3 class="mt-1 text-xl font-black text-horizon-white">{{ isHistoryView ? 'Archived Cycle View' : 'No Available Destinations' }}</h3>
            <p class="mt-2 text-sm text-text-secondary">
              {{ isHistoryView
                ? 'Transfers are locked while you are viewing archived cycle history. Switch back to the current cycle to move funds or inventory.'
                : 'This ledger can only transfer into books you manage. Once you have another shared ledger available, the transfer tools will show up here.' }}
            </p>
          </div>
        </div>

        <div class="hz-ledger-panel rounded-[1.75rem] p-4 sm:p-5">
          <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">How Transfers Work</div>
          <div class="mt-4 space-y-3 text-sm text-text-secondary">
            <p>Every transfer writes a matched expense in the source ledger and income in the destination ledger.</p>
            <p>Inventory moves can transfer the full stack or just part of it. Partial moves split the quantity into a new destination record.</p>
            <p>Fund transfer records stay locked so the two books never drift out of sync.</p>
            <p>Use clear notes so both sides of the movement still make sense later when you review the cycle history.</p>
          </div>
        </div>
      </section>

      <section v-else-if="activeTab === 'trades'" class="grid gap-4 xl:grid-cols-[24rem_minmax(0,1fr)]">
        <aside v-if="canEditCurrentView" class="hz-ledger-panel rounded-[1.75rem] p-4 sm:p-5">
          <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">{{ tradeModeLabel }}</div>
          <h3 class="mt-1 text-xl font-black text-horizon-white">Commodity Run</h3>
          <p class="mt-2 text-sm text-text-secondary">
            {{ editingTradeId ? 'Adjust the route, prices, or ship assignment for this run.' : 'Record buy and sell legs so Horizon can keep your cargo profit clean.' }}
          </p>

          <form class="mt-4 space-y-4" @submit.prevent="submitTrade">
            <HorizonSelect
              v-model="tradeForm.wipe_cycle_id"
              :options="formCycleOptions"
              label="Cycle"
              placeholder="Select cycle"
            />

            <HorizonSelect
              v-model="tradeForm.commodity_uex_id"
              :options="commoditySelectOptions"
              label="Commodity"
              placeholder="Select commodity"
              searchable
              search-placeholder="Search commodities"
            />

            <div
              v-if="selectedTradePricing"
              class="hz-ledger-panel-soft rounded-[1rem] px-3 py-2 text-xs text-text-secondary"
            >
              <div v-if="selectedTradePricing.buy_price_per_unit !== null">
                Best known buy: {{ formatMoney(selectedTradePricing.buy_price_per_unit) }} per unit
                <span v-if="selectedTradePricing.buy_terminal_name"> at {{ selectedTradePricing.buy_terminal_name }}</span>
              </div>
              <div v-if="selectedTradePricing.sell_price_per_unit !== null" class="mt-1">
                Best known sell: {{ formatMoney(selectedTradePricing.sell_price_per_unit) }} per unit
                <span v-if="selectedTradePricing.sell_terminal_name"> at {{ selectedTradePricing.sell_terminal_name }}</span>
              </div>
            </div>

            <div
              v-if="tradeRouteAssist"
              class="rounded-[1rem] border border-emerald-300/15 bg-emerald-300/5 px-3 py-2 text-xs text-emerald-50"
            >
              <div v-if="tradeRouteAssist.marginPerUnit !== null">
                Rough margin: {{ formatMoney(tradeRouteAssist.marginPerUnit) }} per {{ tradeForm.unit_type || 'unit' }}
              </div>
              <div v-if="tradeRouteAssist.estimatedProfit !== null" class="mt-1">
                Estimated run profit: {{ formatMoney(tradeRouteAssist.estimatedProfit) }} for {{ tradeRouteAssist.quantity }} {{ tradeForm.unit_type || 'unit' }}
              </div>
              <div
                v-if="tradeRouteAssist.buyTerminalName || tradeRouteAssist.sellTerminalName"
                class="mt-1 text-text-secondary"
              >
                Buy
                <span class="text-horizon-white">{{ tradeRouteAssist.buyTerminalName || 'wherever you find the best price' }}</span>
                and sell
                <span class="text-horizon-white">{{ tradeRouteAssist.sellTerminalName || 'wherever you find the best price' }}</span>.
              </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
              <input v-model="tradeForm.quantity" type="number" step="0.0001" class="hz-input" placeholder="Quantity" />
              <input v-model="tradeForm.unit_type" type="text" class="hz-input" placeholder="Unit type, like SCU" />
              <input v-model="tradeForm.buy_price_per_unit" type="number" step="0.01" class="hz-input" placeholder="Buy price per unit" />
              <input v-model="tradeForm.sell_price_per_unit" type="number" step="0.01" class="hz-input" placeholder="Sell price per unit" />
            </div>
            <input v-model="tradeForm.cargo_capacity_used" type="number" step="0.0001" class="hz-input" placeholder="Cargo capacity used, optional" />
            <HorizonDateTimePicker
              v-model="tradeForm.trade_date"
              label="Trade date"
              clearable
              show-now
            />

            <textarea v-model="tradeForm.notes" class="hz-input min-h-28 resize-y" placeholder="Notes"></textarea>

            <div class="flex flex-wrap justify-end gap-2">
              <HorizonButton
                v-if="editingTradeId"
                type="button"
                variant="secondary"
                :disabled="tradeForm.processing"
                @click="resetTradeForm"
              >
                Cancel Edit
              </HorizonButton>

              <HorizonButton type="submit" :disabled="tradeForm.processing">
                {{ editingTradeId ? 'Update Trade' : 'Log Trade' }}
              </HorizonButton>
            </div>
          </form>
        </aside>

        <aside v-else class="hz-ledger-panel rounded-[1.75rem] p-4 sm:p-5">
          <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Read Only</div>
          <h3 class="mt-1 text-xl font-black text-horizon-white">{{ readOnlySectionContent('Trade', 'trade runs').heading }}</h3>
          <p class="mt-2 text-sm text-text-secondary">
            {{ readOnlySectionContent('Trade', 'trade runs').body }}
          </p>
        </aside>

        <div class="hz-ledger-panel rounded-[1.75rem] p-4 sm:p-5">
          <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Trade Runs</div>
          <div class="mt-4 space-y-3">
            <article
              v-for="trade in ledger.trades ?? []"
              :key="trade.id"
              class="hz-ledger-panel-soft rounded-[1.25rem] p-4"
              :class="editingTradeId === trade.id
                ? 'border-[color:var(--horizon-sunset-blue)]/40'
                : 'border-white/[0.055]'"
            >
              <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div>
                  <div class="text-base font-black text-horizon-white">{{ trade.commodity || 'Unknown commodity' }}</div>
                  <div class="mt-1 text-xs uppercase tracking-[0.14em] text-text-muted">
                    {{ trade.quantity }} {{ trade.unit_type }}
                  </div>
                  <div class="mt-2 text-sm text-text-secondary">
                    Cost {{ formatMoney(trade.total_cost) }} • Revenue {{ formatMoney(trade.total_revenue) }}
                  </div>
                  <div v-if="trade.notes" class="mt-3 whitespace-pre-line text-sm text-text-secondary">
                    {{ trade.notes }}
                  </div>
                </div>

                <div class="text-right">
                  <div class="text-base font-black text-horizon-white">{{ formatMoney(trade.profit) }}</div>
                  <div class="mt-1 text-xs text-text-muted">{{ formatDate(trade.trade_date) }}</div>
                </div>
              </div>

              <div v-if="canEditCurrentView" class="mt-4 flex flex-wrap justify-end gap-2">
                <HorizonButton type="button" size="sm" variant="ghost" @click="startTradeEdit(trade)">
                  Edit
                </HorizonButton>
                <HorizonButton type="button" size="sm" variant="ghost" @click="duplicateTrade(trade)">
                  Duplicate
                </HorizonButton>
                <HorizonButton
                  type="button"
                  size="sm"
                  variant="danger"
                  @click="deleteLedgerRecord(tradeDestroyHref(trade.id), 'Delete this trade run?', resetTradeForm)"
                >
                  Delete
                </HorizonButton>
              </div>
            </article>
          </div>
        </div>
      </section>

      <section v-else-if="activeTab === 'inventory'" class="grid gap-4 xl:grid-cols-[24rem_minmax(0,1fr)]">
        <aside v-if="canEditCurrentView" class="hz-ledger-panel rounded-[1.75rem] p-4 sm:p-5">
          <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">{{ inventoryModeLabel }}</div>
          <h3 class="mt-1 text-xl font-black text-horizon-white">Tracked Items</h3>
          <p class="mt-2 text-sm text-text-secondary">
            {{ editingInventoryItemId ? 'Refine the item source, quantity, value, or assigned ship.' : 'Track weapons, components, commodities, and custom stash records against a cycle.' }}
          </p>

          <form class="mt-4 space-y-4" @submit.prevent="submitInventory">
            <HorizonSelect
              v-model="inventoryForm.wipe_cycle_id"
              :options="formCycleOptions"
              label="Cycle"
              placeholder="Select cycle"
            />

            <select v-model="inventoryForm.source_type" class="hz-input">
              <option value="item">Item</option>
              <option value="component">Component</option>
              <option value="commodity">Commodity</option>
              <option value="custom">Custom</option>
            </select>

            <HorizonSelect
              v-if="inventoryForm.source_type !== 'custom'"
              v-model="inventoryForm.uex_reference_id"
              :options="inventoryReferenceSelectOptions"
              label="Cached UEX Reference"
              placeholder="Select cached UEX reference"
              searchable
              search-placeholder="Search cached references"
            />

            <div
              v-if="selectedInventorySuggestion"
              class="hz-ledger-panel-soft rounded-[1rem] px-3 py-2 text-xs text-text-secondary"
            >
              <div v-if="selectedInventorySuggestion.unit_purchase_price !== null">
                Suggested buy value: {{ formatMoney(selectedInventorySuggestion.unit_purchase_price) }} per {{ selectedInventorySuggestion.unit_label || 'unit' }}
                <span v-if="selectedInventorySuggestion.purchase_terminal_name"> at {{ selectedInventorySuggestion.purchase_terminal_name }}</span>
              </div>
              <div v-if="selectedInventorySuggestion.unit_estimated_value !== null" class="mt-1">
                Suggested estimate: {{ formatMoney(selectedInventorySuggestion.unit_estimated_value) }} per {{ selectedInventorySuggestion.unit_label || 'unit' }}
                <span v-if="selectedInventorySuggestion.estimated_terminal_name"> from {{ selectedInventorySuggestion.estimated_terminal_name }}</span>
              </div>
            </div>

            <input v-if="inventoryForm.source_type === 'custom'" v-model="inventoryForm.custom_name" type="text" class="hz-input" placeholder="Custom item name" />
            <div class="grid gap-3 sm:grid-cols-2">
              <input v-model="inventoryForm.category" type="text" class="hz-input" placeholder="Category" />
              <input v-model="inventoryForm.quantity" type="number" step="0.0001" class="hz-input" placeholder="Quantity" />
              <input v-model="inventoryForm.unit_label" type="text" class="hz-input" placeholder="Unit label, like SCU or units" />
              <input v-model="inventoryForm.location_name" type="text" class="hz-input" placeholder="Free-text location" />
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
              <input v-model="inventoryForm.purchase_price" type="number" step="0.01" class="hz-input" placeholder="Purchase price, optional" />
              <input v-model="inventoryForm.estimated_value" type="number" step="0.01" class="hz-input" placeholder="Estimated value, optional" />
            </div>
            <HorizonDateTimePicker
              v-model="inventoryForm.acquired_at"
              label="Acquired at"
              clearable
              show-now
            />
            <textarea v-model="inventoryForm.notes" class="hz-input min-h-28 resize-y" placeholder="Notes"></textarea>

            <div class="flex flex-wrap justify-end gap-2">
              <HorizonButton
                v-if="editingInventoryItemId"
                type="button"
                variant="secondary"
                :disabled="inventoryForm.processing"
                @click="resetInventoryForm"
              >
                Cancel Edit
              </HorizonButton>

              <HorizonButton type="submit" :disabled="inventoryForm.processing">
                {{ editingInventoryItemId ? 'Update Inventory' : 'Save Inventory' }}
              </HorizonButton>
            </div>
          </form>
        </aside>

        <aside v-else class="hz-ledger-panel rounded-[1.75rem] p-4 sm:p-5">
          <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Read Only</div>
          <h3 class="mt-1 text-xl font-black text-horizon-white">{{ readOnlySectionContent('Inventory', 'inventory records').heading }}</h3>
          <p class="mt-2 text-sm text-text-secondary">
            {{ readOnlySectionContent('Inventory', 'inventory records').body }}
          </p>
        </aside>

        <div class="hz-ledger-panel rounded-[1.75rem] p-4 sm:p-5">
          <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Inventory Records</div>
          <div class="mt-4 space-y-3">
            <article
              v-for="item in ledger.inventoryItems ?? []"
              :key="item.id"
              class="hz-ledger-panel-soft rounded-[1.25rem] p-4"
              :class="editingInventoryItemId === item.id
                ? 'border-[color:var(--horizon-sunset-blue)]/40'
                : 'border-white/[0.055]'"
            >
              <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div>
                  <div class="text-base font-black text-horizon-white">{{ item.reference_label || 'Custom entry' }}</div>
                  <div class="mt-1 text-xs uppercase tracking-[0.14em] text-text-muted">
                    {{ item.source_type }}<span v-if="item.category"> • {{ item.category }}</span> • {{ item.quantity }} {{ item.unit_label || 'units' }}
                  </div>
                  <div class="mt-2 text-sm text-text-secondary">
                    <span v-if="item.location_name">{{ item.location_name }}</span>
                  </div>
                  <div v-if="item.notes" class="mt-3 whitespace-pre-line text-sm text-text-secondary">
                    {{ item.notes }}
                  </div>
                </div>

                <div class="text-right">
                  <div class="text-sm font-black text-horizon-white">{{ item.estimated_value !== null ? formatMoney(item.estimated_value) : 'No estimate' }}</div>
                  <div class="mt-1 text-xs text-text-muted">{{ formatLedgerStatus(item.status) }}</div>
                  <div v-if="item.estimated_value_source === 'uex_estimate'" class="mt-2">
                    <span class="rounded-full border border-sky-300/20 bg-sky-300/10 px-2 py-1 text-[10px] font-bold uppercase tracking-[0.16em] text-sky-100">
                      UEX estimate
                    </span>
                  </div>
                </div>
              </div>

              <div v-if="canEditCurrentView" class="mt-4 flex flex-wrap justify-end gap-2">
                <HorizonButton type="button" size="sm" variant="ghost" @click="startInventoryEdit(item)">
                  Edit
                </HorizonButton>
                <HorizonButton type="button" size="sm" variant="ghost" @click="duplicateInventoryItem(item)">
                  Duplicate
                </HorizonButton>
                <HorizonButton
                  type="button"
                  size="sm"
                  variant="danger"
                  @click="deleteLedgerRecord(inventoryDestroyHref(item.id), 'Delete this inventory record?', resetInventoryForm)"
                >
                  Delete
                </HorizonButton>
              </div>
            </article>
          </div>
        </div>
      </section>

      <section v-else-if="activeTab === 'ships'" class="grid gap-4 xl:grid-cols-[24rem_minmax(0,1fr)]">
        <aside v-if="canEditCurrentView" class="hz-ledger-panel rounded-[1.75rem] p-4 sm:p-5">
          <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">{{ shipModeLabel }}</div>
          <h3 class="mt-1 text-xl font-black text-horizon-white">Tracked Ships</h3>
          <p class="mt-2 text-sm text-text-secondary">
            {{ editingShipId ? 'Update the hull, status, or hangar notes for this asset.' : 'Keep a cycle-aware list of owned, pledged, loaned, or rented ships.' }}
          </p>

          <form class="mt-4 space-y-4" @submit.prevent="submitShip">
            <HorizonSelect
              v-model="shipForm.wipe_cycle_id"
              :options="formCycleOptions"
              label="Cycle"
              placeholder="Select cycle"
            />

            <HorizonSelect
              v-model="shipForm.vehicle_uex_id"
              :options="shipSelectOptions"
              label="Ship"
              placeholder="Select ship"
              searchable
              search-placeholder="Search ships"
            />

            <div
              v-if="selectedShipPricing"
              class="hz-ledger-panel-soft rounded-[1rem] px-3 py-2 text-xs text-text-secondary"
            >
              <div v-if="selectedShipPricing.purchase_price !== null">
                Best known purchase price: {{ formatMoney(selectedShipPricing.purchase_price) }}
                <span v-if="selectedShipPricing.purchase_terminal_name"> at {{ selectedShipPricing.purchase_terminal_name }}</span>
              </div>
              <div v-if="selectedShipPricing.rental_price !== null" class="mt-1">
                Known rental price: {{ formatMoney(selectedShipPricing.rental_price) }}
                <span v-if="selectedShipPricing.rental_terminal_name"> at {{ selectedShipPricing.rental_terminal_name }}</span>
              </div>
            </div>

            <input v-model="shipForm.custom_name" type="text" class="hz-input" placeholder="Custom ship name, optional" />
            <div class="grid gap-3 sm:grid-cols-2">
              <input v-model="shipForm.purchase_price" type="number" step="0.01" class="hz-input" placeholder="aUEC price" />
              <input v-model="shipForm.current_location" type="text" class="hz-input" placeholder="Current location" />
            </div>

            <select v-model="shipForm.status" class="hz-input">
              <option value="owned">Owned</option>
              <option value="pledged">Pledged</option>
              <option value="rented">Rented</option>
              <option value="loaner">Loaner</option>
            </select>

            <HorizonDateTimePicker
              v-model="shipForm.acquired_at"
              label="Acquired at"
              clearable
              show-now
            />
            <textarea v-model="shipForm.notes" class="hz-input min-h-28 resize-y" placeholder="Notes"></textarea>

            <div class="flex flex-wrap justify-end gap-2">
              <HorizonButton
                v-if="editingShipId"
                type="button"
                variant="secondary"
                :disabled="shipForm.processing"
                @click="resetShipForm"
              >
                Cancel Edit
              </HorizonButton>

              <HorizonButton type="submit" :disabled="shipForm.processing">
                {{ editingShipId ? 'Update Ship' : 'Save Ship' }}
              </HorizonButton>
            </div>
          </form>
        </aside>

        <aside v-else class="hz-ledger-panel rounded-[1.75rem] p-4 sm:p-5">
          <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Read Only</div>
          <h3 class="mt-1 text-xl font-black text-horizon-white">{{ readOnlySectionContent('Fleet', 'ship assets').heading }}</h3>
          <p class="mt-2 text-sm text-text-secondary">
            {{ readOnlySectionContent('Fleet', 'ship assets').body }}
          </p>
        </aside>

        <div class="hz-ledger-panel rounded-[1.75rem] p-4 sm:p-5">
          <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Ship Assets</div>
          <div class="mt-4 grid gap-3 md:grid-cols-2">
            <article
              v-for="ship in ledger.shipAssets ?? []"
              :key="ship.id"
              class="hz-ledger-panel-soft rounded-[1.25rem] p-4"
              :class="editingShipId === ship.id
                ? 'border-[color:var(--horizon-sunset-blue)]/40'
                : 'border-white/[0.055]'"
            >
              <div class="text-base font-black text-horizon-white">{{ ship.ship_name }}</div>
              <div class="mt-1 text-xs uppercase tracking-[0.14em] text-text-muted">
                {{ formatLedgerStatus(ship.status) }}<span v-if="ship.serial_or_label"> • {{ ship.serial_or_label }}</span>
              </div>
              <div class="mt-2 text-sm text-text-secondary">
                {{ ship.current_location || 'Location not set' }}
              </div>
              <div v-if="ship.notes" class="mt-3 whitespace-pre-line text-sm text-text-secondary">
                {{ ship.notes }}
              </div>
              <div class="mt-2 text-sm text-horizon-white">
                {{ ship.purchase_price !== null ? formatMoney(ship.purchase_price) : 'No purchase value recorded' }}
              </div>
              <div v-if="ship.purchase_price_source === 'uex_estimate'" class="mt-2">
                <span class="rounded-full border border-sky-300/20 bg-sky-300/10 px-2 py-1 text-[10px] font-bold uppercase tracking-[0.16em] text-sky-100">
                  UEX estimate
                </span>
              </div>

              <div v-if="canEditCurrentView" class="mt-4 flex flex-wrap justify-end gap-2">
                <HorizonButton type="button" size="sm" variant="ghost" @click="startShipEdit(ship)">
                  Edit
                </HorizonButton>
                <HorizonButton type="button" size="sm" variant="ghost" @click="duplicateShip(ship)">
                  Duplicate
                </HorizonButton>
                <HorizonButton
                  type="button"
                  size="sm"
                  variant="danger"
                  @click="deleteLedgerRecord(shipDestroyHref(ship.id), 'Delete this ship asset? Linked records will keep their history but lose the ship link.', resetShipForm)"
                >
                  Delete
                </HorizonButton>
              </div>
            </article>
          </div>
        </div>
      </section>

      <section v-else-if="activeTab === 'reports'" class="grid gap-5 xl:grid-cols-2">
        <div class="hz-ledger-panel rounded-[1.75rem] p-5">
          <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Profit / Loss By Cycle</div>
          <div class="mt-4 space-y-3">
            <div
              v-for="row in ledger.reports?.profitLossByWipe ?? []"
              :key="row.label"
              class="hz-ledger-panel-soft flex items-center justify-between rounded-[1.25rem] px-4 py-3"
            >
              <div class="text-sm font-bold text-horizon-white">{{ row.label }}</div>
              <div class="text-sm text-text-secondary">{{ formatMoney(row.value) }}</div>
            </div>
          </div>
        </div>

        <div class="hz-ledger-panel rounded-[1.75rem] p-5">
          <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Income By Source</div>
          <div class="mt-4 space-y-3">
            <div
              v-for="row in ledger.reports?.incomeBySource ?? []"
              :key="`income-${row.label}`"
              class="hz-ledger-panel-soft flex items-center justify-between rounded-[1.25rem] px-4 py-3"
            >
              <div class="text-sm font-bold text-horizon-white">{{ row.label }}</div>
              <div class="text-sm text-text-secondary">{{ formatMoney(row.value) }}</div>
            </div>
          </div>
        </div>

        <div class="hz-ledger-panel rounded-[1.75rem] p-5">
          <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Trade Profit By Commodity</div>
          <div class="mt-4 space-y-3">
            <div
              v-for="row in ledger.reports?.tradeProfitByCommodity ?? []"
              :key="`commodity-${row.label}`"
              class="hz-ledger-panel-soft flex items-center justify-between rounded-[1.25rem] px-4 py-3"
            >
              <div class="text-sm font-bold text-horizon-white">{{ row.label }}</div>
              <div class="text-sm text-text-secondary">{{ formatMoney(row.value) }}</div>
            </div>
          </div>
        </div>

        <div class="hz-ledger-panel rounded-[1.75rem] p-5">
          <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Inventory Value By Category</div>
          <div class="mt-4 space-y-3">
            <div
              v-for="row in ledger.reports?.inventoryValueByCategory ?? []"
              :key="`inventory-${row.label}`"
              class="hz-ledger-panel-soft flex items-center justify-between rounded-[1.25rem] px-4 py-3"
            >
              <div class="text-sm font-bold text-horizon-white">{{ row.label }}</div>
              <div class="text-sm text-text-secondary">{{ formatMoney(row.value) }}</div>
            </div>
          </div>
        </div>
      </section>

      <section v-else class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_24rem]">
        <div class="hz-ledger-panel rounded-[1.75rem] p-5">
          <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Ledger Settings</div>
          <h3 class="mt-1 text-xl font-black text-horizon-white">{{ settingsHeading }}</h3>
          <div class="mt-4 grid gap-4 md:grid-cols-2">
            <div class="hz-ledger-panel-soft rounded-[1.25rem] p-4">
              <div class="text-xs font-bold uppercase tracking-[0.14em] text-text-muted">Default Account</div>
              <div class="mt-2 text-lg font-black text-horizon-white">{{ ledger.account?.name }}</div>
              <div class="mt-1 text-sm text-text-secondary">{{ ledger.account?.currency }}</div>
            </div>

            <div class="hz-ledger-panel-soft rounded-[1.25rem] p-4">
              <div class="text-xs font-bold uppercase tracking-[0.14em] text-text-muted">Current Cycle</div>
              <div class="mt-2 text-lg font-black text-horizon-white">{{ currentWipe?.name }}</div>
              <div class="mt-1 text-sm text-text-secondary">{{ currentWipe?.star_citizen_version || 'Version pending' }}</div>
            </div>
          </div>
        </div>

        <aside class="hz-ledger-panel rounded-[1.75rem] p-5">
          <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Module Notes</div>
          <div class="mt-4 space-y-3 text-sm text-text-secondary">
            <p>Ledger balances are calculated from transactions plus recorded trade profit.</p>
            <p>Cycles archive your history instead of deleting it.</p>
            <p>UEX records shown here come from the local Horizon snapshot, not live page-load calls.</p>
          </div>
        </aside>
      </section>
        </div>
      </section>
    </div>
  </HorizonContainer>
</template>
