<template>
  <div class="mx-auto max-w-6xl space-y-6">
    <!-- Squadron admin header -->
    <section class="relative overflow-hidden rounded-[2rem] border border-white/[0.055] bg-[rgba(21,25,42,0.46)] p-5 ">
      <div class="pointer-events-none absolute inset-0 opacity-40">
        <div class="absolute left-8 top-0 h-px w-48 bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-blue)] to-transparent"></div>
        <div class="absolute bottom-0 right-10 h-px w-64 bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-magenta)] to-transparent"></div>
      </div>

      <div class="relative flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
        <div>
          <div class="text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--horizon-text-secondary)]">
            Squadron Administration
          </div>

          <h2 class="mt-1 text-2xl font-black text-horizon-white">
            Squadron Registry
          </h2>

          <p class="mt-2 max-w-3xl text-sm text-text-secondary">
            Create, edit, assign leaders, and retire Horizon squadron records.
          </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
          <div class="rounded-2xl border border-white/[0.055] bg-white/[0.042] px-4 py-3">
            <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
              Total
            </div>

            <div class="mt-1 text-sm font-semibold text-horizon-white">
              {{ squadrons.length }} Squadrons
            </div>
          </div>

          <HorizonButton
            variant="primary"
            size="sm"
            @click="openCreateModal"
          >
            Create Squadron
          </HorizonButton>
        </div>
      </div>
    </section>

    <!-- Squadron cards -->
    <section class="rounded-[2rem] border border-white/[0.055] bg-[color:var(--horizon-void-700)]/70 p-4  md:p-5">
      <div class="mb-5 flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
        <div>
          <div class="text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--horizon-text-secondary)]">
            Unit Records
          </div>

          <h3 class="mt-1 text-xl font-black text-horizon-white">
            {{ squadrons.length }} Registered Units
          </h3>

          <p class="mt-1 text-sm text-text-secondary">
            Manage squadron identity, branch alignment, division, status, and assigned command.
          </p>
        </div>
      </div>

      <div
        v-if="squadrons.length"
        class="grid gap-4"
      >
        <article
          v-for="sq in squadrons"
          :key="sq.id"
          class="group relative overflow-hidden rounded-[1.75rem] border border-white/10 bg-[rgba(21,25,42,0.64)] p-5  transition duration-200 hover:-translate-y-0.5 hover:border-white/[0.055] hover:"
        >
          <div class="pointer-events-none absolute inset-0 opacity-0 transition duration-200 group-hover:opacity-100">
            <div class="absolute inset-x-8 top-0 h-px bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-blue)] to-transparent"></div>
            <div class="absolute inset-x-10 bottom-0 h-px bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-magenta)] to-transparent"></div>
          </div>

          <div class="relative grid gap-5 lg:grid-cols-[auto_minmax(0,1fr)_auto] lg:items-center">
            <div
              v-if="sq?.emblem_url"
              class="flex h-24 w-24 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-white/[0.055] bg-black/20 "
            >
              <img
                :src="sq?.emblem?.thumbnail_url || sq?.emblem?.medium_url || sq?.emblem?.url || sq?.emblem_url"
                :alt="sq?.emblem?.alt_text || `${sq?.name} emblem`"
                class="h-full w-full object-contain"
                loading="lazy"
              />
            </div>

            <div
              v-else
              class="flex h-24 w-24 shrink-0 items-center justify-center rounded-2xl border border-white/[0.055] bg-white/[0.04] text-3xl font-black text-horizon-white "
            >
              {{ String(sq.name || 'S').slice(0, 1).toUpperCase() }}
            </div>

            <div class="min-w-0 space-y-3">
              <div>
                <div class="text-xs font-bold uppercase tracking-[0.2em] text-text-muted">
                  Squadron Record #{{ sq.id }}
                </div>

                <div class="mt-1 truncate text-2xl font-black tracking-tight text-horizon-white">
                  {{ sq.name }}
                </div>

                <div class="mt-1 text-sm text-text-secondary">
                  Slug:
                  <span class="font-semibold text-horizon-white">{{ sq.slug }}</span>
                </div>
              </div>

              <div class="flex flex-wrap gap-2">
                <span
                  class="rounded-full border px-3 py-1 text-xs font-semibold"
                  :class="sq.status === 'active'
                    ? 'border-emerald-300/25 bg-emerald-300/10 text-emerald-100'
                    : sq.status === 'disbanded'
                      ? 'border-red-300/25 bg-red-300/10 text-red-100'
                      : 'border-amber-300/25 bg-amber-300/10 text-amber-100'"
                >
                  {{ formatTitle(sq.status) }}
                </span>

                <span class="rounded-full border border-white/[0.055] bg-white/[0.042] px-3 py-1 text-xs font-semibold text-[color:var(--horizon-text-primary)]">
                  {{ sq.branch ? formatTitle(sq.branch) : 'No Branch' }}
                </span>

                <span class="rounded-full border border-white/[0.055] bg-white/[0.042] px-3 py-1 text-xs font-semibold text-[color:var(--horizon-text-primary)]">
                  {{ sq.division ? formatTitle(sq.division) : 'No Division' }}
                </span>
              </div>

              <div class="rounded-2xl border border-white/[0.055] bg-white/[0.024] p-3">
                <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
                  Squadron Leader
                </div>

                <div class="mt-1 text-sm text-text-secondary">
                  <span
                    v-if="sq.leader"
                    class="font-semibold"
                    :style="leaderNameColor(sq.leader) ? { color: leaderNameColor(sq.leader) } : undefined"
                  >
                    {{ sq.leader.rsi_handle ?? sq.leader.discord_name }}
                  </span>

                  <span v-else>
                    No leader assigned.
                  </span>
                </div>
              </div>
            </div>

            <div class="flex flex-wrap gap-2 lg:justify-end">
              <HorizonButton
                size="sm"
                variant="primary"
                @click="openEditModal(sq)"
              >
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
        </article>
      </div>

      <div
        v-else
        class="rounded-[1.5rem] border border-dashed border-white/15 bg-white/[0.025] p-10 text-center"
      >
        <div class="text-2xl font-black text-horizon-white">
          No Squadrons Found
        </div>

        <p class="mx-auto mt-2 max-w-xl text-sm text-text-secondary">
          Create the first squadron record to begin building the organization structure.
        </p>

        <HorizonButton
          class="mt-4"
          variant="primary"
          size="sm"
          @click="openCreateModal"
        >
          Create Squadron
        </HorizonButton>
      </div>
    </section>

    <!-- CREATE / EDIT MODAL -->
    <div
      v-if="modalOpen"
      class="fixed inset-0 z-[90] flex items-center justify-center bg-black/75 p-4 backdrop-blur-md"
      @click.self="closeModal"
    >
      <div class="pointer-events-none absolute inset-0 overflow-hidden">
        <div class="absolute left-1/4 top-10 h-96 w-96 rounded-full bg-[color:var(--horizon-sunset-blue)]/16 blur-3xl"></div>
        <div class="absolute bottom-10 right-1/4 h-96 w-96 rounded-full bg-[color:var(--horizon-sunset-magenta)]/14 blur-3xl"></div>
      </div>

      <div class="relative z-10 flex max-h-[88vh] w-full max-w-3xl flex-col overflow-hidden rounded-[2rem] border border-white/[0.055] bg-[linear-gradient(135deg,var(--horizon-void-700),var(--horizon-void-900))]  hz-animate-pop">
        <div class="pointer-events-none absolute inset-0 opacity-40">
          <div class="absolute left-8 top-0 h-px w-56 bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-blue)] to-transparent"></div>
          <div class="absolute bottom-0 right-10 h-px w-72 bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-magenta)] to-transparent"></div>
        </div>

        <header class="relative shrink-0 border-b border-[color:var(--horizon-sunset-blue)]/20 bg-white/[0.025] p-5">
          <div class="flex items-start justify-between gap-4">
            <div>
              <div class="text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--horizon-text-secondary)]">
                {{ isEditing ? 'Edit Squadron' : 'Create Squadron' }}
              </div>

              <div class="mt-1 text-2xl font-black text-horizon-white">
                {{ isEditing ? editingSquadron?.name : 'New Squadron Record' }}
              </div>

              <p class="mt-1 text-sm text-text-secondary">
                Configure squadron identity, branch, division, status, and leadership assignment.
              </p>
            </div>

            <button
              type="button"
              class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-white/[0.055] bg-white/[0.024] text-lg font-bold text-text-secondary transition hover:border-white/[0.055] hover:bg-white/[0.042] hover:text-horizon-white"
              aria-label="Close squadron editor"
              @click="closeModal"
            >
              ✕
            </button>
          </div>
        </header>

        <div class="relative flex-1 overflow-y-auto p-5">
          <div class="space-y-5">
            <section class="rounded-[1.5rem] border border-[color:var(--horizon-sunset-blue)]/20 bg-white/[0.042] p-4">
              <div class="mb-4">
                <div class="text-xs font-bold uppercase tracking-[0.2em] text-text-muted">
                  Identity
                </div>

                <p class="mt-1 text-sm text-text-secondary">
                  Core public squadron naming and URL identity.
                </p>
              </div>

              <div class="grid gap-4 md:grid-cols-2">
                <div>
                  <label class="mb-1 block text-xs font-bold uppercase tracking-[0.16em] text-text-muted">
                    Name
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

            <section class="relative z-30 rounded-[1.5rem] border border-white/[0.055] bg-white/[0.024] p-4">
              <div class="mb-4">
                <div class="text-xs font-bold uppercase tracking-[0.2em] text-text-muted">
                  Organization
                </div>

                <p class="mt-1 text-sm text-text-secondary">
                  Operational status, branch alignment, and division.
                </p>
              </div>

              <div class="grid gap-4 md:grid-cols-3">
                <HorizonSelect
                  v-model="form.status"
                  :options="[
                    { label: 'Active', value: 'active' },
                    { label: 'Inactive', value: 'inactive' },
                    { label: 'Disbanded', value: 'disbanded' },
                  ]"
                  label="Status"
                  class="w-full"
                />

                <HorizonSelect
                  v-model="form.branch"
                  :options="branchOptions"
                  label="Branch"
                  class="w-full"
                />

                <HorizonSelect
                  v-model="form.division"
                  :options="divisionOptions"
                  label="Division"
                  class="w-full"
                />
              </div>
            </section>

            <section class="relative z-20 rounded-[1.5rem] border border-white/[0.055] bg-white/[0.042] p-4">
              <div class="mb-4">
                <div class="text-xs font-bold uppercase tracking-[0.2em] text-text-muted">
                  Leadership
                </div>

                <p class="mt-1 text-sm text-text-secondary">
                  Assign or clear the squadron leader.
                </p>
              </div>

              <div
                v-if="isEditing"
                class="mb-4 rounded-2xl border border-white/[0.055] bg-white/[0.024] p-3 text-sm text-text-secondary"
              >
                Current leader:
                <span
                  v-if="editingSquadron?.leader"
                  class="font-semibold text-horizon-white"
                >
                  {{ editingSquadron.leader.rsi_handle ?? editingSquadron.leader.discord_name }}
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

              <HorizonSelect
                v-model="form.leader_id"
                :options="[
                  { label: 'None', value: null },
                  ...eligibleLeaders.map(u => ({
                    label: `${u.rank ?? 'Rank'} (${u.rank_level}) • ${u.rsi_handle ?? u.discord_name ?? 'Unknown'}`,
                    value: u.id,
                  })),
                ]"
                label="Leader"
                class="w-full"
              />
            </section>
          </div>
        </div>

        <footer class="relative shrink-0 border-t border-[color:var(--horizon-sunset-blue)]/20 bg-white/[0.025] p-5">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="text-sm text-text-secondary">
              {{ isEditing ? 'Save changes to this squadron record.' : 'Create a new squadron record.' }}
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
                @click="saveSquadron"
              >
                {{ isEditing ? 'Save Changes' : 'Create Squadron' }}
              </HorizonButton>
            </div>
          </div>
        </footer>
      </div>
    </div>
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
import { ref, onMounted, onBeforeUnmount, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'

import HorizonButton from '@/Components/HorizonButton.vue'
import HorizonSelect from '@/Components/HorizonSelect.vue'
import HorizonConfirmDialog from '@/Components/HorizonConfirmDialog.vue'

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
})

