<script setup>
import { Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import HorizonContainer from '@/Components/HorizonContainer.vue'

const props = defineProps({
  topic: Object,
  entries: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
})
</script>

<template>
  <HorizonContainer class="py-8 md:py-10">
    <div class="mx-auto max-w-6xl space-y-8">
      <Link :href="route('archive.index')" class="text-sm font-semibold text-text-secondary hover:text-horizon-white">
        Back to Archive
      </Link>

      <section class="rounded-3xl border border-white/10 bg-white/[0.035] p-6 md:p-8">
        <div class="text-xs font-bold uppercase tracking-[0.24em] text-text-muted">
          {{ topic.category_label || 'Archive Topic' }}
        </div>

        <h1 class="mt-2 text-3xl font-black text-horizon-white md:text-5xl">
          {{ topic.title }}
        </h1>

        <p class="mt-3 max-w-3xl text-sm leading-6 text-text-secondary md:text-base">
          {{ topic.description || 'No description has been written for this archive topic yet.' }}
        </p>

        <div class="mt-4 flex flex-wrap gap-2 text-xs font-semibold text-text-muted">
          <span class="rounded-full border border-white/10 px-3 py-1">{{ topic.minimum_rank_label }}</span>
          <span class="rounded-full border border-white/10 px-3 py-1">{{ entries.length }} visible entries</span>
        </div>
      </section>

      <section class="space-y-4">
        <div>
          <div class="text-xs font-bold uppercase tracking-[0.24em] text-text-muted">Entries</div>
          <h2 class="text-2xl font-black text-horizon-white">Documents and records</h2>
        </div>

        <div v-if="entries.length" class="grid gap-4 md:grid-cols-2">
          <Link v-for="entry in entries" :key="entry.id" :href="entry.href" class="rounded-2xl border border-white/10 bg-white/[0.035] p-5 hover:border-[color:var(--horizon-sunset-blue)]/45">
            <div class="text-xs font-bold uppercase tracking-[0.2em] text-[color:var(--horizon-sunset-blue)]">
              {{ entry.minimum_rank_label }}
            </div>
            <h3 class="mt-2 text-xl font-black text-horizon-white">
              {{ entry.title }}
            </h3>
            <p class="mt-2 text-sm leading-6 text-text-secondary">
              {{ entry.excerpt || 'No excerpt has been written for this archive entry yet.' }}
            </p>
          </Link>
        </div>

        <div v-else class="rounded-2xl border border-white/10 bg-white/[0.035] p-6 text-sm text-text-secondary">
          No visible entries matched this topic.
        </div>
      </section>
    </div>
  </HorizonContainer>
</template>
