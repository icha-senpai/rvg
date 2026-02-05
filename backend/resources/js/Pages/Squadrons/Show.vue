<script setup>
import SquadronExpandedPanel from './Components/SquadronExpandedPanel.vue'
import HorizonContainer from '@/Components/HorizonContainer.vue';
/* -------------------------------------------------
   Props from Inertia
------------------------------------------------- */
const props = defineProps({
  squadronId: {
    type: Number,
    required: true,
  },
})
</script>

<template>
  <HorizonContainer class="space-y-10">
    <div class="mx-auto max-w-5xl hz-stack">
      <div class="hz-title-lg">Squadron</div>
      <SquadronExpandedPanel
        variant="embedded"
        :squadronId="props.squadronId"
        :show-close-button="false"
      />
    </div>
  </HorizonContainer>
</template>
