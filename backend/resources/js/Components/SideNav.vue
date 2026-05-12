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
    squadrons.find(squadron => squadron?.pivot?.membership_status === 'active') ??
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

const directorBadgeLabel = computed(() => {
  if (!isDirectorLike.value) return null

  const roles = user.value?.roles ?? []
  const roleSlugs = roles.map(role => role?.slug ?? role?.name ?? role).filter(Boolean)

  if (roleSlugs.includes('tech_director')) return 'TECH DIRECTOR'
  if (roleSlugs.includes('director')) return 'DIRECTOR'

  return 'DIRECTOR ACCESS'
})

const navGroups = computed(() => {
  if (!user.value) return []

  const url = page.url

  const groups = [
    {
      key: 'command',
      label: 'Command',
      eyebrow: 'Mission flow',
      tone: 'blue',
      items: [
        {
          key: 'home',
          label: 'Welcome',
          shortLabel: 'Home',
          icon: '⌂',
          routeName: 'home',
          params: undefined,
          isActive: url === '/',
          tone: 'blue',
          status: 'Hub',
        },
        {
          key: 'operations_member',
          label: 'Operations',
          shortLabel: 'Ops',
          icon: '✦',
          routeName: 'operations.member',
          params: undefined,
          isActive:
            url === '/operations' ||
            url === '/operations/' ||
            url.startsWith('/operations?') ||
            /^\/operations\/\d+/.test(url),
          tone: 'cyan',
          status: 'Live',
        },
        {
          key: 'operations_dashboard',
          label: 'Operations Dashboard',
          shortLabel: 'Ops Dash',
          icon: '⟁',
          routeName: 'operations.index',
          params: undefined,
          isActive: url === '/operations/dashboard' || url === '/operations/dashboard/' || url.startsWith('/operations/dashboard?'),
          show: canSeeOperationsDashboard.value,
          tone: 'magenta',
          status: 'Officer',
        },
      ],
    },
    {
      key: 'organization',
      label: 'Organization',
      eyebrow: 'People + units',
      tone: 'indigo',
      items: [
        {
          key: 'squadrons_index',
          label: 'Squadrons',
          shortLabel: 'Squads',
          icon: '⬡',
          routeName: 'squadrons.index',
          params: undefined,
          isActive: url === '/squadrons' || url.startsWith('/squadrons?'),
          tone: 'indigo',
          status: 'Units',
        },
        {
          key: 'my_squadron',
          label: 'My Squadron',
          shortLabel: 'Squad',
          icon: '◆',
          routeName: 'squadrons.show',
          params: mySquadron.value ? mySquadron.value.slug : undefined,
          isActive: mySquadron.value ? url === `/squadrons/${mySquadron.value.slug}` : false,
          show: !!mySquadron.value,
          badge: mySquadron.value?.name ?? 'Active',
          tone: 'cyan',
          status: 'Assigned',
        },
        {
          key: 'members_index',
          label: 'Members',
          shortLabel: 'Members',
          icon: '☷',
          routeName: 'members.index',
          params: undefined,
          isActive: url === '/members' || url === '/members/' || url.startsWith('/members?'),
          tone: 'blue',
          status: 'Roster',
        },
      ],
    },
    {
      key: 'archive',
      label: 'Archive',
      eyebrow: 'Docs + records',
      tone: 'magenta',
      items: [
        {
          key: 'archive',
          label: 'Horizon Archive',
          shortLabel: 'Archive',
          icon: '◫',
          href: 'https://docs.horizoninterstellar.com/collection/horizon-archive-ehz6KYHyv1/overview',
          isActive: false,
          tone: 'magenta',
          status: 'Docs',
        },
      ],
    },
    {
      key: 'system',
      label: 'System',
      eyebrow: 'Director tools',
      show: canSeeEverything.value,
      tone: 'red',
      items: [
        {
          key: 'admin_dashboard',
          label: 'Admin Dashboard',
          shortLabel: 'Admin',
          icon: '⚙',
          routeName: 'admin.dashboard',
          params: undefined,
          isActive: url.startsWith('/admin'),
          tone: 'red',
          status: 'Admin',
        },
      ],
    },
  ]

  return groups
    .filter(group => group.show !== false)
    .map(group => ({
      ...group,
      items: group.items.filter(item => item.show !== false),
    }))
    .filter(group => group.items.length > 0)
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
    // Local storage is optional.
  }
}

