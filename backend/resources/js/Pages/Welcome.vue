<template>
  <div class="min-h-screen bg-bg-base text-text-primary p-10 hz-noise hz-vignette hz-stack-xl">

    <!-- ========================================================= -->
    <!-- HERO HEADER: Horizon Interstellar Command                  -->
    <!-- ========================================================= -->
    <section class="hz-stack-md text-center pt-10 pb-20 relative">

      <!-- Neon background glow -->
      <div class="absolute inset-0 pointer-events-none opacity-[0.18]">
        <div class="absolute -top-40 left-1/2 -translate-x-1/2 w-[80%] h-[600px] rounded-full blur-[160px] bg-horizon-blue"></div>
      </div>

      <h2 class="hz-title-xl text-horizon-white hz-glyph tracking-wide">
        Horizon Interstellar
      </h2>

      <p class="hz-body text-text-secondary max-w-[48rem] mx-auto">
        A full demonstration of the Horizon Tailwind v4 design language:
        alloy geometry, deep shadow systems, neon accents, HUD layers,
        typography, panels, holography, cards, buttons, stats, and more.
      </p>

    </section>


  </div>
</template>

<script setup>
import HorizonButton from '@/Components/HorizonButton.vue';
import HorizonInput from '@/Components/HorizonInput.vue';
import HorizonAlert from '@/Components/HorizonAlert.vue';
import HorizonPanel from '@/Components/HorizonPanel.vue';
import HorizonSectionHeader from '@/Components/HorizonSectionHeader.vue';
import HorizonStat from '@/Components/HorizonStat.vue';
import HUDStatusBar from '@/Components/HUDStatusBar.vue';
import CommandWidget from '@/Components/CommandWidget.vue';
import MiniMapPanel from '@/Components/MiniMapPanel.vue';
import SquadronBadge from '@/Components/SquadronBadge.vue';
</script>
