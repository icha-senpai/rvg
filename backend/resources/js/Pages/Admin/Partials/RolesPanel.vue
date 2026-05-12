<template>
  <div class="mx-auto max-w-6xl space-y-6">
    <!-- Roles admin header -->
    <section class="relative overflow-hidden rounded-[2rem] border border-[color:var(--horizon-sunset-indigo)]/35 bg-[radial-gradient(circle_at_top_left,var(--horizon-glow-blue),transparent_36%),radial-gradient(circle_at_top_right,var(--horizon-glow-magenta),transparent_34%),linear-gradient(135deg,var(--horizon-void-700),var(--horizon-void-900))] p-5 shadow-[0_0_40px_rgba(67,56,202,0.16)]">
      <div class="pointer-events-none absolute inset-0 opacity-40">
        <div class="absolute left-8 top-0 h-px w-48 bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-blue)] to-transparent"></div>
        <div class="absolute bottom-0 right-10 h-px w-64 bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-magenta)] to-transparent"></div>
      </div>

      <div class="relative flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
        <div>
          <div class="text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--horizon-text-secondary)]">
            Access Administration
          </div>

          <h2 class="mt-1 text-2xl font-black text-horizon-white">
            Role Registry
          </h2>

          <p class="mt-2 max-w-3xl text-sm text-text-secondary">
            Create, edit, and retire Horizon platform roles used for access control and command visibility.
          </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
          <div class="rounded-2xl border border-[color:var(--horizon-sunset-magenta)]/25 bg-[color:var(--horizon-sunset-magenta)]/10 px-4 py-3">
            <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
              Total
            </div>

            <div class="mt-1 text-sm font-semibold text-horizon-white">
              {{ roles.length }} Roles
            </div>
          </div>

          <HorizonButton
            variant="primary"
            size="sm"
            @click="openCreate"
          >
            Create Role
          </HorizonButton>
        </div>
      </div>
    </section>

    <!-- Roles cards -->
    <section class="rounded-[2rem] border border-[color:var(--horizon-sunset-blue)]/25 bg-[color:var(--horizon-void-700)]/70 p-4 shadow-[0_0_32px_rgba(30,64,175,0.10)] md:p-5">
      <div class="mb-5 flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
        <div>
          <div class="text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--horizon-text-secondary)]">
            Access Records
          </div>

          <h3 class="mt-1 text-xl font-black text-horizon-white">
            {{ roles.length }} Registered Roles
          </h3>

          <p class="mt-1 text-sm text-text-secondary">
            Role records provide named access markers for users, panels, and command tooling.
          </p>
        </div>
      </div>

      <div
        v-if="roles.length"
        class="grid gap-4 md:grid-cols-2"
      >
        <article
          v-for="role in roles"
          :key="role.id"
          class="group relative overflow-hidden rounded-[1.75rem] border border-white/10 bg-[linear-gradient(135deg,rgba(30,64,175,0.10),var(--horizon-void-700)_42%,var(--horizon-void-900))] p-5 shadow-[0_0_28px_rgba(30,64,175,0.10)] transition duration-200 hover:-translate-y-0.5 hover:border-[color:var(--horizon-sunset-magenta)]/40 hover:shadow-[0_0_42px_rgba(192,38,211,0.14)]"
        >
          <div class="pointer-events-none absolute inset-0 opacity-0 transition duration-200 group-hover:opacity-100">
            <div class="absolute inset-x-8 top-0 h-px bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-blue)] to-transparent"></div>
            <div class="absolute inset-x-10 bottom-0 h-px bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-magenta)] to-transparent"></div>
          </div>

          <div class="relative flex flex-col gap-5">
            <div class="flex items-start justify-between gap-4">
              <div class="min-w-0">
                <div class="text-xs font-bold uppercase tracking-[0.2em] text-text-muted">
                  Role Record #{{ role.id }}
                </div>

                <div class="mt-1 truncate text-2xl font-black tracking-tight text-horizon-white">
                  {{ role.name }}
                </div>

                <div class="mt-1 text-sm text-text-secondary">
                  Slug:
                  <span class="font-semibold text-horizon-white">{{ role.slug }}</span>
                </div>
              </div>

              <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl border border-[color:var(--horizon-sunset-magenta)]/25 bg-[color:var(--horizon-sunset-magenta)]/10 text-xl font-black text-horizon-white shadow-[0_0_20px_rgba(192,38,211,0.12)]">
                {{ String(role.name || 'R').slice(0, 1).toUpperCase() }}
              </div>
            </div>

            <div class="flex flex-wrap gap-2">
              <span class="rounded-full border border-[color:var(--horizon-sunset-blue)]/25 bg-[color:var(--horizon-sunset-blue)]/10 px-3 py-1 text-xs font-semibold text-[color:var(--horizon-text-primary)]">
                Access Role
              </span>

              <span class="rounded-full border border-white/10 bg-white/[0.035] px-3 py-1 text-xs font-semibold text-text-secondary">
                {{ role.slug }}
              </span>
            </div>

            <div class="flex flex-wrap gap-2 border-t border-white/10 pt-4">
              <HorizonButton
                size="sm"
                variant="primary"
                @click="openEdit(role)"
              >
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
        </article>
      </div>

      <div
        v-else
        class="rounded-[1.5rem] border border-dashed border-white/15 bg-white/[0.025] p-10 text-center"
      >
        <div class="text-2xl font-black text-horizon-white">
          No Roles Found
        </div>

        <p class="mx-auto mt-2 max-w-xl text-sm text-text-secondary">
          Create the first role record to begin defining access structure.
        </p>

        <HorizonButton
          class="mt-4"
          variant="primary"
          size="sm"
          @click="openCreate"
        >
          Create Role
        </HorizonButton>
      </div>
    </section>

    <!-- MODAL -->
    <div
      v-if="modalOpen"
      class="fixed inset-0 z-[90] flex items-center justify-center bg-black/75 p-4 backdrop-blur-md"
      @click.self="closeModal"
    >
      <div class="pointer-events-none absolute inset-0 overflow-hidden">
        <div class="absolute left-1/4 top-10 h-96 w-96 rounded-full bg-[color:var(--horizon-sunset-blue)]/16 blur-3xl"></div>
        <div class="absolute bottom-10 right-1/4 h-96 w-96 rounded-full bg-[color:var(--horizon-sunset-magenta)]/14 blur-3xl"></div>
      </div>

      <div class="relative z-10 flex max-h-[88vh] w-full max-w-2xl flex-col overflow-hidden rounded-[2rem] border border-[color:var(--horizon-sunset-indigo)]/45 bg-[linear-gradient(135deg,var(--horizon-void-700),var(--horizon-void-900))] shadow-[0_0_72px_rgba(67,56,202,0.28)] hz-animate-pop">
        <div class="pointer-events-none absolute inset-0 opacity-40">
          <div class="absolute left-8 top-0 h-px w-56 bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-blue)] to-transparent"></div>
          <div class="absolute bottom-0 right-10 h-px w-72 bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-magenta)] to-transparent"></div>
        </div>

        <header class="relative shrink-0 border-b border-[color:var(--horizon-sunset-blue)]/20 bg-white/[0.025] p-5">
          <div class="flex items-start justify-between gap-4">
            <div>
              <div class="text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--horizon-text-secondary)]">
                {{ isEditing ? 'Edit Role' : 'Create Role' }}
              </div>

              <div class="mt-1 text-2xl font-black text-horizon-white">
                {{ isEditing ? form.name : 'New Access Role' }}
              </div>

              <p class="mt-1 text-sm text-text-secondary">
                Configure a role name and unique slug for administrative access logic.
              </p>
            </div>

            <button
              type="button"
              class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-white/10 bg-white/[0.035] text-lg font-bold text-text-secondary transition hover:border-[color:var(--horizon-sunset-magenta)]/35 hover:bg-[color:var(--horizon-sunset-magenta)]/10 hover:text-horizon-white"
              aria-label="Close role editor"
              @click="closeModal"
            >
              ✕
            </button>
          </div>
        </header>

        <div class="relative flex-1 overflow-y-auto p-5">
          <section class="rounded-[1.5rem] border border-[color:var(--horizon-sunset-blue)]/20 bg-[color:var(--horizon-sunset-blue)]/10 p-4">
            <div class="mb-4">
              <div class="text-xs font-bold uppercase tracking-[0.2em] text-text-muted">
                Role Identity
              </div>

              <p class="mt-1 text-sm text-text-secondary">
                Use a clear human-readable name and a stable machine slug.
              </p>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
              <div>
                <label class="mb-1 block text-xs font-bold uppercase tracking-[0.16em] text-text-muted">
                  Role Name
                </label>
                <input
                  v-model="form.name"
                  class="hz-input w-full"
                />
              </div>

              <div>
                <label class="mb-1 block text-xs font-bold uppercase tracking-[0.16em] text-text-muted">
                  Slug
                </label>
                <input
                  v-model="form.slug"
                  class="hz-input w-full"
                />
              </div>
            </div>
          </section>
        </div>

        <footer class="relative shrink-0 border-t border-[color:var(--horizon-sunset-blue)]/20 bg-white/[0.025] p-5">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="text-sm text-text-secondary">
              {{ isEditing ? 'Save changes to this role record.' : 'Create a new access role.' }}
            </div>

            <div class="flex flex-wrap gap-2 sm:justify-end">
              <HorizonButton
                variant="ghost"
                size="sm"
                @click="closeModal"
              >
                Cancel
              </HorizonButton>

              <HorizonButton
                variant="primary"
                size="sm"
                @click="saveRole"
              >
                {{ isEditing ? 'Save Changes' : 'Create Role' }}
              </HorizonButton>
            </div>
          </div>
        </footer>
      </div>
    </div>
  </div>

  <HorizonConfirmDialog
    ref="deleteConfirmDialog"
    title="Delete Role"
    confirm-label="Delete"
    cancel-label="Cancel"
    variant="danger"
    message="This action cannot be undone."
    @confirm="confirmDeleteRole"
  />
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

