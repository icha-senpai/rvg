<template>
  <div class="mx-auto max-w-5xl hz-stack">

    <!-- SEARCH PANEL -->
    <div class="hz-panel hz-stack">
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
    <div class="hz-panel hz-stack">
      <div class="hz-title-lg">Users ({{ totalUsers }})</div>

      <div class="hz-stack">

        <!-- USER CARD -->
        <div
          v-for="u in users.data"
          :key="u.id"
          class="hz-card-soft hz-row-between"
        >
          <div class="hz-stack-sm">
            <div class="hz-title-md">{{ u.rsi_handle || u.discord_name || 'Unknown' }}</div>

            <div class="hz-text-muted">
              ID {{ u.id }} · Rank {{ u.rank || 'none' }} ({{ u.rank_level || '-' }})
            </div>

            <div class="hz-text-muted">
              Verification Status: {{ u.global_status || 'unset' }}
            </div>

            <div class="hz-text-muted">
              Roles:
              <span v-if="!u.roles?.length">None</span>
              <span v-else class="hz-text-soft">{{ formatRoleList(u.roles) }}</span>
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
      <div class="hz-row-between hz-stack-sm">
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
        Edit User · {{ editingUser.rsi_handle || editingUser.discord_name || 'Unknown' }}
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
        <input v-model="form.rank" class="hz-input" />
      </div>

      <div>
        <label class="hz-text-soft">Rank Level</label>
        <input v-model.number="form.rank_level" type="number" class="hz-input" />
      </div>

      <div>
        <label class="hz-text-soft">Global Status</label>
        <input v-model="form.global_status" class="hz-input" />
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

      <HorizonButton variant="primary" size="sm" @click="saveUser">
        Save Changes
      </HorizonButton>
    </div>

  </div>
</div>


  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import HorizonButton from '@/Components/HorizonButton.vue';

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
  'commander_squadron',
  'commander_staff',
  'lieutenant',
  'member',
  'tech_team',
  'mission_commander',
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

const sortedRoles = computed(() => [...(props.roles ?? [])].sort(compareRoles));

function formatRoleList(userRoles) {
  const sorted = [...(userRoles ?? [])].sort(compareRoles);
  return sorted.map(r => r.name).join(', ');
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
  timezone: '',
  availability_status: '',
  loa_note: '',
  bio: '',
  role_ids: [],
});

function openUserEditor(user) {
  editingUser.value = { ...user };
  form.value = {
    id: user.id,
    rsi_handle: user.rsi_handle || '',
    rank: user.rank || '',
    rank_level: user.rank_level || 1,
    global_status: user.global_status || '',
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
});

onBeforeUnmount(() => {
  if (searchDebounceId) {
    clearTimeout(searchDebounceId);
    searchDebounceId = null;
  }

  window.removeEventListener('keydown', handleKeydown);
});

/* ============================================================
   SAVE ACTIONS
============================================================ */
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
