<script setup>
import { computed, ref, onMounted, onBeforeUnmount, watch, nextTick } from 'vue'
import { Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

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
    description: 'Manage wipe cycles, rollout state, and the Horizon Ledger module foundation.',
    count: props.ledger?.wipeCycles?.length ?? 0,
    tone: 'indigo',
  },
  {
    key: 'uex',
    label: 'UEX',
    eyebrow: 'External Data',
    description: 'Review local UEX snapshot coverage, last sync status, and the exact commands used to refresh the dataset.',
    count: props.uex?.total_rows ?? 0,
    tone: 'amber',
  },
])

const commandLinks = computed(() => [
  {
    label: 'Archive Management',
    eyebrow: 'Knowledge Hub',
    description: 'Create topics, manage entries, and control rank-gated archive visibility.',
    href: route('admin.archive.index'),
    tone: 'magenta',
    stat: `${archiveSummary.value.topics} topics`,
  },
  {
    label: 'Archive Trash',
    eyebrow: 'Recovery Bay',
    description: 'Restore soft-deleted Archive content or permanently purge old records.',
    href: route('admin.archive.trash.index'),
    tone: 'danger',
    stat: `${archiveSummary.value.trashTotal} trashed`,
  },
  {
    label: 'Archive Audit',
    eyebrow: 'Activity Log',
    description: 'Review create, update, delete, restore, and purge events for Archive content.',
    href: route('admin.archive.audit.index'),
    tone: 'indigo',
    stat: 'Logs',
  },
  {
    label: 'Public Archive',
    eyebrow: 'Member View',
    description: 'Open the member-facing Archive exactly as verified users see it.',
    href: route('archive.index'),
    tone: 'blue',
    stat: `${archiveSummary.value.entries} entries`,
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

function tabCardClass(tab) {
    if (activeTab.value === tab.key) {
      switch (tab.tone) {
        case 'magenta':
          return 'border-white/[0.055] bg-white/[0.042] '
        case 'indigo':
          return 'border-white/[0.055] bg-white/[0.042] '
        case 'emerald':
          return 'border-emerald-300/35 bg-emerald-300/10 '
        case 'amber':
          return 'border-amber-300/35 bg-amber-300/10 '
        case 'cyan':
        case 'blue':
        default:
        return 'border-[color:var(--horizon-sunset-blue)]/40 bg-white/[0.042] '
    }
  }

  return 'border-white/[0.055] bg-white/[0.024] hover:border-white/[0.055] hover:bg-white/[0.045]'
}

function commandCardClass(link) {
  switch (link.tone) {
    case 'magenta':
      return 'border-white/[0.055] bg-white/[0.042] hover:border-white/[0.055] hover:bg-[color:var(--horizon-sunset-magenta)]/15'
    case 'indigo':
      return 'border-white/[0.055] bg-white/[0.042] hover:border-white/[0.055] hover:bg-[color:var(--horizon-sunset-indigo)]/15'
    case 'emerald':
      return 'border-white/[0.055] bg-white/[0.042] hover:border-emerald-300/30 hover:bg-emerald-300/10'
    case 'danger':
      return 'border-red-300/25 bg-red-300/10 hover:border-red-300/45 hover:bg-red-300/15'
    case 'blue':
    default:
      return 'border-white/[0.055] bg-white/[0.042] hover:border-[color:var(--horizon-sunset-blue)]/55 hover:bg-[color:var(--horizon-sunset-blue)]/15'
  }
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
    <div class="mx-auto max-w-6xl space-y-8">
      <!-- Admin command hero -->
      <section class="hz-surface-welcome relative overflow-hidden rounded-[2rem] border border-white/[0.055] p-6 ">
        <div class="pointer-events-none absolute inset-0 opacity-40">
          <div class="absolute left-8 top-0 h-px w-48 bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-blue)] to-transparent"></div>
          <div class="absolute bottom-0 right-10 h-px w-64 bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-magenta)] to-transparent"></div>
        </div>

        <div class="relative grid gap-6 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
          <div>
            <div class="text-xs font-bold uppercase tracking-[0.28em] text-[color:var(--horizon-text-secondary)]">
              Horizon Administrative Command
            </div>

            <h1 class="mt-2 text-3xl font-black tracking-tight text-horizon-white md:text-5xl">
              Admin Dashboard
            </h1>

            <p class="mt-3 max-w-3xl text-sm text-text-secondary md:text-base">
              Manage personnel, squadrons, access roles, administrative media systems, and the Archive knowledge hub from one command surface.
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

          <div class="rounded-2xl border border-white/[0.055] bg-white/[0.035] px-4 py-3 text-right">
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
      </section>

      <!-- Admin command links -->
      <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <Link
          v-for="link in commandLinks"
          :key="link.label"
          :href="link.href"
          class="hz-surface-welcome group rounded-[1.5rem] border p-5 transition duration-200 hover:-translate-y-0.5"
          :class="commandCardClass(link)"
        >
          <div class="flex items-center justify-between gap-3">
            <div class="text-xs font-bold uppercase tracking-[0.2em] text-text-muted">
              {{ link.eyebrow }}
            </div>

            <div class="rounded-full border border-white/[0.055] bg-white/[0.024] px-3 py-1 text-xs font-bold text-horizon-white">
              {{ link.stat }}
            </div>
          </div>

          <div class="mt-3 text-2xl font-black text-horizon-white">
            {{ link.label }}
          </div>

          <p class="mt-3 text-sm leading-6 text-text-secondary">
            {{ link.description }}
          </p>
        </Link>
      </section>

      <section class="grid gap-4 md:grid-cols-4">
        <div class="hz-surface-welcome rounded-[1.25rem] border border-white/[0.055] p-4">
          <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Topics</div>
          <div class="mt-1 text-2xl font-black text-horizon-white">{{ archiveSummary.topics }}</div>
        </div>

        <div class="hz-surface-welcome rounded-[1.25rem] border border-white/[0.055] p-4">
          <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Entries</div>
          <div class="mt-1 text-2xl font-black text-horizon-white">{{ archiveSummary.entries }}</div>
        </div>

        <div class="hz-surface-welcome rounded-[1.25rem] border border-white/[0.055] p-4">
          <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Taxonomy</div>
          <div class="mt-1 text-2xl font-black text-horizon-white">{{ archiveSummary.categories + archiveSummary.tags }}</div>
        </div>

        <div class="rounded-[1.25rem] border border-red-300/20 bg-red-300/10 p-4">
          <div class="text-xs font-bold uppercase tracking-[0.18em] text-red-200/80">Trash</div>
          <div class="mt-1 text-2xl font-black text-red-100">{{ archiveSummary.trashTotal }}</div>
        </div>
      </section>

      <!-- Admin tab cards -->
      <section class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        <button
          v-for="tab in tabItems"
          :key="tab.key"
          type="button"
          class="rounded-[1.5rem] border p-5 text-left transition duration-200 hover:-translate-y-0.5"
          :class="tabCardClass(tab)"
          @click="activeTab = tab.key"
        >
          <div class="text-xs font-bold uppercase tracking-[0.2em] text-text-muted">
            {{ tab.eyebrow }}
          </div>

          <div class="mt-2 flex items-center justify-between gap-3">
            <div class="text-2xl font-black text-horizon-white">
              {{ tab.label }}
            </div>

            <div
              v-if="tab.count !== null"
              class="rounded-full border border-white/[0.055] bg-white/[0.024] px-3 py-1 text-xs font-bold text-horizon-white"
            >
              {{ tab.count }}
            </div>
          </div>

          <p class="mt-3 text-sm leading-6 text-text-secondary">
            {{ tab.description }}
          </p>
        </button>
      </section>

      <!-- Active console shell -->
      <section class="hz-surface-welcome rounded-[2rem] border border-white/[0.055] p-4  md:p-5">
        <div class="mb-5 flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
          <div>
            <div class="text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--horizon-text-secondary)]">
              {{ currentTab.eyebrow }} Console
            </div>

            <h2 class="mt-1 text-xl font-black text-horizon-white">
              {{ currentTab.label }}
            </h2>

            <p class="mt-1 text-sm text-text-secondary">
              {{ currentTab.description }}
            </p>
          </div>

          <div class="hz-surface-welcome rounded-full border border-white/[0.055] px-3 py-1 text-xs font-bold uppercase tracking-[0.16em] text-text-muted">
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
  </HorizonContainer>
</template>



