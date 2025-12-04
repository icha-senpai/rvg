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

      <h1 class="hz-title-xxl text-horizon-white hz-glyph tracking-wide">
        Horizon UI Theme Showcase
      </h1>

      <p class="hz-body text-text-secondary max-w-[48rem] mx-auto">
        A full demonstration of the Horizon Tailwind v4 design language:
        alloy geometry, deep shadow systems, neon accents, HUD layers,
        typography, panels, holography, cards, buttons, stats, and more.
      </p>

      <div class="flex justify-center gap-4 mt-8">
        <HorizonButton variant="primary" size="lg">
          Explore Components
        </HorizonButton>
        <HorizonButton variant="outline" size="lg">
          View Source
        </HorizonButton>
      </div>
    </section>


    <!-- ========================================================= -->
    <!-- CORE COLORS & BACKGROUNDS                                 -->
    <!-- ========================================================= -->
    <section class="hz-stack-md max-w-[72rem] mx-auto">
      <HorizonSectionHeader
        label="Foundation"
        title="Background Layers"
      />

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">

        <HorizonPanel class="text-center py-8">
          <div class="hz-title-md text-horizon-white">bg-base</div>
          <div class="hz-caption hz-text-muted">Root background layer</div>
        </HorizonPanel>

        <HorizonPanel class="text-center py-8">
          <div class="hz-title-md text-horizon-white">bg-surface</div>
          <div class="hz-caption hz-text-muted">Cards & containers</div>
        </HorizonPanel>

        <HorizonPanel class="text-center py-8">
          <div class="hz-title-md text-horizon-white">bg-elevated</div>
          <div class="hz-caption hz-text-muted">Holo / modal layer</div>
        </HorizonPanel>

        <HorizonPanel class="text-center py-8">
          <div class="hz-title-md text-horizon-white">bg-hover</div>
          <div class="hz-caption hz-text-muted">Interactive hover layer</div>
        </HorizonPanel>

      </div>
    </section>


    <!-- ========================================================= -->
    <!-- TYPOGRAPHY SYSTEM                                         -->
    <!-- ========================================================= -->
    <section class="hz-stack-md max-w-[72rem] mx-auto">
      <HorizonSectionHeader
        label="Typography"
        title="Horizon Text System"
      />

      <div class="hz-stack-sm">
        <h1 class="hz-title-xxl">Heading XL – Fleet Command</h1>
        <h2 class="hz-title-xl">Heading L – Research Directorate</h2>
        <h3 class="hz-title-lg">Heading M – Squadron Status</h3>
        <h4 class="hz-title-md">Heading S – System Metrics</h4>
        <p class="hz-body">This is the standard paragraph body.</p>
        <p class="hz-text-muted">This is muted text, ideal for metadata.</p>

        <a class="hz-link">This is a Horizon link.</a>
      </div>
    </section>


    <!-- ========================================================= -->
    <!-- BUTTON MATRIX                                             -->
    <!-- ========================================================= -->
    <section class="hz-stack-md max-w-[72rem] mx-auto">
      <HorizonSectionHeader label="Controls" title="Buttons" />

      <div class="flex flex-wrap gap-4">
        <HorizonButton variant="primary">Primary</HorizonButton>
        <HorizonButton variant="outline">Outline</HorizonButton>
        <HorizonButton variant="ghost">Ghost</HorizonButton>
        <HorizonButton variant="neutral">Neutral</HorizonButton>
        <HorizonButton variant="danger">Danger</HorizonButton>
      </div>
    </section>


    <!-- ========================================================= -->
    <!-- PANELS, CARDS, & SURFACES                                -->
    <!-- ========================================================= -->
    <section class="hz-stack-md max-w-[72rem] mx-auto">
      <HorizonSectionHeader label="Surfaces" title="Panels & Cards" />

      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

        <HorizonPanel>
          <div class="hz-title-md text-horizon-white">Standard Panel</div>
          <p class="hz-caption hz-text-muted">Clean, readable, stable.</p>
        </HorizonPanel>

        <div class="hz-card hz-shadow-soft hz-overlay-light p-6 rounded-2xl">
          <div class="hz-title-md text-horizon-white">Luminous Card</div>
          <p class="hz-caption hz-text-muted">Soft glow for key sections.</p>
        </div>

        <div class="hz-card hz-shadow-deep p-6 rounded-2xl">
          <div class="hz-title-md text-horizon-white">Deep Card</div>
          <p class="hz-caption hz-text-muted">Extra depth for emphasis.</p>
        </div>

      </div>
    </section>


    <!-- ========================================================= -->
    <!-- COMMAND WIDGET & STATS                                    -->
    <!-- ========================================================= -->
    <section class="hz-stack-md max-w-[72rem] mx-auto">
      <HorizonSectionHeader label="Data" title="Stats & Widgets" />

      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <CommandWidget title="Field Ops" :stat="27" label="Active Missions" />
        <HorizonStat label="Total Pilots" :value="841" />
        <HorizonStat label="Verified Members" :value="802" />
      </div>
    </section>


    <!-- ========================================================= -->
    <!-- ALERTS, CALL OUTS                                         -->
    <!-- ========================================================= -->
    <section class="hz-stack-md max-w-[72rem] mx-auto">
      <HorizonSectionHeader label="Feedback" title="Alerts" />

      <HorizonAlert title="Info" message="This is an informational notice." variant="info" />
      <HorizonAlert title="Warning" message="This requires attention." variant="warning" />
      <HorizonAlert title="Danger" message="Critical system failure." variant="danger" />
      <HorizonAlert title="Success" message="Operation completed." variant="success" />
    </section>


    <!-- ========================================================= -->
    <!-- INPUTS & FORMS                                            -->
    <!-- ========================================================= -->
    <section class="hz-stack-md max-w-[72rem] mx-auto">
      <HorizonSectionHeader label="Forms" title="Inputs" />

      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <HorizonInput label="Pilot Name" placeholder="Aria Solari" />
        <HorizonInput label="Callsign" placeholder="Vapor-7" />
      </div>
    </section>


    <!-- ========================================================= -->
    <!-- SQUADRON BADGES                                           -->
    <!-- ========================================================= -->
    <section class="hz-stack-md max-w-[72rem] mx-auto">
      <HorizonSectionHeader label="Identity" title="Squadron Badges" />

      <div class="flex flex-wrap gap-6">
        <SquadronBadge
          name="Raven Guard"
          motto="Shadow Above, Talon Below"
          logo="/img/raven.png"
        />
        <SquadronBadge
          name="Star Lance"
          motto="Pierce the Void"
          logo="/img/lance.png"
        />
        <SquadronBadge
          name="Echo Wing"
          motto="Signal in the Dark"
          logo="/img/echo.png"
        />
      </div>
    </section>


    <!-- ========================================================= -->
    <!-- ADVANCED GEOMETRIC CARDS                                  -->
    <!-- ========================================================= -->
    <section class="hz-stack-md max-w-[72rem] mx-auto">
      <HorizonSectionHeader label="Geometry" title="Advanced Horizon Surfaces" />

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">

        <div class="horizon-demo-curve-1 hz-card p-6">
          <div class="hz-title-md text-horizon-white">Curved Horizon</div>
          <p class="hz-caption hz-text-muted">Soft curvature luxury.</p>
        </div>

        <div class="horizon-demo-curve-2 hz-card p-6">
          <div class="hz-title-md text-horizon-white">Pulse Curve</div>
          <p class="hz-caption hz-text-muted">Micro-rim expression.</p>
        </div>

        <div class="horizon-demo-curve-3 hz-card p-6">
          <div class="hz-title-md text-horizon-white">Delta Curve</div>
          <p class="hz-caption hz-text-muted">Directional intent.</p>
        </div>

        <div class="horizon-demo-curve-4 hz-card p-6">
          <div class="hz-title-md text-horizon-white">Asymmetric</div>
          <p class="hz-caption hz-text-muted">Balanced imbalance.</p>
        </div>

        <div class="horizon-demo-curve-5 hz-card p-6">
          <div class="hz-title-md text-horizon-white">Cradle Curve</div>
          <p class="hz-caption hz-text-muted">Warm geometry.</p>
        </div>

        <div class="horizon-demo-curve-6 hz-card p-6">
          <div class="hz-title-md text-horizon-white">Hybrid Curve</div>
          <p class="hz-caption hz-text-muted">Soft + assertive.</p>
        </div>

      </div>
    </section>


    <!-- ========================================================= -->
    <!-- HUD ELEMENTS                                               -->
    <!-- ========================================================= -->
    <section class="hz-stack-md max-w-[72rem] mx-auto">
      <HorizonSectionHeader label="HUD" title="Interface Elements" />

      <HUDStatusBar label="System Load" value="62%" />

      <MiniMapPanel class="mt-6 h-64 flex items-center justify-center">
        <span class="hz-caption">Mini-map visualization panel</span>
      </MiniMapPanel>
    </section>


  </div>
</template>

<script setup>
</script>
