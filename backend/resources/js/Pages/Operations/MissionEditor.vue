<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { route } from 'ziggy-js';
import { Ziggy } from '../../ziggy';

// Horizon Components
import HorizonContainer from '@/Components/HorizonContainer.vue';
import HorizonButton from '@/Components/HorizonButton.vue';
import HorizonInput from '@/Components/HorizonInput.vue';
import HorizonPanel from '@/Components/HorizonPanel.vue';
import HorizonSection from '@/Components/HorizonSection.vue';
import HorizonSelect from '@/Components/HorizonSelect.vue';

// ----------------------
// PROPS
// ----------------------
const props = defineProps({
  squadronId: { type: Number, required: true },
  mission: { type: Object, default: null },
});
const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;

// ----------------------
// MODE
// ----------------------
const isEdit = computed(() => props.mission !== null);

// ----------------------
// DATETIME — NO CONVERSION
// Clean UTC string -> datetime-local compatible string
// ----------------------
function cleanUTC(iso) {
  if (!iso) return '';
  return iso.replace('Z', '').slice(0, 16);
}

// ----------------------
// FORM
// ----------------------
const form = useForm({
  title: props.mission?.title ?? '',
  operation_kind: props.mission?.operation_kind ?? 'mission',
  type: props.mission?.type ?? '',
  
  // PURE UTC, NO CONVERSION
  starts_at: props.mission?.starts_at ? cleanUTC(props.mission.starts_at) : '',
  ends_at: props.mission?.ends_at ? cleanUTC(props.mission.ends_at) : '',
  rsvp_deadline: props.mission?.rsvp_deadline ? cleanUTC(props.mission.rsvp_deadline) : '',

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
  const isPublishing = mode === 'published';
  form.status = isPublishing ? 'published' : 'draft';

  // ----------------------
  // VALIDATION: REQUIRE FULL DATE + TIME
  // ----------------------
  function isDateTimeComplete(dt) {
    if (!dt) return false;
    // Must match YYYY-MM-DDTHH:MM (datetime-local format)
    return /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(dt);
  }

  if (!isDateTimeComplete(form.starts_at)) {
    alert("Start time must include both date AND time.");
    return;
  }

  if (form.ends_at && !isDateTimeComplete(form.ends_at)) {
    alert("End time must include both date AND time.");
    return;
  }

  if (form.rsvp_deadline && !isDateTimeComplete(form.rsvp_deadline)) {
    alert("RSVP deadline must include both date AND time.");
    return;
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
          if (isPublishing) {
            try {
              await axios.post(route('operations.publish', props.mission.id, Ziggy));
            } catch (err) {
              console.error("Publish error:", err);
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
    console.log("SENDING STATUS:", form.status);
    const response = await form.post(
      route('operations.store', { squadron: props.squadronId }, Ziggy),
      { preserveScroll: true }
    );

    const newId =
      response?.props?.operation?.id ??
      form?.id ??
      response?.operation?.id;
    // FINAL guaranteed fallback — extract ID from URL
    if (!newId && response?.url) {
      const match = response.url.match(/operations\/(\d+)/);
      if (match) newId = match[1];
    }
    console.log("RESOLVED NEW ID:", newId);

    if (!newId) {
      console.error("Could not determine operation ID after creation.");
      return;
    }


    window.location.href = route('operations.show', newId, Ziggy);

  } catch (err) {
    console.error("CREATE ERROR:", err);
  }
}

// ----------------------
// DELETE HANDLER
// ----------------------
async function destroyOperation() {
  if (!props.mission) return;
  if (!confirm("Delete this operation? This cannot be undone.")) return;

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
    <div class="flex items-center justify-between mb-4">
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
      <div class="text-s text-horizon-offwhite mt-2 opacity-80">
        ⏱️ Detected timezone: <strong>{{ timezone }}</strong><br>
        If this is incorrect, adjust your OS timezone for accurate scheduling.
      </div>
      <!-- LEFT SIDE -->
      <div class="space-y-6">

        <!-- Mission Details -->
        <HorizonSection title="Mission Details">
          <div class="hz-stack">
            <HorizonInput
              v-model="form.title"
              label="Title"
              placeholder="Convoy Escort – Stanton Corridor"
            />

            <HorizonInput
              label="Subtype (optional)"
              placeholder="Escort / Recon / Patrol"
              v-model="form.type"
            />
          </div>
        </HorizonSection>

        <!-- Timing -->
        <HorizonSection title="Scheduling">
          <div class="grid md:grid-cols-2 gap-4">
            <HorizonInput
              label="Starts at"
              type="datetime-local"
              v-model="form.starts_at"
            />

            <HorizonInput
              label="Ends at (optional)"
              type="datetime-local"
              v-model="form.ends_at"
            />
          </div>
        </HorizonSection>

        <!-- Description -->
        <HorizonSection title="Description">
          <textarea
            v-model="form.description"
            rows="6"
            class="hz-textarea w-full"
            placeholder="Mission overview..."
          ></textarea>
        </HorizonSection>

        <!-- Notes -->
        <HorizonSection title="Notes">
          <textarea
            v-model="form.notes"
            rows="6"
            class="hz-textarea w-full"
          ></textarea>
        </HorizonSection>

      </div>


      <!-- RIGHT SIDE -->
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
              label="Strictness"
              v-model="form.operation_strictness"
              :options="[
                { label: 'Default', value: '' },
                { label: 'Casual', value: 'casual' },
                { label: 'Normal', value: 'normal' },
                { label: 'Strict', value: 'strict' },
                { label: 'Roleplay', value: 'roleplay' }
              ]"
            />

            <HorizonInput
              label="Sign Up Deadline"
              type="datetime-local"
              v-model="form.rsvp_deadline"
            />

          </div>
        </HorizonSection>

        <!-- Media -->
        <HorizonSection title="Media">
          <HorizonInput label="Icon" v-model="form.icon" />
          <HorizonInput label="Image URL" placeholder="https://" v-model="form.image_url" />
        </HorizonSection>

        <!-- Slots -->
        <HorizonSection title="Slots">
          <div class="flex justify-between items-center mb-3">
            <div class="hz-section-label">Role Slots</div>

            <HorizonButton size="sm" variant="ghost" @click="addSlot">
              Add Slot
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
            No slots defined. Operation allows freeform participation.
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

  </HorizonContainer>
</template>
