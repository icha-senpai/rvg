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
    <article class="mx-auto max-w-4xl space-y-8">
      <div class="flex flex-wrap gap-3 text-sm font-semibold text-text-secondary">
        <Link :href="route('archive.index')" class="hover:text-horizon-white">Archive</Link>
        <span>/</span>
        <Link :href="topic.href" class="hover:text-horizon-white">{{ topic.title }}</Link>
      </div>

      <header class="overflow-hidden rounded-3xl border border-white/10 bg-white/[0.035]">
        <div v-if="entry.banner_image_path" class="relative h-48 overflow-hidden border-b border-white/10 md:h-72">
          <img :src="entry.banner_image_path" :alt="entry.title" class="h-full w-full object-cover" />
          <div class="absolute inset-0 bg-gradient-to-t from-[color:var(--horizon-void-900)] via-[color:var(--horizon-void-900)]/35 to-transparent"></div>
        </div>

        <div class="p-6 md:p-8">
          <div class="text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--horizon-sunset-blue)]">
            {{ topic.category_label || 'Archive Entry' }}
          </div>

          <h1 class="mt-3 text-3xl font-black text-horizon-white md:text-5xl">
            {{ entry.title }}
          </h1>

          <p v-if="entry.excerpt" class="mt-4 text-base leading-7 text-text-secondary">
            {{ entry.excerpt }}
          </p>

          <div class="mt-5 flex flex-wrap gap-2 text-xs font-semibold text-text-muted">
            <span class="rounded-full border border-white/10 px-3 py-1">{{ entry.minimum_rank_label }}</span>
            <span v-if="entry.published_label" class="rounded-full border border-white/10 px-3 py-1">Published {{ entry.published_label }}</span>
            <span v-if="entry.updated_label" class="rounded-full border border-white/10 px-3 py-1">Updated {{ entry.updated_label }}</span>
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

      <section class="rounded-3xl border border-white/10 bg-white/[0.035] p-6 md:p-8">
        <div
          v-if="entry.body"
          class="hz-rte-content"
          v-html="entry.body"
        />
        <div v-else class="text-text-secondary">
          No body content has been written for this archive entry yet.
        </div>
      </section>

      <section v-if="relatedEntries.length" class="space-y-4">
        <div>
          <div class="text-xs font-bold uppercase tracking-[0.24em] text-text-muted">Related</div>
          <h2 class="text-2xl font-black text-horizon-white">More from {{ topic.title }}</h2>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
          <Link v-for="related in relatedEntries" :key="related.id" :href="related.href" class="rounded-2xl border border-white/10 bg-white/[0.035] p-5 hover:border-[color:var(--horizon-sunset-blue)]/45">
            <div v-if="related.categories?.length || related.tags?.length" class="mb-3 flex flex-wrap gap-2 text-xs font-semibold">
              <span v-for="category in related.categories" :key="`related-category-${related.id}-${category.id}`" class="rounded-full border border-[color:var(--horizon-sunset-blue)]/25 bg-[color:var(--horizon-sunset-blue)]/10 px-2.5 py-1 text-[color:var(--horizon-sunset-blue)]">
                {{ category.name }}
              </span>
              <span v-for="tag in related.tags" :key="`related-tag-${related.id}-${tag.id}`" class="rounded-full border border-[color:var(--horizon-sunset-magenta)]/25 bg-[color:var(--horizon-sunset-magenta)]/10 px-2.5 py-1 text-[color:var(--horizon-sunset-magenta)]">
                #{{ tag.name }}
              </span>
            </div>
            <h3 class="text-lg font-black text-horizon-white">{{ related.title }}</h3>
            <p class="mt-2 text-sm leading-6 text-text-secondary">{{ related.excerpt }}</p>
          </Link>
        </div>
      </section>
    </article>
  </HorizonContainer>
</template>
