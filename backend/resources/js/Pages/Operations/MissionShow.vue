<script setup>
import HorizonContainer from '@/Components/HorizonContainer.vue';
import HorizonPanel from '@/Components/HorizonPanel.vue';
import HorizonButton from '@/Components/HorizonButton.vue';
import ProgressPill from '@/Components/ProgressPill.vue';
import HorizonSelect from '@/Components/HorizonSelect.vue';

import { ref, reactive, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
  operation: Object,
  participants: Array,
  participantsBySlot: Object,
  unassignedParticipants: Array,
  currentParticipant: Object,
});

const operation = props.operation;
const currentParticipant = props.currentParticipant ?? null;

/* ============================================================
   FORMATTER
============================================================ */
function asText(v) {
  return v ? String(v) : "TBD";
}

function formatUTC(dt) {
  if (!dt) return 'TBD';

  const clean = String(dt).replace('Z', '').replace('+00:00', '');
  const date = clean.slice(0, 10);
  const time = clean.slice(11, 16);

  return `${date} ${time} UTC`;
}

function formatLocal(dt) {
  if (!dt) return 'TBD';

  const d = new Date(dt);
  if (Number.isNaN(d.getTime())) return String(dt);

  return d.toLocaleString(undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
}

/* ============================================================
   STATUS PILL VARIANT
============================================================ */
const statusVariant = computed(() => {
  switch (operation.status) {
    case "draft": return "neutral";
    case "published": return "info";
    case "in_progress": return "primary";
    case "completed": return "success";
    case "canceled": return "danger";
    default: return "neutral";
  }
});

/* ============================================================
   JOIN FORM + PREFILL
============================================================ */
const joinForm = reactive({
  slot: "",
  notes: "",
});

watch(
  () => currentParticipant,
  () => {
    if (currentParticipant) {
      joinForm.slot = currentParticipant.slot ?? "";
      joinForm.notes = currentParticipant.notes ?? "";
    }
  },
  { immediate: true }
);

const joinProcessing = ref(false);

/* ============================================================
   JOIN (WEB)
============================================================ */
async function join() {
  joinProcessing.value = true;

  router.post(
    route("operations.join", operation.id),
    {
      slot: joinForm.slot,
      notes: joinForm.notes,
      operation_role_id: null,
    },
    {
      preserveScroll: true,
      onFinish: () => {
        joinProcessing.value = false;
        router.visit(window.location.href, { preserveScroll: true });
      },
    }
  );
}

/* ============================================================
   LEAVE (WEB)
============================================================ */
async function leave() {
  if (!confirm("Leave this operation?")) return;

  joinProcessing.value = true;

  router.post(
    route("operations.leave", operation.id),
    {},
    {
      preserveScroll: true,
      onFinish: () => {
        joinProcessing.value = false;
        router.visit(window.location.href, { preserveScroll: true });
      },
    }
  );
}

/* ============================================================
   UPDATE SLOT (WEB)
============================================================ */
async function updateSlot() {
  if (!currentParticipant) return;

  joinProcessing.value = true;

  router.post(
    route("operations.participants.slot", {
      operation: operation.id,
      participant: currentParticipant.id,
    }),
    {
      slot: joinForm.slot,
      operation_role_id: null,
    },
    {
      preserveScroll: true,
      onFinish: () => {
        joinProcessing.value = false;
        router.visit(window.location.href, { preserveScroll: true });
      },
    }
  );
}
</script>



<template>
  <HorizonContainer>

    <!-- HEADER -->
    <div class="flex items-center justify-between mb-12">

      <div class="flex items-center gap-4">
        <HorizonButton
          variant="ghost"
          size="sm"
          @click="$inertia.visit(route('operations.index'))"
        >
          ⟵ Back
        </HorizonButton>

        <div class="hz-stack-sm">
          <div class="hz-section-label">
            {{ operation.operation_kind === 'mission' ? 'Mission' : 'Event' }}
          </div>

          <h1 class="hz-title-lg text-horizon-white">
            {{ operation.title }}
          </h1>
        </div>
      </div>

      <ProgressPill :variant="statusVariant">
        {{ operation.status }}
      </ProgressPill>

    </div>

    <!-- MAIN GRID -->
    <div class="mt-10 mb-20 grid grid-cols-1 lg:grid-cols-[2fr_1fr] gap-12">

      <!-- LEFT COLUMN -->
      <section class="flex flex-col gap-8">

        <!-- META PANEL -->
        <HorizonPanel class="rounded-xl shadow-lg hz-overlay-light">
          <div class="grid md:grid-cols-2 gap-6">

            <div class="hz-stack-xs">
              <div class="hz-section-label">Squadron</div>
              <div class="hz-body-strong">
                {{ operation.squadron?.name ?? 'TBD' }}
              </div>
            </div>

            <div class="hz-stack-xs">
              <div class="hz-section-label">Creator</div>
              <div class="hz-body-strong">
                {{ operation.creator?.rsi_handle ?? 'TBD' }}
              </div>
            </div>

            <div class="hz-stack-xs">
              <div class="hz-section-label">Window</div>
              <div class="hz-body-strong">
                <div>
                  {{ formatUTC(operation.starts_at) }}
                </div>
                <div class="hz-caption text-horizon-offwhite opacity-70">
                  {{ formatLocal(operation.starts_at) }} (local)
                </div>

                <div class="mt-2" v-if="operation.ends_at">
                  <div>
                    {{ formatUTC(operation.ends_at) }}
                  </div>
                  <div class="hz-caption text-horizon-offwhite opacity-70">
                    {{ formatLocal(operation.ends_at) }} (local)
                  </div>
                </div>
                <div v-else class="mt-2">
                  TBD
                </div>
              </div>
            </div>

            <div class="hz-stack-xs">
              <div class="hz-section-label">Visibility</div>
              <div class="hz-body-strong">
                {{ operation.visibility ?? 'open' }}
              </div>
            </div>



            <div class="hz-stack-xs">
              <div class="hz-section-label">Strictness</div>
              <div class="hz-body-strong">
                {{ operation.operation_strictness ?? 'normal' }}
              </div>
            </div>

            <div v-if="operation.rsvp_deadline" class="hz-stack-xs">
              <div class="hz-section-label">Sign up Deadline</div>
              <div class="hz-body-strong">
                <div>
                  {{ formatUTC(operation.rsvp_deadline) }}
                </div>
                <div class="hz-caption text-horizon-offwhite opacity-70">
                  {{ formatLocal(operation.rsvp_deadline) }} (local)
                </div>
              </div>
            </div>

            <div v-if="operation.type" class="hz-stack-xs">
              <div class="hz-section-label">Type</div>
              <div class="hz-body-strong">
                {{ operation.type }}
              </div>
            </div>

          </div>
        </HorizonPanel>

        <!-- BRIEFING -->
        <HorizonPanel
          v-if="operation.description"
          class="rounded-xl shadow-lg hz-overlay-light"
        >
          <div class="hz-section-label mb-2">Briefing</div>
          <p class="hz-body">{{ operation.description }}</p>
        </HorizonPanel>

        <!-- NOTES -->
        <HorizonPanel
          v-if="operation.notes"
          class="rounded-xl shadow-lg hz-overlay-light"
        >
          <div class="hz-section-label mb-2">Notes</div>
          <p class="hz-body">{{ operation.notes }}</p>
        </HorizonPanel>

        <!-- SLOTS -->
        <HorizonPanel class="rounded-xl shadow-lg hz-overlay-light">

          <div class="hz-section-label mb-3">Slots</div>

          <!-- Slots exist -->
          <div
            v-if="(operation.slots || []).length"
            class="flex flex-col gap-4"
          >

            <div
              v-for="slotName in operation.slots"
              :key="slotName"
              class="p-4 rounded-xl bg-bg-elevated/60 hz-inset hz-stack-xs shadow"
            >

              <div class="flex justify-between items-center">
                <div class="hz-body-strong">{{ slotName }}</div>
                <div class="hz-caption opacity-70">
                  {{ (participantsBySlot[slotName] || []).length }} participants
                </div>
              </div>

              <ul class="hz-caption hz-stack-2xs">
                <li
                  v-for="p in participantsBySlot[slotName] || []"
                  :key="p.id"
                >
                  • {{ p.user?.rsi_handle ?? p.user?.display_name ?? p.user?.name ?? 'Unknown' }}
                  <span class="opacity-60">
                    ({{ p.attendance_status }})
                  </span>
                </li>

                <li
                  v-if="!(participantsBySlot[slotName] || []).length"
                  class="opacity-60"
                >
                  No one assigned yet.
                </li>
              </ul>

            </div>

          </div>

          <!-- No slots -->
          <div v-else class="hz-caption hz-text-muted">
            No slots defined. Participants join as “Unassigned”.
          </div>

          <!-- Unassigned -->
          <div v-if="unassignedParticipants.length" class="mt-6">
            <div class="hz-section-label mb-1">Unassigned</div>

            <ul class="hz-caption hz-stack-2xs">
              <li
                v-for="p in unassignedParticipants"
                :key="p.id"
              >
                • {{ p.user?.rsi_handle ?? p.user?.display_name ?? p.user?.name ?? 'Unknown' }}
                <span class="opacity-60">
                  ({{ p.attendance_status }})
                </span>
              </li>
            </ul>
          </div>

        </HorizonPanel>

      </section>

      <!-- RIGHT SIDEBAR -->
      <aside class="flex flex-col gap-8 sticky top-10 h-fit">

        <!-- USER STATUS -->
        <HorizonPanel class="rounded-xl shadow-lg hz-overlay-light">
          <div class="hz-section-label mb-3">Your Status</div>

          <!-- Already joined -->
          <template v-if="currentParticipant">
            <p class="hz-body mb-3">
              You are signed up as
              <strong>{{ currentParticipant.slot ?? 'Unassigned' }}</strong>
              ({{ currentParticipant.attendance_status }}).
            </p>

            <div class="flex gap-3">
              <HorizonButton
                variant="primary"
                class="flex-1"
                @click="updateSlot"
              >
                Update Slot
              </HorizonButton>

              <HorizonButton
                variant="outline"
                class="flex-1"
                @click="leave"
              >
                Leave Operation
              </HorizonButton>
            </div>
          </template>

          <!-- Not joined -->
          <template v-else>
            <p class="hz-body mb-4">
              Join this {{ operation.operation_kind }} with an optional slot &
              note.
            </p>

            <div class="hz-stack">
              <HorizonSelect
                label="Slot (optional)"
                v-model="joinForm.slot"
                :options="operation.slots.map(slot => ({ label: slot, value: slot }))"
              />


              <!-- Notes -->
              <div class="hz-stack-xs">
                <label class="hz-section-label">Notes (optional)</label>
                <textarea
                  v-model="joinForm.notes"
                  rows="3"
                  class="hz-textarea w-full"
                  placeholder="Ship, role preference, or context..."
                ></textarea>
              </div>

              <HorizonButton
                variant="primary"
                class="w-full"
                @click="join"
                :disabled="joinProcessing"
              >
                Join Operation
              </HorizonButton>

            </div>
          </template>

        </HorizonPanel>

        <!-- PARTICIPANTS -->
        <HorizonPanel class="rounded-xl shadow-lg hz-overlay-light">
          <div class="hz-section-label mb-3">Participants</div>

          <p class="hz-body mb-3">
            {{ participants.length }} total.
          </p>

          <ul class="hz-caption max-h-48 overflow-auto hz-stack-2xs">
            <li
              v-for="p in participants"
              :key="p.id"
              class="flex justify-between"
            >
              <span>
                {{ p.user?.rsi_handle ?? p.user?.display_name ?? p.user?.name ?? 'Unknown' }}

                <span class="opacity-60">
                  ({{ p.slot ?? 'Unassigned' }})
                </span>
              </span>
              <span class="opacity-60">
                {{ p.attendance_status }}
              </span>
            </li>
          </ul>
        </HorizonPanel>

       

      </aside>

    </div>
  </HorizonContainer>
</template>

