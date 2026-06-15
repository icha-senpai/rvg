<script setup>
import { computed, ref, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

import HorizonContainer from '@/Components/HorizonContainer.vue'
import HorizonButton from '@/Components/HorizonButton.vue'
import HorizonInput from '@/Components/HorizonInput.vue'

import { formatOrgRoleLabel, getHighestOrgRoleSlug, getOrgRoleColor } from '@/roleColors'

const props = defineProps({
  users: Object,
  filters: Object,
})

const users = computed(() => props.users ?? {})
const members = computed(() => Array.isArray(users.value?.data) ? users.value.data : [])
const search = ref(props.filters?.search ?? '')

let searchDebounceId = null

const totalUsers = computed(() => users.value?.total ?? 0)
const currentPage = computed(() => users.value?.current_page ?? 1)
const lastPage = computed(() => users.value?.last_page ?? 1)
const prevUrl = computed(() => users.value?.prev_page_url || null)
const nextUrl = computed(() => users.value?.next_page_url || null)

const activeSearch = computed(() => String(props.filters?.search ?? '').trim())

const listedCount = computed(() => members.value.length)

const roleSortOrder = [
  'director',
  'tech_director',
  'grand_admiral',
  'admiral',
  'wing_commander',
  'cit',
  'commander',
  'lieutenant',
  'member',
  'tech_team',
  'viewer',
]

const roleOrderIndex = new Map(roleSortOrder.map((slug, index) => [slug, index]))

function runSearch(value) {
  const trimmed = String(value ?? '').trim()

  router.visit(route('members.index'), {
    data: trimmed ? { search: trimmed } : {},
    preserveState: true,
    preserveScroll: true,
    replace: true,
    only: ['users', 'filters'],
  })
}

function applySearch() {
  if (searchDebounceId) {
    clearTimeout(searchDebounceId)
    searchDebounceId = null
  }

  runSearch(search.value)
}

function clearSearch() {
  if (searchDebounceId) {
    clearTimeout(searchDebounceId)
    searchDebounceId = null
  }

  search.value = ''
  runSearch('')
}

watch(search, (value) => {
  if (searchDebounceId) {
    clearTimeout(searchDebounceId)
  }

  searchDebounceId = setTimeout(() => {
    runSearch(value)
  }, 250)
})

function goTo(url) {
  if (!url) return

  router.visit(url, {
    preserveScroll: true,
    preserveState: true,
  })
}

function compareRoles(a, b) {
  const aKey = a?.slug ?? ''
  const bKey = b?.slug ?? ''

  const aOrder = roleOrderIndex.has(aKey) ? roleOrderIndex.get(aKey) : Number.POSITIVE_INFINITY
  const bOrder = roleOrderIndex.has(bKey) ? roleOrderIndex.get(bKey) : Number.POSITIVE_INFINITY

  if (aOrder !== bOrder) return aOrder - bOrder

  const aName = String(a?.name ?? '').toLowerCase()
  const bName = String(b?.name ?? '').toLowerCase()

  return aName.localeCompare(bName)
}

function sortedRoles(userRoles) {
  return [...(userRoles ?? [])].sort(compareRoles)
}

function formatRoleList(userRoles) {
  return sortedRoles(userRoles).map(role => role.name).join(', ')
}

function normalizeArray(value) {
  if (!Array.isArray(value)) return []

  return value
    .map(item => String(item ?? '').trim())
    .filter(Boolean)
}

function previewChipList(value, max = 3) {
  const list = normalizeArray(value)
  const items = list.slice(0, max)

  return {
    items,
    moreCount: Math.max(0, list.length - items.length),
  }
}

function experienceRatingGrid(value) {
  const mapping = [
    ['space_combat', 'Space'],
    ['ground_combat', 'Ground'],
    ['logistics_support', 'Logistics'],
    ['medical', 'Medical'],
  ]

  return mapping.map(([key, label]) => {
    const rating = value && typeof value === 'object' ? value[key] : null
    const isSet = !(rating === null || rating === undefined || rating === '')

    return {
      key,
      label,
      value: isSet ? rating : null,
      isSet,
    }
  })
}

function userNameColor(user) {
  const slug = getHighestOrgRoleSlug(user?.roles, user?.rank)

  return getOrgRoleColor(slug)
}

function profileHref(user) {
  if (user?.rsi_handle) return route('member.profile', user.rsi_handle)
  if (user?.id) return `/user/${user.id}`

  return '#'
}

function displayName(user) {
  return user?.rsi_handle || user?.discord_name || 'Unknown Member'
}

function avatarInitial(user) {
  return String(displayName(user) || 'M').slice(0, 1).toUpperCase()
}

function rankLabel(user) {
  const rankSlug = getHighestOrgRoleSlug(user?.roles, user?.rank)
  if (rankSlug === 'cit') return 'C.I.T'
  if (rankSlug) return formatOrgRoleLabel(rankSlug)

  const rankLevel = Number(user?.rank_level ?? 0)

  return (
    {
      1: 'Member',
      2: 'Lieutenant',
      3: 'Commander',
      4: 'Wing Commander',
      5: 'Admiral',
      6: 'Grand Admiral',
    }[rankLevel] ?? 'Unranked'
  )
}

function primaryRole(user) {
  const roles = sortedRoles(user?.roles ?? [])

  return roles[0]?.name ?? rankLabel(user)
}

function memberRegion(user) {
  return String(user?.region ?? '').trim() || 'UNASSIGNED'
}

function memberCardStyle(user) {
  const roleColor = userNameColor(user) ?? 'var(--horizon-sunset-blue)'

  return {
    '--member-rank-color': roleColor,
  }
}

function hasFavoriteData(user) {
  return normalizeArray(user?.favorite_ships).length > 0 || normalizeArray(user?.favorite_guns).length > 0
}
</script>

<template>
  <HorizonContainer class="py-8 md:py-10">
    <div class="mx-auto max-w-7xl">
      <section class="hz-surface-welcome relative overflow-hidden rounded-[2rem] border border-white/[0.055]">
        <div class="pointer-events-none absolute inset-0 opacity-40">
          <div class="absolute left-8 top-0 h-px w-48 bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-blue)] to-transparent"></div>
          <div class="absolute bottom-0 right-10 h-px w-64 bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-magenta)] to-transparent"></div>
        </div>

        <div class="relative">
          <div class="p-6">
            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
              <div class="min-w-0">
                <div class="text-xs font-bold uppercase tracking-[0.28em] text-[color:var(--horizon-text-secondary)]">
                  Horizon Personnel Registry
                </div>

                <h1 class="mt-2 text-3xl font-black tracking-tight text-horizon-white md:text-5xl">
                  Members
                </h1>

                <p class="mt-3 max-w-3xl text-sm text-text-secondary md:text-base">
                  Browse active members.
                </p>
              </div>
            </div>
          </div>

          <div class="relative z-30 p-5">
            <div class="mb-5 flex justify-end">
              <div
                v-if="activeSearch"
                class="rounded-2xl border border-white/[0.055] bg-white/[0.024] px-4 py-3"
              >
                <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
                  Active Filter
                </div>

                <div class="mt-1 max-w-60 truncate text-sm font-semibold text-horizon-white">
                  {{ activeSearch }}
                </div>
              </div>
            </div>

            <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center">
              <HorizonInput
                v-model="search"
                type="text"
                class="hz-input"
                placeholder="Search by RSI handle, Discord name, or region..."
                @keyup.enter="applySearch"
              />

              <div class="flex flex-wrap gap-2">
                <HorizonButton
                  variant="primary"
                  size="sm"
                  @click="applySearch"
                >
                  Search
                </HorizonButton>

                <HorizonButton
                  variant="ghost"
                  size="sm"
                  @click="clearSearch"
                >
                  Clear
                </HorizonButton>
              </div>
            </div>
          </div>

          <div class="p-4 md:p-5">
            <div class="mb-5 flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
              <div>
                <div class="text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--horizon-text-secondary)]">
                  Personnel Board
                </div>

                <h2 class="mt-1 text-xl font-black text-horizon-white">
                  {{ totalUsers }} Member Records
                </h2>

                <p class="mt-1 text-sm text-text-secondary">
                  Showing {{ listedCount }} records on this page.
                </p>
              </div>

              <div class="rounded-full border border-white/[0.055] bg-white/[0.024] px-3 py-1 text-xs font-bold uppercase tracking-[0.16em] text-text-muted">
                Page {{ currentPage }} of {{ lastPage }}
              </div>
            </div>

            <div
              v-if="members.length"
              class="grid gap-6 xl:grid-cols-2"
            >
              <article
                v-for="member in members"
                :key="member.id"
                class="hz-surface-welcome relative overflow-hidden group rounded-[1.9rem] border border-white/[0.055] transition duration-200 hover:-translate-y-0.5 hover:border-white/15"
                :style="memberCardStyle(member)"
              >
                <div class="pointer-events-none absolute inset-x-9 top-[4rem] h-px bg-white/65"></div>

                <Link
                  :href="profileHref(member)"
                  class="relative block px-6 py-5"
                  title="View profile"
                >
                  <div class="grid min-h-32 grid-cols-[auto_minmax(0,1fr)] gap-5">
                    <div class="pt-1">
                      <img
                        v-if="member.discord_avatar"
                        :src="member.discord_avatar"
                        alt=""
                        class="h-20 w-20 rounded-full border border-white/10 object-cover shadow-[0_10px_24px_rgba(0,0,0,0.35)]"
                      />

                      <div
                        v-else
                        class="flex h-20 w-20 items-center justify-center rounded-full border border-white/10 bg-black/85 text-2xl font-black text-horizon-white shadow-[0_10px_24px_rgba(0,0,0,0.35)]"
                      >
                        {{ avatarInitial(member) }}
                      </div>
                    </div>

                    <div class="min-w-0 pt-1">
                      <div
                        class="truncate pr-18 text-[1.85rem] font-semibold tracking-tight text-horizon-white"
                      >
                        {{ displayName(member) }}
                      </div>

                      <div
                        class="mt-2 text-[1.15rem] font-medium italic"
                        :style="{ color: 'var(--member-rank-color)' }"
                      >
                        {{ rankLabel(member) }}
                      </div>
                    </div>
                  </div>

                  <div class="absolute bottom-4 right-6 text-[1.35rem] font-medium tracking-wide text-horizon-white/90">
                    {{ memberRegion(member) }}
                  </div>
                </Link>
              </article>
            </div>

            <div
              v-else
              class="hz-surface-welcome rounded-[1.5rem] border border-dashed border-white/15 p-10 text-center"
            >
              <div class="text-2xl font-black text-horizon-white">
                No Members Found
              </div>

              <p class="mx-auto mt-2 max-w-xl text-sm text-text-secondary">
                Adjust the search query or clear the filter to return to the full personnel registry.
              </p>

              <HorizonButton
                v-if="activeSearch"
                class="mt-4"
                variant="ghost"
                size="sm"
                @click="clearSearch"
              >
                Clear Search
              </HorizonButton>
            </div>

            <!-- Pagination -->
            <div
              v-if="lastPage > 1"
              class="mt-6 flex items-center justify-between border-t border-white/10 pt-5"
            >
              <HorizonButton
                size="sm"
                variant="ghost"
                :disabled="!prevUrl"
                @click="goTo(prevUrl)"
              >
                Previous
              </HorizonButton>

              <div class="text-xs font-semibold uppercase tracking-[0.16em] text-text-muted">
                Page {{ currentPage }} of {{ lastPage }}
              </div>

              <HorizonButton
                size="sm"
                variant="ghost"
                :disabled="!nextUrl"
                @click="goTo(nextUrl)"
              >
                Next
              </HorizonButton>
            </div>
          </div>
        </div>
      </section>
    </div>
  </HorizonContainer>
</template>
