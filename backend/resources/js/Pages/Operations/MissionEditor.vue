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

          <!-- Operation kind -->
          <div class="hz-stack-sm">
            <label class="hz-section-label">Operation kind</label>
            <select v-model="form.operation_kind" class="hz-input w-full">
              <option value="mission">Mission</option>
              <option value="event">Event</option>
            </select>
          </div>

          <!-- Type -->
          <div class="hz-stack-sm">
            <label class="hz-section-label">Type (optional)</label>
            <input
              v-model="form.type"
              class="hz-input w-full"
              placeholder="e.g. Escort / Recon / Training"
            />
          </div>

          <!-- Start / End -->
          <div class="grid md:grid-cols-2 gap-4">
            <div class="hz-stack-sm">
              <label class="hz-section-label">Starts at</label>
              <input
                v-model="form.starts_at"
                type="datetime-local"
                class="hz-input w-full"
              />
            </div>

            <div class="hz-stack-sm">
              <label class="hz-section-label">Ends at (optional)</label>
              <input
                v-model="form.ends_at"
                type="datetime-local"
                class="hz-input w-full"
              />
            </div>
          </div>

          <!-- Description -->
          <div class="hz-stack-sm">
            <label class="hz-section-label">Description</label>
            <textarea
              v-model="form.description"
              rows="4"
              class="hz-textarea w-full"
              placeholder="Briefly describe the mission or event."
            ></textarea>
          </div>

          <!-- Notes -->
          <div class="hz-stack-sm">
            <label class="hz-section-label">Notes (GM / Ops notes)</label>
            <textarea
              v-model="form.notes"
              rows="4"
              class="hz-textarea w-full"
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

            <!-- Visibility -->
            <div class="hz-stack-sm">
              <label class="hz-section-label">Visibility</label>
              <select v-model="form.visibility" class="hz-input w-full">
                <option value="open">Open</option>
                <option value="squadron">Squadron-only</option>
                <option value="private">Private</option>
              </select>
            </div>

            <!-- Difficulty -->
            <div class="hz-stack-sm">
              <label class="hz-section-label">Difficulty</label>
              <select v-model="form.difficulty" class="hz-input w-full">
                <option value="">Unspecified</option>
                <option value="low">Low</option>
                <option value="medium">Medium</option>
                <option value="high">High</option>
              </select>
            </div>

            <!-- Strictness -->
            <div class="hz-stack-sm">
              <label class="hz-section-label">Strictness</label>
              <select v-model="form.operation_strictness" class="hz-input w-full">
                <option value="">Default</option>
                <option value="casual">Casual</option>
                <option value="normal">Normal</option>
                <option value="strict">Strict</option>
                <option value="roleplay">Roleplay</option>
              </select>
            </div>

            <!-- RSVP Deadline -->
            <div class="hz-stack-sm">
              <label class="hz-section-label">RSVP deadline</label>
              <input
                v-model="form.rsvp_deadline"
                type="datetime-local"
                class="hz-input w-full"
              />
            </div>

            <!-- Icon -->
            <div class="hz-stack-sm">
              <label class="hz-section-label">Icon (short code)</label>
              <input
                v-model="form.icon"
                class="hz-input w-full"
                placeholder="e.g. shield, skull, star"
              />
            </div>

            <!-- Image URL -->
            <div class="hz-stack-sm">
              <label class="hz-section-label">Image URL</label>
              <input
                v-model="form.image_url"
                class="hz-input w-full"
                placeholder="https://..."
              />
            </div>

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
              <input
                v-model="form.slots[index]"
                class="hz-input flex-1"
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
