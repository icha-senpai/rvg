<script setup>
import { computed, ref, onMounted, onBeforeUnmount, watch, nextTick } from 'vue'
import HorizonContainer from '@/Components/HorizonContainer.vue'

import UsersPanel from './Partials/UsersPanel.vue'
import SquadronsPanel from './Partials/SquadronsPanel.vue'
import RolesPanel from './Partials/RolesPanel.vue'
import MediaPanel from './Partials/MediaPanel.vue'
import OperationsPanel from './Partials/OperationsPanel.vue'
import LedgerPanel from './Partials/LedgerPanel.vue'
import UexPanel from './Partials/UexPanel.vue'

const props = defineProps({
  users: Object,
  squadrons: Array,
  roles: Array,
  operations: {
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
  canceledOperations: {
    type: Array,
    default: () => [],
  },
  verifiedMembers: {
    type: Array,
    default: () => [],
  },
  filters: Object,
  eligibleLeaders: Array,
  archiveStats: { type: Object, default: () => ({}) },
  ledger: { type: Object, default: () => ({}) },
  uex: { type: Object, default: () => ({}) },
})

const allowedTabs = new Set(['users', 'squadrons', 'roles', 'media', 'operations', 'ledger', 'uex'])
const activeTabStorageKey = 'adminDashboard.activeTab'

const archiveSummary = computed(() => ({
  topics: props.archiveStats?.topics ?? 0,
  entries: props.archiveStats?.entries ?? 0,
  categories: props.archiveStats?.categories ?? 0,
  tags: props.archiveStats?.tags ?? 0,
  trashTotal: props.archiveStats?.trash_total ?? 0,
}))

const tabItems = computed(() => [
  {
    key: 'users',
    label: 'Users',
    eyebrow: 'Personnel',
    description: 'Manage verification, ranks, roles, and member access.',
    count: props.users?.total ?? props.users?.data?.length ?? 0,
    tone: 'blue',
  },
  {
    key: 'squadrons',
    label: 'Squadrons',
    eyebrow: 'Units',
    description: 'Manage squadron records, command assignments, and rosters.',
    count: props.squadrons?.length ?? 0,
    tone: 'indigo',
  },
  {
    key: 'roles',
    label: 'Roles',
    eyebrow: 'Access',
    description: 'Review and manage platform roles and permission structure.',
    count: props.roles?.length ?? 0,
    tone: 'magenta',
  },
  {
    key: 'operations',
    label: 'Operations',
    eyebrow: 'Lifecycle',
    description: 'Review completed operations, maintain After Action Reports, and inspect canceled operations with reasons.',
    count: (props.operations?.length ?? 0) + (props.canceledOperations?.length ?? 0),
    tone: 'emerald',
  },
  {
    key: 'media',
    label: 'Media',
    eyebrow: 'Library',
    description: 'Inspect uploaded media and administrative media tools.',
    count: null,
    tone: 'cyan',
  },
  {
    key: 'ledger',
    label: 'Ledger',
    eyebrow: 'Economy',
    description: 'Manage cycle controls, rollout state, and leadership ledger tools.',
    count: props.ledger?.wipeCycles?.length ?? 0,
    tone: 'indigo',
  },
  {
    key: 'uex',
    label: 'UEX',
    eyebrow: 'External Data',
    description: 'Review UEX sync status, snapshot coverage, and synced dataset tools.',
    count: props.uex?.total_rows ?? 0,
    tone: 'amber',
  },
])

const currentTab = computed(() => {
  return tabItems.value.find(tab => tab.key === activeTab.value) ?? tabItems.value[0]
})

function getTabFromUrl() {
  const params = new URLSearchParams(window.location.search)
  const tab = params.get('tab')

  return allowedTabs.has(tab) ? tab : null
}

function setTabInUrl(tab) {
  const url = new URL(window.location.href)

  if (tab && allowedTabs.has(tab)) {
    url.searchParams.set('tab', tab)
  } else {
    url.searchParams.delete('tab')
  }

  window.history.replaceState({}, '', url)
}

function getInitialTab() {
  const urlTab = getTabFromUrl()
  if (urlTab) return urlTab

  const saved = window.sessionStorage.getItem(activeTabStorageKey)
  if (allowedTabs.has(saved)) return saved

  return 'users'
}

const activeTab = ref(getInitialTab())

const scrollStorageKeyForTab = (tab) => `adminDashboard.scrollY.${tab}`
let scrollDebounceId = null

function persistScrollPosition() {
  if (scrollDebounceId) return

  scrollDebounceId = window.setTimeout(() => {
    scrollDebounceId = null
    window.sessionStorage.setItem(scrollStorageKeyForTab(activeTab.value), String(window.scrollY || 0))
  }, 50)
}

function restoreScrollPosition() {
  const raw = window.sessionStorage.getItem(scrollStorageKeyForTab(activeTab.value))
  const y = raw ? Number(raw) : 0

  if (!Number.isFinite(y) || y <= 0) return

  nextTick(() => {
    window.scrollTo({
      top: y,
      left: 0,
      behavior: 'auto',
    })
  })
}

watch(
  () => activeTab.value,
  (tab, prevTab) => {
    window.sessionStorage.setItem(activeTabStorageKey, tab)
    setTabInUrl(tab)

    if (prevTab) {
      window.sessionStorage.setItem(scrollStorageKeyForTab(prevTab), String(window.scrollY || 0))
    }

    restoreScrollPosition()
  },
  { immediate: true }
)

onMounted(() => {
  restoreScrollPosition()
  window.addEventListener('scroll', persistScrollPosition, { passive: true })
})

onBeforeUnmount(() => {
  window.removeEventListener('scroll', persistScrollPosition)

  if (scrollDebounceId) {
    clearTimeout(scrollDebounceId)
    scrollDebounceId = null
  }
})
</script>

<template>
  <HorizonContainer class="py-8 md:py-10">
    <div class="mx-auto max-w-7xl">
      <section class="hz-surface-welcome relative overflow-hidden rounded-[2rem] border border-white/[0.055] p-4 md:p-5 xl:p-6">
        <div class="pointer-events-none absolute inset-0 opacity-40">
          <div class="absolute left-8 top-0 h-px w-48 bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-blue)] to-transparent"></div>
          <div class="absolute bottom-0 right-10 h-px w-64 bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-magenta)] to-transparent"></div>
        </div>

        <div class="relative grid gap-6 xl:grid-cols-[minmax(0,1fr)_auto] xl:items-end">
          <div>
            <div class="text-xs font-bold uppercase tracking-[0.28em] text-[color:var(--horizon-text-secondary)]">
              Horizon Administrative Command
            </div>

            <h1 class="mt-2 text-3xl font-black tracking-tight text-horizon-white md:text-4xl">
              Admin Dashboard
            </h1>

            <p class="mt-3 max-w-3xl text-sm text-text-secondary md:text-base">
              Manage people, access, operations, ledger tooling, and UEX syncs from one calmer control surface.
            </p>

            <div class="mt-4 flex flex-wrap gap-2">
              <span class="rounded-full border border-white/[0.055] bg-white/[0.042] px-3 py-1 text-xs font-semibold text-[color:var(--horizon-text-primary)]">
                {{ props.users?.total ?? props.users?.data?.length ?? 0 }} Users
              </span>

              <span class="rounded-full border border-white/[0.055] bg-white/[0.042] px-3 py-1 text-xs font-semibold text-[color:var(--horizon-text-primary)]">
                {{ props.squadrons?.length ?? 0 }} Squadrons
              </span>

              <span class="rounded-full border border-white/[0.055] bg-white/[0.042] px-3 py-1 text-xs font-semibold text-[color:var(--horizon-text-primary)]">
                {{ props.roles?.length ?? 0 }} Roles
              </span>

              <span class="rounded-full border border-white/[0.055] bg-white/[0.024] px-3 py-1 text-xs font-semibold text-[color:var(--horizon-text-primary)]">
                {{ archiveSummary.entries }} Archive Entries
              </span>
            </div>

          </div>

          <div class="rounded-2xl border border-white/[0.055] bg-white/[0.035] px-4 py-3 xl:min-w-[15rem] xl:text-right">
            <div class="text-xs font-bold uppercase tracking-[0.2em] text-text-muted">
              Active Console
            </div>

            <div class="mt-1 text-2xl font-black text-horizon-white">
              {{ currentTab.label }}
            </div>

            <div class="mt-1 text-xs text-text-secondary">
              {{ currentTab.eyebrow }}
            </div>
          </div>
        </div>

        <div class="relative mt-6">
          <section class="rounded-[1.5rem] border border-white/[0.055] bg-white/[0.018] p-4 md:p-5">
            <div class="mb-5 flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
              <div>
                <div class="text-xs font-bold uppercase tracking-[0.22em] text-[color:var(--horizon-text-secondary)]">
                  {{ currentTab.eyebrow }} Console
                </div>

                <h2 class="mt-1 text-xl font-black text-horizon-white">
                  {{ currentTab.label }}
                </h2>

                <p class="mt-1 text-sm text-text-secondary">
                  {{ currentTab.description }}
                </p>
              </div>

              <div class="rounded-full border border-white/[0.055] bg-white/[0.024] px-3 py-1 text-xs font-bold uppercase tracking-[0.16em] text-text-muted">
                {{ activeTab }}
              </div>
            </div>

            <div
              v-if="activeTab === 'users'"
              class="hz-animate-fade"
            >
              <UsersPanel
                :users="users"
                :roles="roles"
                :filters="filters"
              />
            </div>

            <div
              v-if="activeTab === 'squadrons'"
              class="hz-animate-fade"
            >
              <SquadronsPanel
                :squadrons="squadrons"
                :eligible-leaders="eligibleLeaders"
              />
            </div>

            <div
              v-if="activeTab === 'roles'"
              class="hz-animate-fade"
            >
              <RolesPanel :roles="roles" />
            </div>

            <div
              v-if="activeTab === 'operations'"
              class="hz-animate-fade"
            >
              <OperationsPanel
                :operations="operations"
                :operation-settlement-loot-options="operationSettlementLootOptions"
                :canceled-operations="canceledOperations"
                :verified-members="verifiedMembers"
              />
            </div>

            <div
              v-if="activeTab === 'media'"
              class="hz-animate-fade"
            >
              <MediaPanel />
            </div>

            <div
              v-if="activeTab === 'ledger'"
              class="hz-animate-fade"
            >
              <LedgerPanel :ledger="ledger" />
            </div>

            <div
              v-if="activeTab === 'uex'"
              class="hz-animate-fade"
            >
              <UexPanel :uex="uex" />
            </div>
          </section>
        </div>
      </section>
    </div>
  </HorizonContainer>
</template>
