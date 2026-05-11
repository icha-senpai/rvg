<template>
  <div class="mx-auto max-w-5xl">
  <HorizonPanel class="p-6 space-y-6 !border !border-[color:var(--horizon-sunset-blue)]">

    <!-- HEADER -->
    <div class="flex justify-between items-center">
      <div class="hz-title-lg">Squadrons</div>

      <HorizonButton variant="primary" size="sm" @click="openCreateModal">
        Create Squadron
      </HorizonButton>
    </div>

    <!-- LIST OF SQUADRONS -->
    <div class="space-y-3">
      <div
        v-for="sq in squadrons"
        :key="sq.id"
        class="hz-card-soft hz-row-between !border !border-[color:var(--horizon-sunset-blue)]"
      >
        <div class="hz-row gap-3 items-center">
          <div
            v-if="sq?.emblem_url"
            class="w-24 h-24 rounded-lg overflow-hidden border border-[color:var(--horizon-sunset-blue)] bg-bg-surface shrink-0"
          >
            <img
              :src="sq?.emblem?.thumbnail_url || sq?.emblem?.medium_url || sq?.emblem?.url || sq?.emblem_url"
              :alt="sq?.emblem?.alt_text || `${sq?.name} emblem`"
              class="w-full h-full object-contain"
              loading="lazy"
            />
          </div>

          <div class="space-y-1">
            <div class="hz-title">{{ sq.name }}</div>

            <div class="hz-caption text-horizon-muted">
              Slug: {{ sq.slug }}
            </div>

            <div class="hz-caption text-horizon-muted">
              Status: {{ sq.status }}
            </div>

            <div v-if="sq.branch" class="hz-caption text-horizon-muted">
              Branch: {{ formatTitle(sq.branch) }}<span v-if="sq.division"> · {{ formatTitle(sq.division) }}</span>
            </div>

            <div class="hz-caption text-horizon-muted">
              Leader:
              <span
                v-if="sq.leader"
                :style="leaderNameColor(sq.leader) ? { color: leaderNameColor(sq.leader) } : undefined"
              >
                {{ sq.leader.rsi_handle ?? sq.leader.discord_name }}
              </span>
              <span v-else>None</span>
            </div>
          </div>
        </div>

        <div class="flex gap-2">
          <HorizonButton size="sm" variant="primary" @click="openEditModal(sq)">
            Edit
          </HorizonButton>

          <HorizonButton
            size="sm"
            variant="danger"
            @click="deleteSquadron(sq.id)"
          >
            Delete
          </HorizonButton>
        </div>
      </div>
    </div>

    <!-- CREATE / EDIT MODAL -->
    <div
      v-if="modalOpen"
      class="hz-overlay flex items-center justify-center"
      @click.self="closeModal"
    >
      <div class="hz-modal hz-stack max-h-[85vh] overflow-y-auto hz-animate-pop">

        <!-- TITLE BAR -->
        <div class="hz-row-between">
          <div class="hz-title-lg">
            {{ isEditing ? 'Edit Squadron' : 'Create Squadron' }}
          </div>
          <HorizonButton
            variant="primary"
            size="sm"
            @click="closeModal"
          >
            ✕
          </HorizonButton>
        </div>

        <!-- FORM -->
        <div class="space-y-4">
          <div>
            <label class="hz-caption block mb-1">Name</label>
            <input v-model="form.name" class="hz-input w-full" />
          </div>

          <div>
            <label class="hz-caption block mb-1">Slug</label>
            <input v-model="form.slug" class="hz-input w-full" />
          </div>

          <div>
          <HorizonSelect
            v-model="form.status"
            :options="[
              { label: 'Active', value: 'active' },
              { label: 'Inactive', value: 'inactive' },
              { label: 'Disbanded', value: 'disbanded' }
            ]"
            label="Status"
            class="w-full"
          />
          </div>

          <div>
            <HorizonSelect
              v-model="form.branch"
              :options="branchOptions"
              label="Branch"
              class="w-full"
            />
          </div>

          <div>
            <HorizonSelect
              v-model="form.division"
              :options="divisionOptions"
              label="Division"
              class="w-full"
            />
          </div>

          <div v-if="isEditing" class="hz-caption text-horizon-muted">
            Current leader:
            <span v-if="editingSquadron?.leader">
              {{ editingSquadron.leader.discord_name }}
            </span>
            <span v-else>None</span>

            <HorizonButton
              v-if="form.leader_id"
              class="ml-3"
              size="xs"
              variant="ghost"
              type="button"
              @click="form.leader_id = null"
            >
              Unassign
            </HorizonButton>
          </div>

          <div>
            <HorizonSelect
              v-model="form.leader_id"
              :options="[
                { label: 'None', value: null },
                ...eligibleLeaders.map(u => ({
                  label: `${u.rank ?? 'Rank'} (${u.rank_level}) • ${u.rsi_handle ?? u.discord_name ?? 'Unknown'}`,
                  value: u.id,
                }))
              ]"
              label="Leader"
              class="w-full"
            />
          </div>
        </div>

        <!-- ACTION BUTTONS -->
        <div class="flex justify-end gap-3 mt-4">
          <HorizonButton variant="primary" size="sm" @click="closeModal">
            Cancel
          </HorizonButton>

          <HorizonButton variant="primary" size="sm" @click="saveSquadron">
            {{ isEditing ? 'Save Changes' : 'Create Squadron' }}
          </HorizonButton>
        </div>

      </div>
    </div>
  </HorizonPanel>
  </div>

  <HorizonConfirmDialog
    ref="deleteConfirmDialog"
    title="Delete Squadron"
    confirm-label="Delete"
    cancel-label="Cancel"
    variant="danger"
    message="This action cannot be undone."
    @confirm="confirmDeleteSquadron"
  />
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';