import HorizonButton from '@/Components/HorizonButton.vue'
import HorizonConfirmDialog from '@/Components/HorizonConfirmDialog.vue'

const props = defineProps({
  roles: {
    type: Array,
    default: () => [],
  },
})

/* MODAL STATE */
const modalOpen = ref(false)
const isEditing = ref(false)

function handleKeydown(event) {
  if (event.key !== 'Escape') return
  if (!modalOpen.value) return

  closeModal()
}

const form = ref({
  id: null,
  name: '',
  slug: '',
})

/* OPEN CREATE */
function openCreate() {
  isEditing.value = false
  modalOpen.value = true

  form.value = {
    id: null,
    name: '',
    slug: '',
  }
}

/* OPEN EDIT */
function openEdit(role) {
  isEditing.value = true
  modalOpen.value = true

  form.value = {
    id: role.id,
    name: role.name,
    slug: role.slug,
  }
}

/* CLOSE */
function closeModal() {
  modalOpen.value = false
}

/* SAVE */
function saveRole() {
  if (isEditing.value) {
    router.post(route('admin.roles.update'), form.value, {
      preserveScroll: true,
      onSuccess: () => closeModal(),
    })
  } else {
    router.post(route('admin.roles.store'), form.value, {
      preserveScroll: true,
      onSuccess: () => closeModal(),
    })
  }
}

const deleteConfirmDialog = ref(null)
const pendingDeleteRoleId = ref(null)

/* DELETE */
function deleteRole(id) {
  pendingDeleteRoleId.value = id
  deleteConfirmDialog.value?.show()
}

function confirmDeleteRole({ close }) {
  const id = pendingDeleteRoleId.value

  if (!id) {
    close()
    return
  }

  router.post(route('admin.roles.delete'), { id }, {
    preserveScroll: true,
    onSuccess: () => {
      close()
      pendingDeleteRoleId.value = null
    },
  })
}

onMounted(() => {
  window.addEventListener('keydown', handleKeydown)
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleKeydown)
})
</script>