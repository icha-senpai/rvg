<template>
  <HorizonContainer class="space-y-10">
    <div class="mx-auto max-w-5xl hz-stack">
      <div class="hz-title-xl">Members</div>

      <!-- SEARCH PANEL -->
      <div class="hz-panel hz-stack border! border-(--horizon-sunset-blue)!">
        <div class="hz-title-lg">Search Members</div>

        <div class="hz-row">
          <input
            v-model="search"
            @keyup.enter="applySearch"
            type="text"
            class="hz-input"
            placeholder="Search by name, RSI handle, or ID..."
          />

          <HorizonButton variant="primary" size="sm" @click="applySearch">
            Search
          </HorizonButton>

          <HorizonButton variant="primary" size="sm" @click="clearSearch">
            Clear
          </HorizonButton>
        </div>
      </div>

      <!-- MEMBERS LIST PANEL -->
      <div class="hz-panel hz-stack border! border-(--horizon-sunset-blue)!">
        <div class="hz-title-lg">Members ({{ totalUsers }})</div>

        <div class="hz-stack">
          <div
            v-for="u in users.data"
            :key="u.id"
            class="hz-card-soft border! border-(--horizon-sunset-blue)! transition hover:border-horizon-blue-light!"
          >
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
              <Link
                :href="route('member.profile', u.id)"
                class="shrink-0 block"
                title="View profile"
              >
                <img
                  v-if="u.discord_avatar"
                  :src="u.discord_avatar"
                  alt=""
                  class="h-18 w-18 sm:h-20 sm:w-20 rounded-2xl object-cover border border-bg-hover"
                />
                <div
                  v-else
                  class="h-18 w-18 sm:h-20 sm:w-20 rounded-2xl bg-horizon-blue-dark border border-bg-hover flex items-center justify-center text-2xl leading-none font-semibold text-horizon-white"
                >
                  {{ String(u.rsi_handle || u.discord_name || 'M').slice(0, 1).toUpperCase() }}
                </div>
              </Link>

              <div class="min-w-0 flex-1 hz-stack-sm">
                <div class="hz-row-between gap-3">
                  <div class="min-w-0 hz-stack-xs">
                    <Link
                      :href="route('member.profile', u.id)"
                      class="hz-title-md inline-block hover:underline truncate"
                      :style="userNameColor(u) ? { color: userNameColor(u) } : undefined"
                    >
                      {{ u.rsi_handle || u.discord_name || 'Unknown' }}
                    </Link>
                  </div>

                  <Link
                    :href="route('member.profile', u.id)"
                    class="shrink-0 px-3 py-2 rounded-xl text-sm font-semibold transition bg-bg-hover border border-bg-hover text-horizon-white hover:border-(--horizon-sunset-blue)!"
                  >
                    View
                  </Link>
                </div>

                <div class="flex flex-wrap gap-2">
                  <span
                    v-if="String(u.callsign ?? '').trim()"
                    class="inline-flex items-center px-2.5 py-1 rounded-full bg-bg-hover border border-bg-hover text-xs font-semibold text-text-secondary"
                    :title="`Callsign: ${u.callsign}`"
                  >
                    Callsign: {{ u.callsign }}
                  </span>
                  <span
                    v-if="String(u.timezone ?? '').trim()"
                    class="inline-flex items-center px-2.5 py-1 rounded-full bg-bg-hover border border-bg-hover text-xs font-semibold text-text-secondary"
                    :title="`Timezone: ${u.timezone}`"
                  >
                    {{ u.timezone }}
                  </span>
                  <span
                    v-if="u.roles?.length"
                    class="inline-flex items-center px-2.5 py-1 rounded-full bg-bg-hover border border-bg-hover text-xs font-semibold text-text-secondary"
                    :title="`Roles: ${formatRoleList(u.roles)}`"
                  >
                    Roles: {{ formatRoleList(u.roles) }}
                  </span>
                </div>

                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                  <div class="hz-stack-xs">
                    <div class="text-xs uppercase tracking-wide text-text-secondary">Experience</div>
                    <div class="grid grid-cols-2 gap-2">
                      <div
                        v-for="cell in experienceRatingGrid(u.experience_ratings)"
                        :key="cell.key"
                        class="rounded-xl bg-bg-hover border border-bg-hover px-3 py-2"
                      >
                        <div class="text-[11px] uppercase tracking-wide text-text-secondary">
                          {{ cell.label }}
                        </div>
                        <div
                          class="text-sm font-semibold"
                          :class="cell.isSet ? 'text-horizon-white' : 'text-text-secondary'"
                        >
                          {{ cell.isSet ? cell.value : '—' }}
                        </div>
                      </div>
                    </div>
                  </div>

                  <div class="hz-stack-xs">
                    <div class="text-xs uppercase tracking-wide text-text-secondary">Favorites</div>

                    <div class="hz-stack-xs">
                      <div class="text-xs text-text-secondary">Ships</div>
                      <div class="flex flex-wrap gap-2">
                        <span
                          v-for="ship in previewChipList(u.favorite_ships).items"
                          :key="ship"
                          class="inline-flex items-center px-2.5 py-1 rounded-full bg-bg-hover border border-bg-hover text-xs font-semibold text-text-secondary max-w-full truncate"
                          :title="ship"
                        >
                          {{ ship }}
                        </span>
                        <span
                          v-if="previewChipList(u.favorite_ships).moreCount > 0"
                          class="inline-flex items-center px-2.5 py-1 rounded-full bg-bg-hover border border-bg-hover text-xs font-semibold text-text-secondary"
                        >
                          +{{ previewChipList(u.favorite_ships).moreCount }} more
                        </span>
                        <span
                          v-if="!normalizeArray(u.favorite_ships).length"
                          class="text-sm text-text-secondary"
                        >
                          None
                        </span>
                      </div>
                    </div>

                    <div class="hz-stack-xs">
                      <div class="text-xs text-text-secondary">Guns</div>
                      <div class="flex flex-wrap gap-2">
                        <span
                          v-for="gun in previewChipList(u.favorite_guns).items"
                          :key="gun"
                          class="inline-flex items-center px-2.5 py-1 rounded-full bg-bg-hover border border-bg-hover text-xs font-semibold text-text-secondary max-w-full truncate"
                          :title="gun"
                        >
                          {{ gun }}
                        </span>
                        <span
                          v-if="previewChipList(u.favorite_guns).moreCount > 0"
                          class="inline-flex items-center px-2.5 py-1 rounded-full bg-bg-hover border border-bg-hover text-xs font-semibold text-text-secondary"
                        >
                          +{{ previewChipList(u.favorite_guns).moreCount }} more
                        </span>
                        <span
                          v-if="!normalizeArray(u.favorite_guns).length"
                          class="text-sm text-text-secondary"
                        >
                          None
                        </span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- PAGINATION -->
        <div class="flex items-center justify-center flex-wrap gap-3">
          <HorizonButton
            variant="primary"
            size="sm"
            :disabled="!prevUrl"
            @click="goTo(prevUrl)"
          >
            Previous
          </HorizonButton>

          <div class="hz-text-soft">Page {{ currentPage }} / {{ lastPage }}</div>

          <HorizonButton
            variant="primary"
            size="sm"
            :disabled="!nextUrl"
            @click="goTo(nextUrl)"
          >
            Next
          </HorizonButton>
        </div>
      </div>
    </div>
  </HorizonContainer>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';

