<template>
  <section class="relative overflow-hidden rounded-[2rem] border border-[color:var(--horizon-sunset-indigo)]/35 bg-[radial-gradient(circle_at_top_left,var(--horizon-glow-blue),transparent_36%),radial-gradient(circle_at_top_right,var(--horizon-glow-magenta),transparent_34%),linear-gradient(135deg,var(--horizon-void-700),var(--horizon-void-900))] p-5 shadow-[0_0_40px_rgba(67,56,202,0.16)]">
    <div class="pointer-events-none absolute inset-0 opacity-40">
      <div class="absolute left-8 top-0 h-px w-48 bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-blue)] to-transparent"></div>
      <div class="absolute bottom-0 right-10 h-px w-64 bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-magenta)] to-transparent"></div>
    </div>

    <div class="relative flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
      <div>
        <div class="text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--horizon-text-secondary)]">
          Media Administration
        </div>

        <h2 class="mt-1 text-2xl font-black text-horizon-white">
          Media Library
        </h2>

        <p class="mt-2 max-w-3xl text-sm text-text-secondary">
          Upload, inspect, rename, describe, and remove Horizon media assets.
        </p>

        <div class="mt-4 flex flex-wrap gap-2">
          <span class="rounded-full border border-[color:var(--horizon-sunset-blue)]/30 bg-[color:var(--horizon-sunset-blue)]/10 px-3 py-1 text-xs font-semibold text-[color:var(--horizon-text-primary)]">
            {{ totalCount }} Files
          </span>

          <span class="rounded-full border border-[color:var(--horizon-sunset-magenta)]/30 bg-[color:var(--horizon-sunset-magenta)]/10 px-3 py-1 text-xs font-semibold text-[color:var(--horizon-text-primary)]">
            {{ humanTotalSize }}
          </span>

          <span class="rounded-full border border-white/10 bg-white/[0.035] px-3 py-1 text-xs font-semibold text-text-secondary">
            R2 suggested near 8 GB
          </span>
        </div>
      </div>

      <HorizonButton
        variant="primary"
        size="sm"
        @click="$emit('upload')"
      >
        Upload Media
      </HorizonButton>
    </div>
  </section>
</template>

<script setup>
import HorizonButton from '@/Components/HorizonButton.vue'

defineProps({
  totalCount: { type: Number, required: true },
  humanTotalSize: { type: String, required: true },
})

defineEmits(['upload'])
</script>