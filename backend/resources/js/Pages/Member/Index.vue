<script setup>
import { computed, ref, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

import HorizonContainer from '@/Components/HorizonContainer.vue'
import HorizonButton from '@/Components/HorizonButton.vue'

import { getHighestOrgRoleSlug, getOrgRoleColor } from '@/roleColors'

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

const membersWithCallsign = computed(() => {
  return members.value.filter(member => String(member?.callsign ?? '').trim()).length
})

const membersWithTimezone = computed(() => {
  return members.value.filter(member => String(member?.timezone ?? '').trim()).length
})

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
  if (user?.rank) return user.rank

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

function hasFavoriteData(user) {
  return normalizeArray(user?.favorite_ships).length > 0 || normalizeArray(user?.favorite_guns).length > 0
}
</script>

<template>
  <HorizonContainer class="py-8 md:py-10">
    <div class="mx-auto max-w-6xl space-y-8">
      <!-- Command hero -->
      <section class="relative overflow-hidden rounded-[2rem] border border-white/[0.055] bg-[rgba(21,25,42,0.46)] p-6 ">
        <div class="pointer-events-none absolute inset-0 opacity-40">
          <div class="absolute left-8 top-0 h-px w-48 bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-blue)] to-transparent"></div>
          <div class="absolute bottom-0 right-10 h-px w-64 bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-magenta)] to-transparent"></div>
        </div>

        <div class="relative grid gap-6 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
          <div class="min-w-0">
            <div class="text-xs font-bold uppercase tracking-[0.28em] text-[color:var(--horizon-text-secondary)]">
              Horizon Personnel Registry
            </div>

            <h1 class="mt-2 text-3xl font-black tracking-tight text-horizon-white md:text-5xl">
              Members
            </h1>

            <p class="mt-3 max-w-3xl text-sm text-text-secondary md:text-base">
              Browse active members, callsigns, roles, experience ratings, and favorite equipment from one personnel board.
            </p>

            <div class="mt-4 flex flex-wrap gap-2">
              <span class="rounded-full border border-white/[0.055] bg-white/[0.042] px-3 py-1 text-xs font-semibold text-[color:var(--horizon-text-primary)]">
                {{ totalUsers }} Active Members
              </span>

              <span
                v-if="activeSearch"
                class="rounded-full border border-white/[0.055] bg-white/[0.042] px-3 py-1 text-xs font-semibold text-[color:var(--horizon-text-primary)]"
              >
                Search: {{ activeSearch }}
              </span>

              <span class="rounded-full border border-white/[0.055] bg-white/[0.024] px-3 py-1 text-xs font-semibold text-text-secondary">
                Page {{ currentPage }} / {{ lastPage }}
              </span>
            </div>
          </div>

          <div class="rounded-2xl border border-white/[0.055] bg-white/[0.035] px-4 py-3 text-right">
            <div class="text-xs font-bold uppercase tracking-[0.2em] text-text-muted">
              Visible
            </div>

            <div class="mt-1 text-3xl font-black text-horizon-white">
              {{ listedCount }}
            </div>

            <div class="mt-1 text-xs text-text-secondary">
              Members on this page
            </div>
          </div>
        </div>
      </section>

      <!-- Stats strip -->
      <section class="grid gap-4 md:grid-cols-3">
        <div class="rounded-[1.5rem] border border-white/[0.055] bg-[linear-gradient(135deg,rgba(30,64,175,0.14),rgba(255,255,255,0.025))] p-5 ">
          <div class="text-xs font-bold uppercase tracking-[0.2em] text-text-muted">
            Registry Total
          </div>
          <div class="mt-2 text-3xl font-black text-horizon-white">
            {{ totalUsers }}
          </div>
          <div class="mt-1 text-sm text-text-secondary">
            Active member records
          </div>
        </div>

        <div class="rounded-[1.5rem] border border-white/[0.055] bg-[linear-gradient(135deg,rgba(67,56,202,0.14),rgba(255,255,255,0.025))] p-5 ">
          <div class="text-xs font-bold uppercase tracking-[0.2em] text-text-muted">
            Callsigns
          </div>
          <div class="mt-2 text-3xl font-black text-horizon-white">
            {{ membersWithCallsign }}
          </div>
          <div class="mt-1 text-sm text-text-secondary">
            Visible members with callsigns
          </div>
        </div>

        <div class="rounded-[1.5rem] border border-white/[0.055] bg-[rgba(21,25,42,0.42)] p-5">
          <div class="text-xs font-bold uppercase tracking-[0.2em] text-text-muted">
            Timezones
          </div>
          <div class="mt-2 text-3xl font-black text-horizon-white">
            {{ membersWithTimezone }}
          </div>
          <div class="mt-1 text-sm text-text-secondary">
            Visible members with timezone data
          </div>
        </div>
      </section>

      <!-- Search command panel -->
      <section class="relative z-30 overflow-visible rounded-[2rem] border border-white/[0.055] bg-[color:var(--horizon-void-700)]/70 p-5 ">
        <div class="mb-5 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <div class="text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--horizon-text-secondary)]">
              Search Console
            </div>

            <h2 class="mt-1 text-xl font-black text-horizon-white">
              Find Personnel
            </h2>

            <p class="mt-1 text-sm text-text-secondary">
              Search by RSI handle, Discord name, callsign, timezone, favorite ships, favorite guns, experience, or member ID.
            </p>
          </div>

          <div
            v-if="activeSearch"
            class="rounded-2xl border border-white/[0.055] bg-white/[0.042] px-4 py-3"
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
          <input
            v-model="search"
            type="text"
            class="hz-input"
            placeholder="Search by name, RSI handle, callsign, timezone, ID..."
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
      </section>

      <!-- Members registry -->
      <section class="rounded-[2rem] border border-white/[0.055] bg-[color:var(--horizon-void-700)]/70 p-4  md:p-5">
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
          class="grid gap-4"
        >
          <article
            v-for="member in members"
            :key="member.id"
            class="group relative overflow-hidden rounded-[1.75rem] border border-white/10 bg-[rgba(21,25,42,0.64)] p-5  transition duration-200 hover:-translate-y-0.5 hover:border-white/[0.055] hover:"
          >
            <div class="pointer-events-none absolute inset-0 opacity-0 transition duration-200 group-hover:opacity-100">
              <div class="absolute inset-x-8 top-0 h-px bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-blue)] to-transparent"></div>
              <div class="absolute inset-x-10 bottom-0 h-px bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-magenta)] to-transparent"></div>
            </div>

            <div class="relative grid gap-5 lg:grid-cols-[auto_minmax(0,1fr)_auto] lg:items-start">
              <!-- Avatar -->
              <Link
                :href="profileHref(member)"
                class="block shrink-0"
                title="View profile"
              >
                <img
                  v-if="member.discord_avatar"
                  :src="member.discord_avatar"
                  alt=""
                  class="h-24 w-24 rounded-2xl border border-white/[0.055] object-cover "
                />

                <div
                  v-else
                  class="flex h-24 w-24 items-center justify-center rounded-2xl border border-white/[0.055] bg-white/[0.04] text-3xl font-black text-horizon-white "
                >
                  {{ avatarInitial(member) }}
                </div>
              </Link>

              <!-- Main info -->
              <div class="min-w-0 space-y-4">
                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                  <div class="min-w-0">
                    <div class="text-xs font-bold uppercase tracking-[0.2em] text-text-muted">
                      Personnel File #{{ member.id }}
                    </div>

                    <Link
                      :href="profileHref(member)"
                      class="mt-1 block truncate text-2xl font-black tracking-tight text-horizon-white hover:underline md:text-3xl"
                      :style="userNameColor(member) ? { color: userNameColor(member) } : undefined"
                    >
                      {{ displayName(member) }}
                    </Link>

                    <div class="mt-1 text-sm text-text-secondary">
                      {{ primaryRole(member) }}
                    </div>
                  </div>

                  <Link
                    :href="profileHref(member)"
                    class="inline-flex shrink-0 items-center justify-center rounded-xl border border-white/[0.055] bg-white/[0.042] px-4 py-2 text-sm font-bold text-horizon-white transition hover:border-white/[0.055] hover:bg-white/[0.042]"
                  >
                    View Dossier
                  </Link>
                </div>

                <!-- Chips -->
                <div class="flex flex-wrap gap-2">
                  <span
                    class="rounded-full border border-white/[0.055] bg-white/[0.042] px-3 py-1 text-xs font-semibold text-[color:var(--horizon-text-primary)]"
                  >
                    {{ rankLabel(member) }}
                  </span>

                  <span
                    v-if="String(member.callsign ?? '').trim()"
                    class="rounded-full border border-white/[0.055] bg-white/[0.042] px-3 py-1 text-xs font-semibold text-[color:var(--horizon-text-primary)]"
                    :title="`Callsign: ${member.callsign}`"
                  >
                    Callsign: {{ member.callsign }}
                  </span>

                  <span
                    v-if="String(member.timezone ?? '').trim()"
                    class="rounded-full border border-white/[0.055] bg-white/[0.024] px-3 py-1 text-xs font-semibold text-text-secondary"
                    :title="`Timezone: ${member.timezone}`"
                  >
                    {{ member.timezone }}
                  </span>

                  <span
                    v-if="member.roles?.length"
                    class="rounded-full border border-white/[0.055] bg-white/[0.024] px-3 py-1 text-xs font-semibold text-text-secondary"
                    :title="`Roles: ${formatRoleList(member.roles)}`"
                  >
                    Roles: {{ formatRoleList(member.roles) }}
                  </span>
                </div>

                <!-- Detail grid -->
                <div class="grid gap-4 xl:grid-cols-2">
                  <section class="rounded-[1.5rem] border border-white/[0.055] bg-white/[0.024] p-4">
                    <div class="mb-3 text-xs font-bold uppercase tracking-[0.2em] text-text-muted">
                      Experience
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                      <div
                        v-for="cell in experienceRatingGrid(member.experience_ratings)"
                        :key="cell.key"
                        class="rounded-xl border border-white/10 bg-black/10 px-3 py-2"
                      >
                        <div class="text-[11px] uppercase tracking-wide text-text-muted">
                          {{ cell.label }}
                        </div>

                        <div
                          class="text-sm font-bold"
                          :class="cell.isSet ? 'text-horizon-white' : 'text-text-secondary'"
                        >
                          {{ cell.isSet ? cell.value : '—' }}
                        </div>
                      </div>
                    </div>
                  </section>

                  <section class="rounded-[1.5rem] border border-white/[0.055] bg-white/[0.024] p-4">
                    <div class="mb-3 text-xs font-bold uppercase tracking-[0.2em] text-text-muted">
                      Favorites
                    </div>

                    <div
                      v-if="hasFavoriteData(member)"
                      class="space-y-3"
                    >
                      <div>
                        <div class="mb-1 text-xs text-text-muted">
                          Ships
                        </div>

                        <div class="flex flex-wrap gap-2">
                          <span
                            v-for="ship in previewChipList(member.favorite_ships).items"
                            :key="ship"
                            class="max-w-full truncate rounded-full border border-[color:var(--horizon-sunset-blue)]/20 bg-white/[0.042] px-2.5 py-1 text-xs font-semibold text-text-secondary"
                            :title="ship"
                          >
                            {{ ship }}
                          </span>

                          <span
                            v-if="previewChipList(member.favorite_ships).moreCount > 0"
                            class="rounded-full border border-white/[0.055] bg-white/[0.024] px-2.5 py-1 text-xs font-semibold text-text-secondary"
                          >
                            +{{ previewChipList(member.favorite_ships).moreCount }} more
                          </span>

                          <span
                            v-if="!normalizeArray(member.favorite_ships).length"
                            class="text-sm text-text-secondary"
                          >
                            None
                          </span>
                        </div>
                      </div>

                      <div>
                        <div class="mb-1 text-xs text-text-muted">
                          Guns
                        </div>

                        <div class="flex flex-wrap gap-2">
                          <span
                            v-for="gun in previewChipList(member.favorite_guns).items"
                            :key="gun"
                            class="max-w-full truncate rounded-full border border-white/[0.055] bg-white/[0.042] px-2.5 py-1 text-xs font-semibold text-text-secondary"
                            :title="gun"
                          >
                            {{ gun }}
                          </span>

                          <span
                            v-if="previewChipList(member.favorite_guns).moreCount > 0"
                            class="rounded-full border border-white/[0.055] bg-white/[0.024] px-2.5 py-1 text-xs font-semibold text-text-secondary"
                          >
                            +{{ previewChipList(member.favorite_guns).moreCount }} more
                          </span>

                          <span
                            v-if="!normalizeArray(member.favorite_guns).length"
                            class="text-sm text-text-secondary"
                          >
                            None
                          </span>
                        </div>
                      </div>
                    </div>

                    <div
                      v-else
                      class="rounded-xl border border-dashed border-white/[0.075] bg-white/[0.018] [0.02] px-3 py-4 text-sm text-text-secondary"
                    >
                      No favorite equipment listed.
                    </div>
                  </section>
                </div>
              </div>
            </div>
          </article>
        </div>

        <div
          v-else
          class="rounded-[1.5rem] border border-dashed border-white/15 bg-white/[0.025] p-10 text-center"
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
      </section>
    </div>
  </HorizonContainer>
</template>







