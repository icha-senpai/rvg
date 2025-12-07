<script setup>
import HorizonContainer from '@/Components/HorizonContainer.vue';
import HorizonPanel from '@/Components/HorizonPanel.vue';
import HorizonButton from '@/Components/HorizonButton.vue';
import ProgressPill from '@/Components/ProgressPill.vue';
import MiniMapPanel from '@/Components/MiniMapPanel.vue';
import { ref, reactive, computed } from 'vue';
import { router } from '@inertiajs/vue3';


const props = defineProps({
  operation: Object,
  participants: Array,
  participantsBySlot: Object,
  unassignedParticipants: Array,
  currentParticipant: Object,
});
const operation = props.operation;
const participants = props.participants ?? [];
const participantsBySlot = props.participantsBySlot ?? {};
const unassignedParticipants = props.unassignedParticipants ?? [];
const currentParticipant = props.currentParticipant ?? null;

function asText(v) {
  if (!v) return 'TBD';
  return String(v);
}
/* ---------------------------------------------
   STATUS VARIANT FOR THE STATUS PILL
--------------------------------------------- */
const statusVariant = computed(() => {
  switch (operation.status) {
    case 'draft': return 'neutral';
    case 'published': return 'info';
    case 'in_progress': return 'primary';
    case 'completed': return 'success';
    case 'canceled': return 'danger';
    default: return 'neutral';
  }
});

/* ---------------------------------------------
   JOIN FORM STATE
--------------------------------------------- */
const joinForm = reactive({
  slot: '',
  notes: '',
});

const joinProcessing = ref(false);

/* ---------------------------------------------
   JOIN / UPDATE / LEAVE PLACEHOLDERS
--------------------------------------------- */
function join() {
  console.log("JOIN", joinForm);
}

function updateSlot() {
  console.log("UPDATE SLOT", joinForm);
}

function leave() {
  console.log("LEAVE OP");
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
              <div class="hz-section-label">Window</div>
              <div class="hz-body-strong">
                {{ asText(operation.starts_at) }} →
                {{ operation.ends_at ? asText(operation.ends_at) : 'TBD' }}
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
              <div class="hz-section-label">RSVP Deadline</div>
              <div class="hz-body-strong">
                {{ asText(operation.rsvp_deadline) }}
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
                  • {{ p.user?.display_name ?? p.user?.name ?? 'Unknown' }}
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
                • {{ p.user?.display_name ?? p.user?.name ?? 'Unknown' }}
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

              <!-- Slot selection -->
              <div v-if="(operation.slots || []).length" class="hz-stack-xs">
                <label class="hz-section-label">Slot (optional)</label>
                <select v-model="joinForm.slot" class="hz-input w-full">
                  <option value="">Unassigned</option>
                  <option
                    v-for="slotName in operation.slots"
                    :key="slotName"
                    :value="slotName"
                  >
                    {{ slotName }}
                  </option>
                </select>
              </div>

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
                {{ p.user?.display_name ?? p.user?.name ?? 'Unknown' }}
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

