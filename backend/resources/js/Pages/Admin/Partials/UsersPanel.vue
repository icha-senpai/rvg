<template>
  <div class="mx-auto max-w-5xl hz-stack">

    <!-- SEARCH PANEL -->
    <div class="hz-panel hz-stack !border !border-[color:var(--horizon-sunset-blue)]">
      <div class="hz-title-lg">Search Users</div>

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

    <!-- USERS LIST PANEL -->
    <div class="hz-panel hz-stack !border !border-[color:var(--horizon-sunset-blue)]">
      <div class="hz-title-lg">Users ({{ totalUsers }})</div>

      <div class="hz-stack">

        <!-- USER CARD -->
        <div
          v-for="u in users.data"
          :key="u.id"
          class="hz-card-soft hz-row-between !border !border-[color:var(--horizon-sunset-blue)]"
        >
          <div class="hz-row gap-3 min-w-0">
            <Link
              :href="u.rsi_handle ? route('member.profile', u.rsi_handle) : `/user/${u.id}`"
              class="shrink-0 block"
              title="View profile"
            >
              <img
                v-if="u.discord_avatar"
                :src="u.discord_avatar"
                alt=""
                class="h-20 w-20 rounded-xl object-cover border border-bg-hover"
              />
              <div
                v-else
                class="h-20 w-20 rounded-xl bg-horizon-blue-dark border border-bg-hover flex items-center justify-center text-2xl leading-none font-semibold text-horizon-white"
              >
                {{ String(u.rsi_handle || u.discord_name || 'M').slice(0, 1).toUpperCase() }}
              </div>
            </Link>

            <div class="hz-stack-sm min-w-0">
              <Link
                :href="u.rsi_handle ? route('member.profile', u.rsi_handle) : `/user/${u.id}`"
                class="hz-title-md inline-block hover:underline"
                :style="userNameColor(u) ? { color: userNameColor(u) } : undefined"
              >
                {{ u.rsi_handle || u.discord_name || 'Unknown' }}
              </Link>

              <div class="hz-text-muted">
                ID {{ u.id }} · Rank {{ formatRankLabel(u.rank) }} ({{ u.rank_level || '-' }})
              </div>

              <div class="hz-text-muted">
                Verification Status: {{ formatGlobalStatusLabel(u.global_status) }}
              </div>

              <div class="hz-text-muted">
                Roles:
                <span v-if="!u.roles?.length">None</span>
                <span v-else class="hz-text-soft">{{ formatRoleList(u.roles) }}</span>
              </div>
            </div>
          </div>
          <HorizonButton
            size="sm"
            variant="primary"
            @click="openUserEditor(u)"
          >
            Edit
          </HorizonButton>

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

        <div class="hz-text-soft">
          Page {{ currentPage }} / {{ lastPage }}
        </div>

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

    <!-- MODAL -->
<div
  v-if="editingUser"
  class="hz-overlay flex items-center justify-center"
  @click.self="closeUserEditor"
