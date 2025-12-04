<script setup>
import { useForm } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import HorizonContainer from '@/Components/HorizonContainer.vue';
import {route} from 'ziggy-js';
import { Ziggy } from '../../ziggy';
// Props coming from controller
const props = defineProps({
  squadronId: {
    type: Number,
    required: true,
  },
  auth: Object,
  isEdit: {
    type: Boolean,
    default: false,
  },
});

// -------------------------------
// FORM STATE
// -------------------------------
const form = useForm({
  title: '',
  operation_kind: 'mission',
  type: '',
  starts_at: '',
  ends_at: '',
  description: '',
  notes: '',
  visibility: 'open',
  difficulty: '',
  operation_strictness: '',
  rsvp_deadline: '',
  icon: '',
  image_url: '',
  slots: [],

  squadron_id: props.squadronId,
});

// expose for template
const processing = form.processing;

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
    form.post(
        route('operations.store', { squadron: props.squadronId }, Ziggy),
        {
            preserveScroll: true,
            onSuccess: () => { console.log("OK"); },
            onError: (e) => { console.error(e); }
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

      <!-- LEFT: Core configuration -->
      <HorizonPanel>
        <div class="hz-stack">

          <!-- Title -->
          <HorizonInput
            v-model="form.title"
            label="Title"
            placeholder="Convoy Escort – Stanton Corridor"
          />

          <!-- Operation Kind -->
          <HorizonInput
            type="select"
            label="Operation kind"
            v-model="form.operation_kind"
            :options="[
              { label: 'Mission', value: 'mission' },
              { label: 'Event', value: 'event' }
            ]"
          />

          <!-- Type -->
          <HorizonInput
            label="Type (optional)"
            placeholder="e.g. Escort / Recon / Training"
            v-model="form.type"
          />

          <!-- Start / End -->
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

          <!-- DESCRIPTION -->
          <div class="hz-stack-sm">
            <label class="hz-section-label">Description</label>

            <textarea
              v-model="form.description"
              rows="6"
              class="hz-textarea w-full resize-y"
              placeholder="Briefly describe the mission or event. You can hit Enter freely."
            ></textarea>
          </div>

          <!-- NOTES -->
          <div class="hz-stack-sm">
            <label class="hz-section-label">Notes (GM / Ops notes)</label>

            <textarea
              v-model="form.notes"
              rows="6"
              class="hz-textarea w-full resize-y"
              placeholder="Additional guidance, expectations, or briefing notes."
            ></textarea>
          </div>

        </div>
      </HorizonPanel>

      <!-- RIGHT: Meta + Slots -->
      <div class="hz-stack">

        <!-- META PANEL -->
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
              label="Icon (short code)"
              placeholder="e.g. shield, skull, star"
              v-model="form.icon"
            />

            <HorizonInput
              label="Image URL"
              placeholder="https://..."
              v-model="form.image_url"
            />

          </div>
        </HorizonPanel>

        <!-- SLOTS PANEL -->
        <HorizonPanel>
          <div class="flex items-center justify-between mb-3">
            <div class="hz-section-label">Slots (optional)</div>

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
                placeholder="e.g. Mission Lead, Escort, Medic"
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
            Leave this empty to allow “Unassigned”, or add recommended roles.
          </p>
        </HorizonPanel>

        <!-- ACTIONS -->
        <div class="flex gap-3">
          <HorizonButton
            variant="primary"
            class="flex-1"
            :disabled="processing"
            @click="submit('primary')"
          >
            {{ isEdit ? 'Save Changes' : 'Create & Publish Draft' }}
          </HorizonButton>

          <HorizonButton
            variant="outline"
            class="flex-1"
            :disabled="processing"
            @click="submit('draft')"
          >
            Save as Draft
          </HorizonButton>
        </div>

      </div>

    </div>

  </HorizonContainer>
</template>

