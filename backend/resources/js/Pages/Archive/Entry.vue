<script setup>
import { Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import HorizonContainer from '@/Components/HorizonContainer.vue'

const props = defineProps({
  topic: Object,
  entry: Object,
  relatedEntries: { type: Array, default: () => [] },
})
</script>

<template>
  <HorizonContainer class="py-8 md:py-10">
    <article class="mx-auto max-w-5xl space-y-8">
      <nav class="flex flex-wrap items-center gap-2 text-sm font-semibold text-text-secondary">
        <Link :href="route('archive.index')" class="rounded-xl border border-white/10 bg-white/[0.035] px-3 py-1.5 hover:border-[color:var(--horizon-sunset-blue)]/35 hover:text-horizon-white">
          Archive
        </Link>
        <span class="text-text-muted">/</span>
        <Link :href="topic.href" class="rounded-xl border border-white/10 bg-white/[0.035] px-3 py-1.5 hover:border-[color:var(--horizon-sunset-blue)]/35 hover:text-horizon-white">
          {{ topic.title }}
        </Link>
      </nav>

      <header class="relative overflow-hidden rounded-[2rem] border border-[color:var(--horizon-sunset-indigo)]/35 bg-[radial-gradient(circle_at_top_left,var(--horizon-glow-blue),transparent_38%),radial-gradient(circle_at_top_right,var(--horizon-glow-magenta),transparent_34%),linear-gradient(135deg,var(--horizon-void-600),var(--horizon-void-900))] shadow-[0_0_48px_rgba(67,56,202,0.16)]">
        <div class="pointer-events-none absolute inset-0 opacity-40">
          <div class="absolute left-8 top-0 h-px w-56 bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-blue)] to-transparent"></div>
          <div class="absolute bottom-0 right-10 h-px w-72 bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-magenta)] to-transparent"></div>
        </div>

        <div v-if="entry.banner_image_path" class="relative h-48 overflow-hidden border-b border-white/10 md:h-72">
          <img :src="entry.banner_image_path" :alt="entry.title" class="h-full w-full object-cover" />
          <div class="absolute inset-0 bg-gradient-to-t from-[color:var(--horizon-void-900)] via-[color:var(--horizon-void-900)]/55 to-transparent"></div>
        </div>

        <div class="relative p-6 md:p-8">
          <div class="flex flex-wrap gap-2">
            <span class="rounded-full border border-[color:var(--horizon-sunset-blue)]/30 bg-[color:var(--horizon-sunset-blue)]/10 px-3 py-1 text-xs font-bold uppercase tracking-[0.18em] text-[color:var(--horizon-text-primary)]">
              {{ topic.category_label || 'Archive Entry' }}
            </span>
            <span class="rounded-full border border-white/10 bg-white/[0.035] px-3 py-1 text-xs font-semibold text-text-secondary">
              {{ entry.minimum_rank_label }}
            </span>
          </div>

          <h1 class="mt-4 max-w-4xl text-3xl font-black tracking-tight text-horizon-white md:text-5xl">
            {{ entry.title }}
          </h1>

          <p v-if="entry.excerpt" class="mt-4 max-w-3xl text-base leading-7 text-text-secondary md:text-lg">
            {{ entry.excerpt }}
          </p>

          <div class="mt-5 flex flex-wrap gap-2 text-xs font-semibold text-text-muted">
            <span v-if="entry.published_label" class="rounded-full border border-white/10 bg-black/10 px-3 py-1">Published {{ entry.published_label }}</span>
            <span v-if="entry.updated_label" class="rounded-full border border-white/10 bg-black/10 px-3 py-1">Updated {{ entry.updated_label }}</span>
          </div>

          <div v-if="entry.categories?.length || entry.tags?.length" class="mt-4 flex flex-wrap gap-2 text-xs font-semibold">
            <span v-for="category in entry.categories" :key="`entry-category-${category.id}`" class="rounded-full border border-[color:var(--horizon-sunset-blue)]/25 bg-[color:var(--horizon-sunset-blue)]/10 px-3 py-1 text-[color:var(--horizon-sunset-blue)]">
              {{ category.name }}
            </span>
            <span v-for="tag in entry.tags" :key="`entry-tag-${tag.id}`" class="rounded-full border border-[color:var(--horizon-sunset-magenta)]/25 bg-[color:var(--horizon-sunset-magenta)]/10 px-3 py-1 text-[color:var(--horizon-sunset-magenta)]">
              #{{ tag.name }}
            </span>
          </div>
        </div>
      </header>

      <section class="relative overflow-hidden rounded-[2rem] border border-white/10 bg-[linear-gradient(145deg,rgba(255,255,255,0.055),rgba(255,255,255,0.025))] p-6 shadow-[0_18px_55px_rgba(0,0,0,0.20)] md:p-8">
        <div class="pointer-events-none absolute inset-x-8 top-0 h-px bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-blue)]/50 to-transparent"></div>

        <div
          v-if="entry.body"
          class="hz-rte-content max-w-none"
          v-html="entry.body"
        />
        <div v-else class="rounded-2xl border border-white/10 bg-black/20 p-5 text-text-secondary">
          No body content has been written for this archive entry yet.
        </div>
      </section>

      <section v-if="relatedEntries.length" class="space-y-4">
        <div class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
          <div>
            <div class="text-xs font-bold uppercase tracking-[0.24em] text-text-muted">Related Records</div>
            <h2 class="text-2xl font-black text-horizon-white">More from {{ topic.title }}</h2>
          </div>

          <Link :href="topic.href" class="text-sm font-bold text-text-secondary hover:text-horizon-white">
            View all in topic →
          </Link>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
          <Link v-for="related in relatedEntries" :key="related.id" :href="related.href" class="group relative overflow-hidden rounded-2xl border border-white/10 bg-white/[0.035] p-5 transition hover:-translate-y-0.5 hover:border-[color:var(--horizon-sunset-blue)]/45 hover:bg-white/[0.055]">
            <div class="pointer-events-none absolute -right-12 -top-16 h-32 w-32 rounded-full bg-[color:var(--horizon-sunset-blue)]/10 blur-3xl"></div>

            <div v-if="related.categories?.length || related.tags?.length" class="relative mb-3 flex flex-wrap gap-2 text-xs font-semibold">
              <span v-for="category in related.categories" :key="`related-category-${related.id}-${category.id}`" class="rounded-full border border-[color:var(--horizon-sunset-blue)]/25 bg-[color:var(--horizon-sunset-blue)]/10 px-2.5 py-1 text-[color:var(--horizon-sunset-blue)]">
                {{ category.name }}
              </span>
              <span v-for="tag in related.tags" :key="`related-tag-${related.id}-${tag.id}`" class="rounded-full border border-[color:var(--horizon-sunset-magenta)]/25 bg-[color:var(--horizon-sunset-magenta)]/10 px-2.5 py-1 text-[color:var(--horizon-sunset-magenta)]">
                #{{ tag.name }}
              </span>
            </div>

            <h3 class="relative text-lg font-black text-horizon-white group-hover:text-[color:var(--horizon-sunset-blue)]">{{ related.title }}</h3>
            <p class="relative mt-2 line-clamp-3 text-sm leading-6 text-text-secondary">{{ related.excerpt || 'No excerpt has been written for this archive entry yet.' }}</p>
          </Link>
        </div>
      </section>
    </article>
  </HorizonContainer>
</template>
