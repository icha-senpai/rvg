<script setup>
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { route } from 'ziggy-js';
import { Ziggy } from '../../ziggy';

// Horizon Components
import HorizonContainer from '@/Components/HorizonContainer.vue';
import HorizonButton from '@/Components/HorizonButton.vue';
import HorizonInput from '@/Components/HorizonInput.vue';
import HorizonSection from '@/Components/HorizonSection.vue';
import HorizonSelect from '@/Components/HorizonSelect.vue';

// ----------------------
// PROPS
// ----------------------
const props = defineProps({
  squadronId: { type: Number, required: false, default: null },
  mission: { type: Object, default: null },
});

const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;

// ----------------------
// MODE
// ----------------------
const isEdit = computed(() => props.mission !== null);

const slotWarningOpen = ref(false);
const slotWarningMessage = ref('');

function openSlotWarning(message) {
  slotWarningMessage.value = message;
  slotWarningOpen.value = true;
}

function closeSlotWarning() {
  slotWarningOpen.value = false;
  slotWarningMessage.value = '';
}

// ----------------------
// UTC HELPERS
// ----------------------
function splitUTC(iso) {
  if (!iso) return { date: '', time: '' };
  const clean = iso.replace('Z', '');
  return {
    date: clean.slice(0, 10),
    time: clean.slice(11, 16),
  };
}

function buildDateTime(date, time) {
  if (!date || !time) return null;

  const normalizedTime = time.length === 5 ? `${time}:00` : time;

  return `${date} ${normalizedTime}`;
}

// ----------------------
// FORM
// ----------------------
const start = splitUTC(props.mission?.starts_at);
const end = splitUTC(props.mission?.ends_at);
const rsvp = splitUTC(props.mission?.rsvp_deadline);

const form = useForm({
  title: props.mission?.title ?? '',
  operation_kind: props.mission?.operation_kind ?? 'mission',
  type: props.mission?.type ?? '',

  // DATE + TIME (SPLIT)
  start_date: start.date,
  start_time: start.time,
  end_date: end.date,
  end_time: end.time,
  rsvp_date: rsvp.date,
  rsvp_time: rsvp.time,

  starts_at: buildDateTime(start.date, start.time),
  ends_at: buildDateTime(end.date, end.time),
  rsvp_deadline: buildDateTime(rsvp.date, rsvp.time),

  description: props.mission?.description ?? '',
  notes: props.mission?.notes ?? '',
  visibility: props.mission?.visibility ?? 'open',
  difficulty: props.mission?.difficulty ?? '',
  operation_strictness: props.mission?.operation_strictness ?? '',
  icon: props.mission?.icon ?? '',
  image_url: props.mission?.image_url ?? '',
  slots: props.mission?.slots ?? [],
  status: props.mission?.status ?? 'draft',
  squadron_id: props.squadronId,
});

// ----------------------
// SLOT HANDLERS
// ----------------------
function addSlot() {
  form.slots.push('');
}

function removeSlot(index) {
  form.slots.splice(index, 1);
}

