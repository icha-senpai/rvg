<template>
  <HorizonContainer>

    <!-- HUD Status Bar -->
    <div class="hz-container-wide mb-20">
      <HUDStatusBar
        label="Operations"
        :value="`${activeCount}/${totalCount} active`"
      />
    </div>

    <!-- Main Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-[2.2fr,1fr] gap-14">

      <!-- LEFT COLUMN -->
      <section class="space-y-14">

        <!-- Header -->
        <HorizonSectionHeader
          label="Operations"
          title="Operations Board"
        />

        <!-- Filter Panel -->
        <HorizonPanel class="p-6 rounded-2xl hz-overlay-light space-y-6">

          <!-- STATUS FILTERS -->
          <div class="flex flex-wrap gap-3">
            <HorizonButton
              v-for="s in statusFilters"
              :key="s.value"
              size="sm"
              :variant="statusFilter === s.value ? 'primary' : 'ghost'"
              @click="statusFilter = s.value"
            >
              {{ s.label }}
            </HorizonButton>
          </div>

          <!-- SEARCH -->
          <div class="flex items-center gap-3">
            <div class="ml-auto w-full sm:w-64">
              <HorizonInput
                v-model="search"
                label="Search"
                placeholder="Title, squadron..."
              />
            </div>
          </div>

        </HorizonPanel>

        <!-- OPERATION GRID -->
        <MissionGrid v-if="filteredOperations.length > 0" class="pt-2">
          <MissionCard
            v-for="op in filteredOperations"
            :key="op.id"
            :title="op.title"
            :description="op.description"
            :start="formatDate(op.starts_at)"
            :eta="op.ends_at ? formatDate(op.ends_at) : 'TBD'"
            :status="op.status"
          >
            <div class="mt-6 flex items-center justify-between">

              <div class="hz-caption">
                Difficulty: {{ op.difficulty ?? 'N/A' }} • Visibility: {{ op.visibility ?? 'open' }}
              </div>

              <HorizonButton
                size="sm"
                variant="outline"
                @click="$inertia.visit(route('operations.show', op.id))"
              >
                View
              </HorizonButton>

            </div>
          </MissionCard>
        </MissionGrid>

        <!-- EMPTY STATE -->
        <HorizonPanel
          v-else
          class="text-center py-20 space-y-6 hz-holo-light hz-lift"
        >
          <div class="hz-title-lg text-horizon-white">
            No Operations Found
          </div>

          <p class="hz-caption hz-text-muted">
            Use the operation editor to create the first entry.
          </p>

          <HorizonButton
            variant="primary"
            size="lg"
            @click="$inertia.visit(route('operations.create', { squadron: 1 }))"
          >
            Create Operation
          </HorizonButton>
        </HorizonPanel>

      </section>

      <!-- RIGHT SIDEBAR -->
      <aside class="space-y-10">

        <CommandWidget
          title="Ops Load"
          :stat="activeCount"
          label="Active or in-progress"
        >
          <div class="hz-caption mt-3">
            {{ draftCount }} draft ·
            {{ plannedCount }} published ·
            {{ completedCount }} completed
          </div>
        </CommandWidget>

        <div class="grid grid-cols-2 gap-4">
          <HorizonStat label="Total Operations" :value="totalCount" />
          <HorizonStat label="Active Ops" :value="activeCount" />
        </div>

        <HorizonPanel class="p-6">
          <div class="hz-section-label mb-3">Filter Context</div>
          <p class="hz-caption leading-relaxed">
            Status: <strong>{{ statusFilterLabel }}</strong><br>
            Search: <strong>{{ search || 'None' }}</strong>
          </p>
        </HorizonPanel>

        <MiniMapPanel class="h-64 flex items-center justify-center">
          <span class="hz-caption">Future: sector / theater overview here</span>
        </MiniMapPanel>

      </aside>

    </div>

  </HorizonContainer>
</template>

<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';

import HorizonContainer from '@/Components/HorizonContainer.vue';
import HorizonButton from '@/Components/HorizonButton.vue';
import HorizonInput from '@/Components/HorizonInput.vue';
import HorizonPanel from '@/Components/HorizonPanel.vue';
import HorizonSectionHeader from '@/Components/HorizonSectionHeader.vue';
import HorizonStat from '@/Components/HorizonStat.vue';
import HUDStatusBar from '@/Components/HUDStatusBar.vue';
import MissionGrid from '@/Components/MissionGrid.vue';
import MissionCard from '@/Components/MissionCard.vue';
import CommandWidget from '@/Components/CommandWidget.vue';
import MiniMapPanel from '@/Components/MiniMapPanel.vue';

const props = defineProps({
  operations: {
    type: Array,
    default: () => [],
  },
});

const statusFilters = [
  { label: 'All', value: 'all' },
  { label: 'Draft', value: 'draft' },
  { label: 'Published', value: 'published' },
  { label: 'In Progress', value: 'in_progress' },
  { label: 'Completed', value: 'completed' },
  { label: 'Canceled', value: 'canceled' },
];

const statusFilter = ref('all');
const search = ref('');

const filteredOperations = computed(() => {
  return props.operations.filter(op => {
    const matchesStatus =
      statusFilter.value === 'all' || op.status === statusFilter.value;

    const q = search.value.toLowerCase();
    const matchesSearch =
      !q ||
      op.title.toLowerCase().includes(q) ||
      (op.description || '').toLowerCase().includes(q);

    return matchesStatus && matchesSearch;
  });
});

const totalCount = computed(() => props.operations.length);
const activeCount = computed(() =>
  props.operations.filter(op =>
    ['published', 'in_progress'].includes(op.status)
  ).length
);

const draftCount = computed(() =>
  props.operations.filter(op => op.status === 'draft').length
);
const completedCount = computed(() =>
  props.operations.filter(op => op.status === 'completed').length
);
const plannedCount = computed(() =>
  props.operations.filter(op => op.status === 'published').length
);

const statusFilterLabel = computed(
  () => statusFilters.find(s => s.value === statusFilter.value)?.label ?? 'All'
);

function formatDate(value) {
  if (!value) return 'TBD';
  return String(value);
}
</script>
