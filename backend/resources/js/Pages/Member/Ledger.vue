<script setup>
import { computed, ref, watch } from 'vue'
import { router, useForm, usePage } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

import HorizonContainer from '@/Components/HorizonContainer.vue'
import HorizonButton from '@/Components/HorizonButton.vue'
import HorizonConfirmDialog from '@/Components/HorizonConfirmDialog.vue'
import HorizonDateTimePicker from '@/Components/HorizonDateTimePicker.vue'
import HorizonDrawer from '@/Components/HorizonDrawer.vue'
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
const page = usePage()

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
const currentUser = computed(() => page.props?.auth?.user ?? null)
const currentUserDisplayName = computed(() => {
  return currentUser.value?.username
    ?? currentUser.value?.rsi_handle
    ?? currentUser.value?.discord_name
    ?? currentUser.value?.name
    ?? 'Member'
})
const currentUserAvatar = computed(() => currentUser.value?.discord_avatar ?? null)
const ledgerEyebrow = computed(() => {
  if (isSquadronLedger.value) return 'Squadron Assets & Funds'
  if (isOrganizationLedger.value) return 'Horizon Treasury'
  return 'My Assets & Funds'
})
const ledgerTitle = computed(() => {
  if (isSquadronLedger.value) return props.squadron?.name ?? 'Squadron Accounting'
  if (isOrganizationLedger.value) return 'Horizon Treasury'
  return `${currentUserDisplayName.value}'s Assets & Funds`
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

const reportPalette = [
  { solid: 'rgba(125, 211, 252, 0.96)', soft: 'rgba(125, 211, 252, 0.18)' },
  { solid: 'rgba(96, 165, 250, 0.96)', soft: 'rgba(96, 165, 250, 0.18)' },
  { solid: 'rgba(56, 189, 248, 0.96)', soft: 'rgba(56, 189, 248, 0.18)' },
  { solid: 'rgba(45, 212, 191, 0.96)', soft: 'rgba(45, 212, 191, 0.18)' },
  { solid: 'rgba(251, 191, 36, 0.96)', soft: 'rgba(251, 191, 36, 0.18)' },
  { solid: 'rgba(248, 113, 113, 0.96)', soft: 'rgba(248, 113, 113, 0.18)' },
]

const INVENTORY_PAGE_SIZE = 10

const profitLossReportRows = computed(() => normalizeReportRows(props.ledger?.reports?.profitLossByWipe ?? [], {
  limit: 6,
}))

const incomeSourceReportRows = computed(() => normalizeReportRows(props.ledger?.reports?.incomeBySource ?? [], {
  limit: 5,
  aggregateRemainder: true,
}))

const tradeCommodityReportRows = computed(() => normalizeReportRows(props.ledger?.reports?.tradeProfitByCommodity ?? [], {
  limit: 5,
  aggregateRemainder: true,
}))

const inventoryCategoryReportRows = computed(() => normalizeReportRows(props.ledger?.reports?.inventoryValueByCategory ?? [], {
  limit: 5,
  aggregateRemainder: true,
}))

const reportSummaryCards = computed(() => [
  {
    label: 'Net Across View',
    value: formatSignedMoney(sumReportValues(profitLossReportRows.value)),
    detail: `${profitLossReportRows.value.length || 0} cycle snapshots`,
  },
  {
    label: 'Income Sources',
    value: formatCount(incomeSourceReportRows.value.length),
    detail: topReportLabel(incomeSourceReportRows.value, 'No income logged'),
  },
  {
    label: 'Trade Leaders',
    value: formatCount(tradeCommodityReportRows.value.length),
    detail: topReportLabel(tradeCommodityReportRows.value, 'No trade profit yet'),
  },
  {
    label: 'Inventory Buckets',
    value: formatCount(inventoryCategoryReportRows.value.length),
    detail: topReportLabel(inventoryCategoryReportRows.value, 'No valued inventory yet'),
  },
])
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
const transactionDrawerOpen = ref(false)
const tradeDrawerOpen = ref(false)
const inventoryDrawerOpen = ref(false)
const shipDrawerOpen = ref(false)
const fundTransferDrawerOpen = ref(false)
const inventoryTransferDrawerOpen = ref(false)
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
  destination_user_id: '',
  destination_squadron_id: '',
  amount: '',
  description: '',
  transaction_date: '',
  notes: '',
})

