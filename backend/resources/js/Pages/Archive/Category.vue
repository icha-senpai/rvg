<script setup>
import { Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import HorizonBadge from '@/Components/HorizonBadge.vue'
import HorizonContainer from '@/Components/HorizonContainer.vue'

const props = defineProps({
  category: { type: Object, required: true },
  topics: { type: Array, default: () => [] },
  entries: { type: Array, default: () => [] },
})
</script>

<template>
  <HorizonContainer class="py-8 md:py-10">
    <div class="mx-auto max-w-7xl space-y-8">
      <Link :href="route('archive.index')" class="inline-flex items-center rounded-xl border border-white/[0.055] bg-white/[0.024] px-4 py-2 text-sm font-semibold text-text-secondary hover:border-[color:var(--horizon-sunset-blue)]/35 hover:text-horizon-white">
        ← Back to Archive
      </Link>

      <section class="hz-surface-welcome relative overflow-hidden rounded-[2rem] border border-white/[0.055] p-6 md:p-8">
        <div class="pointer-events-none absolute inset-0 opacity-40">
          <div class="absolute left-8 top-0 h-px w-56 bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-blue)] to-transparent"></div>
          <div class="absolute bottom-0 right-10 h-px w-72 bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-magenta)] to-transparent"></div>
        </div>

        <div class="relative">
          <div class="flex flex-wrap gap-2">
            <HorizonBadge variant="neutral" uppercase>
              Archive Category
            </HorizonBadge>
            <HorizonBadge variant="muted">
              {{ topics.length }} topic{{ topics.length === 1 ? '' : 's' }}
            </HorizonBadge>
            <HorizonBadge variant="muted">
              {{ entries.length }} direct entr{{ entries.length === 1 ? 'y' : 'ies' }}
            </HorizonBadge>
          </div>

          <h1 class="mt-4 text-3xl font-black tracking-tight text-horizon-white md:text-5xl">
            {{ category.name }}
          </h1>

          <p class="mt-3 max-w-3xl text-sm leading-6 text-text-secondary md:text-base">
            {{ category.description || '' }}
          </p>
        </div>
      </section>

      <section class="space-y-4">
        <div>
          <div class="text-xs font-bold uppercase tracking-[0.24em] text-text-muted">Direct Category Entries</div>
          <h2 class="text-2xl font-black text-horizon-white">Documents directly inside {{ category.name }}</h2>
        </div>

        <div v-if="entries.length" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          <Link v-for="entry in entries" :key="entry.id" :href="entry.href" class="hz-surface-welcome group relative rounded-2xl border border-white/[0.055] p-5 transition hover:-translate-y-0.5 hover:border-[color:var(--horizon-sunset-blue)]/45 hover:bg-white/[0.055]">
            <div class="relative flex flex-wrap gap-2">
              <HorizonBadge variant="info" uppercase>{{ entry.minimum_rank_label }}</HorizonBadge>
              <HorizonBadge v-for="tagItem in entry.tags" :key="`category-entry-tag-${entry.id}-${tagItem.id}`" variant="accent">#{{ tagItem.name }}</HorizonBadge>
            </div>

            <h3 class="relative mt-3 text-xl font-black text-horizon-white group-hover:text-[color:var(--horizon-sunset-blue)]">{{ entry.title }}</h3>
            <p class="relative mt-2 line-clamp-3 text-sm leading-6 text-text-secondary">{{ entry.excerpt || 'No excerpt has been written for this archive entry yet.' }}</p>
            <div v-if="entry.updated_label" class="relative mt-4 text-xs font-semibold text-text-muted">Updated {{ entry.updated_label }}</div>
          </Link>
        </div>

        <div v-else class="hz-surface-welcome rounded-2xl border border-white/[0.055] p-6 text-sm text-text-secondary">
          No direct category entries are currently visible here.
        </div>
      </section>

      <section class="space-y-4">
        <div>
          <div class="text-xs font-bold uppercase tracking-[0.24em] text-text-muted">Topics</div>
          <h2 class="text-2xl font-black text-horizon-white">Knowledge blocks inside {{ category.name }}</h2>
        </div>

        <div v-if="topics.length" class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
          <Link v-for="topic in topics" :key="topic.id" :href="topic.href" class="hz-surface-welcome group relative rounded-[1.75rem] border border-white/10 p-5 shadow-[0_18px_55px_rgba(0,0,0,0.22)] transition hover:-translate-y-1 hover:border-white/[0.055]">
            <div class="relative space-y-4">
              <div class="flex aspect-square items-center justify-center overflow-hidden rounded-2xl border border-white/10 bg-black/20 p-3">
                <img v-if="topic.card_image_path" :src="topic.card_image_path" :alt="topic.title" class="h-full w-full rounded-xl object-contain" />
                <div v-else class="px-4 text-center text-5xl text-[color:var(--horizon-sunset-blue)]/80">◫</div>
              </div>

              <div>
                <div class="flex flex-wrap gap-2">
                  <HorizonBadge variant="muted">{{ topic.minimum_rank_label }}</HorizonBadge>
                </div>

                <h3 class="mt-3 text-xl font-black text-horizon-white group-hover:text-white">{{ topic.title }}</h3>
                <p class="mt-2 line-clamp-3 text-sm leading-6 text-text-secondary">{{ topic.description }}</p>
              </div>

              <div class="flex items-center justify-between border-t border-white/10 pt-4 text-xs font-semibold text-text-muted">
                <span>{{ topic.visible_entries_count }} visible entries</span>
                <span>Open →</span>
              </div>
            </div>
          </Link>
        </div>

        <div v-else class="hz-surface-welcome rounded-2xl border border-white/[0.055] p-6 text-sm text-text-secondary">
          No archive topics are currently visible in this category.
        </div>
      </section>
    </div>
  </HorizonContainer>
</template>
