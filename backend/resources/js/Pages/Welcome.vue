<template>
  <div class="min-h-screen bg-bg-base text-text-primary p-10 space-y-16">

    <!-- MAIN TITLE -->
    <section>
      <h1 class="text-4xl font-bold text-horizon-white mb-2">Horizon UI Theme Showcase</h1>
      <p class="text-text-secondary text-lg">
        A full demonstration of the Horizon Tailwind v4 theme, including backgrounds, typography,
        buttons, cards, alerts, lists, accents, shapes, curves, and geometric elements.
      </p>
    </section>

    <!-- BACKGROUNDS -->
    <section class="space-y-4">
      <h2 class="text-2xl font-semibold text-horizon-white">Background Layers</h2>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

        <div class="bg-bg-base p-6 rounded-xl border border-bg-hover">
          <h3 class="font-bold mb-1 text-horizon-white">bg-base</h3>
          <p class="text-text-secondary">Primary site background: #0F131A</p>
        </div>

        <div class="bg-bg-surface p-6 rounded-xl">
          <h3 class="font-bold mb-1 text-horizon-white">bg-surface</h3>
          <p class="text-text-secondary">Cards, containers, dashboard panels.</p>
        </div>

        <div class="bg-bg-elevated p-6 rounded-xl">
          <h3 class="font-bold mb-1 text-horizon-white">bg-elevated</h3>
          <p class="text-text-secondary">Modals, code blocks, layered UI.</p>
        </div>

        <div class="bg-bg-hover p-6 rounded-xl">
          <h3 class="font-bold mb-1 text-horizon-white">bg-hover</h3>
          <p class="text-text-secondary">Hover interaction layer.</p>
        </div>

      </div>
    </section>

    <!-- TYPOGRAPHY -->
    <section class="space-y-4">
      <h2 class="text-2xl font-semibold text-horizon-white">Typography</h2>

      <h1 class="text-4xl font-bold">Heading 1 – Horizon Interstellar Command</h1>
      <h2 class="text-3xl font-semibold">Heading 2 – Frontier Research Division</h2>
      <h3 class="text-2xl font-semibold">Heading 3 – Defence Wing Overview</h3>

      <p class="text-text-secondary">
        This is your primary paragraph style. Clean, readable, balanced contrast on dark backgrounds.
      </p>

      <p class="text-text-muted">
        And this is muted text. Perfect for timestamps, labels, or lower-priority data.
      </p>

      <a class="text-text-link hover:text-horizon-blue-light">
        This is a Horizon-styled link.
      </a>
    </section>

    <!-- BUTTONS -->
    <section class="space-y-4">
      <h2 class="text-2xl font-semibold text-horizon-white">Buttons</h2>

      <div class="flex flex-wrap gap-4">

        <button class="px-4 py-2 rounded bg-horizon-blue hover:bg-horizon-blue-dark text-horizon-white">
          Primary Action
        </button>

        <button class="px-4 py-2 rounded bg-bg-elevated hover:bg-bg-hover text-text-secondary">
          Secondary
        </button>

        <button class="px-4 py-2 rounded border border-horizon-blue text-horizon-blue hover:bg-bg-hover">
          Ghost
        </button>

        <button class="px-4 py-2 rounded bg-horizon-gray text-bg-base">
          Neutral
        </button>

      </div>
    </section>

    <!-- SIMPLE CARDS -->
    <section class="space-y-4">
      <h2 class="text-2xl font-semibold text-horizon-white">Basic Card Components</h2>

      <div class="bg-bg-surface p-6 rounded-xl shadow space-y-2">
        <h3 class="text-xl font-bold">Horizon Intel Briefing</h3>
        <p class="text-text-secondary">
          The Horizon Collective spans multiple divisions—Lifelines, Frontiers, Industries,
          Defence, and Interstellar Command. This card demonstrates core content layout.
        </p>

        <button class="mt-3 px-4 py-2 rounded bg-horizon-blue hover:bg-horizon-blue-dark text-horizon-white">
          View Details
        </button>
      </div>
    </section>

    <!-- ALERTS -->
    <section class="space-y-6">
      <h2 class="text-2xl font-semibold text-horizon-white">Alerts & Callouts</h2>

      <div class="p-4 bg-horizon-blue/20 rounded border border-horizon-blue">
        <p class="text-horizon-blue-light font-semibold">Info Alert</p>
        <p class="text-text-secondary">Horizon blue is perfect for informational context.</p>
      </div>

      <div class="p-4 bg-horizon-gray/20 rounded border border-horizon-gray">
        <p class="text-horizon-gray font-semibold">Neutral Notice</p>
        <p class="text-text-secondary">Useful for passive system messages.</p>
      </div>

      <div class="p-4 bg-horizon-alloy/20 rounded border border-horizon-alloy">
        <p class="text-horizon-alloy font-semibold">Interstellar Tone</p>
        <p class="text-text-secondary">For technical, metallic, or sci-fi-focused messaging.</p>
      </div>
    </section>

    <!-- LISTS -->
    <section class="space-y-4">
      <h2 class="text-2xl font-semibold text-horizon-white">Lists</h2>

      <ul class="list-disc pl-6 text-text-secondary space-y-1">
        <li>Readable dark-mode list items</li>
        <li>Soft contrast so nothing strains the eyes</li>
        <li>Perfect for dashboards, data, or mission logs</li>
      </ul>
    </section>

    <!-- CODE BLOCK -->
    <section class="space-y-4">
      <h2 class="text-2xl font-semibold text-horizon-white">Inline & Block Code</h2>

      <p class="text-text-secondary">
        Use inline code like
        <code class="bg-bg-elevated px-1 py-0.5 rounded text-horizon-blue">const x = 42;</code>
        inside sentences.
      </p>

      <pre class="bg-bg-elevated p-4 rounded-lg overflow-auto text-sm text-horizon-blue-light">
