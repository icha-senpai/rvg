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

            <div class="hz-caption text-horizon-muted">
              Leader:
              <span v-if="sq.leader"> {{ sq.leader.discord_name }} </span>
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
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { router } from '@inertiajs/vue3';

import HorizonPanel from '@/Components/HorizonPanel.vue';
import HorizonButton from '@/Components/HorizonButton.vue';
import HorizonSelect from '@/Components/HorizonSelect.vue';

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

/* MODAL STATE */
const modalOpen = ref(false);
const isEditing = ref(false);

const editingSquadron = ref(null);

function handleKeydown(event) {
  if (event.key !== 'Escape') return;
  if (!modalOpen.value) return;

  closeModal();
}

const form = ref({
  id: null,
  name: '',
  slug: '',
  status: 'active',
  leader_id: null,
});

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

/* DELETE */
function deleteSquadron(id) {
  if (!confirm('Delete this squadron?')) return;

  router.post(route('admin.squadrons.delete'), { id }, {
    preserveScroll: true,
  });
}
</script>
