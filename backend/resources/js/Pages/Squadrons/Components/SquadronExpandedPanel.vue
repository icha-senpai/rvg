<script setup>
import SquadronPanelHeader from './SquadronPanelHeader.vue'
import SquadronOverviewSection from './SquadronOverviewSection.vue'
import SquadronViewerStatus from './SquadronViewerStatus.vue'
import SquadronRoster from './SquadronRoster.vue'


const props = defineProps({
  squadron: Object,
  members: Array,
  viewerMembership: Object,
  permissions: Object,
  isLoading: Boolean,
  activeAction: String,
})

const emit = defineEmits([
  'close',
  'apply',
  'leave',
  'accept-member',
  'reject-member',
  'promote-lt',
  'remove-member',
])
</script>


<template>
  <div class="hz-panel hz-stack">

    <!-- Header -->
    <SquadronPanelHeader
      :squadron="squadron"
      @close="emit('close')"
    />

    <!-- Overview -->
    <SquadronOverviewSection
      :squadron="squadron"
    />

    <!-- Viewer context / actions -->
    <SquadronViewerStatus
      :viewerMembership="viewerMembership"
      :permissions="permissions"
      :squadron="squadron"
      :isLoading="isLoading"
      @apply="emit('apply')"
      @leave="emit('leave')"
    />

    <!-- Roster will be mounted here next -->
     <!-- Roster -->
    <SquadronRoster
      :members="members"
      :permissions="permissions"
      :activeAction="activeAction"
      @accept-member="emit('accept-member', member)"
      @reject-member="emit('reject-member', member)"
      @promote-lt="emit('promote-lt', member)"
      @remove-member="emit('remove-member', member)"
    />

  </div>
</template>
