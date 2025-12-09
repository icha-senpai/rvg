<template>
  <HorizonPanel class="p-6 space-y-6">

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
        class="p-4 hz-overlay-light rounded-xl flex justify-between items-center"
      >
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

        <div class="flex gap-2">
          <HorizonButton size="sm" variant="outline" @click="openEditModal(sq)">
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
      class="fixed inset-0 z-50 bg-black/70 flex items-center justify-center"
    >
      <div class="w-full max-w-xl max-h-[80vh] overflow-y-auto hz-overlay-light rounded-2xl p-6 space-y-4">

        <!-- TITLE BAR -->
        <div class="flex justify-between items-center mb-2">
          <div class="hz-title-lg">
            {{ isEditing ? 'Edit Squadron' : 'Create Squadron' }}
          </div>
          <button
            class="hz-caption text-horizon-muted hover:text-horizon-white"
            @click="closeModal"
          >
            ✕
          </button>
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
              v-model="form.leader_id"
              :options="[
                { label: 'None', value: null },
                ...users.map(u => ({ label: u.rsi_handle, value: u.id }))
              ]"
              label="Leader"
              class="w-full"
            />
          </div>
        </div>

        <!-- ACTION BUTTONS -->
        <div class="flex justify-end gap-3 mt-4">
          <HorizonButton variant="ghost" size="sm" @click="closeModal">
            Cancel
          </HorizonButton>

          <HorizonButton variant="primary" size="sm" @click="saveSquadron">
            {{ isEditing ? 'Save Changes' : 'Create Squadron' }}
          </HorizonButton>
        </div>

      </div>
    </div>
  </HorizonPanel>
</template>

<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

import HorizonPanel from '@/Components/HorizonPanel.vue';
import HorizonButton from '@/Components/HorizonButton.vue';
import HorizonSelect from '@/Components/HorizonSelect.vue';

const props = defineProps({
  squadrons: {
    type: Array,
    required: true,
  },
  users: {
    type: Array,
    default: () => [],
  },
});

/* MODAL STATE */
const modalOpen = ref(false);
const isEditing = ref(false);

const form = ref({
  id: null,
  name: '',
  slug: '',
  status: 'active',
  leader_id: '',
});

/* OPEN CREATE */
function openCreateModal() {
  isEditing.value = false;
  modalOpen.value = true;

  form.value = {
    id: null,
    name: '',
    slug: '',
    status: 'active',
    leader_id: '',
  };
}

/* OPEN EDIT */
function openEditModal(sq) {
  isEditing.value = true;
  modalOpen.value = true;

  form.value = {
    id: sq.id,
    name: sq.name,
    slug: sq.slug,
    status: sq.status,
    leader_id: sq.leader ? sq.leader.id : '',
  };
}


/* CLOSE */
function closeModal() {
  modalOpen.value = false;
}

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