const form = ref({
  id: null,
  name: '',
  slug: '',
  status: 'active',
  branch: null,
  division: null,
  leader_id: null,
})

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
const modalOpen = ref(false)
const isEditing = ref(false)

const editingSquadron = ref(null)

function handleKeydown(event) {
  if (event.key !== 'Escape') return
  if (!modalOpen.value) return

  closeModal()
}

/* OPEN CREATE */
function openCreateModal() {
  isEditing.value = false
  modalOpen.value = true

  editingSquadron.value = null

  form.value = {
    id: null,
    name: '',
    slug: '',
    status: 'active',
    branch: null,
    division: null,
    leader_id: null,
  }
}

/* OPEN EDIT */
function openEditModal(sq) {
  isEditing.value = true
  modalOpen.value = true

  editingSquadron.value = sq

  form.value = {
    id: sq.id,
    name: sq.name,
    slug: sq.slug,
    status: sq.status,
    branch: sq.branch ?? null,
    division: sq.division ?? null,
    leader_id: sq.leader_id ?? null,
  }
}

/* CLOSE */
function closeModal() {
  modalOpen.value = false
  editingSquadron.value = null
}

onMounted(() => {
  window.addEventListener('keydown', handleKeydown)
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleKeydown)
})

/* SAVE */
function saveSquadron() {
  if (isEditing.value) {
    router.post(route('admin.squadrons.update'), form.value, {
      preserveScroll: true,
      onSuccess: () => closeModal(),
    })
  } else {
    router.post(route('admin.squadrons.store'), form.value, {
      preserveScroll: true,
      onSuccess: () => closeModal(),
    })
  }
}

const deleteConfirmDialog = ref(null)
const pendingDeleteSquadronId = ref(null)

/* DELETE */
function deleteSquadron(id) {
  pendingDeleteSquadronId.value = id
  deleteConfirmDialog.value?.show()
}

function confirmDeleteSquadron({ close }) {
  const id = pendingDeleteSquadronId.value

  if (!id) {
    close()
    return
  }

  router.post(route('admin.squadrons.delete'), { id }, {
    preserveScroll: true,
    onSuccess: () => {
      close()
      pendingDeleteSquadronId.value = null
    },
  })
}
</script>