>

  <div 
    class="hz-modal hz-stack max-h-[85vh] overflow-y-auto hz-animate-pop"
  >

    <!-- HEADER -->
    <div class="hz-row-between">
      <div class="hz-title-lg">
        Edit User ·
        <span :style="userNameColor(editingUser) ? { color: userNameColor(editingUser) } : undefined">
          {{ editingUser.rsi_handle || editingUser.discord_name || 'Unknown' }}
        </span>
      </div>

      <HorizonButton variant="primary" size="sm" @click="closeUserEditor">
        ✕
      </HorizonButton>
    </div>

    <!-- FORM -->
    <div class="hz-stack">

      <div>
        <label class="hz-text-soft">RSI Handle</label>
        <input v-model="form.rsi_handle" class="hz-input" />
      </div>

      <div>
        <label class="hz-text-soft">Rank</label>
        <select v-model="form.rank" class="hz-input">
          
          <option v-for="opt in rankOptions" :key="opt.value" :value="opt.value">
            {{ opt.label }}
          </option>
        </select>
      </div>

      <div>
        <label class="hz-text-soft">Rank Level</label>
        <input v-model.number="form.rank_level" type="number" class="hz-input" disabled />
      </div>

      <div>
        <label class="hz-text-soft">Global Status</label>
        <select v-model="form.global_status" class="hz-input">
          
          <option v-for="opt in globalStatusOptions" :key="opt.value" :value="opt.value">
            {{ opt.label }}
          </option>
        </select>
      </div>

      <div>
        <label class="hz-text-soft">RSI Verified At</label>
        <div class="relative" ref="rsiVerifiedAtPickerContainer">
          <button
            type="button"
            class="hz-input w-full text-left cursor-pointer flex items-center justify-between gap-3 bg-horizon-blue-dark"
            @click="toggleRsiVerifiedAtPicker"
          >
            <span class="truncate">
              {{ rsiVerifiedAtDisplay }}
            </span>
            <span class="text-xs text-[var(--color-text-secondary)] shrink-0">
              Edit
            </span>
          </button>

          <div
            v-if="rsiVerifiedAtPickerOpen"
            class="mt-2 w-full rounded-xl shadow-2xl overflow-visible
                   bg-bg-surface border border-[color:var(--horizon-sunset-blue)]"
          >
            <div class="px-3 py-3 border-b border-white/10 flex items-center justify-between gap-2">
              <button
                type="button"
                class="px-2 py-1 rounded-lg bg-bg-hover border border-bg-hover hover:border-[color:var(--horizon-sunset-blue)]"
                @click="goRsiVerifiedAtPrevMonth"
              >
                ‹
              </button>

              <div class="text-sm font-semibold text-horizon-white">
                {{ rsiVerifiedAtMonthLabel }} {{ rsiVerifiedAtPickerYear }}
              </div>

              <button
                type="button"
                class="px-2 py-1 rounded-lg bg-bg-hover border border-bg-hover hover:border-[color:var(--horizon-sunset-blue)]"
                @click="goRsiVerifiedAtNextMonth"
              >
                ›
              </button>
            </div>

            <div class="px-3 pt-3">
              <div class="grid grid-cols-7 gap-1 text-[11px] text-[var(--color-text-secondary)]">
                <div v-for="d in weekdayLabels" :key="d" class="text-center">
                  {{ d }}
                </div>
              </div>

              <div class="mt-2 grid grid-cols-7 gap-1">
                <button
                  v-for="cell in rsiVerifiedAtCalendarCells"
                  :key="cell.key"
                  type="button"
                  class="h-9 w-9 rounded-lg text-sm flex items-center justify-center border border-transparent"
                  :class="[
                    cell.isBlank
                      ? 'opacity-0 pointer-events-none'
                      : (cell.isSelected
                        ? 'bg-[color:var(--horizon-sunset-blue)] text-horizon-white'
                        : 'text-[var(--color-text-primary)] hover:bg-[var(--color-horizon-blue-10)]'),
                  ]"
                  @click="!cell.isBlank && selectRsiVerifiedAtDay(cell.day)"
                >
                  {{ cell.day }}
                </button>
              </div>
            </div>

            <div class="px-3 py-3 border-t border-white/10">
              <div class="hz-stack-sm">
                <div class="flex items-end gap-2">
                  <div class="hz-stack-xs w-20">
                    <div class="text-[11px] text-[var(--color-text-secondary)]">Hour</div>
                    <button
                      type="button"
                      ref="rsiVerifiedAtHourButton"
                      class="hz-input bg-horizon-blue-dark w-20 text-left px-3 py-2 flex items-center justify-between"
                      @click="toggleRsiVerifiedAtHourMenu"
                    >
                      <span>{{ String(rsiVerifiedAtHour).padStart(2, '0') }}</span>
                      <span class="text-[10px] text-[var(--color-text-secondary)]">▾</span>
                    </button>

                    <div
                      v-if="rsiVerifiedAtHourMenuOpen"
                      class="fixed max-h-40 overflow-y-auto rounded-lg shadow-2xl
                             bg-bg-surface border border-bg-hover p-1 z-50"
                      :style="rsiVerifiedAtHourMenuStyle"
                    >
                      <button
                        v-for="h in 24"
                        :key="h"
                        type="button"
                        class="w-full px-2 py-1 rounded-md text-sm text-left"
                        :class="(rsiVerifiedAtHour === (h - 1))
                          ? 'bg-[color:var(--horizon-sunset-blue)] text-horizon-white'
                          : 'text-[var(--color-text-primary)] hover:bg-[var(--color-horizon-blue-10)]'"
                        @click="selectRsiVerifiedAtHour(h - 1)"
                      >
                        {{ String(h - 1).padStart(2, '0') }}
                      </button>
                    </div>
                  </div>

                  <div class="hz-stack-xs w-24">
                    <div class="text-[11px] text-[var(--color-text-secondary)]">Minute</div>
                    <button
                      type="button"
                      ref="rsiVerifiedAtMinuteButton"
                      class="hz-input bg-horizon-blue-dark w-24 text-left px-3 py-2 flex items-center justify-between"
                      @click="toggleRsiVerifiedAtMinuteMenu"
                    >
                      <span>{{ String(rsiVerifiedAtMinute).padStart(2, '0') }}</span>
                      <span class="text-[10px] text-[var(--color-text-secondary)]">▾</span>
                    </button>

                    <div
                      v-if="rsiVerifiedAtMinuteMenuOpen"
                      class="fixed max-h-40 overflow-y-auto rounded-lg shadow-2xl
                             bg-bg-surface border border-bg-hover p-1 z-50"
                      :style="rsiVerifiedAtMinuteMenuStyle"
                    >
                      <button
                        v-for="m in 60"
                        :key="m"
                        type="button"
                        class="w-full px-2 py-1 rounded-md text-sm text-left"
                        :class="(rsiVerifiedAtMinute === (m - 1))
                          ? 'bg-[color:var(--horizon-sunset-blue)] text-horizon-white'
                          : 'text-[var(--color-text-primary)] hover:bg-[var(--color-horizon-blue-10)]'"
                        @click="selectRsiVerifiedAtMinute(m - 1)"
                      >
                        {{ String(m - 1).padStart(2, '0') }}
                      </button>
                    </div>
                  </div>
                </div>

                <div class="flex items-center justify-end gap-2 flex-wrap">
                  <button
                    type="button"
                    class="px-3 py-2 rounded-lg bg-bg-hover border border-bg-hover hover:border-[color:var(--horizon-sunset-blue)] text-sm"
                    @click="setRsiVerifiedAtNow"
                  >
                    Now
                  </button>

                  <button
                    type="button"
                    class="px-3 py-2 rounded-lg bg-bg-hover border border-bg-hover hover:border-[color:var(--horizon-sunset-blue)] text-sm"
                    @click="clearRsiVerifiedAt"
                  >
                    Clear
                  </button>
                </div>
              </div>

              <div class="hz-text-muted mt-2">
                Clearing this marks the user as not RSI verified.
              </div>
            </div>
          </div>
        </div>
      </div>

      <div>
        <label class="hz-text-soft">Timezone</label>
        <input v-model="form.timezone" class="hz-input" />
      </div>

      <div>
        <label class="hz-text-soft">Availability Status</label>
        <input v-model="form.availability_status" class="hz-input" />
      </div>

      <div>
        <label class="hz-text-soft">LOA Note</label>
        <textarea v-model="form.loa_note" class="hz-textarea"></textarea>
      </div>

      <div>
        <label class="hz-text-soft">Bio</label>
        <textarea v-model="form.bio" class="hz-textarea"></textarea>
      </div>

      <!-- ROLES -->
      <div class="hz-stack-sm">
        <label class="hz-text-soft">Roles</label>

        <div class="hz-card hz-stack-sm">
          <div
            v-for="role in sortedRoles"
            :key="role.id"
            class="hz-row"
          >
            <input
              type="checkbox"
              :value="role.id"
              v-model="form.role_ids"
            />
            <span class="hz-text-soft">{{ role.name }}</span>
          </div>
        </div>

        <HorizonButton
          variant="secondary"
          size="sm"
          @click="saveUserRoles"
        >
          Save Roles
        </HorizonButton>
      </div>
    </div>

    <!-- ACTION ROW -->
    <div class="hz-row-between pt-2">
      <HorizonButton variant="primary" size="sm" @click="closeUserEditor">
        Cancel
      </HorizonButton>

      <div class="hz-row gap-2">
        <HorizonButton variant="danger" size="sm" @click="unverifyUser">
          Unverify User
        </HorizonButton>

        <HorizonButton variant="primary" size="sm" @click="saveUser">
          Save Changes
        </HorizonButton>
      </div>
    </div>

  </div>
