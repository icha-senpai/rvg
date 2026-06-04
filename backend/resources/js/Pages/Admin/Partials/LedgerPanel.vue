<script setup>
import { computed, ref } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

import HorizonButton from '@/Components/HorizonButton.vue'
import HorizonConfirmDialog from '@/Components/HorizonConfirmDialog.vue'
import HorizonDateTimePicker from '@/Components/HorizonDateTimePicker.vue'

const props = defineProps({
  ledger: {
    type: Object,
    default: () => ({
      feature: {
        enabled: false,
        preview_user_ids: [],
      },
      currentWipe: null,
      counts: {},
      wipeCycles: [],
    }),
  },
})
const page = usePage()

const wipeTypeOptions = [
  'unknown',
  'none',
  'partial',
  'full',
  'economy',
  'inventory',
  'ships',
  'reputation',
]

const createWipeForm = useForm({
  name: '',
  star_citizen_version: '',
  wipe_type: 'unknown',
  started_at: '',
  notes: '',
})
const editCycleForm = useForm({
  name: '',
  star_citizen_version: '',
  wipe_type: 'unknown',
  started_at: '',
})

const currentWipeLabel = computed(() => props.ledger?.currentWipe?.name ?? 'Not set')
const flashSuccess = computed(() => page.props.flash?.success ?? null)
const confirmDialog = ref(null)
const pendingConfirmation = ref(null)
const editingCycleId = ref(null)
const savedCycleId = ref(null)

function toLocalInputValue(value) {
  if (!value) return ''

  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return ''

  const localDate = new Date(date.getTime() - date.getTimezoneOffset() * 60000)
  return localDate.toISOString().slice(0, 16)
}

function submitWipeCycle() {
  createWipeForm.post(route('admin.ledger.wipes.store'), {
    preserveScroll: true,
    onSuccess: () => {
      createWipeForm.reset('name', 'star_citizen_version', 'started_at', 'notes')
      createWipeForm.wipe_type = 'unknown'
    },
  })
}

function startRenameCycle(wipe) {
  editingCycleId.value = wipe.id
  savedCycleId.value = null
  editCycleForm.clearErrors()
  editCycleForm.name = wipe.name ?? ''
  editCycleForm.star_citizen_version = wipe.star_citizen_version ?? ''
  editCycleForm.wipe_type = wipe.wipe_type ?? 'unknown'
  editCycleForm.started_at = toLocalInputValue(wipe.started_at)
}

function cancelRenameCycle() {
  editingCycleId.value = null
  editCycleForm.clearErrors()
  editCycleForm.reset()
}

function submitRenameCycle(id) {
  editCycleForm.post(route('admin.ledger.wipes.rename', id), {
    preserveScroll: true,
    onSuccess: () => {
      savedCycleId.value = id
      cancelRenameCycle()
    },
  })
}

function activateWipeCycle(id) {
  pendingConfirmation.value = {
    title: 'Set Current Cycle',
    message: 'Set this cycle as the current live ledger cycle?',
    confirmLabel: 'Set Current',
    variant: 'warning',
    action: ({ close, finish }) => {
      useForm({}).post(route('admin.ledger.wipes.activate', id), {
        preserveScroll: true,
        onSuccess: () => {
          close()
        },
        onFinish: () => {
          finish()
        },
      })
    },
  }

  confirmDialog.value?.show()
}

function closeWipeCycle(id) {
  pendingConfirmation.value = {
    title: 'Close Cycle',
    message: 'Close this cycle now? If no current cycle remains, Horizon will create a fresh one automatically.',
    confirmLabel: 'Close Cycle',
    variant: 'danger',
    action: ({ close, finish }) => {
      useForm({}).post(route('admin.ledger.wipes.close', id), {
        preserveScroll: true,
        onSuccess: () => {
          close()
        },
        onFinish: () => {
          finish()
        },
      })
    },
  }

  confirmDialog.value?.show()
}

function handleConfirmDialogConfirm(controls) {
  pendingConfirmation.value?.action?.(controls)
}
</script>

