<template>
  <HorizonContainer>
    <!-- Header row -->
    <div class="flex items-center justify-between mb-6">
      <div class="flex items-center gap-3">
        <HorizonButton
          variant="ghost"
          size="sm"
          @click="$inertia.visit(route('operations.index'))"
        >
          ⟵ Back
        </HorizonButton>

        <div>
          <div class="hz-overline mb-1">
            {{ operation.operation_kind === 'mission' ? 'Mission' : 'Event' }}
          </div>
          <h1 class="hz-h1 text-horizon-white hz-glyph">
            {{ operation.title }}
          </h1>
        </div>
      </div>

      <ProgressPill :variant="statusVariant">
        {{ operation.status }}
      </ProgressPill>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-[2fr,1.2fr] gap-8">
      <!-- LEFT: details and notes -->
      <section class="space-y-6">
        <!-- Core meta -->
        <HorizonPanel>
          <div class="grid md:grid-cols-2 gap-4">
            <div>
              <div class="hz-data-label mb-1">Window</div>
              <div class="hz-body-strong">
                {{ asText(operation.starts_at) }}
                →
                {{ operation.ends_at ? asText(operation.ends_at) : 'TBD' }}
              </div>
            </div>

            <div>
              <div class="hz-data-label mb-1">Visibility</div>
              <div class="hz-body-strong">
                {{ operation.visibility ?? 'open' }}
              </div>
            </div>

            <div>
              <div class="hz-data-label mb-1">Difficulty</div>
              <div class="hz-body-strong">
                {{ operation.difficulty ?? 'unspecified' }}
              </div>
            </div>

            <div>
              <div class="hz-data-label mb-1">Strictness</div>
              <div class="hz-body-strong">
                {{ operation.operation_strictness ?? 'normal' }}
              </div>
            </div>

            <div v-if="operation.rsvp_deadline">
              <div class="hz-data-label mb-1">RSVP Deadline</div>
              <div class="hz-body-strong">
                {{ asText(operation.rsvp_deadline) }}
              </div>
            </div>

            <div v-if="operation.type">
              <div class="hz-data-label mb-1">Type</div>
              <div class="hz-body-strong">
                {{ operation.type }}
              </div>
            </div>
          </div>
        </HorizonPanel>

        <!-- Description -->
        <HorizonPanel v-if="operation.description">
          <div class="hz-section-label mb-2">Briefing</div>
          <p class="hz-body">
            {{ operation.description }}
          </p>
        </HorizonPanel>

        <!-- Notes / GM text -->
        <HorizonPanel v-if="operation.notes">
          <div class="hz-section-label mb-2">Notes</div>
          <p class="hz-body">
            {{ operation.notes }}
          </p>
        </HorizonPanel>

        <!-- Slots overview -->
        <HorizonPanel>
          <div class="hz-section-label mb-3">Slots</div>

          <div v-if="(operation.slots || []).length" class="space-y-3">
            <div
              v-for="slotName in operation.slots"
              :key="slotName"
              class="bg-bg-elevated rounded-xl p-3 hz-inset"
            >
              <div class="flex justify-between items-center mb-1">
                <div class="hz-body-strong">{{ slotName }}</div>
                <div class="hz-caption">
                  {{ (participantsBySlot[slotName] || []).length }} participants
                </div>
              </div>

              <ul class="hz-caption space-y-1">
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

          <div v-else class="hz-caption text-text-muted">
            No slots defined yet. Participants will join as “Unassigned”.
          </div>

          <!-- Unassigned group -->
          <div v-if="unassignedParticipants.length" class="mt-4">
            <div class="hz-data-label mb-1">Unassigned</div>
            <ul class="hz-caption space-y-1">
              <li v-for="p in unassignedParticipants" :key="p.id">
                • {{ p.user?.display_name ?? p.user?.name ?? 'Unknown' }}
                <span class="opacity-60">
                  ({{ p.attendance_status }})
                </span>
              </li>
            </ul>
          </div>
        </HorizonPanel>
      </section>

      <!-- RIGHT: join controls, participants, holo panel -->
      <aside class="space-y-6">
        <!-- Join / leave -->
        <HorizonPanel>
          <div class="hz-section-label mb-2">Your Status</div>

          <div v-if="currentParticipant">
            <p class="hz-body mb-2">
              You are signed up as
              <strong>
                {{ currentParticipant.slot ?? 'Unassigned' }}
              </strong>
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
          </div>

          <div v-else>
            <p class="hz-body mb-3">
              Join this {{ operation.operation_kind }} with an optional slot & note.
            </p>

            <div class="space-y-3">
              <div v-if="(operation.slots || []).length">
                <label class="hz-data-label mb-1 block">
                  Slot (optional)
                </label>
                <select
                  v-model="joinForm.slot"
                  class="hz-input w-full"
                >
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

              <div>
                <label class="hz-data-label mb-1 block">
                  Notes (optional)
                </label>
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
          </div>
        </HorizonPanel>

        <!-- Quick participants summary -->
        <HorizonPanel>
          <div class="hz-section-label mb-2">Participants</div>
          <p class="hz-body mb-3">
            {{ participants.length }} total participants.
          </p>
          <ul class="hz-caption space-y-1 max-h-48 overflow-auto">
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

        <!-- Holo panel / image -->
        <MiniMapPanel>
          <div class="text-center">
            <div v-if="operation.image_url" class="mb-2">
              <img
                :src="operation.image_url"
                alt="Operation"
                class="mx-auto max-h-40 rounded-xl hz-rim"
              />
            </div>
            <span class="hz-caption">
              Icon: {{ operation.icon ?? 'None' }}
            </span>
          </div>
        </MiniMapPanel>
      </aside>
    </div>
  </HorizonContainer>