function getItemHref(item) {
  if (item.href) return item.href

  return route(item.routeName, item.params)
}

function iconToneClass(item, active = false) {
  const tone = item?.tone ?? 'blue'

  if (active) {
    switch (tone) {
      case 'magenta':
        return 'border-[color:var(--horizon-sunset-magenta)]/45 bg-[color:var(--horizon-sunset-magenta)]/15 text-horizon-white shadow-[0_0_18px_rgba(192,38,211,0.18)]'
      case 'indigo':
        return 'border-[color:var(--horizon-sunset-indigo)]/45 bg-[color:var(--horizon-sunset-indigo)]/15 text-horizon-white shadow-[0_0_18px_rgba(67,56,202,0.18)]'
      case 'red':
        return 'border-red-300/35 bg-red-300/10 text-red-100 shadow-[0_0_18px_rgba(248,113,113,0.16)]'
      case 'cyan':
      case 'blue':
      default:
        return 'border-[color:var(--horizon-sunset-blue)]/45 bg-[color:var(--horizon-sunset-blue)]/15 text-horizon-white shadow-[0_0_18px_rgba(30,64,175,0.18)]'
    }
  }

  switch (tone) {
    case 'magenta':
      return 'border-white/[0.06] bg-white/[0.035] text-text-secondary group-hover:border-[color:var(--horizon-sunset-magenta)]/35 group-hover:bg-[color:var(--horizon-sunset-magenta)]/10 group-hover:text-horizon-white'
    case 'indigo':
      return 'border-white/[0.06] bg-white/[0.035] text-text-secondary group-hover:border-[color:var(--horizon-sunset-indigo)]/35 group-hover:bg-[color:var(--horizon-sunset-indigo)]/10 group-hover:text-horizon-white'
    case 'red':
      return 'border-white/[0.06] bg-white/[0.035] text-text-secondary group-hover:border-red-300/35 group-hover:bg-red-300/10 group-hover:text-red-100'
    case 'cyan':
    case 'blue':
    default:
      return 'border-white/[0.06] bg-white/[0.035] text-text-secondary group-hover:border-[color:var(--horizon-sunset-blue)]/35 group-hover:bg-[color:var(--horizon-sunset-blue)]/10 group-hover:text-horizon-white'
  }
}

function activeRailClass(item) {
  const tone = item?.tone ?? 'blue'

  switch (tone) {
    case 'magenta':
      return 'bg-[color:var(--horizon-sunset-magenta)] shadow-[0_0_18px_rgba(192,38,211,0.95)]'
    case 'indigo':
      return 'bg-[color:var(--horizon-sunset-indigo)] shadow-[0_0_18px_rgba(67,56,202,0.95)]'
    case 'red':
      return 'bg-red-300 shadow-[0_0_18px_rgba(248,113,113,0.85)]'
    case 'cyan':
    case 'blue':
    default:
      return 'bg-[color:var(--horizon-sunset-blue)] shadow-[0_0_18px_rgba(30,64,175,0.95)]'
  }
}

