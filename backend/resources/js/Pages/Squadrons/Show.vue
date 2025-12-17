<script setup>
import { ref } from 'vue'
import SquadronExpandedPanel from './Components/SquadronExpandedPanel.vue'
import HorizonButton from '@/Components/HorizonButton.vue';
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

/* -------------------------------------------------
   Page state
------------------------------------------------- */
const isExpanded = ref(false)

/* -------------------------------------------------
   Panel controls
------------------------------------------------- */
function openPanel() {
  isExpanded.value = true
}

function closePanel() {
  isExpanded.value = false
}
</script>

<template>
  <HorizonContainer class="space-y-10">
  <div class="mx-auto max-w-5xl hz-stack">
    <div class="hz-title-lg"> Your Squadron</div>
    <HorizonButton
      variant="primary"
      size="sm"
      @click="openPanel"
    >
      View Squadron
    </HorizonButton>

    <SquadronExpandedPanel
      v-if="isExpanded"
      :squadronId="props.squadronId"
      @close="closePanel"
    />
  </div>
  </HorizonContainer>
</template>
