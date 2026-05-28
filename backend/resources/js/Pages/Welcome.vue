<template>
  <HorizonContainer>

    <!-- ========================================================= -->
    <!-- HERO HEADER: Horizon Interstellar Command                  -->
    <!-- ========================================================= -->
    <section class="hz-stack-md text-center pt-10 pb-20 relative">

      <!-- Neon background glow -->
      <div class="absolute inset-0 pointer-events-none opacity-[0.18]">
        <div class="absolute -top-40 left-1/2 -translate-x-1/2 w-[80%] h-[600px] rounded-full blur-[160px] bg-[var(--horizon-sunset-blue)]"></div>
      </div>

      <h2 class="hz-title-xl text-horizon-white hz-glyph tracking-wide">
        <img
          src="/images/PNG_Horizontally_Logo.png"
          alt="Horizon Interstellar"
          class="mx-auto h-24 sm:h-28 md:h-32 lg:h-36 w-auto max-w-full object-contain"
        />
      </h2>
      <br>
      <p class="hz-body text-text-secondary max-w-[48rem] mx-auto">
        HORIZON was founded in 2955 with the goal of providing entertaining events and operations for every kind of player, regardless of activity level, playtime, or nationality.
        HORIZON acts as a community hub for anyone who wants to get the most out of their Star Citizen experience.
        Join our daily operations and events, hosted by our highly experienced officers.
        Our leadership team has many years of practice in creating fun, engaging content at every level of complexity and scale.
        From relaxed 4 player sessions to massive operations with 100+ participants, HORIZON has the experience, structure, and organization to make it happen.
      </p>
      <br>
      <p class="hz-body text-text-secondary max-w-[48rem] mx-auto">
        This site is currently in active development. Things are subject to change without warning.
      </p>
    </section>


  </HorizonContainer>
</template>

<script setup>
import HorizonContainer from '@/Components/HorizonContainer.vue';

</script>









