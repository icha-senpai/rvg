<script setup>
import { computed, ref, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

import HorizonButton from '@/Components/HorizonButton.vue'
import HorizonContainer from '@/Components/HorizonContainer.vue'
import HorizonInput from '@/Components/HorizonInput.vue'
import HorizonSelect from '@/Components/HorizonSelect.vue'

const props = defineProps({
  topics: { type: Array, default: () => [] },
  entries: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
})

const search = ref(props.filters?.search ?? '')
const sort = ref(props.filters?.sort ?? 'recent')
let searchDebounceId = null

const hasSearch = computed(() => String(props.filters?.search ?? '').trim().length > 0)
const topicCount = computed(() => props.topics.length)
const resultCount = computed(() => props.entries.length)

const sortOptions = [
  { value: 'recent', label: 'Recently updated' },
  { value: 'title', label: 'Title A-Z' },
  { value: 'oldest', label: 'Oldest updated' },
]

function currentQuery(overrides = {}) {
  const next = {
    search: search.value,
    sort: sort.value,
    ...overrides,
  }

  const query = {}
  const trimmed = String(next.search ?? '').trim()

  if (trimmed) query.search = trimmed
  if (next.sort && next.sort !== 'recent') query.sort = next.sort

  return query
}

function runSearch(value = search.value) {
  search.value = value

  router.visit(route('archive.index'), {
    data: currentQuery({ search: value }),
    preserveState: true,
    preserveScroll: true,
    replace: true,
    only: ['topics', 'entries', 'filters'],
  })
}

function applySearch() {
  if (searchDebounceId) {
    clearTimeout(searchDebounceId)
    searchDebounceId = null
  }

  runSearch(search.value)
}

function applySort() {
  router.visit(route('archive.index'), {
    data: currentQuery(),
    preserveState: true,
    preserveScroll: true,
    replace: true,
    only: ['topics', 'entries', 'filters'],
  })
}

function clearSearch() {
  if (searchDebounceId) {
    clearTimeout(searchDebounceId)
    searchDebounceId = null
  }

  search.value = ''
  sort.value = 'recent'
  router.visit(route('archive.index'), {
    data: {},
    preserveState: true,
    preserveScroll: true,
    replace: true,
    only: ['topics', 'entries', 'filters'],
  })
}

watch(search, value => {
  if (searchDebounceId) clearTimeout(searchDebounceId)
  searchDebounceId = setTimeout(() => runSearch(value), 250)
})
</script>

<template>
  <HorizonContainer class="py-8 md:py-10">
    <div class="mx-auto max-w-7xl space-y-8">
      <section class="relative overflow-hidden rounded-[2rem] border border-[color:var(--horizon-sunset-indigo)]/45 bg-[radial-gradient(circle_at_top_left,var(--horizon-glow-blue),transparent_34%),radial-gradient(circle_at_top_right,var(--horizon-glow-magenta),transparent_32%),linear-gradient(135deg,var(--horizon-void-600),var(--horizon-void-900))] p-6 shadow-[0_0_48px_rgba(67,56,202,0.18)] md:p-8">
        <div class="pointer-events-none absolute inset-0 opacity-40">
          <div class="absolute left-8 top-0 h-px w-48 bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-blue)] to-transparent"></div>
          <div class="absolute bottom-0 right-10 h-px w-64 bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-magenta)] to-transparent"></div>
        </div>

        <div class="relative grid gap-6 lg:grid-cols-[minmax(0,1fr)_28rem] lg:items-end">
          <div class="min-w-0">
            <div class="text-xs font-bold uppercase tracking-[0.28em] text-[color:var(--horizon-text-secondary)]">Horizon Internal Knowledge Library</div>
            <h1 class="mt-2 text-3xl font-black tracking-tight text-horizon-white md:text-5xl">Archive</h1>
            <p class="mt-3 max-w-3xl text-sm text-text-secondary md:text-base">
              Browse doctrine, records, policies, organizational history, and curated Horizon knowledge from one structured command archive.
            </p>

            <div class="mt-4 flex flex-wrap gap-2">
              <span class="rounded-full border border-[color:var(--horizon-sunset-blue)]/30 bg-[color:var(--horizon-sunset-blue)]/10 px-3 py-1 text-xs font-semibold text-[color:var(--horizon-text-primary)]">{{ topicCount }} Visible Topics</span>
              <span v-if="hasSearch" class="rounded-full border border-[color:var(--horizon-sunset-magenta)]/30 bg-[color:var(--horizon-sunset-magenta)]/10 px-3 py-1 text-xs font-semibold text-[color:var(--horizon-text-primary)]">{{ resultCount }} Matching Entries</span>
              <span class="rounded-full border border-white/10 bg-white/[0.035] px-3 py-1 text-xs font-semibold text-text-secondary">Rank-filtered</span>
            </div>
          </div>

          <form class="rounded-2xl border border-white/10 bg-black/20 p-3 shadow-inner shadow-black/20" @submit.prevent="applySearch">
            <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_10rem_auto] md:items-end">
              <HorizonInput
                id="archive-search"
                v-model="search"
                label="Search Archive"
                type="search"
                placeholder="Search topics and visible entries..."
                class="min-w-0"
              />

              <HorizonSelect
                v-model="sort"
                label="Sort Results"
                :options="sortOptions"
                @update:model-value="applySort"
              />

              <HorizonButton type="submit" class="shrink-0">Search</HorizonButton>
            </div>

            <div class="mt-3 flex flex-wrap items-center gap-2">
              <HorizonButton v-if="hasSearch" type="button" variant="ghost" size="xs" @click="clearSearch">Clear search</HorizonButton>
              <span class="text-xs text-text-muted">Search results can be sorted by title or update date.</span>
            </div>
          </form>
        </div>
      </section>

      <section v-if="hasSearch" class="space-y-4">
        <div class="flex items-center justify-between gap-4">
          <div>
            <div class="text-xs font-bold uppercase tracking-[0.24em] text-text-muted">Search Results</div>
            <h2 class="text-2xl font-black text-horizon-white">Visible matching entries</h2>
          </div>
        </div>

        <div v-if="entries.length" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          <Link v-for="entry in entries" :key="entry.id" :href="entry.href" class="group rounded-2xl border border-white/10 bg-white/[0.035] p-5 transition hover:-translate-y-0.5 hover:border-[color:var(--horizon-sunset-blue)]/45 hover:bg-white/[0.055]">
            <div class="text-xs font-bold uppercase tracking-[0.2em] text-[color:var(--horizon-sunset-blue)]">{{ entry.topic?.title ?? 'Archive Entry' }}</div>
            <h3 class="mt-2 text-lg font-black text-horizon-white group-hover:text-[color:var(--horizon-sunset-blue)]">{{ entry.title }}</h3>
            <p class="mt-2 line-clamp-3 text-sm text-text-secondary">{{ entry.excerpt || 'No excerpt has been written for this archive entry yet.' }}</p>
            <div class="mt-4 flex flex-wrap gap-2 text-xs font-semibold text-text-muted">
              <span class="rounded-full border border-white/10 px-2.5 py-1">{{ entry.minimum_rank_label }}</span>
              <span v-if="entry.updated_label" class="rounded-full border border-white/10 px-2.5 py-1">Updated {{ entry.updated_label }}</span>
              <span v-for="category in entry.categories" :key="`search-category-${entry.id}-${category.id}`" class="rounded-full border border-[color:var(--horizon-sunset-blue)]/25 bg-[color:var(--horizon-sunset-blue)]/10 px-2.5 py-1 text-[color:var(--horizon-sunset-blue)]">{{ category.name }}</span>
              <span v-for="tag in entry.tags" :key="`search-tag-${entry.id}-${tag.id}`" class="rounded-full border border-[color:var(--horizon-sunset-magenta)]/25 bg-[color:var(--horizon-sunset-magenta)]/10 px-2.5 py-1 text-[color:var(--horizon-sunset-magenta)]">#{{ tag.name }}</span>
            </div>
          </Link>
        </div>

        <div v-else class="rounded-2xl border border-white/10 bg-white/[0.035] p-6 text-sm text-text-secondary">No visible archive entries matched this search.</div>
      </section>

      <section class="space-y-4">
        <div>
          <div class="text-xs font-bold uppercase tracking-[0.24em] text-text-muted">Archive Topics</div>
          <h2 class="text-2xl font-black text-horizon-white">Curated knowledge blocks</h2>
        </div>

        <div v-if="topics.length" class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
          <Link v-for="topic in topics" :key="topic.id" :href="topic.href" class="group relative overflow-hidden rounded-[1.75rem] border border-white/10 bg-[linear-gradient(145deg,rgba(255,255,255,0.06),rgba(255,255,255,0.025))] p-5 shadow-[0_18px_55px_rgba(0,0,0,0.22)] transition hover:-translate-y-1 hover:border-[color:var(--horizon-sunset-magenta)]/45">
            <div class="pointer-events-none absolute inset-0 opacity-70">
              <div class="absolute -right-16 -top-20 h-40 w-40 rounded-full bg-[color:var(--horizon-sunset-blue)]/10 blur-3xl"></div>
              <div class="absolute -bottom-20 left-8 h-40 w-40 rounded-full bg-[color:var(--horizon-sunset-magenta)]/10 blur-3xl"></div>
            </div>

            <div class="relative space-y-4">
              <div class="flex aspect-square items-center justify-center overflow-hidden rounded-2xl border border-white/10 bg-black/20 p-3">
                <img v-if="topic.card_image_path" :src="topic.card_image_path" :alt="topic.title" class="h-full w-full rounded-xl object-contain" />
                <div v-else class="px-4 text-center text-5xl text-[color:var(--horizon-sunset-blue)]/80">◫</div>
              </div>

              <div>
                <div class="flex flex-wrap gap-2">
                  <span class="rounded-full border border-[color:var(--horizon-sunset-magenta)]/30 bg-[color:var(--horizon-sunset-magenta)]/10 px-3 py-1 text-xs font-bold uppercase tracking-[0.16em] text-[color:var(--horizon-text-primary)]">{{ topic.category_label || 'Archive' }}</span>
                  <span class="rounded-full border border-white/10 bg-white/[0.035] px-3 py-1 text-xs font-semibold text-text-secondary">{{ topic.minimum_rank_label }}</span>
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

        <div v-else class="rounded-2xl border border-white/10 bg-white/[0.035] p-6 text-sm text-text-secondary">No archive topics are currently visible to your rank.</div>
      </section>
    </div>
  </HorizonContainer>
</template>
