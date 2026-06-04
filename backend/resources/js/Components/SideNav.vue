<script setup>
import { computed, ref, watch, onMounted, onBeforeUnmount } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'

import { canCreateOperation, isDirectorLike as userIsDirectorLike } from '@/auth'
import { getHighestOrgRoleSlug, getOrgRoleColor } from '@/roleColors'

const page = usePage()

const user = computed(() => page.props?.auth?.user ?? null)
const archiveNavigation = computed(() => page.props?.archiveNavigation ?? { categories: [] })
const ledgerEnabled = computed(() => Boolean(page.props?.features?.ledger))

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

const mySquadronLedgerRouteName = computed(() => {
  if (!mySquadron.value) return null

  return mySquadron.value?.slug ? 'squadrons.ledger' : 'squadrons.ledgerById'
})

const mySquadronLedgerRouteParams = computed(() => {
  if (!mySquadron.value) return undefined

  return {
    squadron: mySquadron.value.slug ?? mySquadron.value.id,
  }
})

const squadronLedgerActive = computed(() => {
  if (!mySquadron.value) return false

  if (mySquadron.value?.slug) {
    return page.url === `/squadrons/${mySquadron.value.slug}/ledger`
      || page.url === `/squadrons/${mySquadron.value.slug}/ledger/`
      || page.url.startsWith(`/squadrons/${mySquadron.value.slug}/ledger?`)
  }

  return /^\/squadrons\/\d+\/ledger(\/|\?|$)/.test(page.url ?? '')
})

