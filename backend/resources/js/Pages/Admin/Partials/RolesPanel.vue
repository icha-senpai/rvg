<template>
  <div class="mx-auto max-w-5xl">
  <HorizonPanel class="p-6 space-y-6">

    <!-- HEADER -->
    <div class="flex justify-between items-center">
      <div class="hz-title-lg">Roles</div>

      <HorizonButton variant="primary" size="sm" @click="openCreate">
        Create Role
      </HorizonButton>
    </div>

    <!-- ROLES LIST -->
    <div class="space-y-3">
      <div
        v-for="role in roles"
        :key="role.id"
        class="p-4 hz-overlay-light rounded-xl flex justify-between items-center"
      >
        <div>
          <div class="hz-title">{{ role.name }}</div>

          <div class="hz-caption text-horizon-muted">
            Slug: {{ role.slug }}
          </div>
        </div>

        <div class="flex gap-2">
          <HorizonButton size="sm" variant="primary" @click="openEdit(role)">
            Edit
          </HorizonButton>

          <HorizonButton
            size="sm"
            variant="danger"
            @click="deleteRole(role.id)"
          >
            Delete
          </HorizonButton>
        </div>
      </div>
    </div>

    <!-- MODAL -->
    <div
      v-if="modalOpen"
      class="hz-overlay flex items-center justify-center"
      @click.self="closeModal"
    >
      <div class="hz-modal hz-stack max-h-[85vh] overflow-y-auto hz-animate-pop">

        <div class="hz-row-between">
          <div class="hz-title-lg">
            {{ isEditing ? 'Edit Role' : 'Create Role' }}
          </div>

          <HorizonButton
            variant="primary"
            size="sm"
            @click="closeModal"
          >
            ✕
          </HorizonButton>
        </div>

        <div class="space-y-4">
          <div>
            <label class="hz-caption mb-1 block">Role Name</label>
            <input v-model="form.name" class="hz-input w-full" />
          </div>

          <div>
            <label class="hz-caption mb-1 block">Slug (unique)</label>
            <input v-model="form.slug" class="hz-input w-full" />
          </div>
        </div>

        <div class="flex justify-end gap-3 mt-4">
          <HorizonButton variant="primary" size="sm" @click="closeModal">
            Cancel
          </HorizonButton>

          <HorizonButton variant="primary" size="sm" @click="saveRole">
            {{ isEditing ? 'Save Changes' : 'Create Role' }}
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
import { route } from 'ziggy-js';

import HorizonPanel from '@/Components/HorizonPanel.vue';
import HorizonButton from '@/Components/HorizonButton.vue';

const props = defineProps({
  roles: Array,
});

/* MODAL STATE */
const modalOpen = ref(false);
const isEditing = ref(false);

function handleKeydown(event) {
  if (event.key !== 'Escape') return;
  if (!modalOpen.value) return;

  closeModal();
}

const form = ref({
  id: null,
  name: '',
  slug: '',
});

/* OPEN CREATE */
function openCreate() {
  isEditing.value = false;
  modalOpen.value = true;

  form.value = {
    id: null,
    name: '',
    slug: '',
  };
}

/* OPEN EDIT */
function openEdit(role) {
  isEditing.value = true;
  modalOpen.value = true;

  form.value = {
    id: role.id,
    name: role.name,
    slug: role.slug,
  };
}

/* CLOSE */
function closeModal() {
  modalOpen.value = false;
}

onMounted(() => {
  window.addEventListener('keydown', handleKeydown);
});

onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleKeydown);
});

/* SAVE */
function saveRole() {
  if (isEditing.value) {
    router.post(route('admin.roles.update'), form.value, {
      preserveScroll: true,
      onSuccess: () => closeModal(),
    });
  } else {
    router.post(route('admin.roles.store'), form.value, {
      preserveScroll: true,
      onSuccess: () => closeModal(),
    });
  }
}

/* DELETE */
function deleteRole(id) {
  if (!confirm('Delete this role?')) return;

  router.post(route('admin.roles.delete'), { id }, {
    preserveScroll: true,
  });
}
</script>