const inventoryTransferForm = useForm({
  inventory_item_id: '',
  destination_type: '',
  destination_user_id: '',
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
const pendingFundTransfers = computed(() => props.ledger?.pendingFundTransfers ?? [])
const pendingInventoryTransfers = computed(() => props.ledger?.pendingInventoryTransfers ?? [])
const recentFundTransfers = computed(() => props.ledger?.recentFundTransfers ?? [])
const transferTabBadge = computed(() => {
  const count = [...pendingFundTransfers.value, ...pendingInventoryTransfers.value]
    .filter(transfer => transfer?.can_approve)
    .length

  if (count <= 0) return null

  return count > 99 ? '99+' : String(count)
})
const transferTargetOptions = computed(() => transferTargets.value.map(target => ({
  value: target.key,
  label: target.label,
})))
const transferInventoryItemOptions = computed(() => transferInventoryItems.value.map(item => ({
  value: String(item.id),
  label: `${item.label} (${formatLedgerQuantity(item.quantity)} ${item.unit_label})`,
})))
const operationEntryFilterOptions = [
  { value: 'all', label: 'All Entries' },
  { value: 'operation', label: 'Operation Linked' },
]
const transactionSearch = ref('')
const transactionEntryFilter = ref('all')
const inventorySearch = ref('')
const inventoryEntryFilter = ref('all')
const inventoryPage = ref(1)
const transactions = computed(() => props.ledger?.transactions ?? [])
const filteredTransactions = computed(() => {
  const query = transactionSearch.value.trim().toLowerCase()

  return transactions.value.filter(transaction => {
    if (transactionEntryFilter.value === 'operation' && !isOperationLinkedEntry(transaction)) {
      return false
    }

    if (!query) {
      return true
    }

    return [
      transaction.description,
      transaction.source_type,
      transaction.notes,
      transaction.operation_title,
    ].some(value => String(value ?? '').toLowerCase().includes(query))
  })
})
const transactionResultsLabel = computed(() => {
  if (!filteredTransactions.value.length) {
    return transactionEntryFilter.value === 'operation'
      ? 'No operation-linked entries match this filter yet.'
      : 'No transactions match this search yet.'
  }

  return `${filteredTransactions.value.length} transaction ${filteredTransactions.value.length === 1 ? 'entry' : 'entries'} in view`
})
const inventoryItems = computed(() => props.ledger?.inventoryItems ?? [])
const filteredInventoryItems = computed(() => {
  const query = inventorySearch.value.trim().toLowerCase()

  return inventoryItems.value.filter(item => {
    if (inventoryEntryFilter.value === 'operation' && !isOperationLinkedEntry(item)) {
      return false
    }

    if (!query) {
      return true
    }

    return [
      item.reference_label,
      item.custom_name,
      item.category,
      item.location_name,
      item.status,
      item.unit_label,
      item.notes,
      item.operation_title,
    ].some(value => String(value ?? '').toLowerCase().includes(query))
  })
})
const inventoryPageCount = computed(() => Math.max(1, Math.ceil(filteredInventoryItems.value.length / INVENTORY_PAGE_SIZE)))
const paginatedInventoryItems = computed(() => {
  const start = (inventoryPage.value - 1) * INVENTORY_PAGE_SIZE
  return filteredInventoryItems.value.slice(start, start + INVENTORY_PAGE_SIZE)
})
const inventoryResultsLabel = computed(() => {
  if (!filteredInventoryItems.value.length) {
    return inventoryEntryFilter.value === 'operation'
      ? 'No operation-linked inventory matches this filter yet.'
      : 'No inventory records match this search.'
  }

  const start = ((inventoryPage.value - 1) * INVENTORY_PAGE_SIZE) + 1
  const end = Math.min(inventoryPage.value * INVENTORY_PAGE_SIZE, filteredInventoryItems.value.length)

  return `Showing ${start}-${end} of ${filteredInventoryItems.value.length} inventory records`
})

const inventoryReferenceSelectOptions = computed(() => inventoryReferenceOptions.value.map(option => ({
  value: String(option.uex_id),
  label: option.name,
})))

const shipSelectOptions = computed(() => (references.value.ships ?? []).map(ship => ({
  value: String(ship.uex_id),
  label: ship.name,
})))
const shipReferenceByVehicleId = computed(() => mapSuggestionsByUexId(references.value.ships ?? []))

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
  recent: false,
  funds: false,
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
const selectedShipReference = computed(() => shipReferenceByVehicleId.value[String(shipForm.vehicle_uex_id ?? '')] ?? null)

function shipImageUrlForVehicle(vehicleUexId) {
  return shipReferenceByVehicleId.value[String(vehicleUexId ?? '')]?.image_url ?? null
}

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

function isOperationLinkedEntry(entry) {
  return Boolean(
    entry?.is_operation_settlement
    || entry?.related_operation_id
    || entry?.operation_title,
  )
}

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

function transactionTransferApproveHref(id) {
  if (isOrganizationLedger.value) {
    return route('organization.ledger.transactions.transfer.approve', id)
  }

  if (isSquadronLedger.value) {
    return route('squadrons.ledger.transactions.transfer.approve', { squadron: props.squadron?.id, transferRequest: id })
  }

  return route('ledger.transactions.transfer.approve', id)
}

function transactionTransferRejectHref(id) {
  if (isOrganizationLedger.value) {
    return route('organization.ledger.transactions.transfer.reject', id)
  }

  if (isSquadronLedger.value) {
    return route('squadrons.ledger.transactions.transfer.reject', { squadron: props.squadron?.id, transferRequest: id })
  }

  return route('ledger.transactions.transfer.reject', id)
}

function transactionTransferReverseHref(id) {
  if (isOrganizationLedger.value) {
    return route('organization.ledger.transactions.transfer.reverse', id)
  }

  if (isSquadronLedger.value) {
    return route('squadrons.ledger.transactions.transfer.reverse', { squadron: props.squadron?.id, transferRequest: id })
  }

  return route('ledger.transactions.transfer.reverse', id)
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

function inventoryTransferApproveHref(id) {
  if (isSquadronLedger.value) {
    return route('squadrons.ledger.inventory.transfer.approve', { squadron: props.squadron?.id, transferRequest: id })
  }

  return route('ledger.inventory.transfer.approve', id)
}

function inventoryTransferRejectHref(id) {
  if (isSquadronLedger.value) {
    return route('squadrons.ledger.inventory.transfer.reject', { squadron: props.squadron?.id, transferRequest: id })
  }

  return route('ledger.inventory.transfer.reject', id)
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

watch(inventorySearch, () => {
  inventoryPage.value = 1
})

watch(inventoryEntryFilter, () => {
  inventoryPage.value = 1
})

watch(filteredInventoryItems, items => {
  if (!items.length) {
    inventoryPage.value = 1
    return
  }

  if (inventoryPage.value > inventoryPageCount.value) {
    inventoryPage.value = inventoryPageCount.value
  }
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
      transferForm.destination_user_id = ''
      transferForm.destination_squadron_id = ''
      return
    }

    transferForm.destination_type = selectedTransferTarget.value.type ?? ''
    transferForm.destination_user_id = selectedTransferTarget.value.user_id ?? ''
    transferForm.destination_squadron_id = selectedTransferTarget.value.squadron_id ?? ''
  }
)

watch(
  () => selectedInventoryTransferTargetKey.value,
  () => {
    if (!selectedInventoryTransferTarget.value) {
      inventoryTransferForm.destination_type = ''
      inventoryTransferForm.destination_user_id = ''
      inventoryTransferForm.destination_squadron_id = ''
      return
    }

    inventoryTransferForm.destination_type = selectedInventoryTransferTarget.value.type ?? ''
    inventoryTransferForm.destination_user_id = selectedInventoryTransferTarget.value.user_id ?? ''
    inventoryTransferForm.destination_squadron_id = selectedInventoryTransferTarget.value.squadron_id ?? ''
  }
)

watch(
  [canTransferFunds, canTransferInventory],
  ([fundsAvailable, inventoryAvailable]) => {
    if (!fundsAvailable) {
      openTransferPanels.value.funds = false
      closeFundTransferDrawer()
    }

    if (!inventoryAvailable) {
      openTransferPanels.value.inventory = false
      closeInventoryTransferDrawer()
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
  transferForm.destination_user_id = selectedTransferTarget.value?.user_id ?? ''
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
  inventoryTransferForm.destination_user_id = selectedInventoryTransferTarget.value?.user_id ?? ''
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

function openTransactionDrawer() {
  resetTransactionForm()
  transactionDrawerOpen.value = true
}

function closeTransactionDrawer() {
  transactionDrawerOpen.value = false
  resetTransactionForm()
}

function openTradeDrawer() {
  resetTradeForm()
  tradeDrawerOpen.value = true
}

function closeTradeDrawer() {
  tradeDrawerOpen.value = false
  resetTradeForm()
}

function openInventoryDrawer() {
  resetInventoryForm()
  inventoryDrawerOpen.value = true
}

function closeInventoryDrawer() {
  inventoryDrawerOpen.value = false
  resetInventoryForm()
}

function openShipDrawer() {
  resetShipForm()
  shipDrawerOpen.value = true
}

function closeShipDrawer() {
  shipDrawerOpen.value = false
  resetShipForm()
}

function openFundTransferDrawer() {
  resetTransferForm()
  fundTransferDrawerOpen.value = true
}

function closeFundTransferDrawer() {
  fundTransferDrawerOpen.value = false
  resetTransferForm()
}

function openInventoryTransferDrawer() {
  resetInventoryTransferForm()
  inventoryTransferDrawerOpen.value = true
}

function closeInventoryTransferDrawer() {
  inventoryTransferDrawerOpen.value = false
  resetInventoryTransferForm()
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
  transactionDrawerOpen.value = true
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
  tradeDrawerOpen.value = true
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
  inventoryDrawerOpen.value = true
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
  shipDrawerOpen.value = true
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
  transactionDrawerOpen.value = true
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
  tradeDrawerOpen.value = true
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
  inventoryDrawerOpen.value = true

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
  shipDrawerOpen.value = true
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
      closeTransactionDrawer()
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
      closeFundTransferDrawer()
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
      closeInventoryTransferDrawer()
    },
  }

  inventoryTransferForm.post(inventoryTransferHref(), options)
}

function approveFundTransfer(transfer) {
  pendingConfirmation.value = {
    title: 'Approve Transfer',
    message: 'Approve this fund transfer request?',
    confirmLabel: 'Approve',
    variant: 'success',
    action: ({ close, finish }) => {
      router.post(transactionTransferApproveHref(transfer.id), {}, {
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

function rejectFundTransfer(transfer) {
  pendingConfirmation.value = {
    title: 'Reject Transfer',
    message: 'Reject this fund transfer request?',
    confirmLabel: 'Reject',
    variant: 'danger',
    action: ({ close, finish }) => {
      router.post(transactionTransferRejectHref(transfer.id), {}, {
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

function reverseFundTransfer(transfer) {
  pendingConfirmation.value = {
    title: 'Reverse Transfer',
    message: 'Reverse this completed fund transfer and write the matching undo records?',
    confirmLabel: 'Reverse',
    variant: 'danger',
    action: ({ close, finish }) => {
      router.post(transactionTransferReverseHref(transfer.id), {}, {
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

function approveInventoryTransfer(transfer) {
  pendingConfirmation.value = {
    title: 'Approve Inventory Move',
    message: 'Approve this inventory transfer request?',
    confirmLabel: 'Approve',
    variant: 'success',
    action: ({ close, finish }) => {
      router.post(inventoryTransferApproveHref(transfer.id), {}, {
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

function rejectInventoryTransfer(transfer) {
  pendingConfirmation.value = {
    title: 'Reject Inventory Move',
    message: 'Reject this inventory transfer request?',
    confirmLabel: 'Reject',
    variant: 'danger',
    action: ({ close, finish }) => {
      router.post(inventoryTransferRejectHref(transfer.id), {}, {
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
      closeTradeDrawer()
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
      closeInventoryDrawer()
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
      closeShipDrawer()
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

function normalizeReportRows(rows, options = {}) {
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

  return trimmedRows.map((row, index) => {
    const palette = reportPalette[index % reportPalette.length]

    return {
      ...row,
      color: palette.solid,
      softColor: palette.soft,
      share: total > 0 ? Math.abs(row.value) / total : 0,
    }
  })
}

function sumReportValues(rows) {
  return (rows ?? []).reduce((sum, row) => sum + Number(row?.value ?? 0), 0)
}

function topReportLabel(rows, fallback) {
  return rows?.[0]?.label ?? fallback
}

function formatMoney(value) {
  const amount = Number(value ?? 0)
  return `${amount.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 })} aUEC`
}

function formatCompactMoney(value) {
  const amount = Number(value ?? 0)

  return new Intl.NumberFormat('en-US', {
    notation: 'compact',
    maximumFractionDigits: amount >= 100000 ? 1 : 0,
  }).format(amount)
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

function buildBarHeight(value, maxValue) {
  const safeMax = Math.max(Number(maxValue ?? 0), 1)
  const height = Math.max((Math.abs(Number(value ?? 0)) / safeMax) * 100, 10)

  return `${Math.min(height, 100)}%`
}

function buildBarWidth(share) {
  const width = Math.max(Number(share ?? 0) * 100, 8)

  return `${Math.min(width, 100)}%`
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

            <div class="mt-2 flex items-center gap-4">
              <div class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-white/10 bg-white/[0.042] md:h-16 md:w-16">
                <img
                  v-if="currentUserAvatar"
                  :src="currentUserAvatar"
                  alt=""
                  class="h-full w-full object-cover"
                />
                <span v-else class="text-xl font-black uppercase text-horizon-white md:text-2xl">
                  {{ String(currentUserDisplayName).slice(0, 1) }}
                </span>
              </div>

              <h1 class="text-3xl font-black tracking-tight text-horizon-white md:text-5xl">
                {{ ledgerTitle }}
              </h1>
            </div>

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
              <div class="hz-ledger-value-positive mt-2 text-2xl font-black">{{ formatMoney(ledger.overview?.cards?.income) }}</div>
            </div>

            <div class="hz-ledger-panel-soft rounded-[1.5rem] p-4">
              <div class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Expenses This View</div>
              <div class="hz-ledger-value-negative mt-2 text-2xl font-black">{{ formatMoney(ledger.overview?.cards?.expenses) }}</div>
            </div>

            <div class="hz-ledger-panel-soft rounded-[1.5rem] p-4">
              <div class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Trade Profit</div>
              <div class="mt-2 text-2xl font-black text-horizon-white">{{ formatMoney(ledger.overview?.cards?.trade_profit) }}</div>
            </div>
          </section>

      <section class="hz-ledger-tabbar rounded-[1.5rem] p-4">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
          <div class="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1 sm:pb-0 md:mx-0 md:flex-wrap md:overflow-visible md:px-0">
            <button
              v-for="tab in tabs"
              :key="tab.key"
              type="button"
              class="flex shrink-0 items-center gap-2 whitespace-nowrap rounded-full border px-4 py-2 text-xs font-bold uppercase tracking-[0.16em] transition duration-200"
              :class="tabClass(tab.key)"
              @click="activeTab = tab.key"
            >
              <span>{{ tab.label }}</span>
              <span
                v-if="tab.key === 'transfers' && transferTabBadge"
                class="grid h-5 min-w-5 place-items-center rounded-full bg-red-500 px-1.5 text-[10px] font-black leading-none text-white"
              >
                {{ transferTabBadge }}
              </span>
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
                    <div v-if="transaction.is_operation_settlement || transaction.operation_title" class="mt-2 flex flex-wrap items-center gap-2">
                      <span
                        v-if="transaction.is_operation_settlement"
                        class="rounded-full border border-emerald-300/20 bg-emerald-300/10 px-2 py-1 text-[10px] font-bold uppercase tracking-[0.16em] text-emerald-100"
                      >
                        Operation
                      </span>
                      <span
                        v-if="transaction.operation_title"
                        class="rounded-full border border-white/[0.08] bg-white/[0.03] px-2 py-1 text-[10px] font-bold uppercase tracking-[0.16em] text-text-secondary"
                      >
                        {{ transaction.operation_title }}
                      </span>
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

      <section v-else-if="activeTab === 'transactions'" class="space-y-4">
        <HorizonDrawer v-if="canEditCurrentView && transactionDrawerOpen" close-label="Close transaction editor drawer" @close="closeTransactionDrawer">
          <template #header>
            <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">{{ transactionModeLabel }}</div>
            <h3 class="mt-1 text-xl font-black text-horizon-white">Income, Expense, Or Adjustment</h3>
            <p class="mt-2 text-sm text-text-secondary">
              {{ editingTransactionId ? 'Update this ledger entry and keep the original cycle context intact.' : 'Log payouts, refuel bills, repairs, or manual balance adjustments.' }}
            </p>
          </template>

          <form class="space-y-4" @submit.prevent="submitTransaction">
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
                @click="closeTransactionDrawer"
              >
                Cancel Edit
              </HorizonButton>

              <HorizonButton type="submit" :disabled="transactionForm.processing">
                {{ editingTransactionId ? 'Update Transaction' : 'Save Transaction' }}
              </HorizonButton>
            </div>
          </form>
        </HorizonDrawer>

        <aside v-if="!canEditCurrentView" class="hz-ledger-panel rounded-[1.75rem] p-4 sm:p-5">
          <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Read Only</div>
          <h3 class="mt-1 text-xl font-black text-horizon-white">{{ readOnlySectionContent('Transaction', 'transactions').heading }}</h3>
          <p class="mt-2 text-sm text-text-secondary">
            {{ readOnlySectionContent('Transaction', 'transactions').body }}
          </p>
        </aside>

        <div class="hz-ledger-panel rounded-[1.75rem] p-4 sm:p-5">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Transaction Log</div>
            <HorizonButton v-if="canEditCurrentView" type="button" size="sm" @click="openTransactionDrawer">
              New Transaction
            </HorizonButton>
          </div>
          <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div class="space-y-3">
              <div class="text-sm text-text-secondary">
                {{ transactionResultsLabel }}
              </div>
              <div class="flex flex-wrap gap-2">
                <button
                  v-for="option in operationEntryFilterOptions"
                  :key="`transaction-filter-${option.value}`"
                  type="button"
                  class="rounded-full border px-3 py-1 text-xs font-semibold transition"
                  :class="transactionEntryFilter === option.value
                    ? 'border-[color:var(--horizon-sunset-blue)]/40 bg-[color:var(--horizon-sunset-blue)]/12 text-horizon-white'
                    : 'border-white/[0.08] bg-white/[0.024] text-text-secondary hover:text-horizon-white'"
                  @click="transactionEntryFilter = option.value"
                >
                  {{ option.label }}
                </button>
              </div>
            </div>
            <div class="w-full sm:w-80">
              <label class="sr-only" for="ledger-transaction-search">Search transactions</label>
              <input
                id="ledger-transaction-search"
                v-model="transactionSearch"
                type="search"
                class="hz-input"
                placeholder="Search transactions or operations"
              />
            </div>
          </div>
          <div v-if="filteredTransactions.length" class="mt-4 space-y-3">
            <article
              v-for="transaction in filteredTransactions"
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
                  <div v-if="transaction.is_operation_settlement || transaction.operation_title" class="mt-2 flex flex-wrap items-center gap-2">
                    <span
                      v-if="transaction.is_operation_settlement"
                      class="rounded-full border border-emerald-300/20 bg-emerald-300/10 px-2 py-1 text-[10px] font-bold uppercase tracking-[0.16em] text-emerald-100"
                    >
                      Operation
                    </span>
                    <span
                      v-if="transaction.operation_title"
                      class="rounded-full border border-white/[0.08] bg-white/[0.03] px-2 py-1 text-[10px] font-bold uppercase tracking-[0.16em] text-text-secondary"
                    >
                      {{ transaction.operation_title }}
                    </span>
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

          <div v-if="!filteredTransactions.length" class="mt-4 rounded-[1.25rem] border border-dashed border-white/10 px-4 py-6 text-sm text-text-secondary">
            No transaction entries match that filter yet.
          </div>
        </div>
      </section>

      <section v-else-if="activeTab === 'transfers'" class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="flex flex-col gap-4">
          <div v-if="pendingFundTransfers.length" class="hz-ledger-panel rounded-[1.75rem] p-4 sm:p-5">
            <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Pending Fund Requests</div>
            <div class="mt-4 space-y-3">
              <article
                v-for="transfer in pendingFundTransfers"
                :key="`pending-fund-${transfer.id}`"
                class="hz-ledger-panel-soft rounded-[1.25rem] p-4"
              >
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                  <div>
                    <div class="text-base font-black text-horizon-white">
                      {{ transfer.direction === 'incoming' ? 'Incoming transfer request' : 'Outgoing transfer request' }}
                    </div>
                    <div class="mt-1 text-xs uppercase tracking-[0.14em] text-text-muted">
                      {{ transfer.from_label }} to {{ transfer.to_label }}
                    </div>
                    <div v-if="transfer.description" class="mt-2 text-sm text-text-secondary">
                      {{ transfer.description }}
                    </div>
                    <div v-if="transfer.notes" class="mt-3 whitespace-pre-line text-sm text-text-secondary">
                      {{ transfer.notes }}
                    </div>
                  </div>

                  <div class="text-right">
                    <div class="text-base font-black text-horizon-white">{{ formatMoney(transfer.amount) }}</div>
                    <div class="mt-1 text-xs text-text-muted">{{ formatDate(transfer.created_at) }}</div>
                  </div>
                </div>

                <div class="mt-4 flex flex-wrap items-center justify-between gap-2">
                  <div class="text-xs text-text-muted">
                    Requested by {{ transfer.requested_by_name }}
                  </div>

                  <div class="flex flex-wrap gap-2">
                    <span
                      v-if="!transfer.can_approve"
                      class="rounded-full border border-white/10 bg-white/[0.04] px-3 py-1 text-[10px] font-bold uppercase tracking-[0.16em] text-text-secondary"
                    >
                      Waiting on approval
                    </span>
                    <template v-else>
                      <HorizonButton type="button" size="sm" variant="success" @click="approveFundTransfer(transfer)">
                        Approve
                      </HorizonButton>
                      <HorizonButton type="button" size="sm" variant="danger" @click="rejectFundTransfer(transfer)">
                        Reject
                      </HorizonButton>
                    </template>
                  </div>
                </div>
              </article>
            </div>
          </div>

          <div v-if="pendingInventoryTransfers.length" class="hz-ledger-panel rounded-[1.75rem] p-4 sm:p-5">
            <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Pending Inventory Requests</div>
            <div class="mt-4 space-y-3">
              <article
                v-for="transfer in pendingInventoryTransfers"
                :key="`pending-inventory-${transfer.id}`"
                class="hz-ledger-panel-soft rounded-[1.25rem] p-4"
              >
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                  <div>
                    <div class="text-base font-black text-horizon-white">
                      {{ transfer.item_label || 'Inventory move' }}
                    </div>
                    <div class="mt-1 text-xs uppercase tracking-[0.14em] text-text-muted">
                      {{ transfer.from_label }} to {{ transfer.to_label }}
                    </div>
                    <div class="mt-2 text-sm text-text-secondary">
                      {{ formatLedgerQuantity(transfer.quantity) }} units pending
                    </div>
                    <div v-if="transfer.notes" class="mt-3 whitespace-pre-line text-sm text-text-secondary">
                      {{ transfer.notes }}
                    </div>
                  </div>

                  <div class="text-right">
                    <div class="text-base font-black text-horizon-white">{{ formatLedgerQuantity(transfer.quantity) }}</div>
                    <div class="mt-1 text-xs text-text-muted">{{ formatDate(transfer.created_at) }}</div>
                  </div>
                </div>

                <div class="mt-4 flex flex-wrap items-center justify-between gap-2">
                  <div class="text-xs text-text-muted">
                    Requested by {{ transfer.requested_by_name }}
                  </div>

                  <div class="flex flex-wrap gap-2">
                    <span
                      v-if="!transfer.can_approve"
                      class="rounded-full border border-white/10 bg-white/[0.04] px-3 py-1 text-[10px] font-bold uppercase tracking-[0.16em] text-text-secondary"
                    >
                      Waiting on approval
                    </span>
                    <template v-else>
                      <HorizonButton type="button" size="sm" variant="success" @click="approveInventoryTransfer(transfer)">
                        Approve
                      </HorizonButton>
                      <HorizonButton type="button" size="sm" variant="danger" @click="rejectInventoryTransfer(transfer)">
                        Reject
                      </HorizonButton>
                    </template>
                  </div>
                </div>
              </article>
            </div>
          </div>

          <div v-if="recentFundTransfers.length" class="order-3 hz-ledger-panel rounded-[1.75rem] p-4 sm:p-5">
            <button
              type="button"
              class="flex w-full items-start justify-between gap-4 text-left"
              @click="toggleTransferPanel('recent')"
            >
              <div>
                <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Transfer History</div>
                <h3 class="mt-1 text-xl font-black text-horizon-white">Recent Fund Transfers</h3>
                <p class="mt-2 text-sm text-text-secondary">
                  Review the latest completed and reversed money moves without leaving the transfer tab.
                </p>
              </div>

              <div class="hz-ledger-panel-soft mt-1 rounded-full px-3 py-1 text-[10px] font-bold uppercase tracking-[0.16em] text-text-secondary">
                {{ isTransferPanelOpen('recent') ? 'Close' : 'Open' }}
              </div>
            </button>

            <div v-if="isTransferPanelOpen('recent')" class="mt-4 overflow-hidden rounded-[1.25rem] border border-white/10">
              <article
                v-for="transfer in recentFundTransfers"
                :key="`recent-fund-${transfer.id}`"
                class="border-b border-white/10 px-4 py-3 last:border-b-0"
              >
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                  <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                      <div class="truncate text-sm font-black text-horizon-white">
                        {{ transfer.description || 'Completed transfer' }}
                      </div>
                      <span class="rounded-full border border-white/10 bg-white/[0.04] px-2 py-0.5 text-[10px] font-bold uppercase tracking-[0.16em] text-text-secondary">
                        {{ transfer.status === 'reversed' ? 'Reversed' : 'Completed' }}
                      </span>
                    </div>
                    <div class="mt-1 text-xs text-text-muted">
                      {{ transfer.from_label }} to {{ transfer.to_label }} • {{ formatDate(transfer.completed_at || transfer.created_at) }}
                    </div>
                    <div v-if="transfer.notes" class="mt-2 line-clamp-2 text-sm text-text-secondary">
                      {{ transfer.notes }}
                    </div>
                  </div>

                  <div class="flex flex-wrap items-center justify-between gap-3 lg:justify-end">
                    <div class="text-sm font-black text-horizon-white">{{ formatMoney(transfer.amount) }}</div>
                    <HorizonButton
                      v-if="transfer.can_reverse"
                      type="button"
                      size="sm"
                      variant="danger"
                      @click="reverseFundTransfer(transfer)"
                    >
                      Reverse
                    </HorizonButton>
                  </div>
                </div>
              </article>
            </div>
          </div>

          <HorizonDrawer v-if="canTransferFunds && fundTransferDrawerOpen" close-label="Close fund transfer drawer" @close="closeFundTransferDrawer">
            <template #header>
              <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Move Funds</div>
              <h3 class="mt-1 text-xl font-black text-horizon-white">Funds Transfer</h3>
              <p class="mt-2 text-sm text-text-secondary">
                Send funds out of {{ ledger.account?.name }} as an approval request. Horizon writes the matching in and out records after the destination approves it.
              </p>
            </template>

            <form class="space-y-4" @submit.prevent="submitTransfer">
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
                searchable
                search-placeholder="Search people, squadrons, or treasury"
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
          </HorizonDrawer>

          <div v-if="canTransferFunds || canTransferInventory" class="order-2 grid gap-4 lg:grid-cols-2">
          <div v-if="canTransferFunds" class="hz-ledger-panel rounded-[1.75rem] p-4 sm:p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
              <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Move Funds</div>
              <HorizonButton type="button" size="sm" @click="openFundTransferDrawer">
                New Fund Transfer
              </HorizonButton>
            </div>

            <h3 class="mt-1 text-xl font-black text-horizon-white">Funds Transfer</h3>
            <p class="mt-2 text-sm text-text-secondary">
              Send funds out of {{ ledger.account?.name }} as an approval request. Horizon writes the matching in and out records after the destination approves it.
            </p>
          </div>

          <HorizonDrawer v-if="canTransferInventory && inventoryTransferDrawerOpen" close-label="Close inventory transfer drawer" @close="closeInventoryTransferDrawer">
            <template #header>
              <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Move Inventory</div>
              <h3 class="mt-1 text-xl font-black text-horizon-white">Inventory Transfer</h3>
              <p class="mt-2 text-sm text-text-secondary">
                Move tracked cargo, components, or gear into another ledger as an approval request while keeping the original cycle history attached to the item.
              </p>
            </template>

            <form class="space-y-4" @submit.prevent="submitInventoryTransfer">
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
                searchable
                search-placeholder="Search people, squadrons, or treasury"
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
          </HorizonDrawer>

          <div v-if="canTransferInventory" class="hz-ledger-panel rounded-[1.75rem] p-4 sm:p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
              <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Move Inventory</div>
              <HorizonButton type="button" size="sm" @click="openInventoryTransferDrawer">
                New Inventory Move
              </HorizonButton>
            </div>

            <h3 class="mt-1 text-xl font-black text-horizon-white">Inventory Transfer</h3>
            <p class="mt-2 text-sm text-text-secondary">
              Move tracked cargo, components, or gear into another ledger as an approval request while keeping the original cycle history attached to the item.
            </p>
          </div>
          </div>

          <div v-if="!canTransferFunds && !canTransferInventory" class="order-2 hz-ledger-panel rounded-[1.75rem] p-4 sm:p-5">
            <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Transfers Locked</div>
            <h3 class="mt-1 text-xl font-black text-horizon-white">{{ isHistoryView ? 'Archived Cycle View' : 'No Available Destinations' }}</h3>
            <p class="mt-2 text-sm text-text-secondary">
              {{ isHistoryView
                ? 'Transfers are locked while you are viewing archived cycle history. Switch back to the current cycle to move funds or inventory.'
                : 'No eligible destinations are available for this ledger right now.' }}
            </p>
          </div>
        </div>

        <div class="hz-ledger-panel rounded-[1.75rem] p-4 sm:p-5">
          <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">How Transfers Work</div>
          <div class="mt-4 space-y-3 text-sm text-text-secondary">
            <p>Every transfer writes a matched expense in the source ledger and income in the destination ledger.</p>
            <p>Some destinations need approval before the move lands, so pending requests stay visible here until someone accepts or rejects them.</p>
            <p>Inventory moves can transfer the full stack or just part of it. Partial moves split the quantity into a new destination record and lock the transfer chain afterward.</p>
            <p>Fund transfer records and their reversals stay locked so the two books never drift out of sync.</p>
            <p>Use clear notes so both sides of the movement still make sense later when you review the cycle history.</p>
          </div>
        </div>
      </section>

      <section v-else-if="activeTab === 'trades'" class="space-y-4">
        <HorizonDrawer v-if="canEditCurrentView && tradeDrawerOpen" close-label="Close trade editor drawer" @close="closeTradeDrawer">
          <template #header>
            <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">{{ tradeModeLabel }}</div>
            <h3 class="mt-1 text-xl font-black text-horizon-white">Commodity Run</h3>
            <p class="mt-2 text-sm text-text-secondary">
              {{ editingTradeId ? 'Adjust the route, prices, or ship assignment for this run.' : 'Record buy and sell legs so Horizon can keep your cargo profit clean.' }}
            </p>
          </template>

          <form class="space-y-4" @submit.prevent="submitTrade">
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
                @click="closeTradeDrawer"
              >
                Cancel Edit
              </HorizonButton>

              <HorizonButton type="submit" :disabled="tradeForm.processing">
                {{ editingTradeId ? 'Update Trade' : 'Log Trade' }}
              </HorizonButton>
            </div>
          </form>
        </HorizonDrawer>

        <aside v-if="!canEditCurrentView" class="hz-ledger-panel rounded-[1.75rem] p-4 sm:p-5">
          <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Read Only</div>
          <h3 class="mt-1 text-xl font-black text-horizon-white">{{ readOnlySectionContent('Trade', 'trade runs').heading }}</h3>
          <p class="mt-2 text-sm text-text-secondary">
            {{ readOnlySectionContent('Trade', 'trade runs').body }}
          </p>
        </aside>

        <div class="hz-ledger-panel rounded-[1.75rem] p-4 sm:p-5">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Trade Runs</div>
            <HorizonButton v-if="canEditCurrentView" type="button" size="sm" @click="openTradeDrawer">
              New Trade
            </HorizonButton>
          </div>
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

      <section v-else-if="activeTab === 'inventory'" class="space-y-4">
        <HorizonDrawer v-if="canEditCurrentView && inventoryDrawerOpen" close-label="Close inventory editor drawer" @close="closeInventoryDrawer">
          <template #header>
            <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">{{ inventoryModeLabel }}</div>
            <h3 class="mt-1 text-xl font-black text-horizon-white">Tracked Items</h3>
            <p class="mt-2 text-sm text-text-secondary">
              {{ editingInventoryItemId ? 'Refine the item source, quantity, value, or assigned ship.' : 'Track weapons, components, commodities, and custom stash records against a cycle.' }}
            </p>
          </template>

          <form class="space-y-4" @submit.prevent="submitInventory">
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
                @click="closeInventoryDrawer"
              >
                Cancel Edit
              </HorizonButton>

              <HorizonButton type="submit" :disabled="inventoryForm.processing">
                {{ editingInventoryItemId ? 'Update Inventory' : 'Save Inventory' }}
              </HorizonButton>
            </div>
          </form>
        </HorizonDrawer>

        <aside v-if="!canEditCurrentView" class="hz-ledger-panel rounded-[1.75rem] p-4 sm:p-5">
          <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Read Only</div>
          <h3 class="mt-1 text-xl font-black text-horizon-white">{{ readOnlySectionContent('Inventory', 'inventory records').heading }}</h3>
          <p class="mt-2 text-sm text-text-secondary">
            {{ readOnlySectionContent('Inventory', 'inventory records').body }}
          </p>
        </aside>

        <div class="hz-ledger-panel rounded-[1.75rem] p-4 sm:p-5">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Inventory Records</div>
            <HorizonButton v-if="canEditCurrentView" type="button" size="sm" @click="openInventoryDrawer">
              New Inventory
            </HorizonButton>
          </div>
          <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div class="space-y-3">
              <div class="text-sm text-text-secondary">
                {{ inventoryResultsLabel }}
              </div>
              <div class="flex flex-wrap gap-2">
                <button
                  v-for="option in operationEntryFilterOptions"
                  :key="`inventory-filter-${option.value}`"
                  type="button"
                  class="rounded-full border px-3 py-1 text-xs font-semibold transition"
                  :class="inventoryEntryFilter === option.value
                    ? 'border-[color:var(--horizon-sunset-blue)]/40 bg-[color:var(--horizon-sunset-blue)]/12 text-horizon-white'
                    : 'border-white/[0.08] bg-white/[0.024] text-text-secondary hover:text-horizon-white'"
                  @click="inventoryEntryFilter = option.value"
                >
                  {{ option.label }}
                </button>
              </div>
            </div>
            <div class="w-full sm:w-80">
              <label class="sr-only" for="ledger-inventory-search">Search inventory</label>
              <input
                id="ledger-inventory-search"
                v-model="inventorySearch"
                type="search"
                class="hz-input"
                placeholder="Search inventory"
              />
            </div>
          </div>

          <div v-if="paginatedInventoryItems.length" class="mt-4 space-y-3">
            <article
              v-for="item in paginatedInventoryItems"
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
                  <div v-if="item.is_operation_settlement || item.operation_title" class="mt-2 flex flex-wrap items-center gap-2">
                    <span
                      v-if="item.is_operation_settlement"
                      class="rounded-full border border-emerald-300/20 bg-emerald-300/10 px-2 py-1 text-[10px] font-bold uppercase tracking-[0.16em] text-emerald-100"
                    >
                      Operation Loot
                    </span>
                    <span
                      v-if="item.operation_title"
                      class="rounded-full border border-white/[0.08] bg-white/[0.03] px-2 py-1 text-[10px] font-bold uppercase tracking-[0.16em] text-text-secondary"
                    >
                      {{ item.operation_title }}
                    </span>
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
                  <div v-if="item.provenance_locked" class="mt-2">
                    <span class="rounded-full border border-amber-300/20 bg-amber-300/10 px-2 py-1 text-[10px] font-bold uppercase tracking-[0.16em] text-amber-100">
                      {{ item.is_operation_settlement ? 'Operation receipt' : 'Transfer locked' }}
                    </span>
                  </div>
                  <div v-if="item.estimated_value_source === 'uex_estimate'" class="mt-2">
                    <span class="rounded-full border border-sky-300/20 bg-sky-300/10 px-2 py-1 text-[10px] font-bold uppercase tracking-[0.16em] text-sky-100">
                      UEX estimate
                    </span>
                  </div>
                </div>
              </div>

              <div v-if="canEditCurrentView" class="mt-4 flex flex-wrap justify-end gap-2">
                <HorizonButton v-if="!item.provenance_locked" type="button" size="sm" variant="ghost" @click="startInventoryEdit(item)">
                  Edit
                </HorizonButton>
                <HorizonButton type="button" size="sm" variant="ghost" @click="duplicateInventoryItem(item)">
                  Duplicate
                </HorizonButton>
                <HorizonButton
                  v-if="!item.provenance_locked"
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

          <div v-else class="mt-4 rounded-[1.25rem] border border-dashed border-white/10 px-4 py-6 text-sm text-text-secondary">
            No inventory records match that filter yet.
          </div>

          <div v-if="inventoryPageCount > 1" class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-white/10 pt-4">
            <div class="text-xs uppercase tracking-[0.14em] text-text-muted">
              Page {{ inventoryPage }} of {{ inventoryPageCount }}
            </div>
            <div class="flex flex-wrap gap-2">
              <HorizonButton
                type="button"
                size="sm"
                variant="ghost"
                :disabled="inventoryPage === 1"
                @click="inventoryPage = Math.max(1, inventoryPage - 1)"
              >
                Previous
              </HorizonButton>
              <HorizonButton
                type="button"
                size="sm"
                variant="ghost"
                :disabled="inventoryPage === inventoryPageCount"
                @click="inventoryPage = Math.min(inventoryPageCount, inventoryPage + 1)"
              >
                Next
              </HorizonButton>
            </div>
          </div>
        </div>
      </section>

      <section v-else-if="activeTab === 'ships'" class="space-y-4">
        <HorizonDrawer v-if="canEditCurrentView && shipDrawerOpen" close-label="Close ship editor drawer" @close="closeShipDrawer">
          <template #header>
            <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">{{ shipModeLabel }}</div>
            <h3 class="mt-1 text-xl font-black text-horizon-white">Tracked Ships</h3>
            <p class="mt-2 text-sm text-text-secondary">
              {{ editingShipId ? 'Update the hull, status, or hangar notes for this asset.' : 'Keep a cycle-aware list of owned, pledged, loaned, or rented ships.' }}
            </p>
          </template>

          <form class="space-y-4" @submit.prevent="submitShip">
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
              v-if="selectedShipReference?.image_url"
              class="hz-ledger-panel-soft overflow-hidden rounded-[1rem] border border-white/[0.08]"
            >
              <img
                :src="selectedShipReference.image_url"
                :alt="selectedShipReference.name"
                class="h-48 w-full object-cover"
                loading="lazy"
                referrerpolicy="no-referrer"
              />
              <div class="flex items-center justify-between gap-3 px-3 py-2 text-xs text-text-secondary">
                <div class="flex min-w-0 items-center gap-3">
                  <span class="truncate font-semibold text-horizon-white">{{ selectedShipReference.name }}</span>
                  <span v-if="selectedShipReference.type" class="shrink-0">{{ selectedShipReference.type }}</span>
                </div>
                <a
                  :href="selectedShipReference.image_url"
                  target="_blank"
                  rel="noreferrer"
                  class="shrink-0 text-sky-200 underline decoration-sky-300/40 underline-offset-2 transition hover:text-sky-100"
                >
                  Open image
                </a>
              </div>
            </div>

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
                @click="closeShipDrawer"
              >
                Cancel Edit
              </HorizonButton>

              <HorizonButton type="submit" :disabled="shipForm.processing">
                {{ editingShipId ? 'Update Ship' : 'Save Ship' }}
              </HorizonButton>
            </div>
          </form>
        </HorizonDrawer>

        <aside v-if="!canEditCurrentView" class="hz-ledger-panel rounded-[1.75rem] p-4 sm:p-5">
          <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Read Only</div>
          <h3 class="mt-1 text-xl font-black text-horizon-white">{{ readOnlySectionContent('Fleet', 'ship assets').heading }}</h3>
          <p class="mt-2 text-sm text-text-secondary">
            {{ readOnlySectionContent('Fleet', 'ship assets').body }}
          </p>
        </aside>

        <div class="hz-ledger-panel rounded-[1.75rem] p-4 sm:p-5">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Ship Assets</div>
            <HorizonButton v-if="canEditCurrentView" type="button" size="sm" @click="openShipDrawer">
              New Ship
            </HorizonButton>
          </div>
          <div class="mt-4 grid gap-3 md:grid-cols-2">
            <article
              v-for="ship in ledger.shipAssets ?? []"
              :key="ship.id"
              class="hz-ledger-panel-soft rounded-[1.25rem] p-4"
              :class="editingShipId === ship.id
                ? 'border-[color:var(--horizon-sunset-blue)]/40'
                : 'border-white/[0.055]'"
            >
              <img
                v-if="shipImageUrlForVehicle(ship.vehicle_uex_id)"
                :src="shipImageUrlForVehicle(ship.vehicle_uex_id)"
                :alt="ship.ship_name"
                class="mb-3 h-40 w-full rounded-[1rem] object-cover"
                loading="lazy"
                referrerpolicy="no-referrer"
              />
              <div v-if="shipImageUrlForVehicle(ship.vehicle_uex_id)" class="mb-3">
                <a
                  :href="shipImageUrlForVehicle(ship.vehicle_uex_id)"
                  target="_blank"
                  rel="noreferrer"
                  class="text-xs font-semibold text-sky-200 underline decoration-sky-300/40 underline-offset-2 transition hover:text-sky-100"
                >
                  Open image
                </a>
              </div>
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
        <div class="hz-ledger-panel rounded-[1.75rem] p-5 xl:col-span-2">
          <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div>
              <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Report Snapshot</div>
              <h3 class="mt-1 text-xl font-black text-horizon-white">A cleaner read on how this ledger is moving</h3>
            </div>
            <div class="max-w-2xl text-sm text-text-secondary">
              These visuals stay tied to the current ledger view, so swapping cycles or opening squadron and organization books changes the report story without sending you to a separate dashboard.
            </div>
          </div>

          <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div
              v-for="card in reportSummaryCards"
              :key="card.label"
              class="hz-ledger-panel-soft rounded-[1.25rem] px-4 py-4"
            >
              <div class="text-[11px] font-bold uppercase tracking-[0.18em] text-text-muted">{{ card.label }}</div>
              <div class="mt-2 text-2xl font-black text-horizon-white">{{ card.value }}</div>
              <div class="mt-2 text-sm text-text-secondary">{{ card.detail }}</div>
            </div>
          </div>
        </div>

        <div class="hz-ledger-panel rounded-[1.75rem] p-5">
          <div class="flex items-center justify-between gap-3">
            <div>
              <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Profit / Loss By Cycle</div>
              <div class="mt-1 text-sm text-text-secondary">Which cycles pulled the most weight in this view.</div>
            </div>
            <div class="rounded-full border border-white/10 px-3 py-1 text-xs font-bold uppercase tracking-[0.14em] text-text-secondary">
              {{ profitLossReportRows.length }} cycles
            </div>
          </div>

          <div v-if="profitLossReportRows.length" class="mt-5 grid gap-5 lg:grid-cols-[minmax(0,1.25fr)_18rem]">
            <div class="hz-ledger-panel-soft rounded-[1.5rem] p-4">
              <div class="flex h-56 items-end gap-3">
                <div
                  v-for="row in profitLossReportRows"
                  :key="`profit-chart-${row.label}`"
                  class="flex min-w-0 flex-1 flex-col justify-end"
                >
                  <div class="px-1 pb-2 text-center text-[11px] font-bold tracking-[0.04em]" :class="row.value >= 0 ? 'text-emerald-200' : 'text-rose-200'">
                    {{ formatCompactMoney(Math.abs(row.value)) }}
                  </div>
                  <div
                    class="w-full rounded-t-[1rem] transition-all"
                    :style="{
                      height: buildBarHeight(row.value, Math.max(...profitLossReportRows.map((entry) => Math.abs(entry.value)), 1)),
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
                v-for="row in profitLossReportRows"
                :key="`profit-list-${row.label}`"
                class="hz-ledger-panel-soft rounded-[1.25rem] px-4 py-3"
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
                    :style="{
                      width: buildBarWidth(row.share),
                      background: row.value >= 0 ? 'rgba(52, 211, 153, 0.88)' : 'rgba(251, 113, 133, 0.88)',
                    }"
                  />
                </div>
              </div>
            </div>
          </div>

          <div v-else class="mt-5 hz-ledger-panel-soft rounded-[1.5rem] px-4 py-8 text-sm text-text-secondary">
            No cycle profit history is available for this ledger view yet.
          </div>
        </div>

        <div class="hz-ledger-panel rounded-[1.75rem] p-5">
          <div class="flex items-center justify-between gap-3">
            <div>
              <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Income By Source</div>
              <div class="mt-1 text-sm text-text-secondary">Where your logged inflow is actually coming from.</div>
            </div>
            <div class="rounded-full border border-white/10 px-3 py-1 text-xs font-bold uppercase tracking-[0.14em] text-text-secondary">
              {{ formatMoney(sumReportValues(incomeSourceReportRows)) }}
            </div>
          </div>

          <div v-if="incomeSourceReportRows.length" class="mt-5 grid gap-5 lg:grid-cols-[15rem_minmax(0,1fr)] lg:items-center">
            <div class="flex items-center justify-center">
              <div class="relative flex h-44 w-44 items-center justify-center rounded-full border border-white/8 p-3">
                <div class="absolute inset-3 rounded-full" :style="buildDonutStyle(incomeSourceReportRows)" />
                <div class="absolute inset-[2.35rem] rounded-full bg-[#07111f]/95 ring-1 ring-white/5" />
                <div class="relative text-center">
                  <div class="text-[11px] font-bold uppercase tracking-[0.18em] text-text-muted">Income</div>
                  <div class="mt-2 text-2xl font-black text-horizon-white">{{ formatCompactMoney(sumReportValues(incomeSourceReportRows)) }}</div>
                  <div class="text-xs text-text-secondary">aUEC tracked</div>
                </div>
              </div>
            </div>

            <div class="space-y-3">
              <div
                v-for="row in incomeSourceReportRows"
                :key="`income-${row.label}`"
                class="hz-ledger-panel-soft rounded-[1.25rem] px-4 py-3"
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
                <div class="mt-2 text-[11px] font-bold uppercase tracking-[0.14em] text-text-muted">
                  {{ Math.round(row.share * 100) }}% of tracked income
                </div>
              </div>
            </div>
          </div>

          <div v-else class="mt-5 hz-ledger-panel-soft rounded-[1.5rem] px-4 py-8 text-sm text-text-secondary">
            No income-source breakdown exists for this view yet.
          </div>
        </div>

        <div class="hz-ledger-panel rounded-[1.75rem] p-5">
          <div class="flex items-center justify-between gap-3">
            <div>
              <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Trade Profit By Commodity</div>
              <div class="mt-1 text-sm text-text-secondary">The cargo that is actually paying off right now.</div>
            </div>
            <div class="rounded-full border border-white/10 px-3 py-1 text-xs font-bold uppercase tracking-[0.14em] text-text-secondary">
              {{ formatMoney(sumReportValues(tradeCommodityReportRows)) }}
            </div>
          </div>

          <div v-if="tradeCommodityReportRows.length" class="mt-5 space-y-3">
            <div
              v-for="row in tradeCommodityReportRows"
              :key="`commodity-${row.label}`"
              class="hz-ledger-panel-soft rounded-[1.25rem] px-4 py-4"
            >
              <div class="flex items-center justify-between gap-3">
                <div class="text-sm font-bold text-horizon-white">{{ row.label }}</div>
                <div class="text-sm text-cyan-100">{{ formatMoney(row.value) }}</div>
              </div>
              <div class="mt-3 h-3 overflow-hidden rounded-full bg-white/6">
                <div
                  class="h-full rounded-full"
                  :style="{
                    width: buildBarWidth(row.share),
                    background: `linear-gradient(90deg, ${row.color}, rgba(255, 255, 255, 0.65))`,
                  }"
                />
              </div>
              <div class="mt-2 flex items-center justify-between text-[11px] font-bold uppercase tracking-[0.14em] text-text-muted">
                <span>{{ Math.round(row.share * 100) }}% of commodity profit</span>
                <span>{{ formatCompactMoney(row.value) }} aUEC</span>
              </div>
            </div>
          </div>

          <div v-else class="mt-5 hz-ledger-panel-soft rounded-[1.5rem] px-4 py-8 text-sm text-text-secondary">
            No trade-profit breakout is available for this view yet.
          </div>
        </div>

        <div class="hz-ledger-panel rounded-[1.75rem] p-5">
          <div class="flex items-center justify-between gap-3">
            <div>
              <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Inventory Value By Category</div>
              <div class="mt-1 text-sm text-text-secondary">A quick feel for where your stored value is sitting.</div>
            </div>
            <div class="rounded-full border border-white/10 px-3 py-1 text-xs font-bold uppercase tracking-[0.14em] text-text-secondary">
              {{ formatMoney(sumReportValues(inventoryCategoryReportRows)) }}
            </div>
          </div>

          <div v-if="inventoryCategoryReportRows.length" class="mt-5 grid gap-5 lg:grid-cols-[15rem_minmax(0,1fr)] lg:items-center">
            <div class="flex items-center justify-center">
              <div class="relative flex h-44 w-44 items-center justify-center rounded-full border border-white/8 p-3">
                <div class="absolute inset-3 rounded-full" :style="buildDonutStyle(inventoryCategoryReportRows)" />
                <div class="absolute inset-[2.35rem] rounded-full bg-[#07111f]/95 ring-1 ring-white/5" />
                <div class="relative text-center">
                  <div class="text-[11px] font-bold uppercase tracking-[0.18em] text-text-muted">Inventory</div>
                  <div class="mt-2 text-2xl font-black text-horizon-white">{{ formatCompactMoney(sumReportValues(inventoryCategoryReportRows)) }}</div>
                  <div class="text-xs text-text-secondary">aUEC value</div>
                </div>
              </div>
            </div>

            <div class="space-y-3">
              <div
                v-for="row in inventoryCategoryReportRows"
                :key="`inventory-${row.label}`"
                class="hz-ledger-panel-soft rounded-[1.25rem] px-4 py-3"
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
                <div class="mt-2 text-[11px] font-bold uppercase tracking-[0.14em] text-text-muted">
                  {{ Math.round(row.share * 100) }}% of valued inventory
                </div>
              </div>
            </div>
          </div>

          <div v-else class="mt-5 hz-ledger-panel-soft rounded-[1.5rem] px-4 py-8 text-sm text-text-secondary">
            No inventory valuation breakdown is available for this view yet.
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