</div>


  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import HorizonButton from '@/Components/HorizonButton.vue';

import { getHighestOrgRoleSlug, getOrgRoleColor } from '@/roleColors'

const props = defineProps({
  users: Object,
  roles: Array,
  filters: Object,
});

const users = computed(() => props.users);
const search = ref(props.filters?.search ?? '');

let searchDebounceId = null;

function runSearch(value) {
  const trimmed = String(value ?? '').trim();

  router.visit(route('admin.dashboard'), {
    data: trimmed ? { search: trimmed } : {},
    preserveState: true,
    preserveScroll: true,
    replace: true,
    only: ['users', 'filters'],
  });
}

/* ============================================================
   COMPUTED
============================================================ */
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

const rankOptions = [
  { label: 'Member', value: 'member', level: 1 },
  { label: 'Lieutenant', value: 'lieutenant', level: 2 },
  { label: 'Commander', value: 'commander', level: 3 },
  { label: 'Wing Commander', value: 'wing_commander', level: 4 },
  { label: 'Admiral', value: 'admiral', level: 5 },
  { label: 'Grand Admiral', value: 'grand_admiral', level: 6 },
];

const rankLabelBySlug = new Map(rankOptions.map(o => [o.value, o.label]));
const rankLevelBySlug = new Map(rankOptions.map(o => [o.value, o.level]));