import HorizonContainer from '@/Components/HorizonContainer.vue';
import HorizonButton from '@/Components/HorizonButton.vue';

import { getHighestOrgRoleSlug, getOrgRoleColor } from '@/roleColors';

const props = defineProps({
  users: Object,
  filters: Object,
});

const users = computed(() => props.users);
const search = ref(props.filters?.search ?? '');

let searchDebounceId = null;

function runSearch(value) {
  const trimmed = String(value ?? '').trim();

  router.visit(route('members.index'), {
    data: trimmed ? { search: trimmed } : {},
    preserveState: true,
    preserveScroll: true,
    replace: true,
    only: ['users', 'filters'],
  });
}

const totalUsers = computed(() => users.value?.total ?? 0);
const currentPage = computed(() => users.value?.current_page ?? 1);
const lastPage = computed(() => users.value?.last_page ?? 1);
const prevUrl = computed(() => users.value?.prev_page_url || null);
const nextUrl = computed(() => users.value?.next_page_url || null);

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
];

const roleOrderIndex = new Map(roleSortOrder.map((slug, index) => [slug, index]));

function compareRoles(a, b) {
  const aKey = a?.slug ?? '';
  const bKey = b?.slug ?? '';

  const aOrder = roleOrderIndex.has(aKey) ? roleOrderIndex.get(aKey) : Number.POSITIVE_INFINITY;
  const bOrder = roleOrderIndex.has(bKey) ? roleOrderIndex.get(bKey) : Number.POSITIVE_INFINITY;

  if (aOrder !== bOrder) return aOrder - bOrder;

  const aName = String(a?.name ?? '').toLowerCase();
  const bName = String(b?.name ?? '').toLowerCase();
  return aName.localeCompare(bName);
}

function formatRoleList(userRoles) {
  const sorted = [...(userRoles ?? [])].sort(compareRoles);
  return sorted.map(r => r.name).join(', ');
}

function formatValueOrDash(value) {
  const raw = String(value ?? '').trim();
  return raw ? raw : '—';
}

function normalizeArray(value) {
  if (!Array.isArray(value)) return [];
  return value
    .map(v => String(v ?? '').trim())
    .filter(Boolean);
}

function previewList(value, max = 3) {
  const list = normalizeArray(value);
  if (!list.length) return 'None';

  const shown = list.slice(0, max);
  const remaining = list.length - shown.length;

  return remaining > 0
    ? `${shown.join(', ')} +${remaining} more`
    : shown.join(', ');
}

function experienceRatingGrid(value) {
  const mapping = [
    ['space_combat', 'Space'],
    ['ground_combat', 'Ground'],
    ['logistics_support', 'Logistics'],
    ['medical', 'Medical'],
  ];

  return mapping.map(([key, label]) => {
    const rating = value && typeof value === 'object' ? value[key] : null;
    const isSet = !(rating === null || rating === undefined || rating === '');

    return {
      key,
      label,
      value: isSet ? rating : null,
      isSet,
    };
  });
}

function previewChipList(value, max = 3) {
  const list = normalizeArray(value);
  const items = list.slice(0, max);
  return {
    items,
    moreCount: Math.max(0, list.length - items.length),
  };
}

function userNameColor(u) {
  const slug = getHighestOrgRoleSlug(u?.roles, u?.rank);
  return getOrgRoleColor(slug);
}

function applySearch() {
  if (searchDebounceId) {
    clearTimeout(searchDebounceId);
    searchDebounceId = null;
  }

  runSearch(search.value);
}

function clearSearch() {
  if (searchDebounceId) {
    clearTimeout(searchDebounceId);
    searchDebounceId = null;
  }

  search.value = '';
  runSearch('');
}

watch(search, (value) => {
  if (searchDebounceId) {
    clearTimeout(searchDebounceId);
  }

  searchDebounceId = setTimeout(() => {
    runSearch(value);
  }, 250);
});

function goTo(url) {
  if (url) {
    router.visit(url, { preserveScroll: true });
  }
}
</script>
