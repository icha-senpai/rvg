<template>
  <HorizonContainer>
    <!-- HUD status bar -->
    <HUDStatusBar
      label="Operations"
      :value="`${activeCount}/${totalCount} active`"
    />

    <div class="mt-16 grid grid-cols-1 lg:grid-cols-[2fr,1fr] gap-8">
      <!-- LEFT: mission list + filters -->
      <section>
        <HorizonSectionHeader
          label="Operations"
          title="Mission & Event Board"
        />

        <!-- Filters -->
        <div class="flex flex-wrap items-center gap-3 mb-6">
          <!-- Status filter -->
          <HorizonButton
            v-for="s in statusFilters"
            :key="s.value"
            size="sm"
            :variant="statusFilter === s.value ? 'primary' : 'ghost'"
            @click="statusFilter = s.value"
          >
            {{ s.label }}
          </HorizonButton>

          <!-- Kind filter -->
          <HorizonButton
            v-for="k in kindFilters"
            :key="k.value"
            size="sm"
            :variant="kindFilter === k.value ? 'primary' : 'ghost'"
            @click="kindFilter = k.value"
          >
            {{ k.label }}
          </HorizonButton>

          <!-- Search -->
          <div class="ml-auto w-64">
            <HorizonInput
              v-model="search"
              label="Search"
              placeholder="Title, squadron..."
            />
          </div>
        </div>

        <!-- Mission grid -->
        <MissionGrid>
          <MissionCard
            v-for="op in filteredOperations"
            :key="op.id"
            :title="op.title"
            :description="op.description"
            :start="formatDate(op.starts_at)"
            :eta="op.ends_at ? formatDate(op.ends_at) : 'TBD'"
            :status="op.status"
          >
            <div class="mt-4 flex items-center justify-between">
              <div class="hz-caption">
                {{ op.operation_kind === 'mission' ? 'Mission' : 'Event' }}
                •
                {{ op.difficulty ? op.difficulty.toUpperCase() : 'N/A' }}
                •
                {{ op.visibility ?? 'open' }}
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
      </section>

      <!-- RIGHT: quick stats / context -->
      <aside class="space-y-6">
        <CommandWidget
          title="Ops Load"
          :stat="activeCount"
          label="Active or in-progress"
        >
          <div class="hz-caption mt-2">
            {{ draftCount }} draft ·
            {{ plannedCount }} published ·
            {{ completedCount }} completed
          </div>
        </CommandWidget>

        <div class="grid grid-cols-2 gap-4">
          <HorizonStat
            label="Total Operations"
            :value="totalCount"
          />
          <HorizonStat
            label="Missions / Events"
            :value="`${missionCount}/${eventCount}`"
          />
        </div>

        <HorizonPanel>
          <div class="hz-section-label mb-2">Filter Context</div>
          <p class="hz-caption">
            Status: <strong>{{ statusFilterLabel }}</strong><br />
            Kind: <strong>{{ kindFilterLabel }}</strong>
          </p>
        </HorizonPanel>

        <MiniMapPanel>
          <span class="hz-caption">
            Future: sector / theater overview can live here.
          </span>
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

const kindFilters = [
  { label: 'All kinds', value: 'all' },
  { label: 'Missions', value: 'mission' },
  { label: 'Events', value: 'event' },
];

const statusFilter = ref('all');
const kindFilter = ref('all');
const search = ref('');

const filteredOperations = computed(() => {
  return props.operations.filter(op => {
    const matchesStatus =
      statusFilter.value === 'all' || op.status === statusFilter.value;

    const matchesKind =
      kindFilter.value === 'all' || op.operation_kind === kindFilter.value;

    const q = search.value.toLowerCase();
    const matchesSearch =
      !q ||
      op.title.toLowerCase().includes(q) ||
      (op.description || '').toLowerCase().includes(q);

    return matchesStatus && matchesKind && matchesSearch;
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
const missionCount = computed(() =>
  props.operations.filter(op => op.operation_kind === 'mission').length
);
const eventCount = computed(() =>
  props.operations.filter(op => op.operation_kind === 'event').length
);

const statusFilterLabel = computed(() => {
  return statusFilters.find(s => s.value === statusFilter.value)?.label ?? 'All';
});

const kindFilterLabel = computed(() => {
  return kindFilters.find(k => k.value === kindFilter.value)?.label ?? 'All kinds';
});

function formatDate(value) {
  if (!value) return 'TBD';
  // assume backend already gives ISO or nice string; keep it simple
  return String(value);
}
</script>