const canSeeOrgLedger = computed(() => {
  if (page.props?.auth?.can?.['ledger.manage-org-ledger']) {
    return true
  }

  const roleSlugs = (user.value?.roles ?? [])
    .map(role => role?.slug ?? role?.name ?? role)
    .filter(Boolean)

  return roleSlugs.includes('director') || roleSlugs.includes('grand_admiral')
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

function normalizeArchiveHref(href) {
  if (!href) return ''

  try {
    const resolved = new URL(href, window.location.origin)
    return `${resolved.pathname}${resolved.search}`
  } catch {
    return String(href)
  }
}

const archiveCategoryChildren = computed(() => {
  const url = page.url ?? ''
  return (archiveNavigation.value?.categories ?? []).map(category => {
    const categoryHref = normalizeArchiveHref(category.href)
    const directEntries = (category.entries ?? []).map(entry => {
      const entryHref = normalizeArchiveHref(entry.href)

      return {
        key: `archive-category-entry-${entry.id}`,
        label: entry.title,
        href: entry.href,
        isActive: url === entryHref || url.startsWith(`${entryHref}?`),
      }
    })

    const topics = (category.topics ?? []).map(topic => {
      const topicHref = normalizeArchiveHref(topic.href)
      const topicEntries = (topic.entries ?? []).map(entry => {
        const entryHref = normalizeArchiveHref(entry.href)

        return {
          key: `archive-entry-${entry.id}`,
          label: entry.title,
          href: entry.href,
          isActive: url === entryHref || url.startsWith(`${entryHref}?`),
        }
      })

      const topicIsActive = url === topicHref || url.startsWith(`${topicHref}/`) || url.startsWith(`${topicHref}?`) || topicEntries.some(entry => entry.isActive)

      return {
        key: `archive-topic-${topic.id}`,
        label: topic.title,
        href: topic.href,
        isActive: topicIsActive,
        meta: topic.visible_entries_count ? `${topic.visible_entries_count}` : null,
        entries: topicEntries,
      }
    })

    const isActive = url === categoryHref
      || url.startsWith(`${categoryHref}/`)
      || url.startsWith(`${categoryHref}?`)
      || directEntries.some(entry => entry.isActive)
      || topics.some(topic => topic.isActive)

    return {
      key: `archive-category-${category.id}`,
      label: category.name,
      href: category.href,
      sortOrder: Number(category.sort_order ?? Number.MAX_SAFE_INTEGER),
      topics,
      entries: directEntries,
      visibleEntriesCount: Number(category.visible_entries_count ?? 0),
      isActive,
      meta: category.visible_entries_count ? `${category.visible_entries_count}` : null,
    }
  }).sort((left, right) => {
    if (left.sortOrder !== right.sortOrder) {
      return left.sortOrder - right.sortOrder
    }

    return left.label.localeCompare(right.label)
  })
})

const adminDashboardChildren = computed(() => {
  if (!canSeeEverything.value) {
    return []
  }

  const url = page.url ?? ''
  const adminTabChildren = [
    {
      key: 'admin-users',
      label: 'Users',
      href: route('admin.dashboard', { tab: 'users' }),
      isActive: url === '/admin' || url === '/admin/' || url === '/admin/dashboard' || url === '/admin/dashboard/' || url === '/admin?tab=users' || url === '/admin/dashboard?tab=users',
      meta: null,
      showJump: false,
    },
    {
      key: 'admin-squadrons',
      label: 'Squadrons',
      href: route('admin.dashboard', { tab: 'squadrons' }),
      isActive: url === '/admin?tab=squadrons' || url === '/admin/dashboard?tab=squadrons',
      meta: null,
      showJump: false,
    },
    {
      key: 'admin-roles',
      label: 'Roles',
      href: route('admin.dashboard', { tab: 'roles' }),
      isActive: url === '/admin?tab=roles' || url === '/admin/dashboard?tab=roles',
      meta: null,
      showJump: false,
    },
    {
      key: 'admin-operations',
      label: 'Operations',
      href: route('admin.dashboard', { tab: 'operations' }),
      isActive: url === '/admin?tab=operations' || url === '/admin/dashboard?tab=operations',
      meta: null,
      showJump: false,
    },
    {
      key: 'admin-media',
      label: 'Media',
      href: route('admin.dashboard', { tab: 'media' }),
      isActive: url === '/admin?tab=media' || url === '/admin/dashboard?tab=media',
      meta: null,
      showJump: false,
    },
    {
      key: 'admin-ledger',
      label: 'Ledger',
      href: route('admin.dashboard', { tab: 'ledger' }),
      isActive: url === '/admin?tab=ledger' || url === '/admin/dashboard?tab=ledger',
      meta: null,
      showJump: false,
    },
    {
      key: 'admin-uex',
      label: 'UEX',
      href: route('admin.dashboard', { tab: 'uex' }),
      isActive: url === '/admin?tab=uex' || url === '/admin/dashboard?tab=uex',
      meta: null,
      showJump: false,
    },
    {
      key: 'admin-archive-manage',
      label: 'Archive Management',
      href: route('admin.archive.index'),
      isActive: url.startsWith('/admin/archive') && !url.startsWith('/admin/archive/trash') && !url.startsWith('/admin/archive/audit') && !url.startsWith('/admin/archive/taxonomy'),
      meta: null,
      showJump: false,
    },
    {
      key: 'admin-archive-trash',
      label: 'Archive Trash',
      href: route('admin.archive.trash.index'),
      isActive: url.startsWith('/admin/archive/trash'),
      meta: null,
      showJump: false,
    },
    {
      key: 'admin-archive-audit',
      label: 'Archive Audit',
      href: route('admin.archive.audit.index'),
      isActive: url.startsWith('/admin/archive/audit'),
      meta: null,
      showJump: false,
    },
  ]

  return adminTabChildren
})

const NAV_ICON_PATHS = Object.freeze({
  home: [
    { d: 'M3.75 10.5 12 4l8.25 6.5' },
    { d: 'M5.75 9.75V20h12.5V9.75' },
  ],
  operations: [
    { d: 'M12 3.5v3.25' },
    { d: 'M12 17.25v3.25' },
    { d: 'M3.5 12h3.25' },
    { d: 'M17.25 12h3.25' },
    { d: 'M6.75 6.75l2.2 2.2' },
    { d: 'M15.05 15.05l2.2 2.2' },
    { d: 'M17.25 6.75l-2.2 2.2' },
    { d: 'M8.95 15.05l-2.2 2.2' },
    { d: 'M12 8.25a3.75 3.75 0 1 0 0 7.5a3.75 3.75 0 0 0 0-7.5Z' },
  ],
  operations_dashboard: [
    { d: 'M4.5 19.5h15' },
    { d: 'M7.5 16v-4.5' },
    { d: 'M12 16V8' },
    { d: 'M16.5 16v-6.5' },
    { d: 'M5 6h14' },
  ],
  squadrons: [
    { d: 'M12 3.75 18.5 7.5v9L12 20.25 5.5 16.5v-9L12 3.75Z' },
  ],
  my_squadron: [
    { d: 'M12 3.75 18.5 6v5.5c0 4.05-2.55 7.15-6.5 9.5-3.95-2.35-6.5-5.45-6.5-9.5V6L12 3.75Z' },
    { d: 'M9.25 11.75 11 13.5l3.75-3.75' },
  ],
  members: [
    { d: 'M8 11a3 3 0 1 0 0-6a3 3 0 0 0 0 6Z' },
    { d: 'M16.5 12a2.5 2.5 0 1 0 0-5a2.5 2.5 0 0 0 0 5Z' },
    { d: 'M3.75 19.25a4.75 4.75 0 0 1 8.5-2.9' },
    { d: 'M13.5 18.25a4 4 0 0 1 5.75-1.35' },
  ],
  ledger: [
    { d: 'M6.5 5.25h9.25A2.25 2.25 0 0 1 18 7.5v11.25H8.75A2.25 2.25 0 0 0 6.5 21' },
    { d: 'M6.5 5.25A2.25 2.25 0 0 0 4.25 7.5v11.25A2.25 2.25 0 0 1 6.5 21' },
    { d: 'M8.75 8.75h6.25' },
  ],
  squadron_ledger: [
    { d: 'M4.75 8.5h14.5V19H4.75z' },
    { d: 'M7.5 8.5V6.75A1.75 1.75 0 0 1 9.25 5h5.5a1.75 1.75 0 0 1 1.75 1.75V8.5' },
    { d: 'M10 13h4' },
  ],
  treasury: [
    { d: 'M12 5.5c-4.14 0-7.5 1.46-7.5 3.25S7.86 12 12 12s7.5-1.46 7.5-3.25S16.14 5.5 12 5.5Z' },
    { d: 'M4.5 8.75V12c0 1.79 3.36 3.25 7.5 3.25s7.5-1.46 7.5-3.25V8.75' },
    { d: 'M4.5 12v3.25c0 1.79 3.36 3.25 7.5 3.25s7.5-1.46 7.5-3.25V12' },
  ],
  shop_bag: [
    { d: 'M6.25 8.25h11.5l-.9 10.75H7.15L6.25 8.25Z' },
    { d: 'M8.75 8.25V6.5a3.25 3.25 0 0 1 6.5 0v1.75' },
  ],
  shop_store: [
    { d: 'M4.5 8.5h15l-1 10.5h-13L4.5 8.5Z' },
    { d: 'M6.75 8.5V7a3.25 3.25 0 0 1 3.25-3.25h4A3.25 3.25 0 0 1 17.25 7v1.5' },
  ],
  archive: [
    { d: 'M4.75 7.75h14.5v11.5H4.75z' },
    { d: 'M8.25 7.75V6.25A1.75 1.75 0 0 1 10 4.5h4a1.75 1.75 0 0 1 1.75 1.75v1.5' },
    { d: 'M9.5 12h5' },
  ],
  admin: [
    { d: 'M12 8.75a3.25 3.25 0 1 0 0 6.5a3.25 3.25 0 0 0 0-6.5Z' },
    { d: 'M12 3.75v2.1' },
    { d: 'M12 18.15v2.1' },
    { d: 'M5.65 5.65l1.5 1.5' },
    { d: 'M16.85 16.85l1.5 1.5' },
    { d: 'M3.75 12h2.1' },
    { d: 'M18.15 12h2.1' },
    { d: 'M5.65 18.35l1.5-1.5' },
    { d: 'M16.85 7.15l1.5-1.5' },
  ],
})

function navIconPaths(icon) {
  return NAV_ICON_PATHS[icon] ?? NAV_ICON_PATHS.home
}

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
          icon: 'home',
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
          icon: 'operations',
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
          icon: 'operations_dashboard',
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
          icon: 'squadrons',
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
          icon: 'my_squadron',
          routeName: 'squadrons.show',
          params: mySquadron.value ? mySquadron.value.slug : undefined,
          isActive: mySquadron.value ? url === `/squadrons/${mySquadron.value.slug}` : false,
          show: !!mySquadron.value,
          tone: 'cyan',
          status: 'Assigned',
        },
        {
          key: 'members_index',
          label: 'Members',
          shortLabel: 'Members',
          icon: 'members',
          routeName: 'members.index',
          params: undefined,
          isActive: url === '/members' || url === '/members/' || url.startsWith('/members?'),
          tone: 'blue',
          status: 'Roster',
        },
      ],
    },
    {
      key: 'ledgers',
      label: 'Assets & Funds',
      eyebrow: 'Personal + shared assets',
      tone: 'amber',
      show: ledgerEnabled.value,
      items: [
        {
          key: 'ledger',
          label: 'My Assets & Funds',
          shortLabel: 'Assets',
          icon: 'ledger',
          routeName: 'ledger.index',
          params: undefined,
          isActive: url === '/ledger' || url === '/ledger/' || url.startsWith('/ledger?'),
          tone: 'indigo',
          status: 'Personal',
        },
        {
          key: 'squadron_ledger',
          label: 'Squadron Assets & Funds',
          shortLabel: 'Assets',
          icon: 'squadron_ledger',
          routeName: mySquadronLedgerRouteName.value,
          params: mySquadronLedgerRouteParams.value,
          isActive: squadronLedgerActive.value,
          show: !!mySquadron.value,
          tone: 'cyan',
          status: 'Squadron',
        },
        {
          key: 'org_ledger',
          label: 'Horizon Treasury',
          shortLabel: 'Horizon',
          icon: 'treasury',
          routeName: 'organization.ledger',
          params: undefined,
          isActive: url === '/organization/ledger' || url === '/organization/ledger/' || url.startsWith('/organization/ledger?'),
          show: canSeeOrgLedger.value,
          tone: 'amber',
          status: 'Org',
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
          icon: 'shop_bag',
          href: 'https://horizon-interstellar.myspreadshop.net/',
          isActive: false,
          tone: 'cyan',
          status: 'Europe',
        },
        {
          key: 'us_shop',
          label: 'US Shop',
          shortLabel: 'US Shop',
          icon: 'shop_store',
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
          icon: 'archive',
          routeName: 'archive.index',
          params: undefined,
          isActive: url === '/archive' || url === '/archive/' || url.startsWith('/archive?') || url.startsWith('/archive/'),
          tone: 'magenta',
          status: 'Docs',
          children: archiveCategoryChildren.value,
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
          icon: 'admin',
          routeName: 'admin.dashboard',
          params: undefined,
          isActive: url.startsWith('/admin'),
          tone: 'red',
          status: 'Admin',
          children: adminDashboardChildren.value,
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
const manuallyCollapsedGroups = ref(new Set())
const manuallyExpandedItems = ref(new Set())
const manuallyCollapsedItems = ref(new Set())

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
  const nextExpanded = new Set(manuallyExpandedGroups.value)
  const nextCollapsed = new Set(manuallyCollapsedGroups.value)

  if (groupIsOpen(group)) {
    nextExpanded.delete(group.key)
    nextCollapsed.add(group.key)
  } else {
    nextCollapsed.delete(group.key)
    nextExpanded.add(group.key)
  }

  manuallyExpandedGroups.value = nextExpanded
  manuallyCollapsedGroups.value = nextCollapsed
}

function groupIsActive(group) {
  return activeGroupKeys.value.has(group.key)
}

function groupIsOpen(group) {
  if (manuallyCollapsedGroups.value.has(group.key)) {
    return false
  }

  return groupIsActive(group) || manuallyExpandedGroups.value.has(group.key)
}

function toggleItem(item) {
  const nextExpanded = new Set(manuallyExpandedItems.value)
  const nextCollapsed = new Set(manuallyCollapsedItems.value)

  if (itemIsOpen(item)) {
    nextExpanded.delete(item.key)
    nextCollapsed.add(item.key)
  } else {
    nextCollapsed.delete(item.key)
    nextExpanded.add(item.key)
  }

  manuallyExpandedItems.value = nextExpanded
  manuallyCollapsedItems.value = nextCollapsed
}

function getItemHref(item) {
  if (item.href) return item.href

  return route(item.routeName, item.params)
}

function itemHasOpenChildren(item) {
  return Boolean(item.children?.length)
}

function itemIsOpen(item) {
  const hasNestedContent = Boolean(item.children?.length || item.entries?.length || item.topics?.length)

  if (!hasNestedContent) {
    return false
  }

  if (manuallyCollapsedItems.value.has(item.key)) {
    return false
  }

  return item.isActive || manuallyExpandedItems.value.has(item.key)
}

function itemChildrenAreOpen(item) {
  return Boolean((item.entries?.length || item.topics?.length) && itemIsOpen(item))
}

function itemChildListIsOpen(item) {
  return Boolean(item.children?.length && itemIsOpen(item))
}

function sidebarExpansionContext(url) {
  const value = String(url ?? '')

  if (value.startsWith('/archive')) return 'archive'
  if (value.startsWith('/admin')) return 'admin'

  return null
}

function archiveCategoryRowClass(item) {
  if (item.isActive) {
    return 'bg-white/[0.08] text-horizon-white'
  }

  if (itemChildrenAreOpen(item)) {
    return 'bg-white/[0.05] text-text-secondary'
  }

  return 'text-text-secondary hover:bg-white/[0.03] hover:text-horizon-white'
}

function archiveCategoryMetaClass(item) {
  if (item.isActive) {
    return 'text-horizon-white/80'
  }

  if (itemChildrenAreOpen(item)) {
    return 'text-text-secondary'
  }

  return 'text-text-muted'
}

function archiveTopicRowClass(item) {
  if (item.isActive) {
    return 'bg-white/[0.07] text-horizon-white'
  }

  if (itemChildrenAreOpen(item)) {
    return 'bg-white/[0.04] text-text-secondary'
  }

  return 'text-text-muted hover:bg-white/[0.03] hover:text-text-secondary'
}

function archiveTopicMetaClass(item) {
  if (item.isActive) {
    return 'text-horizon-white/80'
  }

  if (itemChildrenAreOpen(item)) {
    return 'text-text-secondary'
  }

  return 'text-text-muted'
}

function archiveEntryRowClass(item) {
  if (item.isActive) {
    return 'bg-white/[0.06] text-horizon-white'
  }

  return 'text-text-muted hover:bg-white/[0.03] hover:text-text-secondary'
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
  (nextUrl, previousUrl) => {
    const nextContext = sidebarExpansionContext(nextUrl)
    const previousContext = sidebarExpansionContext(previousUrl)

    if (nextContext !== previousContext) {
      closeMobileNav()
      manuallyExpandedGroups.value = new Set()
      manuallyCollapsedGroups.value = new Set()
      manuallyExpandedItems.value = new Set()
      manuallyCollapsedItems.value = new Set()
    }
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
                <div
                  v-if="!item.href && itemHasOpenChildren(item)"
                  class="flex items-center gap-1"
                >
                  <Link
                    :href="getItemHref(item)"
                    class="group relative flex min-w-0 flex-1 items-center gap-3 rounded-xl px-3 py-2.5 text-base font-semibold transition"
                    :class="itemRowClass(item)"
                    @click="closeMobileNav"
                  >
                    <span
                      v-if="item.isActive"
                      class="absolute left-0 top-1/2 h-7 w-1 -translate-y-1/2 rounded-r-full"
                      :class="activeRailClass(item)"
                    />

                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-white/[0.035] text-horizon-white/80">
                      <svg viewBox="0 0 24 24" class="h-[18px] w-[18px]" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path v-for="(path, index) in navIconPaths(item.icon)" :key="`${item.key}-mobile-link-${index}`" :d="path.d" />
                      </svg>
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

                  <button
                    type="button"
                    class="shrink-0 rounded-lg px-2 py-1.5 text-xs font-bold text-text-muted transition hover:bg-white/[0.03] hover:text-text-secondary"
                    @click.stop="toggleItem(item)"
                  >
                    <span class="block transition-transform" :class="itemChildListIsOpen(item) ? 'rotate-90' : ''">›</span>
                  </button>
                </div>

                <Link
                  v-else-if="!item.href"
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

                  <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-white/[0.035] text-horizon-white/80">
                    <svg viewBox="0 0 24 24" class="h-[18px] w-[18px]" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                      <path v-for="(path, index) in navIconPaths(item.icon)" :key="`${item.key}-mobile-link-${index}`" :d="path.d" />
                    </svg>
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
                  <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-white/[0.035] text-horizon-white/80">
                    <svg viewBox="0 0 24 24" class="h-[18px] w-[18px]" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                      <path v-for="(path, index) in navIconPaths(item.icon)" :key="`${item.key}-mobile-external-${index}`" :d="path.d" />
                    </svg>
                  </span>

                  <span class="min-w-0 flex-1 truncate">
                    {{ item.label }}
                  </span>

                  <span class="text-sm text-text-muted">↗</span>
                </a>

                <div
                  v-if="itemChildListIsOpen(item)"
                  class="ml-6 mt-1 space-y-1 border-l border-white/10 pl-3"
                >
                  <div
                    v-for="child in item.children"
                    :key="child.key"
                    class="space-y-1"
                  >
                    <div class="flex items-center gap-1">
                      <Link
                        v-if="!(child.topics?.length || child.entries?.length)"
                        :href="child.href"
                        class="min-w-0 flex-1 rounded-lg px-2.5 py-1.5 text-left text-sm font-semibold transition"
                        :class="archiveCategoryRowClass(child)"
                        @click="closeMobileNav"
                      >
                        <div class="flex items-center justify-between gap-2">
                          <span class="truncate">{{ child.label }}</span>
                          <span v-if="child.meta" class="shrink-0 text-[10px]" :class="archiveCategoryMetaClass(child)">{{ child.meta }}</span>
                        </div>
                      </Link>

                      <button
                        v-else
                        type="button"
                        class="min-w-0 flex-1 rounded-lg px-2.5 py-1.5 text-left text-sm font-semibold transition"
                        :class="archiveCategoryRowClass(child)"
                        @click="toggleItem(child)"
                      >
                        <div class="flex items-center justify-between gap-2">
                          <span class="truncate">{{ child.label }}</span>
                          <span v-if="child.meta" class="shrink-0 text-[10px]" :class="archiveCategoryMetaClass(child)">{{ child.meta }}</span>
                        </div>
                      </button>

                      <Link
                        v-if="child.showJump !== false"
                        :href="child.href"
                        class="shrink-0 rounded-lg px-2 py-1.5 text-xs font-bold text-text-muted transition hover:bg-white/[0.03] hover:text-text-secondary"
                      >
                        ↗
                      </Link>

                      <button
                        v-if="child.topics?.length || child.entries?.length"
                        type="button"
                        class="shrink-0 rounded-lg px-2 py-1.5 text-xs font-bold text-text-muted transition hover:bg-white/[0.03] hover:text-text-secondary"
                        @click.stop="toggleItem(child)"
                      >
                        <span class="block transition-transform" :class="itemChildrenAreOpen(child) ? 'rotate-90' : ''">›</span>
                      </button>
                    </div>

                    <div
                      v-if="itemChildrenAreOpen(child) && (child.entries?.length || child.topics?.length)"
                      class="ml-3 space-y-1 border-l border-white/10 pl-2"
                    >
                      <Link
                        v-for="entry in child.entries"
                        :key="entry.key"
                        :href="entry.href"
                        class="block rounded-lg px-2 py-1.5 text-[11px] font-semibold leading-4 transition"
                        :class="archiveEntryRowClass(entry)"
                      >
                        {{ entry.label }}
                      </Link>

                      <div
                        v-for="topic in child.topics"
                        :key="topic.key"
                        class="space-y-1"
                      >
                        <div class="flex items-center gap-1">
                          <button
                            type="button"
                            class="min-w-0 flex-1 rounded-lg px-2.5 py-1.5 text-left text-sm font-semibold transition"
                            :class="archiveTopicRowClass(topic)"
                            @click="toggleItem(topic)"
                          >
                            <div class="flex items-center justify-between gap-2">
                              <span class="truncate">{{ topic.label }}</span>
                              <span v-if="topic.meta" class="shrink-0 text-[10px]" :class="archiveTopicMetaClass(topic)">{{ topic.meta }}</span>
                            </div>
                          </button>

                          <Link
                            :href="topic.href"
                            class="shrink-0 rounded-lg px-2 py-1.5 text-xs font-bold text-text-muted transition hover:bg-white/[0.03] hover:text-text-secondary"
                          >
                            ↗
                          </Link>

                          <button
                            v-if="topic.entries?.length"
                            type="button"
                            class="shrink-0 rounded-lg px-2 py-1.5 text-xs font-bold text-text-muted transition hover:bg-white/[0.03] hover:text-text-secondary"
                            @click.stop="toggleItem(topic)"
                          >
                            <span class="block transition-transform" :class="itemChildrenAreOpen(topic) ? 'rotate-90' : ''">›</span>
                          </button>
                        </div>

                        <div
                          v-if="itemChildrenAreOpen(topic) && topic.entries?.length"
                          class="ml-3 space-y-1 border-l border-white/10 pl-2"
                        >
                          <Link
                            v-for="entry in topic.entries"
                            :key="entry.key"
                            :href="entry.href"
                            class="block rounded-lg px-2 py-1.5 text-[11px] font-semibold leading-4 transition"
                            :class="archiveEntryRowClass(entry)"
                          >
                            {{ entry.label }}
                          </Link>
                        </div>
                      </div>
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
                <div
                  v-if="!item.href && itemHasOpenChildren(item)"
                  class="flex items-center gap-1"
                >
                  <Link
                    :href="getItemHref(item)"
                    class="group relative flex min-w-0 flex-1 items-center rounded-xl text-base font-semibold transition"
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

                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-white/[0.035] text-horizon-white/80">
                      <svg viewBox="0 0 24 24" class="h-[18px] w-[18px]" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path v-for="(path, index) in navIconPaths(item.icon)" :key="`${item.key}-desktop-link-${index}`" :d="path.d" />
                      </svg>
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

                  <button
                    v-if="desktopExpanded"
                    type="button"
                    class="shrink-0 rounded-lg px-2 py-1.5 text-xs font-bold text-text-muted transition hover:bg-white/[0.03] hover:text-text-secondary"
                    @click.stop="toggleItem(item)"
                  >
                    <span class="block transition-transform" :class="itemChildListIsOpen(item) ? 'rotate-90' : ''">›</span>
                  </button>
                </div>

                <Link
                  v-else-if="!item.href"
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

                  <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-white/[0.035] text-horizon-white/80">
                    <svg viewBox="0 0 24 24" class="h-[18px] w-[18px]" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                      <path v-for="(path, index) in navIconPaths(item.icon)" :key="`${item.key}-desktop-link-${index}`" :d="path.d" />
                    </svg>
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
                  <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-white/[0.035] text-horizon-white/80">
                    <svg viewBox="0 0 24 24" class="h-[18px] w-[18px]" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                      <path v-for="(path, index) in navIconPaths(item.icon)" :key="`${item.key}-desktop-external-${index}`" :d="path.d" />
                    </svg>
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
                  v-if="desktopExpanded && itemChildListIsOpen(item)"
                  class="ml-6 mt-1 space-y-1 border-l border-white/10 pl-3"
                >
                  <div
                    v-for="child in item.children"
                    :key="child.key"
                    class="space-y-1"
                  >
                    <div class="flex items-center gap-1">
                      <Link
                        v-if="!(child.topics?.length || child.entries?.length)"
                        :href="child.href"
                        class="min-w-0 flex-1 rounded-lg px-2.5 py-1.5 text-left text-sm font-semibold transition"
                        :class="archiveCategoryRowClass(child)"
                      >
                        <div class="flex items-center justify-between gap-2">
                          <span class="truncate">{{ child.label }}</span>
                          <span v-if="child.meta" class="shrink-0 text-[10px]" :class="archiveCategoryMetaClass(child)">{{ child.meta }}</span>
                        </div>
                      </Link>

                      <button
                        v-else
                        type="button"
                        class="min-w-0 flex-1 rounded-lg px-2.5 py-1.5 text-left text-sm font-semibold transition"
                        :class="archiveCategoryRowClass(child)"
                        @click="toggleItem(child)"
                      >
                        <div class="flex items-center justify-between gap-2">
                          <span class="truncate">{{ child.label }}</span>
                          <span v-if="child.meta" class="shrink-0 text-[10px]" :class="archiveCategoryMetaClass(child)">{{ child.meta }}</span>
                        </div>
                      </button>

                      <Link
                        v-if="child.showJump !== false"
                        :href="child.href"
                        class="shrink-0 rounded-lg px-2 py-1.5 text-xs font-bold text-text-muted transition hover:bg-white/[0.03] hover:text-text-secondary"
                      >
                        ↗
                      </Link>

                      <button
                        v-if="child.topics?.length || child.entries?.length"
                        type="button"
                        class="shrink-0 rounded-lg px-2 py-1.5 text-xs font-bold text-text-muted transition hover:bg-white/[0.03] hover:text-text-secondary"
                        @click.stop="toggleItem(child)"
                      >
                        <span class="block transition-transform" :class="itemChildrenAreOpen(child) ? 'rotate-90' : ''">›</span>
                      </button>
                    </div>

                    <div
                      v-if="itemChildrenAreOpen(child) && (child.entries?.length || child.topics?.length)"
                      class="ml-3 space-y-1 border-l border-white/10 pl-2"
                    >
                      <Link
                        v-for="entry in child.entries"
                        :key="entry.key"
                        :href="entry.href"
                        class="block rounded-lg px-2 py-1.5 text-[11px] font-semibold leading-4 transition"
                        :class="archiveEntryRowClass(entry)"
                      >
                        {{ entry.label }}
                      </Link>

                      <div
                        v-for="topic in child.topics"
                        :key="topic.key"
                        class="space-y-1"
                      >
                        <div class="flex items-center gap-1">
                          <button
                            type="button"
                            class="min-w-0 flex-1 rounded-lg px-2.5 py-1.5 text-left text-sm font-semibold transition"
                            :class="archiveTopicRowClass(topic)"
                            @click="toggleItem(topic)"
                          >
                            <div class="flex items-center justify-between gap-2">
                              <span class="truncate">{{ topic.label }}</span>
                              <span v-if="topic.meta" class="shrink-0 text-[10px]" :class="archiveTopicMetaClass(topic)">{{ topic.meta }}</span>
                            </div>
                          </button>

                          <Link
                            :href="topic.href"
                            class="shrink-0 rounded-lg px-2 py-1.5 text-xs font-bold text-text-muted transition hover:bg-white/[0.03] hover:text-text-secondary"
                          >
                            ↗
                          </Link>

                          <button
                            v-if="topic.entries?.length"
                            type="button"
                            class="shrink-0 rounded-lg px-2 py-1.5 text-xs font-bold text-text-muted transition hover:bg-white/[0.03] hover:text-text-secondary"
                            @click.stop="toggleItem(topic)"
                          >
                            <span class="block transition-transform" :class="itemChildrenAreOpen(topic) ? 'rotate-90' : ''">›</span>
                          </button>
                        </div>

                        <div
                          v-if="itemChildrenAreOpen(topic) && topic.entries?.length"
                          class="ml-3 space-y-1 border-l border-white/10 pl-2"
                        >
                          <Link
                            v-for="entry in topic.entries"
                            :key="entry.key"
                            :href="entry.href"
                            class="block rounded-lg px-2 py-1.5 text-[11px] font-semibold leading-4 transition"
                            :class="archiveEntryRowClass(entry)"
                          >
                            {{ entry.label }}
                          </Link>
                        </div>
                      </div>
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