// ----------------------
// SUBMIT HANDLER
// ----------------------
async function submit(mode) {
  const currentStatus = props.mission?.status ?? 'draft';
  const isPublishing = mode === 'published';
  const shouldPublishTransition = isPublishing && (!isEdit.value || currentStatus === 'draft');

  if (!isEdit.value) {
    form.status = isPublishing ? 'published' : 'draft';
  } else {
    form.status = currentStatus;
  }

  // ----------------------
  // BUILD DATETIMES
  // ----------------------
  form.starts_at = buildDateTime(form.start_date, form.start_time);
  form.ends_at = buildDateTime(form.end_date, form.end_time);
  form.rsvp_deadline = buildDateTime(form.rsvp_date, form.rsvp_time);

  // ----------------------
  // VALIDATION
  // ----------------------
  const re = /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/;

  if (!re.test(form.starts_at)) {
    alert('Start date and time are required.');
    return;
  }

  if (form.ends_at && !re.test(form.ends_at)) {
    alert('End time must include date and time.');
    return;
  }

  if (form.rsvp_deadline && !re.test(form.rsvp_deadline)) {
    alert('RSVP deadline must include date and time.');
    return;
  }

  if (Array.isArray(form.slots) && form.slots.length) {
    const trimmedSlots = form.slots.map(slot => (typeof slot === 'string' ? slot.trim() : slot));
    const invalidIndexes = trimmedSlots
      .map((slot, index) => ({ slot, index }))
      .filter(({ slot }) => typeof slot !== 'string' || slot.length === 0)
      .map(({ index }) => index + 1);

    if (invalidIndexes.length) {
      openSlotWarning(
        `Role slots can’t be empty. Please fill or remove slot(s): ${invalidIndexes.join(', ')}`
      );
      return;
    }

    form.slots = trimmedSlots;
  }

  // ----------------------
  // EDIT MODE
  // ----------------------
  if (isEdit.value) {
    return form.put(
      route('operations.update', props.mission.id, Ziggy),
      {
        preserveScroll: true,
        async onSuccess() {
          if (shouldPublishTransition && currentStatus === 'draft') {
            try {
              await axios.post(route('operations.publish', props.mission.id, Ziggy));
            } catch (err) {
              console.error('Publish error:', err);
            }
          }

          window.location.href = route('operations.show', props.mission.id, Ziggy);
        },
        onError: (e) => console.error('UPDATE ERROR:', e),
      }
    );
  }

  // ----------------------
  // CREATE MODE
  // ----------------------
  try {
    const storeUrl = props.squadronId
      ? route('operations.store', { squadron: props.squadronId }, Ziggy)
      : route('operations.storeGlobal', {}, Ziggy);

    const response = await form.post(storeUrl, { preserveScroll: true });

    let newId =
      response?.props?.operation?.id ??
      response?.operation?.id;

    if (!newId && response?.url) {
      const match = response.url.match(/operations\/(\d+)/);
      if (match) newId = match[1];
    }

    if (!newId) {
      console.error('Could not resolve operation ID.');
      return;
    }

    window.location.href = route('operations.show', newId, Ziggy);

  } catch (err) {
    console.error('CREATE ERROR:', err);
  }
}

// ----------------------
// DELETE HANDLER
// ----------------------
async function destroyOperation() {
  if (!props.mission) return;
  if (!confirm('Delete this operation? This cannot be undone.')) return;

  await form.delete(
    route('operations.destroy', props.mission.id, Ziggy),
    {
      preserveScroll: true,
      onSuccess: () => {
        window.location.href = route('operations.index', Ziggy);
      }
    }
  );
}
</script>