import HorizonPanel from '@/Components/HorizonPanel.vue';
import HorizonButton from '@/Components/HorizonButton.vue';
import HorizonSelect from '@/Components/HorizonSelect.vue';
import HorizonConfirmDialog from '@/Components/HorizonConfirmDialog.vue';

import { getHighestOrgRoleSlug, getOrgRoleColor } from '@/roleColors'

const props = defineProps({
  squadrons: {
    type: Array,
    required: true,
  },
  eligibleLeaders: {
    type: Array,
    default: () => [],
  },
});

const form = ref({
  id: null,
  name: '',
  slug: '',
  status: 'active',
  branch: null,
  division: null,
  leader_id: null,
});

const branchOptions = [
  { label: 'None', value: null },
  { label: 'Defence', value: 'defence' },
  { label: 'Industries', value: 'industries' },
  { label: 'Frontiers', value: 'frontiers' },
  { label: 'Lifeline', value: 'lifeline' },
]

const divisionsByBranch = {
  defence: [
    { label: 'Marines', value: 'marines' },
    { label: 'Navy', value: 'navy' },
    { label: 'Airforce', value: 'airforce' },
  ],
  industries: [
    { label: 'Procurement', value: 'procurement' },
    { label: 'Logistics', value: 'logistics' },
    { label: 'Construction', value: 'construction' },
  ],
  frontiers: [
    { label: 'Exploration', value: 'exploration' },
    { label: 'Science', value: 'science' },
    { label: 'Development', value: 'development' },
  ],
  lifeline: [
    { label: 'Triage', value: 'triage' },
    { label: 'Recovery', value: 'recovery' },
    { label: 'Medical', value: 'medical' },
  ],
}

const divisionOptions = computed(() => {
  const branch = form.value?.branch
  if (!branch || !divisionsByBranch[branch]) {
    return [{ label: 'None', value: null }]
  }

  return [{ label: 'None', value: null }, ...divisionsByBranch[branch]]
})

watch(
  () => form.value.branch,
  (branch) => {
    if (!branch) {
      form.value.division = null
      return
    }

    const allowed = (divisionsByBranch[branch] ?? []).map(d => d.value)
    if (form.value.division && !allowed.includes(form.value.division)) {
      form.value.division = null
    }
  }
)

function formatTitle(value) {
  const raw = String(value ?? '').trim()
  if (!raw) return ''

  return raw
    .replace(/[_-]+/g, ' ')
    .split(' ')
    .map(w => (w ? w.charAt(0).toUpperCase() + w.slice(1) : ''))
    .join(' ')
}

function leaderNameColor(leader) {
  const slug = getHighestOrgRoleSlug(leader?.roles, leader?.rank)
  return getOrgRoleColor(slug)
}

/* MODAL STATE */
const modalOpen = ref(false);
const isEditing = ref(false);

const editingSquadron = ref(null);

function handleKeydown(event) {
  if (event.key !== 'Escape') return;
  if (!modalOpen.value) return;

  closeModal();
}

/* OPEN CREATE */
function openCreateModal() {
  isEditing.value = false;
  modalOpen.value = true;

  editingSquadron.value = null;

  form.value = {
    id: null,
    name: '',
    slug: '',
    status: 'active',
    branch: null,
    division: null,
    leader_id: null,
  };
}

/* OPEN EDIT */
function openEditModal(sq) {
  isEditing.value = true;
  modalOpen.value = true;

  editingSquadron.value = sq;

  form.value = {
    id: sq.id,
    name: sq.name,
    slug: sq.slug,
    status: sq.status,
    branch: sq.branch ?? null,
    division: sq.division ?? null,
    leader_id: sq.leader_id ?? null,
  };
}


/* CLOSE */
function closeModal() {
  modalOpen.value = false;
  editingSquadron.value = null;
}

onMounted(() => {
  window.addEventListener('keydown', handleKeydown);
});

onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleKeydown);
});

/* SAVE */
function saveSquadron() {
  if (isEditing.value) {
    router.post(route('admin.squadrons.update'), form.value, {
      preserveScroll: true,
      onSuccess: () => closeModal(),
    });
  } else {
    router.post(route('admin.squadrons.store'), form.value, {
      preserveScroll: true,
      onSuccess: () => closeModal(),
    });
  }
}

const deleteConfirmDialog = ref(null);
const pendingDeleteSquadronId = ref(null);

/* DELETE */
function deleteSquadron(id) {
  pendingDeleteSquadronId.value = id;
  deleteConfirmDialog.value?.show();
}

function confirmDeleteSquadron({ close }) {
  const id = pendingDeleteSquadronId.value;
  if (!id) {
    close();
    return;
  }

  router.post(route('admin.squadrons.delete'), { id }, {
    preserveScroll: true,
    onSuccess: () => {
      close();
      pendingDeleteSquadronId.value = null;
    },
  });
}
</script>
