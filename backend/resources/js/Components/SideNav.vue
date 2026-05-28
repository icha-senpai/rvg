<script setup>
import { computed, ref, watch, onMounted, onBeforeUnmount } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'

import { canCreateOperation, isDirectorLike as userIsDirectorLike } from '@/auth'
import { getHighestOrgRoleSlug, getOrgRoleColor } from '@/roleColors'

const page = usePage()

const user = computed(() => page.props?.auth?.user ?? null)
const archiveNavigation = computed(() => page.props?.archiveNavigation ?? { topics: [] })

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

const archiveTopicChildren = computed(() => {
  const url = page.url ?? ''

  return (archiveNavigation.value?.topics ?? []).map(topic => ({
    key: `archive-topic-${topic.id}`,
    label: topic.title,
    href: topic.href,
    isActive: url === topic.href || url.startsWith(`${topic.href}/`) || url.startsWith(`${topic.href}?`),
    meta: topic.visible_entries_count ? `${topic.visible_entries_count}` : null,
    entries: (topic.entries ?? []).map(entry => ({
      key: `archive-entry-${entry.id}`,
      label: entry.title,
      href: entry.href,
      isActive: url === entry.href || url.startsWith(`${entry.href}?`),
    })),
  }))
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
      key: 'shop',
      label: 'Shop',
      eyebrow: 'Official merch',
      tone: 'cyan',
      items: [
        {
          key: 'eu_shop',
          label: 'EU Shop',
          shortLabel: 'EU Shop',
          icon: '€',
          href: 'https://horizon-interstellar.myspreadshop.net/',
          isActive: false,
          tone: 'cyan',
          status: 'Europe',
        },
        {
          key: 'us_shop',
          label: 'US Shop',
          shortLabel: 'US Shop',
          icon: '$',
          href: 'https://horizon-interstellar.myspreadshop.com/',
          isActive: false,
          tone: 'blue',
          status: 'United States',
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
          routeName: 'archive.index',
          params: undefined,
          isActive: url === '/archive' || url === '/archive/' || url.startsWith('/archive?') || url.startsWith('/archive/'),
          tone: 'magenta',
          status: 'Docs',
          children: archiveTopicChildren.value,
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
const manuallyExpandedGroups = ref(new Set())

const activeGroupKeys = computed(() => new Set(
  navGroups.value
    .filter(group => group.items.some(item => item.isActive))
    .map(group => group.key)
))

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

function toggleGroup(group) {
  const next = new Set(manuallyExpandedGroups.value)

  if (next.has(group.key)) {
    next.delete(group.key)
  } else {
    next.add(group.key)
  }

  manuallyExpandedGroups.value = next
}

function groupIsActive(group) {
  return activeGroupKeys.value.has(group.key)
}

function groupIsOpen(group) {
  return groupIsActive(group) || manuallyExpandedGroups.value.has(group.key)
}

function getItemHref(item) {
  if (item.href) return item.href

  return route(item.routeName, item.params)
}

function itemHasOpenChildren(item) {
  return Boolean(item.isActive && item.children?.length)
}

function groupLabelClass(group) {
  return groupIsActive(group)
    ? 'text-horizon-white'
    : 'text-text-muted hover:text-text-secondary'
}

function itemRowClass(item) {
  if (item.isActive) {
    return 'bg-white/[0.06] text-horizon-white'
  }

  return 'text-text-secondary hover:bg-white/[0.035] hover:text-horizon-white'
}

function activeRailClass(item) {
  const tone = item?.tone ?? 'blue'

  switch (tone) {
    case 'magenta':
      return 'bg-[color:var(--horizon-sunset-magenta)]'
    case 'indigo':
      return 'bg-[color:var(--horizon-sunset-indigo)]'
    case 'red':
      return 'bg-red-300'
    case 'cyan':
    case 'blue':
    default:
      return 'bg-[color:var(--horizon-sunset-blue)]'
  }
}

watch(
  () => page.url,
  () => {
    closeMobileNav()
    manuallyExpandedGroups.value = new Set()
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
    class="fixed left-3 top-3 z-[80] inline-flex items-center gap-2 rounded-xl bg-[color:var(--horizon-void-700)] px-3 py-2 text-horizon-white shadow-lg md:hidden"
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
      class="absolute inset-0 bg-black/70 backdrop-blur-sm"
      @click="closeMobileNav"
    ></div>

    <aside class="absolute inset-y-0 left-0 w-[18.5rem] max-w-[85vw] animate-[hz-slide-in-left_180ms_ease-out] overflow-hidden border-r border-white/10 bg-[linear-gradient(180deg,var(--horizon-void-700),var(--horizon-void-900))] backdrop-blur-xl">
      <div class="flex h-full min-h-0 flex-col gap-4 p-4">
        <div class="-mx-4 -mt-4 shrink-0 overflow-hidden border-b border-white/10 bg-black">
          <img
            src="/images/Horizon_GIF.gif"
            alt="Horizon Interstellar"
            class="block h-32 w-full scale-200 object-contain -translate-y-1"
          />
        </div>

        <div class="flex shrink-0 items-center justify-between gap-3 px-1 py-1">
          <div class="min-w-0">
            <div class="text-base font-bold uppercase tracking-[0.24em] text-[color:var(--horizon-text-secondary)]">
              Navigation
            </div>

            <div class="mt-1 truncate text-[11px] text-text-muted">
              Horizon command rail
            </div>
          </div>

          <button
            type="button"
            class="shrink-0 rounded-lg px-3 py-2 text-sm font-semibold text-text-secondary transition hover:bg-white/[0.04] hover:text-horizon-white"
            @click="closeMobileNav"
          >
            Close
          </button>
        </div>

        <nav class="flex min-h-0 flex-1 flex-col gap-5 overflow-auto px-1">
          <section
            v-for="group in navGroups"
            :key="group.key"
            class="space-y-1.5"
          >
            <button
              type="button"
              class="flex w-full items-center justify-between px-2 py-1 text-left text-xs font-black uppercase tracking-[0.24em] transition"
              :class="groupLabelClass(group)"
              @click="toggleGroup(group)"
            >
              <span class="truncate">{{ group.label }}</span>
              <span class="text-sm transition-transform" :class="groupIsOpen(group) ? 'rotate-90' : ''">›</span>
            </button>

            <div v-if="groupIsOpen(group)" class="space-y-1">
              <template
                v-for="item in group.items"
                :key="item.key"
              >
                <Link
                  v-if="!item.href"
                  :href="getItemHref(item)"
                  class="group relative flex items-center gap-3 rounded-xl px-3 py-2.5 text-base font-semibold transition"
                  :class="itemRowClass(item)"
                  @click="closeMobileNav"
                >
                  <span
                    v-if="item.isActive"
                    class="absolute left-0 top-1/2 h-7 w-1 -translate-y-1/2 rounded-r-full"
                    :class="activeRailClass(item)"
                  />

                  <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-white/[0.035] text-sm leading-none">
                    {{ item.icon }}
                  </span>

                  <span class="min-w-0 flex-1 truncate">
                    {{ item.label }}
                  </span>

                  <span
                    v-if="item.badge"
                    class="max-w-20 shrink-0 truncate rounded-full bg-white/[0.05] px-2 py-0.5 text-[10px] text-text-secondary"
                  >
                    {{ item.badge }}
                  </span>
                </Link>

                <a
                  v-else
                  :href="item.href"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="group relative flex items-center gap-3 rounded-xl px-3 py-2.5 text-base font-semibold text-text-secondary transition hover:bg-white/[0.035] hover:text-horizon-white"
                  @click="closeMobileNav"
                >
                  <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-white/[0.035] text-sm leading-none">
                    {{ item.icon }}
                  </span>

                  <span class="min-w-0 flex-1 truncate">
                    {{ item.label }}
                  </span>

                  <span class="text-sm text-text-muted">↗</span>
                </a>

                <div
                  v-if="itemHasOpenChildren(item)"
                  class="ml-6 mt-1 space-y-1 border-l border-white/10 pl-3"
                >
                  <div
                    v-for="child in item.children"
                    :key="child.key"
                    class="space-y-1"
                  >
                    <Link
                      :href="child.href"
                      class="flex items-center justify-between gap-2 rounded-lg px-2.5 py-1.5 text-sm font-semibold transition"
                      :class="child.isActive ? 'bg-white/[0.055] text-horizon-white' : 'text-text-muted hover:bg-white/[0.03] hover:text-text-secondary'"
                      @click="closeMobileNav"
                    >
                      <span class="truncate">{{ child.label }}</span>
                      <span v-if="child.meta" class="shrink-0 text-[10px] text-text-muted">{{ child.meta }}</span>
                    </Link>

                    <div
                      v-if="child.isActive && child.entries?.length"
                      class="ml-3 space-y-1 border-l border-white/10 pl-2"
                    >
                      <Link
                        v-for="entry in child.entries"
                        :key="entry.key"
                        :href="entry.href"
                        class="block rounded-lg px-2 py-1.5 text-[11px] font-semibold leading-4 transition"
                        :class="entry.isActive ? 'bg-white/[0.055] text-horizon-white' : 'text-text-muted hover:bg-white/[0.03] hover:text-text-secondary'"
                        @click="closeMobileNav"
                      >
                        {{ entry.label }}
                      </Link>
                    </div>
                  </div>
                </div>
              </template>
            </div>
          </section>
        </nav>

        <div class="shrink-0 border-t border-white/10 px-1 pt-4">
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
                class="h-11 w-11 rounded-xl object-cover"
              />

              <div
                v-else
                class="flex h-11 w-11 items-center justify-center rounded-xl bg-white/[0.04] text-sm font-black text-horizon-white"
              >
                {{ String(user.rsi_handle ?? user.discord_name ?? 'M').slice(0, 1).toUpperCase() }}
              </div>
            </Link>

            <div class="min-w-0">
              <div class="text-[11px] text-text-muted">
                Personnel File
              </div>

              <div
                class="truncate text-sm font-bold text-horizon-white"
                :style="userNameColor ? { color: userNameColor } : undefined"
              >
                {{ user.rsi_handle ?? user.discord_name ?? 'Member' }}
              </div>

              <div class="truncate text-sm text-text-secondary">
                {{ rankName }}<span v-if="directorBadgeLabel"> · {{ directorBadgeLabel }}</span>
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
    :class="desktopExpanded ? 'w-[18rem]' : 'w-[5.25rem]'"
  >
    <div class="relative h-full overflow-hidden border-r border-white/10 bg-[linear-gradient(180deg,var(--horizon-void-700),var(--horizon-void-900))] backdrop-blur-xl">
      <div class="relative flex h-full min-h-0 flex-col gap-4 p-4">
        <div
          class="-mx-4 -mt-4 shrink-0 overflow-hidden border-b border-white/10 bg-black transition-all duration-300"
          :class="desktopExpanded ? 'h-32' : 'h-20'"
        >
          <img
            src="/images/Horizon_GIF.gif"
            alt="Horizon Interstellar"
            class="block w-full object-contain transition-all duration-300"
            :class="desktopExpanded ? 'h-32 scale-200 -translate-y-1' : 'h-20 scale-175 -translate-y-1'"
          />
        </div>

        <div
          class="flex shrink-0 items-center py-1 transition-all duration-300"
          :class="desktopExpanded ? 'justify-between gap-3 px-1' : 'justify-center px-0'"
        >
          <div
            class="min-w-0 transition-all duration-200"
            :class="desktopExpanded ? 'opacity-100' : 'pointer-events-none w-0 opacity-0'"
          >
            <div class="text-base font-bold uppercase tracking-[0.24em] text-[color:var(--horizon-text-secondary)]">
              Navigation
            </div>

            <div class="mt-1 truncate text-[11px] text-text-muted">
              Horizon command rail
            </div>
          </div>

          <button
            type="button"
            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-text-secondary transition hover:bg-white/[0.04] hover:text-horizon-white"
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

        <nav class="flex min-h-0 flex-1 flex-col gap-5 overflow-auto px-1">
          <section
            v-for="group in navGroups"
            :key="group.key"
            class="space-y-1.5"
          >
            <button
              v-if="desktopExpanded"
              type="button"
              class="flex w-full items-center justify-between px-2 py-1 text-left text-xs font-black uppercase tracking-[0.24em] transition"
              :class="groupLabelClass(group)"
              @click="toggleGroup(group)"
            >
              <span class="truncate">{{ group.label }}</span>
              <span class="text-sm transition-transform" :class="groupIsOpen(group) ? 'rotate-90' : ''">›</span>
            </button>

            <div
              v-if="desktopExpanded ? groupIsOpen(group) : true"
              class="space-y-1"
            >
              <template
                v-for="item in group.items"
                :key="item.key"
              >
                <Link
                  v-if="!item.href"
                  :href="getItemHref(item)"
                  class="group relative flex items-center rounded-xl text-base font-semibold transition"
                  :class="[
                    desktopExpanded ? 'gap-3 px-3 py-2.5' : 'justify-center px-2 py-2.5',
                    itemRowClass(item)
                  ]"
                  :title="desktopExpanded ? undefined : item.label"
                >
                  <span
                    v-if="item.isActive"
                    class="absolute left-0 top-1/2 h-7 w-1 -translate-y-1/2 rounded-r-full"
                    :class="activeRailClass(item)"
                  />

                  <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-white/[0.035] text-sm leading-none">
                    {{ item.icon }}
                  </span>

                  <span
                    class="min-w-0 flex-1 truncate transition-all duration-200"
                    :class="desktopExpanded ? 'opacity-100' : 'pointer-events-none w-0 opacity-0'"
                  >
                    {{ item.label }}
                  </span>

                  <span
                    v-if="desktopExpanded && item.badge"
                    class="ml-auto max-w-20 shrink-0 truncate rounded-full bg-white/[0.05] px-2 py-0.5 text-[10px] leading-none text-text-secondary"
                  >
                    {{ item.badge }}
                  </span>
                </Link>

                <a
                  v-else
                  :href="item.href"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="group relative flex items-center rounded-xl text-base font-semibold text-text-secondary transition hover:bg-white/[0.035] hover:text-horizon-white"
                  :class="desktopExpanded ? 'gap-3 px-3 py-2.5' : 'justify-center px-2 py-2.5'"
                  :title="desktopExpanded ? undefined : item.label"
                >
                  <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-white/[0.035] text-sm leading-none">
                    {{ item.icon }}
                  </span>

                  <span
                    class="min-w-0 flex-1 truncate transition-all duration-200"
                    :class="desktopExpanded ? 'opacity-100' : 'pointer-events-none w-0 opacity-0'"
                  >
                    {{ item.label }}
                  </span>

                  <span
                    v-if="desktopExpanded"
                    class="ml-auto text-sm text-text-muted"
                  >
                    ↗
                  </span>
                </a>

                <div
                  v-if="desktopExpanded && itemHasOpenChildren(item)"
                  class="ml-6 mt-1 space-y-1 border-l border-white/10 pl-3"
                >
                  <div
                    v-for="child in item.children"
                    :key="child.key"
                    class="space-y-1"
                  >
                    <Link
                      :href="child.href"
                      class="flex items-center justify-between gap-2 rounded-lg px-2.5 py-1.5 text-sm font-semibold transition"
                      :class="child.isActive ? 'bg-white/[0.055] text-horizon-white' : 'text-text-muted hover:bg-white/[0.03] hover:text-text-secondary'"
                    >
                      <span class="truncate">{{ child.label }}</span>
                      <span v-if="child.meta" class="shrink-0 text-[10px] text-text-muted">{{ child.meta }}</span>
                    </Link>

                    <div
                      v-if="child.isActive && child.entries?.length"
                      class="ml-3 space-y-1 border-l border-white/10 pl-2"
                    >
                      <Link
                        v-for="entry in child.entries"
                        :key="entry.key"
                        :href="entry.href"
                        class="block rounded-lg px-2 py-1.5 text-[11px] font-semibold leading-4 transition"
                        :class="entry.isActive ? 'bg-white/[0.055] text-horizon-white' : 'text-text-muted hover:bg-white/[0.03] hover:text-text-secondary'"
                      >
                        {{ entry.label }}
                      </Link>
                    </div>
                  </div>
                </div>
              </template>
            </div>
          </section>
        </nav>

        <Link
          :href="profileHref"
          class="shrink-0 border-t border-white/10 pt-4 transition-all duration-300"
          :class="desktopExpanded ? 'px-1' : 'px-0'"
        >
          <div
            class="flex items-center"
            :class="desktopExpanded ? 'gap-3' : 'justify-center'"
          >
            <img
              v-if="user.discord_avatar"
              :src="user.discord_avatar"
              alt=""
              class="h-11 w-11 shrink-0 rounded-xl object-cover"
            />

            <div
              v-else
              class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white/[0.04] text-sm font-black text-horizon-white"
            >
              {{ String(user.rsi_handle ?? user.discord_name ?? 'M').slice(0, 1).toUpperCase() }}
            </div>

            <div
              class="min-w-0 transition-all duration-200"
              :class="desktopExpanded ? 'opacity-100' : 'pointer-events-none w-0 opacity-0'"
            >
              <div class="text-[11px] text-text-muted">
                Personnel File
              </div>

              <div
                class="truncate text-sm font-bold text-horizon-white"
                :style="userNameColor ? { color: userNameColor } : undefined"
              >
                {{ user.rsi_handle ?? user.discord_name ?? 'Member' }}
              </div>

              <div class="truncate text-sm text-text-secondary">
                {{ rankName }}<span v-if="directorBadgeLabel"> · {{ directorBadgeLabel }}</span>
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








