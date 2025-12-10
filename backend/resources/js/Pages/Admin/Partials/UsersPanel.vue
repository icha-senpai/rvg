<template>
  <div class="hz-stack">

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

        <button class="hz-btn hz-btn-primary" @click="applySearch">
          Search
        </button>

        <button class="hz-btn hz-btn-secondary" @click="clearSearch">
          Clear
        </button>
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
              Status: {{ u.global_status || 'unset' }}
            </div>

            <div class="hz-text-muted">
              Roles:
              <span v-if="!u.roles?.length">None</span>
              <span
                v-for="r in u.roles"
                :key="r.id"
                class="hz-text-soft"
              >
                {{ r.name }}
              </span>
            </div>
          </div>

          <button
            class="hz-btn hz-btn-secondary hz-btn-sm"
            @click="openUserEditor(u)"
          >
            Edit
          </button>
        </div>
      </div>

      <!-- PAGINATION -->
      <div class="hz-row-between hz-stack-sm">
        <button
          class="hz-btn hz-btn-secondary"
          :disabled="!prevUrl"
          @click="goTo(prevUrl)"
        >
          Previous
        </button>

        <div class="hz-text-soft">
          Page {{ currentPage }} / {{ lastPage }}
        </div>

        <button
          class="hz-btn hz-btn-secondary"
          :disabled="!nextUrl"
          @click="goTo(nextUrl)"
        >
          Next
        </button>
      </div>
    </div>

    <!-- MODAL -->
<div v-if="editingUser" class="hz-overlay flex items-center justify-center">

  <div 
    class="hz-modal hz-stack max-h-[85vh] overflow-y-auto hz-animate-pop"
  >

    <!-- HEADER -->
    <div class="hz-row-between">
      <div class="hz-title-lg">
        Edit User · {{ editingUser.rsi_handle || editingUser.discord_name || 'Unknown' }}
      </div>

      <button class="hz-btn hz-btn-ghost hz-btn-sm" @click="closeUserEditor">
        ✕
      </button>
    </div>

    <!-- FORM -->
    <div class="hz-stack">

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
            v-for="role in roles"
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

        <button
          class="hz-btn hz-btn-secondary hz-btn-sm"
          @click="saveUserRoles"
        >
          Save Roles
        </button>
      </div>
    </div>

    <!-- ACTION ROW -->
    <div class="hz-row-between pt-2">
      <button class="hz-btn hz-btn-ghost" @click="closeUserEditor">
        Cancel
      </button>

      <button class="hz-btn hz-btn-primary" @click="saveUser">
        Save Changes
      </button>
    </div>

  </div>
</div>


  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
  users: Object,
  roles: Array,
  filters: Object,
});

const users = props.users;
const search = ref(props.filters?.search ?? '');

/* ============================================================
   COMPUTED
============================================================ */
const totalUsers = computed(() => users?.total ?? 0);
const currentPage = computed(() => users?.current_page ?? 1);
const lastPage = computed(() => users?.last_page ?? 1);
const prevUrl = computed(() => users?.prev_page_url || null);
const nextUrl = computed(() => users?.next_page_url || null);

/* ============================================================
   SEARCH
============================================================ */
function applySearch() {
  router.visit(route('admin.dashboard'), {
    data: { search: search.value },
    preserveState: true,
    replace: true,
  });
}

function clearSearch() {
  search.value = '';
  router.visit(route('admin.dashboard'), {
    data: {},
    preserveState: true,
    replace: true,
  });
}

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

const form = ref({
  id: null,
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