<template>
  <HorizonContainer class="space-y-10">

    <!-- Header -->
    <div class="mx-auto max-w-5xl flex items-center justify-between mb-4">
      <div class="hz-stack-sm">
        <div class="hz-section-label">
          {{ isEdit ? 'Update Operation' : 'New Operation' }}
        </div>

        <h1 class="hz-title-lg text-horizon-white">
          {{ isEdit ? 'Edit Mission/Event' : 'Create Mission/Event' }}
        </h1>
      </div>

      <HorizonButton
        variant="ghost"
        @click="$inertia.visit(route('operations.index'))"
      >
        Cancel
      </HorizonButton>
    </div>

    <!-- Main Layout -->
    <div class="mx-auto max-w-5xl space-y-10">
      <div class="text-sm text-horizon-offwhite mt-2 opacity-80">
        ⏱️ Detected timezone: <strong>{{ timezone }}</strong><br>
        If this is incorrect, adjust your OS timezone for accurate scheduling.
      </div>
      <!-- LEFT SIDE -->
      <div class="space-y-6">

        <!-- Operation Details -->
        <HorizonSection title="Operation Details">
          <div class="hz-stack">
            <HorizonInput
              v-model="form.title"
              label="Title"
              placeholder="Convoy Escort - Stanton Corridor"
            />

            <HorizonInput
              label="Operation Type (optional)"
              placeholder="Escort / Recon / Patrol / Meeting / Other"
              v-model="form.type"
            />
          </div>
        </HorizonSection>

        <!-- Timing -->
        <HorizonSection title="Scheduling">
          <div class="grid md:grid-cols-2 gap-4">
            <HorizonInput
              label="Start Date"
              type="date"
              v-model="form.start_date"
            />

            <HorizonInput
              label="Start Time"
              type="time"
              v-model="form.start_time"
            />

            <HorizonInput
              label="End Date (optional)"
              type="date"
              v-model="form.end_date"
            />

            <HorizonInput
              label="End Time (optional)"
              type="time"
              v-model="form.end_time"
            />
          </div>

          <div class="grid md:grid-cols-2 gap-4">
            <HorizonInput
              label="Sign Up Deadline - Date"
              type="date"
              v-model="form.rsvp_date"
            />

            <HorizonInput
              label="Sign Up Deadline - Time"
              type="time"
              v-model="form.rsvp_time"
            />
          </div>
        </HorizonSection>

        <!-- Description -->
        <HorizonSection title="Operation Briefing">
          <textarea
            v-model="form.description"
            rows="6"
            class="hz-textarea w-full"
            placeholder="Operation overview...High level details, this is for the discord embed, etc."
          ></textarea>
        </HorizonSection>

        <!-- Notes -->
        <HorizonSection title="Operation Extended Briefing">
          <textarea
            v-model="form.notes"
            rows="6"
            class="hz-textarea w-full"
            placeholder="Expanded detail not for discord"
          ></textarea>
        </HorizonSection>

      </div>


     
      <div class="space-y-6">

        <!-- Meta -->
        <HorizonSection title="Meta Information">
          <div class="hz-stack">

            <HorizonSelect
              label="Visibility"
              v-model="form.visibility"
              :options="[
                { label: 'Open', value: 'open' },
                { label: 'Squadron Only', value: 'squadron' },
              ]"
            />


            <HorizonSelect
              label="Comms Strictness"
              v-model="form.operation_strictness"
              :options="[
                { label: 'Default', value: '' },
                { label: 'Casual', value: 'casual' },
                { label: 'Normal', value: 'normal' },
                { label: 'Strict', value: 'strict' },
                { label: 'Roleplay', value: 'roleplay' }
              ]"
            />



          </div>
        </HorizonSection>

        <!-- Media 
        <HorizonSection title="Media">
          <HorizonInput label="Icon" v-model="form.icon" />
          <HorizonInput label="Image URL" placeholder="https://" v-model="form.image_url" />
        </HorizonSection> -->

        <!-- Roles -->
        <HorizonSection title="Roles">
          <div class="flex justify-between items-center mb-3">
            <div class="hz-section-label">Roles</div>

            <HorizonButton size="sm" variant="ghost" @click="addSlot">
              Add Role
            </HorizonButton>
          </div>

          <div v-if="form.slots.length" class="space-y-3">
            <div
              v-for="(slot, index) in form.slots"
              :key="index"
              class="flex items-center gap-3"
            >
              <HorizonInput
                v-model="form.slots[index]"
                placeholder="Escort / Medic / Lead"
              />

              <HorizonButton
                size="xs"
                variant="outline"
                @click="removeSlot(index)"
              >
                ✕
              </HorizonButton>
            </div>
          </div>

          <p v-else class="hz-caption hz-text-muted">
            No roles defined. Operation allows freeform participation.
          </p>
        </HorizonSection>


        <!-- ACTION BUTTONS -->
        <div class="flex gap-4 pt-4">
          <HorizonButton
            variant="primary"
            class="flex-1"
            :disabled="form.processing"
            @click="submit('published')"
          >
            {{ isEdit ? 'Save Changes' : 'Publish Operation' }}
          </HorizonButton>

          <HorizonButton
            variant="outline"
            class="flex-1"
            :disabled="form.processing"
            @click="submit('draft')"
          >
            Save as Draft
          </HorizonButton>
        </div>

      </div>
    </div>

    <div
      v-if="slotWarningOpen"
      class="hz-overlay flex items-center justify-center"
      @click.self="closeSlotWarning"
    >
      <div class="hz-modal hz-stack max-h-[85vh] overflow-y-auto hz-animate-pop">
        <div class="hz-row-between">
          <div class="hz-title-lg">Missing Role Slot</div>
          <HorizonButton variant="primary" size="sm" @click="closeSlotWarning">
            ✕
          </HorizonButton>
        </div>

        <div class="hz-text-soft">{{ slotWarningMessage }}</div>

        <div class="hz-row-between pt-2">
          <div></div>
          <HorizonButton variant="primary" size="sm" @click="closeSlotWarning">
            OK
          </HorizonButton>
        </div>
      </div>
    </div>

  </HorizonContainer>
</template>
