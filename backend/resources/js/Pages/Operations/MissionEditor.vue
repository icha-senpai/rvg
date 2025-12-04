<template>
  <HorizonContainer>
    <div class="flex items-center justify-between mb-6">
      <div>
        <div class="hz-overline mb-1">
          {{ isEdit ? 'Update Operation' : 'New Operation' }}
        </div>
        <h1 class="hz-h1 text-horizon-white">
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

    <div class="grid grid-cols-1 lg:grid-cols-[2fr,1.2fr] gap-8">
      <!-- LEFT: core config -->
      <HorizonPanel>
        <div class="space-y-4">
          <HorizonInput
            v-model="form.title"
            label="Title"
            placeholder="Convoy Escort – Stanton Corridor"
          />

          <div>
            <label class="hz-data-label mb-1 block">Operation kind</label>
            <select
              v-model="form.operation_kind"
              class="hz-input w-full"
            >
              <option value="mission">Mission</option>
              <option value="event">Event</option>
            </select>
          </div>

          <div>
            <label class="hz-data-label mb-1 block">Type (optional)</label>
            <input
              v-model="form.type"
              class="hz-input w-full"
              placeholder="e.g. Escort / Recon / Training"
            />
          </div>

          <div class="grid md:grid-cols-2 gap-4">
            <div>
              <label class="hz-data-label mb-1 block">Starts at</label>
              <input
                v-model="form.starts_at"
                type="datetime-local"
                class="hz-input w-full"
              />
            </div>
            <div>
              <label class="hz-data-label mb-1 block">Ends at (optional)</label>
              <input
                v-model="form.ends_at"
                type="datetime-local"
                class="hz-input w-full"
              />
            </div>
          </div>

          <div>
            <label class="hz-data-label mb-1 block">Description</label>
            <textarea
              v-model="form.description"
              rows="4"
              class="hz-textarea w-full"
              placeholder="Briefly describe the mission or event."
            ></textarea>
          </div>

          <div>
            <label class="hz-data-label mb-1 block">Notes (GM / Ops notes)</label>
            <textarea
              v-model="form.notes"
              rows="4"
              class="hz-textarea w-full"
              placeholder="Additional guidance, expectations, or briefing notes."
            ></textarea>
          </div>
        </div>
      </HorizonPanel>

      <!-- RIGHT: meta + slots -->
      <div class="space-y-6">
        <!-- Meta toggles -->
        <HorizonPanel>
          <div class="hz-section-label mb-3">Meta</div>

          <div class="space-y-3">
            <div>
              <label class="hz-data-label mb-1 block">Visibility</label>
              <select
                v-model="form.visibility"
                class="hz-input w-full"
              >
                <option value="open">Open</option>
                <option value="squadron">Squadron-only</option>
                <option value="private">Private</option>
              </select>
            </div>

            <div>
              <label class="hz-data-label mb-1 block">Difficulty</label>
              <select
                v-model="form.difficulty"
                class="hz-input w-full"
              >
                <option value="">Unspecified</option>
                <option value="low">Low</option>
                <option value="medium">Medium</option>
                <option value="high">High</option>
              </select>
            </div>

            <div>
              <label class="hz-data-label mb-1 block">Strictness</label>
              <select
                v-model="form.operation_strictness"
                class="hz-input w-full"
              >
                <option value="">Default</option>
                <option value="casual">Casual</option>
                <option value="normal">Normal</option>
                <option value="strict">Strict</option>
                <option value="roleplay">Roleplay</option>
              </select>
            </div>

            <div>
              <label class="hz-data-label mb-1 block">RSVP deadline</label>
              <input
                v-model="form.rsvp_deadline"
                type="datetime-local"
                class="hz-input w-full"
              />
            </div>

            <div>
              <label class="hz-data-label mb-1 block">Icon (short code)</label>
              <input
                v-model="form.icon"
                class="hz-input w-full"
                placeholder="e.g. shield, skull, star"
              />
            </div>

            <div>
              <label class="hz-data-label mb-1 block">Image URL</label>
              <input
                v-model="form.image_url"
                class="hz-input w-full"
                placeholder="https://..."
              />
            </div>
          </div>
        </HorizonPanel>

        <!-- Slots config -->
        <HorizonPanel>
          <div class="flex items-center justify-between mb-3">
            <div class="hz-section-label">Slots (optional)</div>
            <HorizonButton
              size="sm"
              variant="ghost"
              @click="addSlot"
            >
              Add slot
            </HorizonButton>
          </div>

          <div v-if="form.slots.length" class="space-y-2">
            <div
              v-for="(slot, index) in form.slots"
              :key="index"
              class="flex items-center gap-2"
            >
              <input
                v-model="form.slots[index]"
                class="hz-input flex-1"
                placeholder="e.g. Mission Lead, Escort, Medic"
              />
              <button
                type="button"
                class="hz-input px-2 py-1 text-xs"
                @click="removeSlot(index)"
              >
                ✕
              </button>
            </div>
          </div>
          <p v-else class="hz-caption text-text-muted">
            You can leave this empty and let participants join as “Unassigned”,
            or add suggested slots like “Mission Lead”, “Escort Heavy”, etc.
          </p>
        </HorizonPanel>

        <!-- Actions -->
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

<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';

import HorizonContainer from '@/Components/HorizonContainer.vue';
import HorizonButton from '@/Components/HorizonButton.vue';
import HorizonPanel from '@/Components/HorizonPanel.vue';
import HorizonInput from '@/Components/HorizonInput.vue';

const props = defineProps({
  mission: {
    type: Object,
    default: null,
  },
  squadronId: {
    type: Number,
    default: null,
  },
});

const isEdit = computed(() => !!props.mission);

const form = useForm({
  title: props.mission?.title ?? '',
  description: props.mission?.description ?? '',
  starts_at: props.mission?.starts_at ?? '',
  ends_at: props.mission?.ends_at ?? '',
  operation_kind: props.mission?.operation_kind ?? 'mission',
  type: props.mission?.type ?? '',
  visibility: props.mission?.visibility ?? 'open',
  difficulty: props.mission?.difficulty ?? '',
  operation_strictness: props.mission?.operation_strictness ?? '',
  icon: props.mission?.icon ?? '',
  image_url: props.mission?.image_url ?? '',
  rsvp_deadline: props.mission?.rsvp_deadline ?? '',
  notes: props.mission?.notes ?? '',
  slots: props.mission?.slots ?? [],
  status: props.mission?.status ?? 'draft',
});

const processing = computed(() => form.processing);

function addSlot() {
  form.slots.push('');
}

function removeSlot(index) {
  form.slots.splice(index, 1);
}

function submit(mode) {
  if (!isEdit.value) {
    // create via API: POST /api/v1/squadrons/{squadron}/operations
    form.post(`/api/v1/squadrons/${props.squadronId}/operations`, {
      preserveScroll: true,
    });
  } else {
    // update via API: PUT /api/v1/operations/{operation}
    form.put(`/api/v1/operations/${props.mission.id}`, {
      preserveScroll: true,
    });
  }
}
</script>
