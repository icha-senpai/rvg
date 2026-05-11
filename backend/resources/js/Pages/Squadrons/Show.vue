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
      <div class="relative overflow-hidden rounded-[2rem] border border-[color:var(--horizon-sunset-indigo)]/45 bg-[radial-gradient(circle_at_top_left,var(--horizon-glow-blue),transparent_34%),radial-gradient(circle_at_top_right,var(--horizon-glow-magenta),transparent_32%),linear-gradient(135deg,var(--horizon-void-600),var(--horizon-void-900))] p-6 shadow-[0_0_48px_rgba(67,56,202,0.18)]">
        <div class="pointer-events-none absolute inset-0 opacity-40">
          <div class="absolute left-8 top-0 h-px w-48 bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-blue)] to-transparent"></div>
          <div class="absolute bottom-0 right-10 h-px w-64 bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-magenta)] to-transparent"></div>
        </div>

        <div class="relative">
          <div class="text-xs font-bold uppercase tracking-[0.28em] text-[color:var(--horizon-text-secondary)]">
            Horizon Squadron File
          </div>

          <h1 class="mt-2 text-3xl font-bold tracking-tight text-horizon-white md:text-4xl">
            Squadron
          </h1>

          <p class="mt-2 max-w-2xl text-sm text-text-secondary">
            Unit profile, recruitment status, roster, and command information.
          </p>
        </div>
      </div>

      <SquadronExpandedPanel
        variant="embedded"
        :squadronId="props.squadronId"
        :show-close-button="false"
      />
    </div>
  </HorizonContainer>
</template>
