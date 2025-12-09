<template>
  <HorizonContainer>
    <HorizonSectionHeader label="Squadrons" title="Squadron Management" />

    <HorizonPanel class="p-6 space-y-6">

      <div class="hz-title-lg mb-4">All Squadrons</div>

      <div class="space-y-3">
        <div
          v-for="sq in squadrons"
          :key="sq.id"
          class="bg-bg-surface border border-bg-hover rounded-lg p-4 flex justify-between items-center"
        >
          <div>
            <div class="hz-title-md text-horizon-white">{{ sq.name }}</div>
            <div class="hz-text-xs text-horizon-blue-light">Slug: {{ sq.slug }}</div>
            <div class="hz-text-sm text-text-soft">
              Status:
              <span
                class="px-2 py-1 rounded bg-[var(--color-bg-elevated)] text-[var(--color-horizon-blue-light)] text-xs"
              >
                {{ sq.status }}
              </span>
            </div>
          </div>

          <!-- Actions -->
          <div class="flex gap-3">
            <!-- EDIT (modal or redirect) -->
            <button
              class="hz-btn hz-btn-sm hz-btn-primary"
              @click="editSquadron(sq)"
            >
              Edit
            </button>

            <!-- DELETE -->
            <button
              class="hz-btn hz-btn-sm hz-btn-danger"
              @click="deleteSquadron(sq.id)"
            >
              Delete
            </button>
          </div>
        </div>
      </div>

    </HorizonPanel>
  </HorizonContainer>
</template>

<script setup>
import { router } from '@inertiajs/vue3'

import HorizonContainer from '@/Components/HorizonContainer.vue';
import HorizonButton from '@/Components/HorizonButton.vue';
import HorizonInput from '@/Components/HorizonInput.vue';
import HorizonPanel from '@/Components/HorizonPanel.vue';
import HorizonSectionHeader from '@/Components/HorizonSectionHeader.vue';
import HorizonSelect from '@/Components/HorizonSelect.vue';

const props = defineProps({
    squadrons: Array,
    eligibleLeaders: Array,
})

function editSquadron(sq) {
  console.log("OPEN EDIT PANEL FOR:", sq)
  // later: open modal or redirect to edit page
}

function deleteSquadron(id) {
  if (!confirm("Delete this squadron?")) return;

  router.post('/admin/squadrons/delete', { id })
}
</script>
