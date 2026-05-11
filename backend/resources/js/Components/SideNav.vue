<script setup>
import { computed, ref, watch, onMounted, onBeforeUnmount } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'

import { canCreateOperation, isDirectorLike as userIsDirectorLike } from '@/auth'
import { getHighestOrgRoleSlug, getOrgRoleColor } from '@/roleColors'

const page = usePage()

const user = computed(() => page.props?.auth?.user ?? null)

const rankLevel = computed(() => Number(user.value?.rank_level ?? 0))

const rankName = computed(() => {
  const existingRankName = user.value?.rank_name

  if (existingRankName) {
    return existingRankName
  }

  return (
    {
      1: 'Member',
      2: 'Lieutenant',
      3: 'Commander',
      4: 'Wing Commander',
      5: 'Admiral',
      6: 'Grand Admiral',
    }[rankLevel.value] ?? 'Unknown'
  )
})

const userNameColor = computed(() => {
  const u = user.value
  const slug = getHighestOrgRoleSlug(u?.roles, u?.rank)

  return getOrgRoleColor(slug)
})

const isDirectorLike = computed(() => {
  return userIsDirectorLike(user.value)
})

const canSeeEverything = computed(() => {
  return isDirectorLike.value
})

const canSeeOperationsDashboard = computed(() => {
  return canCreateOperation(user.value)
})

const mySquadron = computed(() => {
  const squadrons = user.value?.squadrons ?? []

  return (
    squadrons.find(s => s?.pivot?.membership_status === 'active') ??
    squadrons[0] ??
    null
  )
})

const profileHref = computed(() => {
  if (!user.value) return null
  if (user.value?.rsi_handle) return route('member.profile', user.value.rsi_handle)
  if (user.value?.id) return `/user/${user.value.id}`

  return null
})

const navItems = computed(() => {
  if (!user.value) return []

  const url = page.url

  return [
    {
      key: 'home',
      label: 'Welcome',
      icon: '⌂',
      routeName: 'home',
      params: undefined,
      isActive: url === '/',
    },
    {
      key: 'operations_member',
      label: 'Operations',
      icon: '✦',
      routeName: 'operations.member',
      params: undefined,
      isActive: url === '/operations' || url === '/operations/' || url.startsWith('/operations?'),
    },
    {
      key: 'operations_dashboard',
      label: 'Operations Dashboard',
      icon: '⟁',
      routeName: 'operations.index',
      params: undefined,
      isActive: url === '/operations/dashboard' || url === '/operations/dashboard/' || url.startsWith('/operations/dashboard?'),
      show: canSeeOperationsDashboard.value,
    },
    {
      key: 'squadrons_index',
      label: 'Squadrons',
      icon: '⬡',
      routeName: 'squadrons.index',
      params: undefined,
      isActive: url === '/squadrons' || url.startsWith('/squadrons?'),
    },
    {
      key: 'my_squadron',
      label: 'Squadron',
      icon: '◆',
      routeName: 'squadrons.show',
      params: mySquadron.value ? mySquadron.value.slug : undefined,
      isActive: mySquadron.value ? url === `/squadrons/${mySquadron.value.slug}` : false,
      show: !!mySquadron.value,
    },
    {
      key: 'archive',
      label: 'Horizon Archive',
      icon: '◫',
      href: 'https://docs.horizoninterstellar.com/collection/horizon-archive-ehz6KYHyv1/overview',
      isActive: false,
    },
    {
      key: 'members_index',
      label: 'Members',
      icon: '☷',
      routeName: 'members.index',
      params: undefined,
      isActive: url === '/members' || url === '/members/' || url.startsWith('/members?'),
    },
    {
      key: 'admin_dashboard',
      label: 'Admin Dashboard',
      icon: '⚙',
      routeName: 'admin.dashboard',
      params: undefined,
      isActive: url.startsWith('/admin'),
      show: canSeeEverything.value,
    },
  ].filter(item => item.show !== false)
})

const mobileOpen = ref(false)
const desktopExpanded = ref(true)

function openMobileNav() {
  mobileOpen.value = true
}

function closeMobileNav() {
  mobileOpen.value = false
}

function toggleDesktopNav() {
  desktopExpanded.value = !desktopExpanded.value

  try {
    window.localStorage.setItem('horizon.sidebar.expanded', desktopExpanded.value ? '1' : '0')
  } catch {
    // Local storage is optional. If the browser blocks it, the sidebar still works.
  }
}

