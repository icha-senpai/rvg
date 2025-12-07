<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import { Ziggy } from '../../ziggy';

// Horizon Components
import HorizonContainer from '@/Components/HorizonContainer.vue';
import HorizonButton from '@/Components/HorizonButton.vue';
import HorizonInput from '@/Components/HorizonInput.vue';
import HorizonPanel from '@/Components/HorizonPanel.vue';
import HorizonSection from '@/Components/HorizonSection.vue';

// ----------------------
// PROPS
// ----------------------
const props = defineProps({
  squadronId: { type: Number, required: true },
  mission: { type: Object, default: null },
});

// ----------------------
// EDIT MODE
// ----------------------
const isEdit = computed(() => props.mission !== null);

// ----------------------
// FORM STATE
// ----------------------
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
function submit(mode) {
  form.status = mode === 'draft' ? 'draft' : 'published';

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
    <div class="grid grid-cols-1 lg:grid-cols-[2fr,1.2fr] gap-10">

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

            <HorizonInput
              label="Visibility"
              type="select"
              v-model="form.visibility"
              :options="[
                { label: 'Open', value: 'open' },
                { label: 'Squadron Only', value: 'squadron' },
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