function itemCardClass(item) {
  if (item.isActive) {
    return 'border-[color:var(--horizon-sunset-blue)]/40 bg-[radial-gradient(circle_at_top_left,var(--horizon-glow-blue),transparent_44%),rgba(255,255,255,0.055)] text-horizon-white shadow-[0_0_24px_rgba(30,64,175,0.20)]'
  }

  return 'border-white/[0.05] bg-white/[0.025] text-text-secondary hover:border-[color:var(--horizon-sunset-blue)]/30 hover:bg-white/[0.055] hover:text-horizon-white'
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
    class="fixed left-3 top-3 z-[80] inline-flex items-center gap-2 rounded-xl border border-[color:var(--horizon-sunset-blue)]/30 bg-[linear-gradient(135deg,var(--horizon-void-700),var(--horizon-void-900))] px-3 py-2 text-horizon-white shadow-[0_0_28px_rgba(30,64,175,0.25)] md:hidden"
    @click="openMobileNav"
  >
    <span class="text-sm font-black">☰</span>
    <span class="text-sm font-semibold">Menu</span>
  </button>

  <!-- Mobile drawer -->
  <div
    v-if="user && mobileOpen"
    class="fixed inset-x-0 top-0 z-[70] md:hidden"
    style="height: 100vh; height: 100dvh;"
  >
    <div
      class="absolute inset-0 bg-black/75 backdrop-blur-md"
      @click="closeMobileNav"
    ></div>

    <aside class="absolute inset-y-0 left-0 w-[18.5rem] max-w-[85vw] animate-[hz-slide-in-left_180ms_ease-out] bg-bg-elevated border-r border-horizon-blue-20 shadow-[0_0_42px_rgba(56,189,248,0.18)]">
      <div class="pointer-events-none absolute inset-0 opacity-50">
        <div class="absolute left-6 top-0 h-px w-44 bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-blue)] to-transparent"></div>
        <div class="absolute bottom-0 right-6 h-px w-56 bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-magenta)] to-transparent"></div>
      </div>

      <div class="relative flex h-full min-h-0 flex-col gap-4 p-4">
        <div class="-mx-4 -mt-4 shrink-0 overflow-hidden border-b border-[color:var(--horizon-sunset-blue)]/25 bg-black">
          <img
            src="/images/Horizon_GIF.gif"
            alt="Horizon Interstellar"
            class="block h-36 w-full scale-220 object-contain -translate-y-1.5"
          />
        </div>

        <div class="flex shrink-0 items-center justify-between gap-3 rounded-2xl border border-white/10 bg-white/[0.025] px-3 py-3">
          <div class="min-w-0">
            <div class="text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--horizon-text-secondary)]">
              Navigation
            </div>

            <div class="mt-1 truncate text-[11px] text-text-muted">
              Horizon command rail
            </div>
          </div>

          <button
            type="button"
            class="shrink-0 rounded-xl border border-white/10 bg-white/[0.035] px-3 py-2 text-sm font-semibold text-text-secondary transition hover:border-[color:var(--horizon-sunset-magenta)]/35 hover:bg-[color:var(--horizon-sunset-magenta)]/10 hover:text-horizon-white"
            @click="closeMobileNav"
          >
            Close
          </button>
        </div>

        <nav class="flex min-h-0 flex-1 flex-col gap-4 overflow-auto px-1">
          <section
            v-for="group in navGroups"
            :key="group.key"
            class="space-y-2"
          >
            <div class="px-2">
              <div class="text-[10px] font-black uppercase tracking-[0.24em] text-text-muted">
                {{ group.label }}
              </div>

              <div class="mt-0.5 text-[10px] text-text-muted/80">
                {{ group.eyebrow }}
              </div>
            </div>

            <template
              v-for="item in group.items"
              :key="item.key"
            >
              <Link
                v-if="!item.href"
                :href="getItemHref(item)"
                class="group relative block rounded-2xl border px-3 py-3 text-base font-semibold transition"
                :class="itemCardClass(item)"
                @click="closeMobileNav"
              >
                <span
                  v-if="item.isActive"
                  class="absolute left-0 top-1/2 h-8 w-1 -translate-y-1/2 rounded-r-full"
                  :class="activeRailClass(item)"
                />

                <div class="flex items-center justify-between gap-3">
                  <div class="flex min-w-0 items-center gap-3">
                    <span
                      class="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border transition"
                      :class="iconToneClass(item, item.isActive)"
                    >
                      <span class="text-base leading-none">
                        {{ item.icon }}
                      </span>

                      <span
                        v-if="item.isActive"
                        class="absolute -right-1 -top-1 h-2.5 w-2.5 rounded-full border border-[color:var(--horizon-void-900)] bg-emerald-300 shadow-[0_0_10px_rgba(110,231,183,0.75)]"
                      ></span>
                    </span>

                    <div class="min-w-0">
                      <div class="truncate">
                        {{ item.label }}
                      </div>

                      <div class="mt-0.5 text-[10px] font-semibold uppercase tracking-[0.14em] text-text-muted">
                        {{ item.status }}
                      </div>
                    </div>
                  </div>

                  <span
                    v-if="item.badge"
                    class="max-w-24 shrink-0 truncate rounded-full border border-[color:var(--horizon-sunset-blue)]/25 bg-[color:var(--horizon-sunset-blue)]/10 px-2 py-1 text-[10px] leading-none text-horizon-white"
                  >
                    {{ item.badge }}
                  </span>
                </div>
              </Link>

              <a
                v-else
                :href="item.href"
                target="_blank"
                rel="noopener noreferrer"
                class="group relative block rounded-2xl border px-3 py-3 text-base font-semibold transition"
                :class="itemCardClass(item)"
                @click="closeMobileNav"
              >
                <div class="flex items-center justify-between gap-3">
                  <div class="flex min-w-0 items-center gap-3">
                    <span
                      class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border transition"
                      :class="iconToneClass(item, false)"
                    >
                      {{ item.icon }}
                    </span>

                    <div class="min-w-0">
                      <div class="truncate">
                        {{ item.label }}
                      </div>

                      <div class="mt-0.5 text-[10px] font-semibold uppercase tracking-[0.14em] text-text-muted">
                        {{ item.status }}
                      </div>
                    </div>
                  </div>

                  <span class="text-xs text-text-muted">
                    ↗
                  </span>
                </div>
              </a>
            </template>
          </section>
        </nav>

        <div class="shrink-0 rounded-2xl border border-[color:var(--horizon-sunset-blue)]/25 bg-[radial-gradient(circle_at_top_left,var(--horizon-glow-blue),transparent_42%),rgba(255,255,255,0.035)] px-3 py-3 shadow-[0_0_28px_rgba(30,64,175,0.14)]">
          <div class="flex items-center gap-3">
            <Link
              :href="profileHref"
              class="block shrink-0"
              title="View your profile"
            >
              <img
                v-if="user.discord_avatar"
                :src="user.discord_avatar"
                alt=""
                class="h-12 w-12 rounded-xl border border-[color:var(--horizon-sunset-blue)]/30 object-cover shadow-[0_0_18px_rgba(30,64,175,0.18)]"
              />

              <div
                v-else
                class="flex h-12 w-12 items-center justify-center rounded-xl border border-[color:var(--horizon-sunset-blue)]/30 bg-white/[0.04] text-sm font-black text-horizon-white"
              >
                {{ String(user.rsi_handle ?? user.discord_name ?? 'M').slice(0, 1).toUpperCase() }}
              </div>
            </Link>

            <div class="min-w-0">
              <div class="text-xs text-text-secondary">
                Personnel File
              </div>

              <div
                class="truncate text-sm font-bold text-horizon-white"
                :style="userNameColor ? { color: userNameColor } : undefined"
              >
                {{ user.rsi_handle ?? user.discord_name ?? 'Member' }}
              </div>

              <div class="text-xs text-text-secondary">
                {{ rankName }}
              </div>

              <div
                v-if="directorBadgeLabel"
                class="mt-1 inline-flex rounded-full border border-[color:var(--horizon-sunset-magenta)]/25 bg-[color:var(--horizon-sunset-magenta)]/10 px-2 py-0.5 text-[9px] font-black uppercase tracking-[0.14em] text-[color:var(--horizon-text-primary)]"
              >
                {{ directorBadgeLabel }}
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
    class="sticky top-0 hidden h-screen shrink-0 transition-[width] duration-300 ease-out md:block"
    :class="desktopExpanded ? 'w-[19.5rem]' : 'w-[5.5rem]'"
  >
    <div class="relative h-full overflow-hidden border-r border-[color:var(--horizon-sunset-indigo)]/35 bg-[linear-gradient(180deg,var(--horizon-void-700),var(--horizon-void-900))] shadow-[0_0_54px_rgba(67,56,202,0.18)] backdrop-blur-xl">
      <div class="pointer-events-none absolute inset-0 opacity-50">
        <div class="absolute left-6 top-0 h-px w-44 bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-blue)] to-transparent"></div>
        <div class="absolute bottom-0 right-6 h-px w-56 bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-magenta)] to-transparent"></div>
      </div>

      <div class="relative flex h-full min-h-0 flex-col gap-4 p-4">
        <div
          class="-mx-4 -mt-4 shrink-0 overflow-hidden border-b border-[color:var(--horizon-sunset-blue)]/25 bg-black transition-all duration-300"
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
          class="flex shrink-0 items-center rounded-2xl border border-white/10 bg-white/[0.025] py-3 transition-all duration-300"
          :class="desktopExpanded ? 'justify-between gap-3 px-3' : 'justify-center px-2'"
        >
          <div
            class="min-w-0 transition-all duration-200"
            :class="desktopExpanded ? 'opacity-100' : 'pointer-events-none w-0 opacity-0'"
          >
            <div class="text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--horizon-text-secondary)]">
              Navigation
            </div>

            <div class="mt-1 truncate text-[11px] text-text-muted">
              Horizon command rail
            </div>
          </div>

          <button
            type="button"
            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-[color:var(--horizon-sunset-blue)]/25 bg-white/[0.035] text-text-secondary transition hover:border-[color:var(--horizon-sunset-magenta)]/35 hover:bg-[color:var(--horizon-sunset-magenta)]/10 hover:text-horizon-white hover:shadow-[0_0_20px_rgba(192,38,211,0.18)]"
            :title="desktopExpanded ? 'Collapse sidebar' : 'Expand sidebar'"
            @click="toggleDesktopNav"
          >
            <span
              class="text-xl leading-none transition-transform duration-300"
              :class="desktopExpanded ? '' : 'rotate-180'"
            >
              ‹
            </span>
          </button>
        </div>

        <nav class="flex min-h-0 flex-1 flex-col gap-4 overflow-auto px-1">
          <section
            v-for="group in navGroups"
            :key="group.key"
            class="space-y-2"
          >
            <div
              class="px-2 transition-all duration-200"
              :class="desktopExpanded ? 'opacity-100' : 'pointer-events-none h-0 overflow-hidden opacity-0'"
            >
              <div class="text-[10px] font-black uppercase tracking-[0.24em] text-text-muted">
                {{ group.label }}
              </div>

              <div class="mt-0.5 text-[10px] text-text-muted/80">
                {{ group.eyebrow }}
              </div>
            </div>

            <template
              v-for="item in group.items"
              :key="item.key"
            >
              <Link
                v-if="!item.href"
                :href="getItemHref(item)"
                class="group relative flex items-center rounded-2xl border text-base font-semibold transition"
                :class="[
                  desktopExpanded ? 'gap-3 px-3 py-3' : 'justify-center px-2 py-3',
                  itemCardClass(item)
                ]"
                :title="desktopExpanded ? undefined : `${item.label} · ${item.status}`"
              >
                <span
                  v-if="item.isActive"
                  class="absolute left-0 top-1/2 h-8 w-1 -translate-y-1/2 rounded-r-full"
                  :class="activeRailClass(item)"
                />

                <span
                  class="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border transition"
                  :class="iconToneClass(item, item.isActive)"
                >
                  <span class="text-base leading-none">
                    {{ item.icon }}
                  </span>

                  <span
                    v-if="item.isActive"
                    class="absolute -right-1 -top-1 h-2.5 w-2.5 rounded-full border border-[color:var(--horizon-void-900)] bg-emerald-300 shadow-[0_0_10px_rgba(110,231,183,0.75)]"
                  ></span>
                </span>

                <div
                  class="min-w-0 transition-all duration-200"
                  :class="desktopExpanded ? 'opacity-100' : 'pointer-events-none w-0 opacity-0'"
                >
                  <div class="truncate">
                    {{ item.label }}
                  </div>

                  <div class="mt-0.5 text-[10px] font-semibold uppercase tracking-[0.14em] text-text-muted">
                    {{ item.status }}
                  </div>
                </div>

                <span
                  v-if="desktopExpanded && item.badge"
                  class="ml-auto max-w-24 shrink-0 truncate rounded-full border border-[color:var(--horizon-sunset-blue)]/25 bg-[color:var(--horizon-sunset-blue)]/10 px-2 py-1 text-[10px] leading-none text-horizon-white"
                >
                  {{ item.badge }}
                </span>
              </Link>

              <a
                v-else
                :href="item.href"
                target="_blank"
                rel="noopener noreferrer"
                class="group relative flex items-center rounded-2xl border text-base font-semibold transition"
                :class="[
                  desktopExpanded ? 'gap-3 px-3 py-3' : 'justify-center px-2 py-3',
                  itemCardClass(item)
                ]"
                :title="desktopExpanded ? undefined : `${item.label} · ${item.status}`"
              >
                <span
                  class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border transition"
                  :class="iconToneClass(item, false)"
                >
                  {{ item.icon }}
                </span>

                <div
                  class="min-w-0 transition-all duration-200"
                  :class="desktopExpanded ? 'opacity-100' : 'pointer-events-none w-0 opacity-0'"
                >
                  <div class="truncate">
                    {{ item.label }}
                  </div>

                  <div class="mt-0.5 text-[10px] font-semibold uppercase tracking-[0.14em] text-text-muted">
                    {{ item.status }}
                  </div>
                </div>

                <span
                  v-if="desktopExpanded"
                  class="ml-auto text-xs text-text-muted"
                >
                  ↗
                </span>
              </a>
            </template>
          </section>
        </nav>

        <Link
          :href="profileHref"
          class="shrink-0 rounded-2xl border border-[color:var(--horizon-sunset-blue)]/25 bg-[radial-gradient(circle_at_top_left,var(--horizon-glow-blue),transparent_42%),rgba(255,255,255,0.035)] shadow-[0_0_28px_rgba(30,64,175,0.14)] transition-all duration-300"
          :class="desktopExpanded ? 'px-3 py-3' : 'px-2 py-3'"
        >
          <div
            class="flex items-center"
            :class="desktopExpanded ? 'gap-3' : 'justify-center'"
          >
            <img
              v-if="user.discord_avatar"
              :src="user.discord_avatar"
              alt=""
              class="h-12 w-12 shrink-0 rounded-xl border border-[color:var(--horizon-sunset-blue)]/30 object-cover shadow-[0_0_18px_rgba(30,64,175,0.18)]"
            />

            <div
              v-else
              class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl border border-[color:var(--horizon-sunset-blue)]/30 bg-white/[0.04] text-sm font-black text-horizon-white"
            >
              {{ String(user.rsi_handle ?? user.discord_name ?? 'M').slice(0, 1).toUpperCase() }}
            </div>

            <div
              class="min-w-0 transition-all duration-200"
              :class="desktopExpanded ? 'opacity-100' : 'pointer-events-none w-0 opacity-0'"
            >
              <div class="text-xs text-text-secondary">
                Personnel File
              </div>

              <div
                class="truncate text-sm font-bold text-horizon-white"
                :style="userNameColor ? { color: userNameColor } : undefined"
              >
                {{ user.rsi_handle ?? user.discord_name ?? 'Member' }}
              </div>

              <div class="truncate text-xs text-text-secondary">
                {{ rankName }}
              </div>

              <div
                v-if="directorBadgeLabel"
                class="mt-1 inline-flex rounded-full border border-[color:var(--horizon-sunset-magenta)]/25 bg-[color:var(--horizon-sunset-magenta)]/10 px-2 py-0.5 text-[9px] font-black uppercase tracking-[0.14em] text-[color:var(--horizon-text-primary)]"
              >
                {{ directorBadgeLabel }}
              </div>
            </div>
          </div>
        </Link>
      </div>
    </div>
  </aside>
</template>
<style scoped>
@keyframes hz-slide-in-left {
  from {
    opacity: 0;
    transform: translateX(-1rem);
  }

  to {
    opacity: 1;
    transform: translateX(0);
  }
}
</style>