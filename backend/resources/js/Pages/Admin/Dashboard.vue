<script setup>
import { computed, ref, onMounted, onBeforeUnmount, watch, nextTick } from 'vue'

import HorizonContainer from '@/Components/HorizonContainer.vue'
import HorizonButton from '@/Components/HorizonButton.vue'

import UsersPanel from './Partials/UsersPanel.vue'
import SquadronsPanel from './Partials/SquadronsPanel.vue'
import RolesPanel from './Partials/RolesPanel.vue'
import MediaPanel from './Partials/MediaPanel.vue'

const props = defineProps({
  users: Object,
  squadrons: Array,
  roles: Array,
  filters: Object,
  eligibleLeaders: Array,
})

const allowedTabs = new Set(['users', 'squadrons', 'roles', 'media'])
const activeTabStorageKey = 'adminDashboard.activeTab'

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
    key: 'media',
    label: 'Media',
    eyebrow: 'Library',
    description: 'Inspect uploaded media and administrative media tools.',
    count: null,
    tone: 'cyan',
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
        return 'border-[color:var(--horizon-sunset-magenta)]/40 bg-[color:var(--horizon-sunset-magenta)]/10 shadow-[0_0_28px_rgba(192,38,211,0.16)]'
      case 'indigo':
        return 'border-[color:var(--horizon-sunset-indigo)]/40 bg-[color:var(--horizon-sunset-indigo)]/10 shadow-[0_0_28px_rgba(67,56,202,0.16)]'
      case 'cyan':
      case 'blue':
      default:
        return 'border-[color:var(--horizon-sunset-blue)]/40 bg-[color:var(--horizon-sunset-blue)]/10 shadow-[0_0_28px_rgba(30,64,175,0.16)]'
    }
  }

  return 'border-white/10 bg-white/[0.025] hover:border-[color:var(--horizon-sunset-blue)]/30 hover:bg-white/[0.045]'
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
      <section class="relative overflow-hidden rounded-[2rem] border border-[color:var(--horizon-sunset-indigo)]/45 bg-[radial-gradient(circle_at_top_left,var(--horizon-glow-blue),transparent_34%),radial-gradient(circle_at_top_right,var(--horizon-glow-magenta),transparent_32%),linear-gradient(135deg,var(--horizon-void-600),var(--horizon-void-900))] p-6 shadow-[0_0_48px_rgba(67,56,202,0.18)]">
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
              Manage personnel, squadrons, access roles, and administrative media systems from one command surface.
            </p>

            <div class="mt-4 flex flex-wrap gap-2">
              <span class="rounded-full border border-[color:var(--horizon-sunset-blue)]/30 bg-[color:var(--horizon-sunset-blue)]/10 px-3 py-1 text-xs font-semibold text-[color:var(--horizon-text-primary)]">
                {{ props.users?.total ?? props.users?.data?.length ?? 0 }} Users
              </span>

              <span class="rounded-full border border-[color:var(--horizon-sunset-indigo)]/30 bg-[color:var(--horizon-sunset-indigo)]/10 px-3 py-1 text-xs font-semibold text-[color:var(--horizon-text-primary)]">
                {{ props.squadrons?.length ?? 0 }} Squadrons
              </span>

              <span class="rounded-full border border-[color:var(--horizon-sunset-magenta)]/30 bg-[color:var(--horizon-sunset-magenta)]/10 px-3 py-1 text-xs font-semibold text-[color:var(--horizon-text-primary)]">
                {{ props.roles?.length ?? 0 }} Roles
              </span>
            </div>
          </div>

          <div class="rounded-2xl border border-[color:var(--horizon-sunset-blue)]/25 bg-white/[0.035] px-4 py-3 text-right">
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

      <!-- Admin tab cards -->
      <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
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
              class="rounded-full border border-white/10 bg-white/[0.035] px-3 py-1 text-xs font-bold text-horizon-white"
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
      <section class="rounded-[2rem] border border-[color:var(--horizon-sunset-blue)]/25 bg-[color:var(--horizon-void-700)]/70 p-4 shadow-[0_0_32px_rgba(30,64,175,0.10)] md:p-5">
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

          <div class="rounded-full border border-white/10 bg-white/[0.035] px-3 py-1 text-xs font-bold uppercase tracking-[0.16em] text-text-muted">
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
          v-if="activeTab === 'media'"
          class="hz-animate-fade"
        >
          <MediaPanel />
        </div>
      </section>
    </div>
  </HorizonContainer>
</template>