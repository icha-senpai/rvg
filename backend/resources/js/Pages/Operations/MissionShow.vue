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
const currentParticipant = computed(() => props.currentParticipant ?? null);

/* ============================================================
   FORMATTER
============================================================ */
function asText(v) {
  return v ? String(v) : "TBD";
}

function formatUTC(dt) {
  if (!dt) return 'TBD';

  const d = toDate(dt);
  if (!d) return String(dt);

  const date = new Intl.DateTimeFormat('en-GB', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
    timeZone: 'UTC',
  }).format(d);

  const time = new Intl.DateTimeFormat('en-GB', {
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
    timeZone: 'UTC',
  }).format(d);

  return `${date} ${time} UTC`;
}

function formatLocal(dt) {
  if (!dt) return 'TBD';

  const d = toDate(dt);
  if (!d) return String(dt);

  const date = new Intl.DateTimeFormat('en-GB', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  }).format(d);

  const timeParts = new Intl.DateTimeFormat('en-GB', {
    hour: '2-digit',
    minute: '2-digit',
    hour12: true,
  }).formatToParts(d);

  const hour = timeParts.find(p => p.type === 'hour')?.value;
  const minute = timeParts.find(p => p.type === 'minute')?.value;
  const dayPeriod = (timeParts.find(p => p.type === 'dayPeriod')?.value ?? '').toLowerCase();

  const time = hour && minute && dayPeriod
    ? `${hour}:${minute} ${dayPeriod}`
    : new Intl.DateTimeFormat('en-GB', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: true,
      }).format(d);

  return `${date} ${time}`;
}

function toDate(value) {
  if (!value) return null;

  let v = String(value).trim();
  v = v.replace(' ', 'T');

  v = v.replace(/\.(\d{3})\d+Z$/i, '.$1Z');
  v = v.replace(/\.(\d{3})\d+$/i, '.$1');

  const hasTimezone = /([zZ]|[+-]\d{2}:\d{2})$/.test(v);
  if (!hasTimezone) v = `${v}Z`;

  const d = new Date(v);
  return Number.isNaN(d.getTime()) ? null : d;
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

const slotOptions = computed(() => {
  const slots = (operation.slots || []).map((slot) => ({ label: slot, value: slot }));
  return [{ label: 'No Role', value: '' }, ...slots];
});

watch(
  currentParticipant,
  (p) => {
    if (p) {
      joinForm.slot = p.slot ?? "";
      joinForm.notes = p.notes ?? "";
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
  if (!currentParticipant.value) return;

  joinProcessing.value = true;

  router.post(
    route("operations.participants.slot", {
      operation: operation.id,
      participant: currentParticipant.value.id,
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
  <HorizonContainer class="space-y-10">

    <!-- HEADER -->
    <div class="mx-auto max-w-5xl flex items-center justify-between mb-4">

      <div class="flex items-center gap-4">
        <!-- 
        <HorizonButton
          variant="ghost"
          size="sm"
          @click="$inertia.visit(route('operations.index'))"
        >
          ⟵ Back
        </HorizonButton>
        -->

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
    <div class="mb-20 mx-auto max-w-5xl space-y-10">

     
      <section class="space-y-6">

        <!-- META PANEL -->
        <HorizonPanel class="rounded-xl shadow-lg ">
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
              <div class="hz-section-label">Time Window</div>
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
                <!--div v-else class="mt-2">
                  TBD
                </div-->
              </div>
            </div>

            <div class="hz-stack-xs">
              <div class="hz-section-label">Visibility</div>
              <div class="hz-body-strong">
                {{ operation.visibility ?? 'open' }}
              </div>
            </div>



            <div class="hz-stack-xs">
              <div class="hz-section-label">Comms Strictness</div>
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
              <div class="hz-section-label">Operation Type</div>
              <div class="hz-body-strong">
                {{ operation.type }}
              </div>
            </div>

          </div>
        </HorizonPanel>

        <!-- BRIEFING -->
        <HorizonPanel
          v-if="operation.description"
          class="rounded-xl shadow-lg "
        >
          <div class="hz-section-label mb-2">Operaton Briefing</div>
          <p class="hz-body whitespace-pre-line">{{ operation.description }}</p>
        </HorizonPanel>

        <!-- NOTES -->
        <HorizonPanel
          v-if="operation.notes"
          class="rounded-xl shadow-lg"
        >
          <div class="hz-section-label mb-2">Operation Extended Briefing</div>
          <p class="hz-body whitespace-pre-line">{{ operation.notes }}</p>
        </HorizonPanel>

        <!-- ROLES -->
        <HorizonPanel class="rounded-xl shadow-lg">

          <div class="hz-section-label mb-3">Roles</div>

          <!-- Roles exist -->
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

          <!-- No roles -->
          <div v-else class="hz-caption hz-text-muted">
            No roles defined. Participants join as “No Role”.
          </div>

          <!-- Unassigned -->
          <div v-if="unassignedParticipants.length" class="mt-6">
            <div class="hz-section-label mb-1">No Role</div>

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

     
      <section class="space-y-6">

        <!-- USER STATUS -->
        <HorizonPanel class="rounded-xl shadow-lg">
          <div class="hz-section-label mb-3">Your Status</div>

          <!-- Already joined -->
          <template v-if="currentParticipant">
            <p class="hz-body mb-3">
              You are signed up as
              <strong>{{ currentParticipant.slot ?? 'No Role' }}</strong>
              ({{ currentParticipant.attendance_status }}).
            </p>

            <HorizonSelect
              label="Role"
              v-model="joinForm.slot"
              :options="slotOptions"
            />

            <div class="flex gap-3">
              <HorizonButton
                variant="primary"
                class="flex-1"
                @click="updateSlot"
              >
                Update Role
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
              Join this {{ operation.operation_kind }} with an optional role.
            </p>

            <div class="hz-stack">
              <HorizonSelect
                label="Role (optional)"
                v-model="joinForm.slot"
                :options="slotOptions"
              />


              <!-- Notes 
              <div class="hz-stack-xs">
                <label class="hz-section-label">Notes (optional)</label>
                <textarea
                  v-model="joinForm.notes"
                  rows="3"
                  class="hz-textarea w-full"
                  placeholder="Ship, role preference, or context..."
                ></textarea>
              </div> -->

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
        <HorizonPanel class="rounded-xl shadow-lg">
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
                  ({{ p.slot ?? 'No Role' }})
                </span>
              </span>
              <span class="opacity-60">
                {{ p.attendance_status }}
              </span>
            </li>
          </ul>
        </HorizonPanel>

       

      </section>

    </div>
  </HorizonContainer>
</template>