function warpJump() {
  return "Horizon Interstellar: Jump successful.";
}
      </pre>
    </section>

<!-- ADVANCED SEXY CARDS -->
<section class="space-y-8">
  <h2 class="text-2xl font-semibold text-horizon-white">Advanced Horizon Cards (Curves & Premium Lines)</h2>

  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">

    <!-- Curved Horizon Panel -->
    <div class="bg-bg-surface rounded-[28px] p-6 shadow-xl border border-bg-hover">
      <h3 class="text-xl font-semibold text-horizon-white">Curved Horizon Panel</h3>
      <p class="text-text-secondary mt-2">A soft, luxurious profile with wide curvature.</p>
    </div>
    <div class="bg-bg-surface p-4 shadow-lg 
            rounded-tl-[18px] rounded-tr-[6px]
            rounded-bl-[24px] rounded-br-[12px]
            border border-horizon-blue/10">
      <h3 class="text-lg font-semibold text-horizon-white">Pulse Curve</h3>
      <p class="text-text-secondary mt-2">Tiny but expressive. Great for dashboards.</p>
    </div>
    <div class="bg-bg-surface p-6 shadow-xl 
            rounded-tl-[36px] rounded-tr-[20px]
            rounded-bl-[28px] rounded-br-[12px]
            border border-horizon-blue/20">
      <h3 class="text-xl font-semibold text-horizon-white">Delta Curve</h3>
      <p class="text-text-secondary mt-2">Smooth, directional, quietly aggressive.</p>
    </div>
    <!-- Soft Asymmetric Curve (replacement for dual-cut) -->
    <div class="bg-bg-surface p-6 shadow-xl 
                rounded-tl-[28px] rounded-tr-[12px]
                rounded-bl-[12px] rounded-br-[28px]
                border border-horizon-blue/20">
      <h3 class="text-xl font-semibold text-horizon-white">Asymmetric Horizon Curve</h3>
      <p class="text-text-secondary mt-2">Asymmetric shape with commanding presence.</p>
    </div>

    <div class="bg-bg-surface p-6 shadow-xl 
            rounded-tl-[48px] rounded-tr-[48px]
            rounded-bl-[20px] rounded-br-[20px]
            border border-bg-hover">
      <h3 class="text-xl font-semibold text-horizon-white">Cradle Curve</h3>
      <p class="text-text-secondary mt-2">Gentle wraparound curvature for warm UI.</p>
    </div>

    <!-- Hybrid Round + Angular-ish curve -->
    <div class="bg-bg-surface p-6 shadow-xl 
                rounded-tl-[32px] rounded-tr-[18px]
                rounded-bl-[8px] rounded-br-[26px]
                border border-horizon-blue/20">
      <h3 class="text-xl font-semibold text-horizon-white">Horizon Hybrid Curve</h3>
      <p class="text-text-secondary mt-2">Soft edges with directional form.</p>
    </div>

    <div class="bg-bg-surface p-6 shadow-xl 
            rounded-tl-[20px] rounded-tr-[40px]
            rounded-bl-[28px] rounded-br-[6px]
            border border-horizon-blue/20">
      <h3 class="text-xl font-semibold text-horizon-white">Blade & Silk</h3>
      <p class="text-text-secondary mt-2">Soft everywhere except one assertive corner.</p>
    </div>


    <!-- Executive Split-Line -->
    <div class="bg-bg-surface p-6 shadow-xl rounded-2xl relative overflow-hidden">
      <div class="absolute top-0 left-0 w-full h-[2px] bg-horizon-blue/40"></div>
      <div class="absolute bottom-0 right-0 w-full h-[2px] bg-horizon-blue-dark/30"></div>

      <h3 class="text-xl font-semibold text-horizon-white">Executive Split-Line</h3>
      <p class="text-text-secondary mt-3">Minimal, premium, corporate sci-fi.</p>
    </div>

    <div class="bg-bg-surface p-6 shadow-xl 
            rounded-tl-[32px] rounded-tr-[12px]
            rounded-bl-[12px] rounded-br-[32px]
            border border-bg-hover">
      <h3 class="text-xl font-semibold text-horizon-white">Symphony Curve</h3>
      <p class="text-text-secondary mt-2">Balance through mirrored asymmetry.</p>
    </div>

    <!-- Neon Trim Card -->
    <div class="bg-bg-surface p-6 rounded-2xl shadow-xl border border-bg-hover relative">
      <div class="absolute inset-0 rounded-2xl border border-horizon-blue/30 blur-[2px]"></div>
      <h3 class="text-xl font-semibold text-horizon-white">Neon Trim Card</h3>
      <p class="text-text-secondary mt-2">Soft glow, tasteful and elegant.</p>
    </div>

    <div class="bg-bg-surface p-6 shadow-xl 
            rounded-tl-[40px] rounded-tr-xl
            rounded-bl-xl rounded-br-[40px]
            border border-horizon-blue/20">
      <h3 class="text-xl font-semibold text-horizon-white">Command Crest</h3>
      <p class="text-text-secondary mt-2">A leader’s curve — strong, elegant, decisive.</p>
    </div>
    <!-- Command Curve (curved version of command card) -->
    <div class="bg-bg-surface p-6 shadow-xl 
                rounded-tr-[40px] rounded-bl-[40px] 
                rounded-tl-xl rounded-br-xl
                border border-bg-hover">
      <h3 class="text-xl font-semibold text-horizon-white">Command Curve Card</h3>
      <p class="text-text-secondary mt-2">Authority through curvature, not edges.</p>
    </div>

  </div>
</section>


  </div>
</template>

<script setup>
// nothing needed
</script>

<style>

</style>