<template>
  <div class="space-y-5">
    <HorizonConfirmDialog
      ref="confirmDialog"
      :title="pendingConfirmation?.title ?? 'Confirm Action'"
      :message="pendingConfirmation?.message ?? ''"
      :confirm-label="pendingConfirmation?.confirmLabel ?? 'Confirm'"
      :variant="pendingConfirmation?.variant ?? 'warning'"
      @confirm="handleConfirmDialogConfirm"
    />

    <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_auto] xl:items-end">
      <div>
        <div class="text-xs font-bold uppercase tracking-[0.2em] text-text-muted">
          Ledger Command
        </div>

        <h3 class="mt-1 text-2xl font-black text-horizon-white">
          Cycle And Module Controls
        </h3>

        <p class="mt-2 max-w-3xl text-sm text-text-secondary">
          Horizon Ledger is scaffolded as a cycle-aware module. Use this console to create the next cycle, switch the current cycle, and keep the feature rollout controlled.
        </p>
      </div>

      <div class="rounded-[1.25rem] border border-white/[0.055] bg-white/[0.024] px-4 py-3 text-right">
        <div class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">
          Current Cycle
        </div>

        <div class="mt-1 text-2xl font-black text-horizon-white">
          {{ currentWipeLabel }}
        </div>
      </div>
    </div>

    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
      <div class="rounded-[1.25rem] border border-white/[0.055] bg-white/[0.024] p-4">
        <div class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Feature Flag</div>
        <div class="mt-2 text-lg font-black text-horizon-white">
          {{ ledger.feature?.enabled ? 'Enabled' : 'Preview Only' }}
        </div>
        <div class="mt-1 text-sm text-text-secondary">
          Preview users: {{ ledger.feature?.preview_user_ids?.length ?? 0 }}
        </div>
      </div>

      <div class="rounded-[1.25rem] border border-white/[0.055] bg-white/[0.024] p-4">
        <div class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Transactions</div>
        <div class="mt-2 text-lg font-black text-horizon-white">{{ ledger.counts?.transactions ?? 0 }}</div>
        <div class="mt-1 text-sm text-text-secondary">Recorded ledger entries.</div>
      </div>

      <div class="rounded-[1.25rem] border border-white/[0.055] bg-white/[0.024] p-4">
        <div class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Trades</div>
        <div class="mt-2 text-lg font-black text-horizon-white">{{ ledger.counts?.trades ?? 0 }}</div>
        <div class="mt-1 text-sm text-text-secondary">Commodity runs logged so far.</div>
      </div>

      <div class="rounded-[1.25rem] border border-white/[0.055] bg-white/[0.024] p-4">
        <div class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Assets</div>
        <div class="mt-2 text-lg font-black text-horizon-white">
          {{ (ledger.counts?.inventory_items ?? 0) + (ledger.counts?.ship_assets ?? 0) }}
        </div>
        <div class="mt-1 text-sm text-text-secondary">Inventory and ship records combined.</div>
      </div>
    </section>

    <section class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_24rem]">
      <div class="rounded-[1.75rem] border border-white/[0.055] bg-white/[0.024] p-5">
        <div class="flex items-center justify-between gap-3">
          <div>
            <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Cycle History</div>
            <h4 class="mt-1 text-xl font-black text-horizon-white">Cycles</h4>
          </div>

          <div class="rounded-full border border-white/[0.055] bg-white/[0.03] px-3 py-1 text-xs font-bold text-horizon-white">
            {{ ledger.wipeCycles?.length ?? 0 }} total
          </div>
        </div>

        <div class="mt-4 grid gap-3">
          <article
            v-for="wipe in ledger.wipeCycles ?? []"
            :key="wipe.id"
            class="rounded-[1.25rem] border border-white/[0.055] bg-black/10 p-4"
          >
            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
              <div>
                <div class="flex flex-wrap items-center gap-2">
                  <template v-if="editingCycleId === wipe.id">
                    <div class="grid w-full max-w-2xl gap-3 md:grid-cols-2">
                      <div class="md:col-span-2">
                        <label class="mb-1 block text-[11px] font-bold uppercase tracking-[0.14em] text-text-muted">
                          Cycle Name
                        </label>
                        <input v-model="editCycleForm.name" type="text" class="hz-input" />
                        <div v-if="editCycleForm.errors.name" class="mt-2 text-xs text-rose-200">
                          {{ editCycleForm.errors.name }}
                        </div>
                      </div>

                      <div>
                        <label class="mb-1 block text-[11px] font-bold uppercase tracking-[0.14em] text-text-muted">
                          Version
                        </label>
                        <input v-model="editCycleForm.star_citizen_version" type="text" class="hz-input" placeholder="4.1.1" />
                        <div v-if="editCycleForm.errors.star_citizen_version" class="mt-2 text-xs text-rose-200">
                          {{ editCycleForm.errors.star_citizen_version }}
                        </div>
                      </div>

                      <div>
                        <label class="mb-1 block text-[11px] font-bold uppercase tracking-[0.14em] text-text-muted">
                          Cycle Type
                        </label>
                        <select v-model="editCycleForm.wipe_type" class="hz-input">
                          <option v-for="option in wipeTypeOptions" :key="option" :value="option">
                            {{ option }}
                          </option>
                        </select>
                        <div v-if="editCycleForm.errors.wipe_type" class="mt-2 text-xs text-rose-200">
                          {{ editCycleForm.errors.wipe_type }}
                        </div>
                      </div>

                      <div class="md:col-span-2">
                        <HorizonDateTimePicker
                          v-model="editCycleForm.started_at"
                          label="Start Time"
                          clearable
                          show-now
                        />
                        <div v-if="editCycleForm.errors.started_at" class="mt-2 text-xs text-rose-200">
                          {{ editCycleForm.errors.started_at }}
                        </div>
                      </div>
                    </div>
                  </template>
                  <div v-else class="text-lg font-black text-horizon-white">
                    {{ wipe.name }}
                  </div>

                  <span
                    class="rounded-full border px-3 py-1 text-[11px] font-bold uppercase tracking-[0.14em]"
                    :class="wipe.is_current
                      ? 'border-emerald-300/35 bg-emerald-300/10 text-emerald-100'
                      : 'border-white/[0.055] bg-white/[0.024] text-text-secondary'"
                  >
                    {{ wipe.is_current ? 'Current' : 'Archived' }}
                  </span>
                </div>

                <div v-if="editingCycleId !== wipe.id" class="mt-2 text-sm text-text-secondary">
                  {{ wipe.star_citizen_version || 'Version pending' }} • {{ wipe.wipe_type }}
                </div>

                <div v-if="editingCycleId !== wipe.id" class="mt-1 text-xs text-text-muted">
                  Started {{ wipe.started_at ? new Date(wipe.started_at).toLocaleString() : 'unknown' }}
                  <span v-if="wipe.ended_at">• Ended {{ new Date(wipe.ended_at).toLocaleString() }}</span>
                </div>

                <div v-else class="mt-3 text-xs text-text-secondary">
                  Update the live cycle details here without creating a brand-new cycle.
                </div>

                <p
                  v-if="wipe.notes && editingCycleId !== wipe.id"
                  class="mt-3 text-sm text-text-secondary"
                >
                  {{ wipe.notes }}
                </p>

                <div
                  v-if="savedCycleId === wipe.id && flashSuccess === 'Current cycle updated.'"
                  class="mt-3 inline-flex rounded-full border border-emerald-300/30 bg-emerald-300/10 px-3 py-1 text-[11px] font-bold uppercase tracking-[0.14em] text-emerald-100"
                >
                  Cycle details saved
                </div>
              </div>

              <div class="flex flex-wrap gap-2">
                <template v-if="editingCycleId === wipe.id">
                  <HorizonButton
                    size="sm"
                    variant="secondary"
                    :disabled="editCycleForm.processing"
                    @click="cancelRenameCycle"
                  >
                    Cancel
                  </HorizonButton>

                  <HorizonButton
                    size="sm"
                    :disabled="editCycleForm.processing"
                    @click="submitRenameCycle(wipe.id)"
                  >
                    Save Cycle
                  </HorizonButton>
                </template>

                <HorizonButton
                  v-else-if="wipe.is_current"
                  size="sm"
                  variant="ghost"
                  @click="startRenameCycle(wipe)"
                >
                  Edit Cycle
                </HorizonButton>

                <HorizonButton
                  v-if="!wipe.is_current && editingCycleId !== wipe.id"
                  size="sm"
                  variant="ghost"
                  @click="activateWipeCycle(wipe.id)"
                >
                  Set Current
                </HorizonButton>

                <HorizonButton
                  v-if="wipe.is_current && editingCycleId !== wipe.id"
                  size="sm"
                  variant="danger"
                  @click="closeWipeCycle(wipe.id)"
                >
                  Close Cycle
                </HorizonButton>
              </div>
            </div>
          </article>
        </div>
      </div>

      <section class="rounded-[1.75rem] border border-white/[0.055] bg-white/[0.024] p-5">
        <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">New Cycle</div>
        <h4 class="mt-1 text-xl font-black text-horizon-white">Start Next Cycle</h4>
        <p class="mt-2 text-sm text-text-secondary">
          Creating a new cycle automatically closes the current one and moves new Ledger records onto the fresh cycle.
        </p>

        <form class="mt-4 space-y-3" @submit.prevent="submitWipeCycle">
          <div>
            <label class="mb-1 block text-xs font-bold uppercase tracking-[0.14em] text-text-muted">
              Name
            </label>
            <input v-model="createWipeForm.name" type="text" class="hz-input" placeholder="4.1.1 Live" />
          </div>

          <div>
            <label class="mb-1 block text-xs font-bold uppercase tracking-[0.14em] text-text-muted">
              Star Citizen Version
            </label>
            <input v-model="createWipeForm.star_citizen_version" type="text" class="hz-input" placeholder="4.1.1" />
          </div>

          <div>
            <label class="mb-1 block text-xs font-bold uppercase tracking-[0.14em] text-text-muted">
              Cycle Type
            </label>
            <select v-model="createWipeForm.wipe_type" class="hz-input">
              <option v-for="option in wipeTypeOptions" :key="option" :value="option">
                {{ option }}
              </option>
            </select>
          </div>

          <HorizonDateTimePicker
            v-model="createWipeForm.started_at"
            label="Start Time"
            clearable
            show-now
          />

          <div>
            <label class="mb-1 block text-xs font-bold uppercase tracking-[0.14em] text-text-muted">
              Notes
            </label>
            <textarea v-model="createWipeForm.notes" class="hz-input min-h-28 resize-y" placeholder="Patch notes, cycle scope, or rollout context..."></textarea>
          </div>

          <div class="flex justify-end">
            <HorizonButton type="submit" :disabled="createWipeForm.processing">
              Create Current Cycle
            </HorizonButton>
          </div>
        </form>
      </section>
    </section>
  </div>
</template>
