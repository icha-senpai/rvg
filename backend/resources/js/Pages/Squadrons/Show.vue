<script setup>
import { ref } from 'vue'
import SquadronExpandedPanel from './Components/SquadronExpandedPanel.vue'
import HorizonButton from '@/Components/HorizonButton.vue';
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
  <div class="hz-container hz-stack">
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
</template>