function getItemHref(item) {
  if (item.href) return item.href

  return route(item.routeName, item.params)
}

watch(
  () => page.url,
  () => {
    closeMobileNav()
  }
)

function handleKeydown(event) {
  if (event.key !== 'Escape') return
  if (!mobileOpen.value) return

  closeMobileNav()
}

onMounted(() => {
  window.addEventListener('keydown', handleKeydown)

  try {
    const savedValue = window.localStorage.getItem('horizon.sidebar.expanded')

    if (savedValue === '0') {
      desktopExpanded.value = false
    }

    if (savedValue === '1') {
      desktopExpanded.value = true
    }
  } catch {
    // Local storage is optional.
  }
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleKeydown)
})
</script>

<template>
  <button
    v-if="user && !mobileOpen"
    type="button"
    class="md:hidden fixed top-3 left-3 z-[80] inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-bg-elevated border border-bg-hover text-horizon-white shadow-deep"
    @click="openMobileNav"
  >
    <span class="text-sm font-semibold">Menu</span>
  </button>

  <!-- Mobile drawer -->
  <div
    v-if="user && mobileOpen"
    class="md:hidden fixed top-0 left-0 right-0 z-[70]"
    style="height: 100vh; height: 100dvh;"
  >
    <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" @click="closeMobileNav"></div>

    <aside class="absolute inset-y-0 left-0 w-[18.5rem] max-w-[85vw] bg-bg-elevated border-r border-horizon-blue-20 shadow-[0_0_42px_rgba(56,189,248,0.18)]">
      <div class="h-full flex flex-col p-4 gap-4 min-h-0">
        <div class="-mx-4 -mt-4 bg-black overflow-hidden shrink-0 border-b border-horizon-blue-20">
          <img
            src="/images/Horizon_GIF.gif"
            alt="Horizon Interstellar"
            class="block w-full h-36 object-contain scale-220 -translate-y-1.5"
          />
        </div>

        <div class="flex items-center justify-between px-2 shrink-0">
          <div>
            <div class="text-xs uppercase tracking-[0.24em] text-text-secondary font-bold">
              Navigation
            </div>
            <div class="text-[11px] text-text-muted mt-1">
              Horizon command layer
            </div>
          </div>

          <button
            type="button"
            class="px-3 py-2 rounded-xl text-sm font-semibold transition text-text-secondary hover:bg-bg-hover hover:text-horizon-white"
            @click="closeMobileNav"
          >
            Close
          </button>
        </div>

        <nav class="flex flex-col gap-2 flex-1 min-h-0 overflow-auto px-1">
          <template v-for="item in navItems" :key="item.key">
            <Link
              v-if="!item.href"
              :href="getItemHref(item)"
              class="group relative px-3 py-3 rounded-2xl text-base font-semibold transition border"
              :class="
                item.isActive
                  ? 'bg-horizon-blue-20 border-horizon-blue/40 text-horizon-white shadow-[0_0_22px_rgba(56,189,248,0.14)]'
                  : 'bg-white/[0.02] border-white/[0.04] text-text-secondary hover:bg-bg-hover hover:border-horizon-blue/30 hover:text-horizon-white'
              "
              @click="closeMobileNav"
            >
              <span
                v-if="item.isActive"
                class="absolute left-0 top-1/2 h-7 w-1 -translate-y-1/2 rounded-r-full bg-horizon-blue shadow-[0_0_16px_rgba(56,189,248,0.9)]"
              />

              <div class="flex items-center justify-between gap-3">
                <div class="flex min-w-0 items-center gap-3">
                  <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-bg-hover border border-white/[0.06] text-horizon-white">
                    {{ item.icon }}
                  </span>

                  <span class="truncate">{{ item.label }}</span>
                </div>

                <span
                  v-if="item.key === 'my_squadron'"
                  class="shrink-0 text-[10px] leading-none px-2 py-1 rounded-full bg-horizon-blue-dark text-horizon-white border border-horizon-blue-20"
                >
                  {{ mySquadron?.name ?? 'Active' }}
                </span>
              </div>
            </Link>

            <a
              v-else
              :href="item.href"
              target="_blank"
              rel="noopener noreferrer"
              class="group relative px-3 py-3 rounded-2xl text-base font-semibold transition border bg-white/[0.02] border-white/[0.04] text-text-secondary hover:bg-bg-hover hover:border-horizon-blue/30 hover:text-horizon-white"
              @click="closeMobileNav"
            >
              <div class="flex items-center justify-between gap-3">
                <div class="flex min-w-0 items-center gap-3">
                  <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-bg-hover border border-white/[0.06] text-horizon-white">
                    {{ item.icon }}
                  </span>

                  <span class="truncate">{{ item.label }}</span>
                </div>

                <span class="text-xs text-text-muted">↗</span>
              </div>
            </a>
          </template>
        </nav>

        <div class="shrink-0 px-3 py-3 rounded-2xl bg-horizon-blue-dark border border-horizon-blue-20 shadow-[0_0_24px_rgba(56,189,248,0.10)]">
          <div class="flex items-center gap-3">
            <Link
              :href="profileHref"
              class="shrink-0 block"
              title="View your profile"
            >
              <img
                v-if="user.discord_avatar"
                :src="user.discord_avatar"
                alt=""
                class="h-10 w-10 rounded-xl object-cover border border-horizon-blue-20"
              />
              <div
                v-else
                class="h-10 w-10 rounded-xl bg-bg-hover border border-horizon-blue-20 flex items-center justify-center text-sm font-semibold text-horizon-white"
              >
                {{ String(user.rsi_handle ?? user.discord_name ?? 'M').slice(0, 1).toUpperCase() }}
              </div>
            </Link>

            <div class="min-w-0">
              <div class="text-xs text-text-secondary">User Info</div>
              <div class="text-sm font-semibold truncate text-horizon-white" :style="userNameColor ? { color: userNameColor } : undefined">
                {{ user.rsi_handle ?? user.discord_name ?? 'Member' }}
              </div>
              <div class="text-xs text-text-secondary">
                Rank {{ rankName }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </aside>
  </div>

  <!-- Desktop expandable sidebar -->
  <aside
    v-if="user"
    class="hidden md:block h-screen shrink-0 sticky top-0 transition-[width] duration-300 ease-out"
    :class="desktopExpanded ? 'w-[18.5rem]' : 'w-20'"
  >
    <div
      class="h-full overflow-hidden bg-bg-elevated/95 border-r border-horizon-blue-20 shadow-[0_0_42px_rgba(56,189,248,0.10)] backdrop-blur-xl"
    >
      <div class="h-full flex flex-col p-4 gap-4 min-h-0">
        <div
          class="-mx-4 -mt-4 bg-black overflow-hidden shrink-0 border-b border-horizon-blue-20 transition-all duration-300"
          :class="desktopExpanded ? 'h-36' : 'h-20'"
        >
          <img
            src="/images/Horizon_GIF.gif"
            alt="Horizon Interstellar"
            class="block w-full object-contain transition-all duration-300"
            :class="desktopExpanded ? 'h-36 scale-220 -translate-y-1.5' : 'h-20 scale-175 -translate-y-1'"
          />
        </div>

        <div
          class="flex items-center shrink-0"
          :class="desktopExpanded ? 'justify-between px-2' : 'justify-center'"
        >
          <div
            class="min-w-0 transition-all duration-200"
            :class="desktopExpanded ? 'opacity-100' : 'pointer-events-none w-0 opacity-0'"
          >
            <div class="text-xs uppercase tracking-[0.24em] text-text-secondary font-bold">
              Navigation
            </div>
            <div class="text-[11px] text-text-muted mt-1 truncate">
              Horizon command layer
            </div>
          </div>

          <button
            type="button"
            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-horizon-blue-20 bg-white/[0.03] text-text-secondary transition hover:bg-horizon-blue-20 hover:text-horizon-white hover:shadow-[0_0_18px_rgba(56,189,248,0.22)]"
            :title="desktopExpanded ? 'Collapse sidebar' : 'Expand sidebar'"
            @click="toggleDesktopNav"
          >
            <span class="transition-transform duration-300" :class="desktopExpanded ? '' : 'rotate-180'">
              ‹
            </span>
          </button>
        </div>

        <nav class="flex flex-col gap-2 flex-1 min-h-0 overflow-auto px-1">
          <template v-for="item in navItems" :key="item.key">
            <Link
              v-if="!item.href"
              :href="getItemHref(item)"
              class="group relative flex items-center rounded-2xl text-base font-semibold transition border"
              :class="[
                desktopExpanded ? 'gap-3 px-3 py-3' : 'justify-center px-2 py-3',
                item.isActive
                  ? 'bg-horizon-blue-20 border-horizon-blue/40 text-horizon-white shadow-[0_0_22px_rgba(56,189,248,0.14)]'
                  : 'bg-white/[0.02] border-white/[0.04] text-text-secondary hover:bg-bg-hover hover:border-horizon-blue/30 hover:text-horizon-white'
              ]"
              :title="desktopExpanded ? undefined : item.label"
            >
              <span
                v-if="item.isActive"
                class="absolute left-0 top-1/2 h-7 w-1 -translate-y-1/2 rounded-r-full bg-horizon-blue shadow-[0_0_16px_rgba(56,189,248,0.9)]"
              />

              <span
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border transition"
                :class="
                  item.isActive
                    ? 'bg-horizon-blue-dark border-horizon-blue/40 text-horizon-white'
                    : 'bg-bg-hover border-white/[0.06] text-text-secondary group-hover:text-horizon-white'
                "
              >
                {{ item.icon }}
              </span>

              <span
                class="min-w-0 truncate transition-all duration-200"
                :class="desktopExpanded ? 'opacity-100' : 'pointer-events-none w-0 opacity-0'"
              >
                {{ item.label }}
              </span>

              <span
                v-if="desktopExpanded && item.key === 'my_squadron'"
                class="ml-auto shrink-0 text-[10px] leading-none px-2 py-1 rounded-full bg-horizon-blue-dark text-horizon-white border border-horizon-blue-20"
              >
                {{ mySquadron?.name ?? 'Active' }}
              </span>
            </Link>

            <a
              v-else
              :href="item.href"
              target="_blank"
              rel="noopener noreferrer"
              class="group relative flex items-center rounded-2xl text-base font-semibold transition border bg-white/[0.02] border-white/[0.04] text-text-secondary hover:bg-bg-hover hover:border-horizon-blue/30 hover:text-horizon-white"
              :class="desktopExpanded ? 'gap-3 px-3 py-3' : 'justify-center px-2 py-3'"
              :title="desktopExpanded ? undefined : item.label"
            >
              <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-bg-hover border border-white/[0.06] text-text-secondary group-hover:text-horizon-white">
                {{ item.icon }}
              </span>

              <span
                class="min-w-0 truncate transition-all duration-200"
                :class="desktopExpanded ? 'opacity-100' : 'pointer-events-none w-0 opacity-0'"
              >
                {{ item.label }}
              </span>

              <span
                v-if="desktopExpanded"
                class="ml-auto text-xs text-text-muted"
              >
                ↗
              </span>
            </a>
          </template>
        </nav>

        <div
          class="shrink-0 rounded-2xl bg-horizon-blue-dark border border-horizon-blue-20 shadow-[0_0_24px_rgba(56,189,248,0.10)] transition-all duration-300"
          :class="desktopExpanded ? 'px-3 py-3' : 'px-2 py-3'"
        >
          <div class="flex items-center" :class="desktopExpanded ? 'gap-3' : 'justify-center'">
            <Link
              :href="profileHref"
              class="shrink-0 block"
              title="View your profile"
            >
              <img
                v-if="user.discord_avatar"
                :src="user.discord_avatar"
                alt=""
                class="h-10 w-10 rounded-xl object-cover border border-horizon-blue-20"
              />
              <div
                v-else
                class="h-10 w-10 rounded-xl bg-bg-hover border border-horizon-blue-20 flex items-center justify-center text-sm font-semibold text-horizon-white"
              >
                {{ String(user.rsi_handle ?? user.discord_name ?? 'M').slice(0, 1).toUpperCase() }}
              </div>
            </Link>

            <div
              class="min-w-0 transition-all duration-200"
              :class="desktopExpanded ? 'opacity-100' : 'pointer-events-none w-0 opacity-0'"
            >
              <div class="text-xs text-text-secondary">User Info</div>
              <div class="text-sm font-semibold truncate text-horizon-white" :style="userNameColor ? { color: userNameColor } : undefined">
                {{ user.rsi_handle ?? user.discord_name ?? 'Member' }}
              </div>
              <div class="text-xs text-text-secondary">
                Rank {{ rankName }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </aside>
</template>