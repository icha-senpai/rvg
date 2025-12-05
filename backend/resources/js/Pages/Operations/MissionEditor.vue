<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import HorizonContainer from '@/Components/HorizonContainer.vue';
import { route } from 'ziggy-js';
import { Ziggy } from '../../ziggy';

// -------------------------------
// PROPS
// -------------------------------
const props = defineProps({
  squadronId: { type: Number, required: true },
  mission: { type: Object, default: null },
});

// -------------------------------
// EDIT MODE
// -------------------------------
const isEdit = computed(() => props.mission !== null);

// -------------------------------
// FORM STATE
// -------------------------------
const form = useForm({
  title: props.mission?.title ?? '',
  operation_kind: props.mission?.operation_kind ?? 'mission',
  type: props.mission?.type ?? '',
  starts_at: props.mission?.starts_at ?? '',
  ends_at: props.mission?.ends_at ?? '',
  description: props.mission?.description ?? '',
  notes: props.mission?.notes ?? '',
  visibility: props.mission?.visibility ?? 'open',
  difficulty: props.mission?.difficulty ?? '',
  operation_strictness: props.mission?.operation_strictness ?? '',
  rsvp_deadline: props.mission?.rsvp_deadline ?? '',
  icon: props.mission?.icon ?? '',
  image_url: props.mission?.image_url ?? '',
  slots: props.mission?.slots ?? [],

  status: props.mission?.status ?? 'draft',

  squadron_id: props.squadronId,
});

// -------------------------------
// SLOT FUNCTIONS
// -------------------------------
function addSlot() {
  form.slots.push('');
}

function removeSlot(index) {
  form.slots.splice(index, 1);
}

// -------------------------------
// SUBMIT HANDLER
// -------------------------------
function submit(mode) {
  form.status = mode === 'draft' ? 'draft' : 'published';

  // EDIT MODE
  if (isEdit.value) {
    return form.put(
      route('operations.update', props.mission.id, Ziggy),
      {
        preserveScroll: true,
        onSuccess: () => console.log("UPDATED"),
        onError: (e) => console.error(e),
      }
    );
  }

  // CREATE MODE
  return form.post(
    route('operations.store', { squadron: props.squadronId }, Ziggy),
    {
      preserveScroll: true,
      onSuccess: () => console.log("CREATED"),
      onError: (e) => console.error(e),
    }
  );
}
</script>

<template>
  <HorizonContainer>

    <!-- Header -->
    <div class="flex items-center justify-between mb-10">
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

    <!-- Main Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-[2fr,1.2fr] gap-8">

      <!-- LEFT -->
      <HorizonPanel>
        <div class="hz-stack">

          <HorizonInput
            v-model="form.title"
            label="Title"
            placeholder="Convoy Escort – Stanton Corridor"
          />

          <HorizonInput
            type="select"
            label="Operation kind"
            v-model="form.operation_kind"
            :options="[
              { label: 'Mission', value: 'mission' },
              { label: 'Event', value: 'event' }
            ]"
          />

          <HorizonInput
            label="Type (optional)"
            placeholder="e.g. Escort / Recon / Training"
            v-model="form.type"
          />

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

          <div class="hz-stack-sm">
            <label class="hz-section-label">Description</label>
            <textarea
              v-model="form.description"
              rows="6"
              class="hz-textarea w-full resize-y"
              placeholder="Brief description..."
            ></textarea>
          </div>

          <div class="hz-stack-sm">
            <label class="hz-section-label">Notes</label>
            <textarea
              v-model="form.notes"
              rows="6"
              class="hz-textarea w-full resize-y"
            ></textarea>
          </div>

        </div>
      </HorizonPanel>

      <!-- RIGHT -->
      <div class="hz-stack">

        <HorizonPanel>
          <div class="hz-section-label mb-3">Meta</div>

          <div class="hz-stack">

            <HorizonInput
              label="Visibility"
              type="select"
              v-model="form.visibility"
              :options="[
                { label: 'Open', value: 'open' },
                { label: 'Squadron-only', value: 'squadron' },
                { label: 'Private', value: 'private' }
              ]"
            />

            <HorizonInput
              label="Difficulty"
              type="select"
              v-model="form.difficulty"
              :options="[
                { label: 'Unspecified', value: '' },
                { label: 'Low', value: 'low' },
                { label: 'Medium', value: 'medium' },
                { label: 'High', value: 'high' }
              ]"
            />

            <HorizonInput
              label="Strictness"
              type="select"
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
              label="RSVP deadline"
              type="datetime-local"
              v-model="form.rsvp_deadline"
            />

            <HorizonInput
              label="Icon"
              v-model="form.icon"
            />

            <HorizonInput
              label="Image URL"
              placeholder="https://..."
              v-model="form.image_url"
            />

          </div>
        </HorizonPanel>

        <!-- SLOTS -->
        <HorizonPanel>
          <div class="flex items-center justify-between mb-3">
            <div class="hz-section-label">Slots</div>

            <HorizonButton size="sm" variant="ghost" @click="addSlot">
              Add slot
            </HorizonButton>
          </div>

          <div v-if="form.slots.length" class="hz-stack-sm">
            <div
              v-for="(slot, index) in form.slots"
              :key="index"
              class="flex items-center gap-2"
            >
              <HorizonInput
                v-model="form.slots[index]"
                placeholder="e.g. Escort / Medic / Lead"
              />

              <button
                type="button"
                class="px-3 py-1 rounded-md bg-[var(--color-bg-hover)] text-[var(--color-text-muted)] text-xs font-semibold"
                @click="removeSlot(index)"
              >
                ✕
              </button>
            </div>
          </div>

          <p v-else class="hz-tiny hz-text-muted">
            Leave blank to allow unassigned participants.
          </p>
        </HorizonPanel>

        <!-- ACTION BUTTONS -->
        <div class="flex gap-3">
          <HorizonButton
            variant="primary"
            class="flex-1"
            :disabled="form.processing"
            @click="submit('published')"
          >
            {{ isEdit ? 'Save Changes' : 'Create & Publish Draft' }}
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