</template>

<script setup>
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

import HorizonContainer from '@/Components/HorizonContainer.vue';
import HorizonButton from '@/Components/HorizonButton.vue';
import HorizonPanel from '@/Components/HorizonPanel.vue';
import ProgressPill from '@/Components/ProgressPill.vue';
import MiniMapPanel from '@/Components/MiniMapPanel.vue';

const props = defineProps({
  operation: {
    type: Object,
    required: true,
  },
  participants: {
    type: Array,
    default: () => [],
  },
});

const page = usePage();
const authUserId = computed(() => page.props.auth?.user?.id ?? null);

const joinForm = ref({
  slot: '',
  notes: '',
});
const joinProcessing = ref(false);

const participants = computed(() => props.participants || []);

const participantsBySlot = computed(() => {
  const grouped = {};
  for (const p of participants.value) {
    const key = p.slot || '';
    if (!grouped[key]) grouped[key] = [];
    grouped[key].push(p);
  }
  return grouped;
});

const unassignedParticipants = computed(
  () => participantsBySlot.value[''] || []
);

const currentParticipant = computed(() => {
  if (!authUserId.value) return null;
  return participants.value.find(p => p.user_id === authUserId.value) || null;
});

const statusVariant = computed(() => {
  switch (props.operation.status) {
    case 'published':
    case 'in_progress':
      return 'blue';
    case 'completed':
      return 'green';
    case 'canceled':
      return 'red';
    default:
      return 'yellow';
  }
});

function asText(value) {
  if (!value) return '';
  return String(value);
}

function join() {
  joinProcessing.value = true;
  router.post(
    `/api/v1/operations/${props.operation.id}/join`,
    { ...joinForm.value },
    {
      preserveScroll: true,
      onFinish: () => { joinProcessing.value = false; },
    }
  );
}

function leave() {
  router.post(
    `/api/v1/operations/${props.operation.id}/leave`,
    {},
    { preserveScroll: true }
  );
}

function updateSlot() {
  if (!currentParticipant.value) return;
  router.put(
    `/api/v1/operations/${props.operation.id}/participants/${currentParticipant.value.id}/slot`,
    { slot: joinForm.value.slot || currentParticipant.value.slot },
    { preserveScroll: true }
  );
}
</script>
