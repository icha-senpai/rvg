<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

import HorizonButton from '@/Components/HorizonButton.vue'
import HorizonContainer from '@/Components/HorizonContainer.vue'
import HorizonInput from '@/Components/HorizonInput.vue'
import HorizonSelect from '@/Components/HorizonSelect.vue'

const props = defineProps({
  categories: { type: Array, default: () => [] },
  entries: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
})

const search = ref(props.filters?.search ?? '')
const sort = ref(props.filters?.sort ?? 'recent')
const openCategoryKeys = ref(new Set())
let searchDebounceId = null

const openCategoryStorageKey = 'archive.index.open-categories'

const hasSearch = computed(() => String(props.filters?.search ?? '').trim().length > 0)
const categoryGroups = computed(() => props.categories ?? [])
const topicCount = computed(() => categoryGroups.value.reduce((count, category) => count + Number(category.visible_topics_count ?? category.topics?.length ?? 0), 0))
const categoryCount = computed(() => categoryGroups.value.length)
const resultCount = computed(() => props.entries.length)

const sortOptions = [
  { value: 'recent', label: 'Recently updated' },
  { value: 'title', label: 'Title A-Z' },
  { value: 'oldest', label: 'Oldest updated' },
]

onMounted(() => {
  loadOpenCategoryKeys()
})

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

function loadOpenCategoryKeys() {
  if (typeof window === 'undefined') return

  try {
    const raw = window.localStorage.getItem(openCategoryStorageKey)

    if (!raw) {
      openCategoryKeys.value = new Set()
      return
    }

    const parsed = JSON.parse(raw)
    openCategoryKeys.value = new Set(Array.isArray(parsed) ? parsed.map(value => String(value)) : [])
  } catch {
    openCategoryKeys.value = new Set()
  }
}

function persistOpenCategoryKeys() {
  if (typeof window === 'undefined') return

  window.localStorage.setItem(openCategoryStorageKey, JSON.stringify(Array.from(openCategoryKeys.value)))
}

function categoryIsOpen(categoryId) {
  return openCategoryKeys.value.has(String(categoryId))
}

function toggleCategory(categoryId) {
  const next = new Set(openCategoryKeys.value)
  const normalizedId = String(categoryId)

  if (next.has(normalizedId)) {
    next.delete(normalizedId)
  } else {
    next.add(normalizedId)
  }

  openCategoryKeys.value = next
  persistOpenCategoryKeys()
}

