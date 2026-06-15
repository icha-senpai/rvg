<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import HorizonContainer from '@/Components/HorizonContainer.vue'
import { normalizeRichTextHtml } from '@/richText'

const props = defineProps({
  category: { type: Object, default: null },
  topic: Object,
  entry: Object,
  relatedEntries: { type: Array, default: () => [] },
})

const entryBodyHtml = computed(() => normalizeRichTextHtml(props.entry?.body))
</script>

<template>
  <HorizonContainer class="py-8 md:py-10">
    <article class="mx-auto max-w-7xl space-y-8">
      <nav class="flex flex-wrap items-center gap-2 text-sm font-semibold text-text-secondary">
        <Link :href="route('archive.index')" class="rounded-xl border border-white/[0.055] bg-white/[0.024] px-3 py-1.5 hover:border-[color:var(--horizon-sunset-blue)]/35 hover:text-horizon-white">
          Archive
        </Link>
        <span class="text-text-muted">/</span>
        <Link v-if="category?.href" :href="category.href" class="rounded-xl border border-white/[0.055] bg-white/[0.024] px-3 py-1.5 hover:border-[color:var(--horizon-sunset-blue)]/35 hover:text-horizon-white">
          {{ category.name }}
        </Link>
        <template v-if="topic?.href">
          <span class="text-text-muted">/</span>
          <Link :href="topic.href" class="rounded-xl border border-white/[0.055] bg-white/[0.024] px-3 py-1.5 hover:border-[color:var(--horizon-sunset-blue)]/35 hover:text-horizon-white">
            {{ topic.title }}
          </Link>
        </template>
      </nav>

      <header class="hz-surface-welcome relative overflow-hidden rounded-[2rem] border border-white/[0.055] ">
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
            <span class="rounded-full border border-white/[0.055] bg-white/[0.042] px-3 py-1 text-xs font-bold uppercase tracking-[0.18em] text-[color:var(--horizon-text-primary)]">
              {{ category?.name || topic?.category_label || 'Archive Entry' }}
            </span>
            <span class="rounded-full border border-white/[0.055] bg-white/[0.024] px-3 py-1 text-xs font-semibold text-text-secondary">
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

          <div v-if="entry.tags?.length" class="mt-4 flex flex-wrap gap-2 text-xs font-semibold">
            <span v-for="tag in entry.tags" :key="`entry-tag-${tag.id}`" class="rounded-full border border-white/[0.055] bg-white/[0.042] px-3 py-1 text-[color:var(--horizon-sunset-magenta)]">
              #{{ tag.name }}
            </span>
          </div>
        </div>
      </header>

      <section class="hz-surface-welcome relative overflow-hidden rounded-[2rem] border border-white/10 p-6 shadow-[0_18px_55px_rgba(0,0,0,0.20)] md:p-8">
        <div class="pointer-events-none absolute inset-x-8 top-0 h-px bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-blue)]/50 to-transparent"></div>

        <div
          v-if="entry.body"
          class="hz-soft hz-rte-content max-w-none space-y-2 [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:list-decimal [&_ol]:pl-5 [&_a]:underline [&_h1]:text-xl [&_h1]:font-semibold [&_h2]:text-lg [&_h2]:font-semibold [&_h3]:text-base [&_h3]:font-semibold [&_h4]:text-sm [&_h4]:font-semibold [&_h5]:text-sm [&_h5]:font-medium [&_h6]:text-xs [&_h6]:font-medium [&_blockquote]:border-l-2 [&_blockquote]:border-(--color-bg-hover) [&_blockquote]:pl-3 [&_blockquote]:opacity-90 [&_hr]:my-3 [&_hr]:border-(--color-bg-hover) [&_code]:rounded [&_code]:px-1 [&_code]:py-0.5 [&_code]:bg-bg-elevated [&_pre]:rounded [&_pre]:p-3 [&_pre]:bg-bg-elevated [&_table]:w-full [&_table]:border-collapse [&_th]:border [&_th]:border-(--color-bg-hover) [&_th]:bg-bg-hover [&_th]:p-2 [&_td]:border [&_td]:border-(--color-bg-hover) [&_td]:bg-bg-elevated [&_td]:p-2 [&_mark]:rounded [&_mark]:px-1 [&_img]:max-w-full [&_img]:h-auto [&_img]:rounded-lg [&_.hz-rte-callout]:rounded-none [&_.hz-rte-callout]:border [&_.hz-rte-callout]:border-(--color-bg-hover) [&_.hz-rte-callout]:bg-bg-elevated"
          v-html="entryBodyHtml"
        />
        <div v-else class="rounded-2xl border border-white/10 bg-black/20 p-5 text-text-secondary">
          No body content has been written for this archive entry yet.
        </div>
      </section>

      <section v-if="relatedEntries.length" class="space-y-4">
        <div class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
          <div>
            <div class="text-xs font-bold uppercase tracking-[0.24em] text-text-muted">Related Records</div>
            <h2 class="text-2xl font-black text-horizon-white">More from {{ topic?.title || category?.name || 'this archive area' }}</h2>
          </div>

          <Link :href="topic?.href || category?.href" class="text-sm font-bold text-text-secondary hover:text-horizon-white">
            View all in {{ topic ? 'topic' : 'category' }} →
          </Link>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
          <Link v-for="related in relatedEntries" :key="related.id" :href="related.href" class="hz-surface-welcome group relative rounded-2xl border border-white/[0.055] p-5 transition hover:-translate-y-0.5 hover:border-[color:var(--horizon-sunset-blue)]/45">
            <div v-if="related.categories?.length || related.tags?.length" class="relative mb-3 flex flex-wrap gap-2 text-xs font-semibold">
              <span v-for="category in related.categories" :key="`related-category-${related.id}-${category.id}`" class="rounded-full border border-white/[0.055] bg-white/[0.042] px-2.5 py-1 text-[color:var(--horizon-sunset-blue)]">
                {{ category.name }}
              </span>
              <span v-for="tag in related.tags" :key="`related-tag-${related.id}-${tag.id}`" class="rounded-full border border-white/[0.055] bg-white/[0.042] px-2.5 py-1 text-[color:var(--horizon-sunset-magenta)]">
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

<style scoped>
:deep(.hz-rte-content .hz-rte-callout) {
  border-radius: 0 !important;
}
</style>