const globalStatusOptions = [
  { label: 'Pending', value: 'pending' },
  { label: 'Active', value: 'active' },
  { label: 'Inactive', value: 'inactive' },
];

const globalStatusLabelByValue = new Map(globalStatusOptions.map(o => [o.value, o.label]));

function titleCaseIdentifier(value) {
  const raw = String(value ?? '').trim();
  if (!raw) return '';

  return raw
    .split('_')
    .filter(Boolean)
    .map(part => part.slice(0, 1).toUpperCase() + part.slice(1))
    .join(' ');
}

function formatRankLabel(value) {
  if (!value) return 'None';
  return rankLabelBySlug.get(value) ?? titleCaseIdentifier(value);
}

function formatGlobalStatusLabel(value) {
  if (!value) return 'Unset';
  return globalStatusLabelByValue.get(value) ?? titleCaseIdentifier(value);
}

function toDatetimeLocal(value) {
  if (!value) return '';

  const d = value instanceof Date ? value : new Date(value);
  if (Number.isNaN(d.getTime())) return '';

  const pad = (n) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function toBackendDatetimeFromLocalInput(value) {
  if (!value) return null;
  return `${String(value).replace('T', ' ')}:00`;
}

function parseLocalDatetime(value) {
  if (!value) return null;
  const [datePart, timePart] = String(value).split('T');
  if (!datePart || !timePart) return null;

  const [year, month, day] = datePart.split('-').map((v) => Number(v));
  const [hour, minute] = timePart.split(':').map((v) => Number(v));

  if (!year || !month || !day) return null;
  if (Number.isNaN(hour) || Number.isNaN(minute)) return null;

  return { year, month, day, hour, minute };
}

function pad2(n) {
  return String(n).padStart(2, '0');
}

function buildLocalDatetime(parts) {
  return `${parts.year}-${pad2(parts.month)}-${pad2(parts.day)}T${pad2(parts.hour)}:${pad2(parts.minute)}`;
}

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

const sortedRoles = computed(() => [...(props.roles ?? [])].sort(compareRoles));

function formatRoleList(userRoles) {
  const sorted = [...(userRoles ?? [])].sort(compareRoles);
  return sorted.map(r => r.name).join(', ');
}

function userNameColor(u) {
  const slug = getHighestOrgRoleSlug(u?.roles, u?.rank)
  return getOrgRoleColor(slug)
}

/* ============================================================
   SEARCH
============================================================ */
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

/* ============================================================
   PAGINATION
============================================================ */
function goTo(url) {
  if (url) {
    router.visit(url, { preserveScroll: true });
  }
}

/* ============================================================
   MODAL & FORM
============================================================ */
const editingUser = ref(null);

function handleKeydown(event) {
  if (event.key !== 'Escape') return;
  if (!editingUser.value) return;

  closeUserEditor();
}

const form = ref({
  id: null,
  rsi_handle: '',
  rank: '',
  rank_level: 1,
  global_status: '',
  rsi_verified_at: null,
  timezone: '',
  availability_status: '',
  loa_note: '',
  bio: '',
  role_ids: [],
});

const rsiVerifiedAtLocal = ref('');

const rsiVerifiedAtPickerOpen = ref(false);
const rsiVerifiedAtPickerYear = ref(new Date().getFullYear());
const rsiVerifiedAtPickerMonthIndex = ref(new Date().getMonth());
const rsiVerifiedAtPickerContainer = ref(null);

const rsiVerifiedAtHourMenuOpen = ref(false);
const rsiVerifiedAtMinuteMenuOpen = ref(false);
const rsiVerifiedAtHourButton = ref(null);
const rsiVerifiedAtMinuteButton = ref(null);
const rsiVerifiedAtHourMenuStyle = ref({});
const rsiVerifiedAtMinuteMenuStyle = ref({});

const weekdayLabels = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'];
const monthLabels = [
  'January',
  'February',
  'March',
  'April',
  'May',
  'June',
  'July',
  'August',
  'September',
  'October',
  'November',
  'December',
];

const rsiVerifiedAtMonthLabel = computed(() => monthLabels[rsiVerifiedAtPickerMonthIndex.value] ?? '');

const rsiVerifiedAtDisplay = computed(() => {
  if (!rsiVerifiedAtLocal.value) return 'Not verified';
  return rsiVerifiedAtLocal.value.replace('T', ' ');
});

function ensureRsiVerifiedAtParts() {
  const parsed = parseLocalDatetime(rsiVerifiedAtLocal.value);
  if (parsed) return parsed;

  const now = new Date();
  return {
    year: now.getFullYear(),
    month: now.getMonth() + 1,
    day: now.getDate(),
    hour: 0,
    minute: 0,
  };
}

const rsiVerifiedAtHour = computed({
  get() {
    return ensureRsiVerifiedAtParts().hour;
  },
  set(next) {
    const parts = ensureRsiVerifiedAtParts();
    parts.hour = Number(next);
    rsiVerifiedAtLocal.value = buildLocalDatetime(parts);
  },
});

const rsiVerifiedAtMinute = computed({
  get() {
    return ensureRsiVerifiedAtParts().minute;
  },
  set(next) {
    const parts = ensureRsiVerifiedAtParts();
    parts.minute = Number(next);
    rsiVerifiedAtLocal.value = buildLocalDatetime(parts);
  },
});

function openRsiVerifiedAtPicker() {
  rsiVerifiedAtPickerOpen.value = true;
  const parts = parseLocalDatetime(rsiVerifiedAtLocal.value);
  if (parts) {
    rsiVerifiedAtPickerYear.value = parts.year;
    rsiVerifiedAtPickerMonthIndex.value = parts.month - 1;
    return;
  }

  const now = new Date();
  rsiVerifiedAtPickerYear.value = now.getFullYear();
  rsiVerifiedAtPickerMonthIndex.value = now.getMonth();
}

function closeRsiVerifiedAtPicker() {
  rsiVerifiedAtHourMenuOpen.value = false;
  rsiVerifiedAtMinuteMenuOpen.value = false;
  rsiVerifiedAtPickerOpen.value = false;
}

function toggleRsiVerifiedAtPicker() {
  if (rsiVerifiedAtPickerOpen.value) {
    closeRsiVerifiedAtPicker();
    return;
  }

  openRsiVerifiedAtPicker();
}

function goRsiVerifiedAtPrevMonth() {
  if (rsiVerifiedAtPickerMonthIndex.value === 0) {
    rsiVerifiedAtPickerMonthIndex.value = 11;
    rsiVerifiedAtPickerYear.value -= 1;
    return;
  }

  rsiVerifiedAtPickerMonthIndex.value -= 1;
}

function goRsiVerifiedAtNextMonth() {
  if (rsiVerifiedAtPickerMonthIndex.value === 11) {
    rsiVerifiedAtPickerMonthIndex.value = 0;
    rsiVerifiedAtPickerYear.value += 1;
    return;
  }

  rsiVerifiedAtPickerMonthIndex.value += 1;
}

function selectRsiVerifiedAtDay(day) {
  const parts = ensureRsiVerifiedAtParts();
  parts.year = rsiVerifiedAtPickerYear.value;
  parts.month = rsiVerifiedAtPickerMonthIndex.value + 1;
  parts.day = Number(day);
  rsiVerifiedAtLocal.value = buildLocalDatetime(parts);
}

function setRsiVerifiedAtNow() {
  const now = new Date();
  rsiVerifiedAtLocal.value = buildLocalDatetime({
    year: now.getFullYear(),
    month: now.getMonth() + 1,
    day: now.getDate(),
    hour: now.getHours(),
    minute: now.getMinutes(),
  });
  rsiVerifiedAtPickerYear.value = now.getFullYear();
  rsiVerifiedAtPickerMonthIndex.value = now.getMonth();
}

function clearRsiVerifiedAt() {
  rsiVerifiedAtLocal.value = '';
}

function computeFixedMenuStyle(triggerEl) {
  if (!triggerEl) return {};

  const rect = triggerEl.getBoundingClientRect();
  const menuHeight = 160;
  const margin = 10;

  const openUp = rect.bottom + menuHeight + margin > window.innerHeight;
  const top = openUp ? rect.top - menuHeight - 6 : rect.bottom + 6;

  const width = rect.width;
  let left = rect.left;

  if (left + width > window.innerWidth - margin) {
    left = window.innerWidth - margin - width;
  }

  if (left < margin) left = margin;

  return {
    top: `${Math.max(margin, top)}px`,
    left: `${left}px`,
    width: `${width}px`,
  };
}

function toggleRsiVerifiedAtHourMenu() {
  rsiVerifiedAtMinuteMenuOpen.value = false;

  if (rsiVerifiedAtHourMenuOpen.value) {
    rsiVerifiedAtHourMenuOpen.value = false;
    return;
  }

  rsiVerifiedAtHourMenuStyle.value = computeFixedMenuStyle(rsiVerifiedAtHourButton.value);
  rsiVerifiedAtHourMenuOpen.value = true;
}

function toggleRsiVerifiedAtMinuteMenu() {
  rsiVerifiedAtHourMenuOpen.value = false;

  if (rsiVerifiedAtMinuteMenuOpen.value) {
    rsiVerifiedAtMinuteMenuOpen.value = false;
    return;
  }

  rsiVerifiedAtMinuteMenuStyle.value = computeFixedMenuStyle(rsiVerifiedAtMinuteButton.value);
  rsiVerifiedAtMinuteMenuOpen.value = true;
}

function selectRsiVerifiedAtHour(value) {
  rsiVerifiedAtHour.value = Number(value);
  rsiVerifiedAtHourMenuOpen.value = false;
}

function selectRsiVerifiedAtMinute(value) {
  rsiVerifiedAtMinute.value = Number(value);
  rsiVerifiedAtMinuteMenuOpen.value = false;
}

const rsiVerifiedAtCalendarCells = computed(() => {
  const year = rsiVerifiedAtPickerYear.value;
  const monthIndex = rsiVerifiedAtPickerMonthIndex.value;

  const first = new Date(year, monthIndex, 1);
  const startWeekday = (first.getDay() + 6) % 7;
  const daysInMonth = new Date(year, monthIndex + 1, 0).getDate();

  const selected = parseLocalDatetime(rsiVerifiedAtLocal.value);

  const cells = [];
  const total = 42;

  for (let i = 0; i < total; i++) {
    const day = i - startWeekday + 1;
    const isBlank = day < 1 || day > daysInMonth;
    const isSelected =
      !isBlank &&
      !!selected &&
      selected.year === year &&
      selected.month === monthIndex + 1 &&
      selected.day === day;

    cells.push({
      key: `${year}-${monthIndex}-${i}`,
      day: isBlank ? '' : day,
      isBlank,
      isSelected,
    });
  }

  return cells;
});

function handleRsiVerifiedAtPickerClickOutside(event) {
  if (!rsiVerifiedAtPickerOpen.value) return;
  const el = rsiVerifiedAtPickerContainer.value;
  if (!el) return;
  if (el.contains(event.target)) return;

  closeRsiVerifiedAtPicker();
}

function handleRsiVerifiedAtViewportChanged() {
  if (!rsiVerifiedAtPickerOpen.value) return;

  rsiVerifiedAtHourMenuOpen.value = false;
  rsiVerifiedAtMinuteMenuOpen.value = false;
}

watch(
  () => rsiVerifiedAtLocal.value,
  (nextValue) => {
    form.value.rsi_verified_at = toBackendDatetimeFromLocalInput(nextValue);
  }
);

watch(
  () => form.value.rank,
  (nextRank) => {
    if (!nextRank) {
      form.value.rank_level = 1;
      return;
    }

    const mapped = rankLevelBySlug.get(nextRank);
    if (mapped) {
      form.value.rank_level = mapped;
    }
  }
);

function openUserEditor(user) {
  editingUser.value = { ...user };

  closeRsiVerifiedAtPicker();

  rsiVerifiedAtLocal.value = toDatetimeLocal(user.rsi_verified_at);

  form.value = {
    id: user.id,
    rsi_handle: user.rsi_handle || '',
    rank: user.rank || '',
    rank_level: user.rank_level || 1,
    global_status: user.global_status || '',
    rsi_verified_at: toBackendDatetimeFromLocalInput(rsiVerifiedAtLocal.value),
    timezone: user.timezone || '',
    availability_status: user.availability_status || '',
    loa_note: user.loa_note || '',
    bio: user.bio || '',
    role_ids: user.roles?.map(r => r.id) ?? [],
  };
}

function closeUserEditor() {
  editingUser.value = null;
}

onMounted(() => {
  window.addEventListener('keydown', handleKeydown);
  window.addEventListener('pointerdown', handleRsiVerifiedAtPickerClickOutside);
  window.addEventListener('resize', handleRsiVerifiedAtViewportChanged);
  window.addEventListener('scroll', handleRsiVerifiedAtViewportChanged, true);
});

onBeforeUnmount(() => {
  if (searchDebounceId) {
    clearTimeout(searchDebounceId);
    searchDebounceId = null;
  }

  window.removeEventListener('keydown', handleKeydown);
  window.removeEventListener('pointerdown', handleRsiVerifiedAtPickerClickOutside);
  window.removeEventListener('resize', handleRsiVerifiedAtViewportChanged);
  window.removeEventListener('scroll', handleRsiVerifiedAtViewportChanged, true);
});

/* ============================================================
   SAVE ACTIONS
============================================================ */
function unverifyUser() {
  const userId = form.value.id;
  if (!userId) return;

  const ok = window.confirm('Unverify this user? They will be forced back through verification and their API tokens will be revoked.');
  if (!ok) return;

  router.post(
    route('admin.users.unverify'),
    { id: userId },
    {
      preserveScroll: true,
      onSuccess: () => {
        closeUserEditor();
        router.visit(window.location.href, { preserveScroll: true });
      },
    }
  );
}

function saveUser() {
  router.post(route('admin.users.update'), form.value, {
    preserveScroll: true,
    onSuccess: () => {
      closeUserEditor();
      router.visit(window.location.href, { preserveScroll: true });
    },
  });
}

function saveUserRoles() {
  router.post(
    route('admin.users.updateRoles'),
    {
      id: form.value.id,
      role_ids: form.value.role_ids,
    },
    {
      preserveScroll: true,
      onSuccess: () => {
        router.visit(window.location.href, { preserveScroll: true });
      },
    }
  );
}
</script>