function runSearch(value = search.value) {
  search.value = value

  router.visit(route('archive.index'), {
    data: currentQuery({ search: value }),
    preserveState: true,
    preserveScroll: true,
    replace: true,
    only: ['categories', 'entries', 'filters'],
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
    only: ['categories', 'entries', 'filters'],
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
    only: ['categories', 'entries', 'filters'],
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
      <section class="hz-surface-welcome relative overflow-visible rounded-[2rem] border border-white/[0.055] p-6  md:p-8">
        <div class="pointer-events-none absolute inset-0 overflow-hidden rounded-[2rem] opacity-40">
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
              <span class="rounded-full border border-white/[0.055] bg-white/[0.042] px-3 py-1 text-xs font-semibold text-[color:var(--horizon-text-primary)]">{{ categoryCount }} Visible Categories</span>
              <span class="rounded-full border border-white/[0.055] bg-white/[0.042] px-3 py-1 text-xs font-semibold text-[color:var(--horizon-text-primary)]">{{ topicCount }} Visible Topics</span>
              <span v-if="hasSearch" class="rounded-full border border-white/[0.055] bg-white/[0.042] px-3 py-1 text-xs font-semibold text-[color:var(--horizon-text-primary)]">{{ resultCount }} Matching Entries</span>
              <span class="rounded-full border border-white/[0.055] bg-white/[0.024] px-3 py-1 text-xs font-semibold text-text-secondary">Rank-filtered</span>
            </div>
          </div>

          <!-- Search -->
          <form class="hz-surface-welcome rounded-xl border border-white/[0.055] p-3" @submit.prevent="applySearch">
            <div class="flex flex-col gap-2 md:flex-row md:items-end">
              <HorizonInput v-model="search" type="search" placeholder="Search archive..." class="md:flex-1" />
              <HorizonSelect v-model="sort" :options="sortOptions" placeholder="Sort" @update:model-value="applySort" class="min-w-[8rem]" />
              <div class="flex gap-2">
                <HorizonButton type="submit" size="sm">Search</HorizonButton>
                <HorizonButton v-if="hasSearch" type="button" variant="ghost" size="sm" @click="clearSearch">Clear</HorizonButton>
              </div>
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
          <Link v-for="entry in entries" :key="entry.id" :href="entry.href" class="hz-surface-welcome group rounded-2xl border border-white/[0.055] p-5 transition hover:-translate-y-0.5 hover:border-[color:var(--horizon-sunset-blue)]/45">
            <div class="text-xs font-bold uppercase tracking-[0.2em] text-[color:var(--horizon-sunset-blue)]">{{ entry.topic?.title ?? entry.category?.name ?? 'Archive Entry' }}</div>
            <h3 class="mt-2 text-lg font-black text-horizon-white group-hover:text-[color:var(--horizon-sunset-blue)]">{{ entry.title }}</h3>
            <p class="mt-2 line-clamp-3 text-sm text-text-secondary">{{ entry.excerpt || 'No excerpt has been written for this archive entry yet.' }}</p>
            <div class="mt-4 flex flex-wrap gap-2 text-xs font-semibold text-text-muted">
              <span class="rounded-full border border-white/10 px-2.5 py-1">{{ entry.minimum_rank_label }}</span>
              <span v-if="entry.updated_label" class="rounded-full border border-white/10 px-2.5 py-1">Updated {{ entry.updated_label }}</span>
              <span v-for="tag in entry.tags" :key="`search-tag-${entry.id}-${tag.id}`" class="rounded-full border border-white/[0.055] bg-white/[0.042] px-2.5 py-1 text-[color:var(--horizon-sunset-magenta)]">#{{ tag.name }}</span>
            </div>
          </Link>
        </div>

        <div v-else class="hz-surface-welcome rounded-2xl border border-white/[0.055] p-6 text-sm text-text-secondary">No visible archive entries matched this search.</div>
      </section>

      <section class="space-y-4">
        <div>

        </div>

        <div v-if="categoryGroups.length" class="space-y-6">
          <section v-for="group in categoryGroups" :key="group.id" class="hz-surface-welcome rounded-[2rem] border border-white/[0.055] p-5 md:p-6">
            <div
              class="flex cursor-pointer flex-col gap-3 border-b border-white/10 pb-4 md:flex-row md:items-end md:justify-between"
              role="button"
              tabindex="0"
              :aria-expanded="categoryIsOpen(group.id)"
              @click="toggleCategory(group.id)"
              @keydown.enter.prevent="toggleCategory(group.id)"
              @keydown.space.prevent="toggleCategory(group.id)"
            >
              <div>
                <div class="text-xs font-bold uppercase tracking-[0.24em] text-text-muted">Category</div>
                <h3 class="mt-1 text-2xl font-black text-horizon-white">{{ group.name }}</h3>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-text-secondary">{{ group.description || '' }}</p>
              </div>

              <div class="flex flex-wrap gap-2 text-xs font-semibold text-text-muted">
                <span class="rounded-full border border-white/10 px-3 py-1">{{ group.visible_topics_count }} topic{{ group.visible_topics_count === 1 ? '' : 's' }}</span>
                <span class="rounded-full border border-white/10 px-3 py-1">{{ group.visible_direct_entries_count }} direct entr{{ group.visible_direct_entries_count === 1 ? 'y' : 'ies' }}</span>
                <span class="rounded-full border border-white/10 px-3 py-1">{{ group.visible_entries_count }} visible entries</span>
                <button
                  type="button"
                  class="inline-flex items-center gap-2 rounded-full border border-white/[0.055] px-3 py-1 text-horizon-white hover:bg-white/[0.05]"
                  @click.stop="toggleCategory(group.id)"
                >
                  <span>{{ categoryIsOpen(group.id) ? 'Collapse' : 'Expand' }}</span>
                  <span class="text-[10px] transition-transform duration-200" :class="categoryIsOpen(group.id) ? 'rotate-180' : ''">⌄</span>
                </button>
                <Link :href="group.href" class="rounded-full border border-[color:var(--horizon-sunset-blue)]/35 px-3 py-1 text-horizon-white hover:bg-[color:var(--horizon-sunset-blue)]/20" @click.stop>Go to Category</Link>
              </div>
            </div>

            <template v-if="categoryIsOpen(group.id)">
              <div v-if="group.direct_entries?.length" class="mt-5 space-y-3">
                <div class="text-xs font-bold uppercase tracking-[0.2em] text-text-muted">Direct Entries</div>
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                  <Link v-for="entry in group.direct_entries" :key="`direct-entry-${entry.id}`" :href="entry.href" class="hz-surface-welcome group rounded-2xl border border-white/[0.055] p-5 transition hover:-translate-y-0.5 hover:border-[color:var(--horizon-sunset-blue)]/45">
                    <div class="text-xs font-bold uppercase tracking-[0.2em] text-[color:var(--horizon-sunset-blue)]">Direct Category Entry</div>
                    <h3 class="mt-2 text-lg font-black text-horizon-white group-hover:text-[color:var(--horizon-sunset-blue)]">{{ entry.title }}</h3>
                    <p class="mt-2 line-clamp-3 text-sm text-text-secondary">{{ entry.excerpt || 'No excerpt has been written for this archive entry yet.' }}</p>
                    <div class="mt-4 flex flex-wrap gap-2 text-xs font-semibold text-text-muted">
                      <span class="rounded-full border border-white/10 px-2.5 py-1">{{ entry.minimum_rank_label }}</span>
                      <span v-if="entry.updated_label" class="rounded-full border border-white/10 px-2.5 py-1">Updated {{ entry.updated_label }}</span>
                      <span v-for="tag in entry.tags" :key="`direct-search-tag-${entry.id}-${tag.id}`" class="rounded-full border border-white/[0.055] bg-white/[0.042] px-2.5 py-1 text-[color:var(--horizon-sunset-magenta)]">#{{ tag.name }}</span>
                    </div>
                  </Link>
                </div>
              </div>

              <div v-if="group.topics?.length" class="mt-5 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                <Link v-for="topic in group.topics" :key="topic.id" :href="topic.href" class="hz-surface-welcome group relative rounded-[1.75rem] border border-white/10 p-5 shadow-[0_18px_55px_rgba(0,0,0,0.22)] transition hover:-translate-y-1 hover:border-white/[0.055]">
                  <div class="pointer-events-none absolute inset-0 opacity-70">
                    <div class="absolute -right-16 -top-20 h-40 w-40 rounded-full bg-white/[0.042] blur-3xl"></div>
                    <div class="absolute -bottom-20 left-8 h-40 w-40 rounded-full bg-white/[0.042] blur-3xl"></div>
                  </div>

                  <div class="relative space-y-4">
                    <div class="flex aspect-square items-center justify-center overflow-hidden rounded-2xl border border-white/10 bg-black/20 p-3">
                      <img v-if="topic.card_image_path" :src="topic.card_image_path" :alt="topic.title" class="h-full w-full rounded-xl object-contain" />
                      <div v-else class="px-4 text-center text-5xl text-[color:var(--horizon-sunset-blue)]/80">◫</div>
                    </div>

                    <div>
                      <div class="flex flex-wrap gap-2">
                        <span class="rounded-full border border-white/[0.055] bg-white/[0.024] px-3 py-1 text-xs font-semibold text-text-secondary">{{ topic.minimum_rank_label }}</span>
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

              <div v-if="!group.topics?.length && !group.direct_entries?.length" class="hz-surface-welcome mt-5 rounded-2xl border border-white/[0.055] p-6 text-sm text-text-secondary">
                No visible archive content is currently available in this category.
              </div>
            </template>
          </section>
        </div>

        <div v-else class="hz-surface-welcome rounded-2xl border border-white/[0.055] p-6 text-sm text-text-secondary">No archive categories are currently visible to your rank.</div>
      </section>
    </div>
  </HorizonContainer>
</template>

