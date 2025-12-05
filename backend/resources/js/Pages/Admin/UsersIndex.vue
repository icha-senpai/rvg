<template>
  <HorizonContainer class="space-y-12">

    <HorizonSectionHeader label="Admin" title="User Control Panel" />

    <!-- SEARCH BAR -->
    <HorizonPanel class="p-6 space-y-4">
      <div class="hz-title-lg mb-2">Search Users</div>

      <div class="flex gap-3">
        <input
          v-model="search"
          @keyup.enter="applySearch"
          type="text"
          class="hz-input w-full"
          placeholder="Search by name, RSI handle, or ID..."
        />

        <HorizonButton variant="primary" size="md" @click="applySearch">
          Search
        </HorizonButton>

        <HorizonButton variant="ghost" size="md" @click="clearSearch">
          Clear
        </HorizonButton>
      </div>
    </HorizonPanel>

    <!-- USERS PANEL -->
    <HorizonPanel class="p-6 space-y-6">
      <div class="hz-title-lg mb-4">
        Users ({{ totalUsers }})
      </div>

      <!-- USER ROW -->
      <div
        v-for="u in users.data"
        :key="u.id"
        class="p-4 hz-overlay-light rounded-xl space-y-3 flex items-center justify-between"
      >
        <div>
          <div class="hz-title">
            {{ u.discord_name || 'Unknown' }}
          </div>

          <div class="hz-caption text-horizon-muted">
            ID {{ u.id }} · Rank: {{ u.rank || 'none' }} ({{ u.rank_level || '-' }})
          </div>

          <div class="hz-caption text-horizon-muted">
            Status: {{ u.global_status || 'unset' }}
          </div>

          <div class="hz-caption text-horizon-muted">
            Roles:
            <span v-if="!u.roles?.length">None</span>
            <span v-for="r in u.roles" :key="r.id">
              {{ r.name }}
            </span>
          </div>
        </div>

        <HorizonButton size="sm" variant="outline" @click="openUserEditor(u)">
          Edit
        </HorizonButton>
      </div>

      <!-- PAGINATION -->
      <div class="flex justify-between items-center mt-8">
        <HorizonButton
          :disabled="!prevUrl"
          @click="goTo(prevUrl)"
        >
          Previous
        </HorizonButton>

        <div class="hz-caption text-horizon-muted">
          Page {{ currentPage }} of {{ lastPage }}
        </div>

        <HorizonButton
          :disabled="!nextUrl"
          @click="goTo(nextUrl)"
        >
          Next
        </HorizonButton>
      </div>
    </HorizonPanel>

    <!-- USER EDITOR MODAL -->
    <div
      v-if="editingUser"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/70"
    >
      <div
        class="w-full max-w-xl max-h-[80vh] overflow-y-auto hz-overlay-light rounded-2xl p-6 space-y-4"
      >
        <div class="flex items-center justify-between mb-2">
          <div class="hz-title-lg">
            Edit User · {{ editingUser.discord_name || 'Unknown' }} (ID {{ editingUser.id }})
          </div>
          <button
            class="hz-caption text-horizon-muted hover:text-horizon-white"
            @click="closeUserEditor"
          >
            ✕
          </button>
        </div>

        <!-- USER FIELDS -->
        <div class="grid grid-cols-1 gap-4">
          <div>
            <label class="hz-caption block mb-1">Rank</label>
            <input v-model="form.rank" type="text" class="hz-input w-full" />
          </div>

          <div>
            <label class="hz-caption block mb-1">Rank Level</label>
            <input v-model.number="form.rank_level" type="number" class="hz-input w-full" />
          </div>

          <div>
            <label class="hz-caption block mb-1">Global Status</label>
            <input v-model="form.global_status" type="text" class="hz-input w-full" />
          </div>

          <div>
            <label class="hz-caption block mb-1">Timezone</label>
            <input v-model="form.timezone" type="text" class="hz-input w-full" />
          </div>

          <div>
            <label class="hz-caption block mb-1">Availability Status</label>
            <input v-model="form.availability_status" type="text" class="hz-input w-full" />
          </div>

          <div>
            <label class="hz-caption block mb-1">LOA Note</label>
            <textarea
              v-model="form.loa_note"
              class="hz-input w-full min-h-[80px]"
            ></textarea>
          </div>

          <div>
            <label class="hz-caption block mb-1">Bio</label>
            <textarea
              v-model="form.bio"
              class="hz-input w-full min-h-[100px]"
            ></textarea>
          </div>

          <!-- ROLES -->
          <div>
            <label class="hz-caption block mb-1">Roles</label>

            <div class="space-y-2 p-3 hz-overlay-dark rounded-lg">
              <div
                v-for="role in roles"
                :key="role.id"
                class="flex items-center gap-3"
              >
                <input
                  type="checkbox"
                  :value="role.id"
                  v-model="form.role_ids"
                  class="hz-checkbox"
                />
                <span class="hz-caption">{{ role.name }}</span>
              </div>
            </div>

            <HorizonButton
              size="sm"
              variant="outline"
              class="mt-3"
              @click="saveUserRoles"
            >
              Save Roles
            </HorizonButton>
          </div>
        </div>

        <div class="flex justify-end gap-3 mt-4">
          <HorizonButton variant="ghost" size="sm" @click="closeUserEditor">
            Cancel
          </HorizonButton>

          <HorizonButton variant="primary" size="sm" @click="saveUser">
            Save Changes
          </HorizonButton>
        </div>
      </div>
    </div>

  </HorizonContainer>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';

import HorizonContainer from '@/Components/HorizonContainer.vue';
import HorizonPanel from '@/Components/HorizonPanel.vue';
import HorizonButton from '@/Components/HorizonButton.vue';
import HorizonSectionHeader from '@/Components/HorizonSectionHeader.vue';

const props = defineProps({
  users: {
    type: Object,
    required: true,
  },
  roles: {
    type: Array,
    default: () => [],
  },
  filters: {
    type: Object,
    default: () => ({}),
  },
});

const users = props.users;

const search = ref(props.filters.search || '');

const totalUsers   = computed(() => users?.total ?? 0);
const currentPage  = computed(() => users?.current_page ?? 1);
const lastPage     = computed(() => users?.last_page ?? 1);
const prevUrl      = computed(() => users?.prev_page_url || null);
const nextUrl      = computed(() => users?.next_page_url || null);

function applySearch() {
  router.visit(route('admin.users.index'), {
    method: 'get',
    data: { search: search.value },
    preserveScroll: true,
  });
}

function clearSearch() {
  search.value = '';
  router.visit(route('admin.users.index'), {
    method: 'get',
    preserveScroll: true,
  });
}

function goTo(url) {
  if (url) {
    router.visit(url, { preserveScroll: true });
  }
}

/* USER EDITOR LOGIC */
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

function saveUser() {
  router.post(route('admin.users.update'), form.value, {
    preserveScroll: true,
    onSuccess: () => closeUserEditor(),
  });
}

function saveUserRoles() {
  router.post(route('admin.users.updateRoles'), {
    id: form.value.id,
    role_ids: form.value.role_ids,
  }, {
    preserveScroll: true,
  });
}
</script>
