<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

const props = defineProps({
  navigation: { type: Object, default: () => ({ topics: [] }) },
  activeTopicSlug: { type: String, default: '' },
  activeEntrySlug: { type: String, default: '' },
})

const topics = computed(() => props.navigation?.topics ?? [])

function topicIsActive(topic) {
  return topic.slug === props.activeTopicSlug
}

function entryIsActive(entry) {
  return entry.slug === props.activeEntrySlug
}
</script>

<template>
  <div class="space-y-4">
    <details class="rounded-3xl border border-white/10 bg-white/[0.035] p-4 shadow-[0_18px_55px_rgba(0,0,0,0.18)] lg:hidden">
      <summary class="cursor-pointer list-none text-sm font-black uppercase tracking-[0.2em] text-horizon-white">
        Browse Archive
      </summary>

      <div class="mt-4 space-y-3">
        <Link :href="route('archive.index')" class="block rounded-xl border border-white/10 bg-black/10 px-3 py-2 text-sm font-bold text-text-secondary hover:border-[color:var(--horizon-sunset-blue)]/35 hover:text-horizon-white">
          Archive Landing
        </Link>

        <div v-for="topic in topics" :key="`mobile-topic-${topic.id}`" class="rounded-2xl border border-white/10 bg-black/10 p-3">
          <Link :href="topic.href" class="flex items-start justify-between gap-3 rounded-xl px-2 py-2 text-sm font-black" :class="topicIsActive(topic) ? 'bg-[color:var(--horizon-sunset-blue)]/15 text-horizon-white' : 'text-text-secondary hover:text-horizon-white'">
            <span>{{ topic.title }}</span>
            <span class="shrink-0 text-xs font-semibold text-text-muted">{{ topic.visible_entries_count }}</span>
          </Link>

          <div v-if="topicIsActive(topic) && topic.entries?.length" class="mt-2 space-y-1 border-t border-white/10 pt-2">
            <Link v-for="entry in topic.entries" :key="`mobile-entry-${entry.id}`" :href="entry.href" class="block rounded-lg px-3 py-2 text-xs font-semibold" :class="entryIsActive(entry) ? 'bg-[color:var(--horizon-sunset-magenta)]/15 text-horizon-white' : 'text-text-muted hover:bg-white/[0.035] hover:text-text-secondary'">
              {{ entry.title }}
            </Link>
          </div>
        </div>
      </div>
    </details>

    <aside class="hidden lg:sticky lg:top-8 lg:block">
      <div class="relative overflow-hidden rounded-[1.75rem] border border-white/10 bg-[linear-gradient(145deg,rgba(255,255,255,0.055),rgba(255,255,255,0.025))] p-4 shadow-[0_18px_55px_rgba(0,0,0,0.20)]">
        <div class="pointer-events-none absolute inset-x-6 top-0 h-px bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-blue)]/45 to-transparent"></div>

        <div class="relative">
          <div class="text-xs font-bold uppercase tracking-[0.24em] text-text-muted">Archive Navigation</div>
          <Link :href="route('archive.index')" class="mt-3 block rounded-xl border border-white/10 bg-black/10 px-3 py-2 text-sm font-bold text-text-secondary hover:border-[color:var(--horizon-sunset-blue)]/35 hover:text-horizon-white">
            Archive Landing
          </Link>

          <div class="mt-4 max-h-[calc(100vh-12rem)] space-y-3 overflow-y-auto pr-1">
            <div v-for="topic in topics" :key="`desktop-topic-${topic.id}`" class="rounded-2xl border border-white/10 bg-black/10 p-3">
              <Link :href="topic.href" class="flex items-start justify-between gap-3 rounded-xl px-2 py-2 text-sm font-black" :class="topicIsActive(topic) ? 'bg-[color:var(--horizon-sunset-blue)]/15 text-horizon-white' : 'text-text-secondary hover:text-horizon-white'">
                <span>{{ topic.title }}</span>
                <span class="shrink-0 rounded-full border border-white/10 px-2 py-0.5 text-[0.65rem] font-semibold text-text-muted">{{ topic.visible_entries_count }}</span>
              </Link>

              <div v-if="topic.categories?.length" class="mt-2 flex flex-wrap gap-1.5 px-2">
                <span v-for="category in topic.categories" :key="`desktop-category-${topic.id}-${category.slug}`" class="rounded-full border border-[color:var(--horizon-sunset-blue)]/20 bg-[color:var(--horizon-sunset-blue)]/10 px-2 py-0.5 text-[0.65rem] font-semibold text-[color:var(--horizon-sunset-blue)]">
                  {{ category.name }}
                </span>
              </div>

              <div v-if="topicIsActive(topic) && topic.entries?.length" class="mt-3 space-y-1 border-t border-white/10 pt-3">
                <Link v-for="entry in topic.entries" :key="`desktop-entry-${entry.id}`" :href="entry.href" class="block rounded-lg px-3 py-2 text-xs font-semibold leading-5" :class="entryIsActive(entry) ? 'bg-[color:var(--horizon-sunset-magenta)]/15 text-horizon-white' : 'text-text-muted hover:bg-white/[0.035] hover:text-text-secondary'">
                  {{ entry.title }}
                </Link>
              </div>
            </div>

            <div v-if="!topics.length" class="rounded-2xl border border-white/10 bg-black/10 p-4 text-sm text-text-secondary">
              No Archive areas are visible to your rank.
            </div>
          </div>
        </div>
      </div>
    </aside>
  </div>
</template>
