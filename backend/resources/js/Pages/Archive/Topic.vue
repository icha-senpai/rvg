<script setup>
import { computed, ref, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import HorizonButton from '@/Components/HorizonButton.vue'
import HorizonContainer from '@/Components/HorizonContainer.vue'
import HorizonInput from '@/Components/HorizonInput.vue'
import HorizonSelect from '@/Components/HorizonSelect.vue'

const props = defineProps({
  topic: Object,
  entries: { type: Array, default: () => [] },
  categoryOptions: { type: Array, default: () => [] },
  tagOptions: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
})

const search = ref(props.filters?.search ?? '')
const sort = ref(props.filters?.sort ?? 'default')
const category = ref(props.filters?.category ?? '')
const tag = ref(props.filters?.tag ?? '')
let searchTimer = null

const hasActiveFilters = computed(() => Boolean(
  String(props.filters?.search ?? '').trim()
  || props.filters?.category
  || props.filters?.tag
  || (props.filters?.sort && props.filters.sort !== 'default')
))

const categorySelectOptions = computed(() => [
  { value: '', label: 'All categories' },
  ...(props.categoryOptions ?? []).map(item => ({
    value: item.slug,
    label: `${item.name} (${item.entries_count})`,
  })),
])

const tagSelectOptions = computed(() => [
  { value: '', label: 'All tags' },
  ...(props.tagOptions ?? []).map(item => ({
    value: item.slug,
    label: `#${item.name} (${item.entries_count})`,
  })),
])

const sortOptions = [
  { value: 'default', label: 'Topic order' },
  { value: 'title', label: 'Title A-Z' },
  { value: 'recent', label: 'Recently updated' },
  { value: 'oldest', label: 'Oldest updated' },
]

function currentQuery(overrides = {}) {
  const next = {
    search: search.value,
    sort: sort.value,
    category: category.value,
    tag: tag.value,
    ...overrides,
  }

  const query = {}
  const trimmed = String(next.search ?? '').trim()

  if (trimmed) query.search = trimmed
  if (next.category) query.category = next.category
  if (next.tag) query.tag = next.tag
  if (next.sort && next.sort !== 'default') query.sort = next.sort

  return query
}

function visitWithFilters(overrides = {}) {
  router.visit(route('archive.topic', props.topic.slug), {
    data: currentQuery(overrides),
    preserveState: true,
    preserveScroll: true,
    replace: true,
    only: ['entries', 'categoryOptions', 'tagOptions', 'filters'],
  })
}

watch(search, value => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    visitWithFilters({ search: value })
  }, 250)
})

function applyFilters() {
  if (searchTimer) {
    clearTimeout(searchTimer)
    searchTimer = null
  }

  visitWithFilters()
}

function clearFilters() {
  if (searchTimer) {
    clearTimeout(searchTimer)
    searchTimer = null
  }

  search.value = ''
  sort.value = 'default'
  category.value = ''
  tag.value = ''

  router.visit(route('archive.topic', props.topic.slug), {
    data: {},
    preserveState: true,
    preserveScroll: true,
    replace: true,
    only: ['entries', 'categoryOptions', 'tagOptions', 'filters'],
  })
}
</script>

<template>
  <HorizonContainer class="py-8 md:py-10">
    <div class="mx-auto max-w-6xl space-y-8">
      <Link :href="route('archive.index')" class="inline-flex items-center rounded-xl border border-white/10 bg-white/[0.035] px-4 py-2 text-sm font-semibold text-text-secondary hover:border-[color:var(--horizon-sunset-blue)]/35 hover:text-horizon-white">
        Back to Archive
      </Link>

      <section class="overflow-hidden rounded-[2rem] border border-white/10 bg-[radial-gradient(circle_at_top_left,var(--horizon-glow-blue),transparent_42%),rgba(255,255,255,0.035)]">
        <div v-if="topic.banner_image_path" class="relative h-48 overflow-hidden border-b border-white/10 md:h-64">
          <img :src="topic.banner_image_path" :alt="topic.title" class="h-full w-full object-cover" />
          <div class="absolute inset-0 bg-gradient-to-t from-[color:var(--horizon-void-900)] via-[color:var(--horizon-void-900)]/35 to-transparent"></div>
        </div>

        <div class="p-6 md:p-8">
          <div class="text-xs font-bold uppercase tracking-[0.24em] text-text-muted">
            {{ topic.category_label || 'Archive Topic' }}
          </div>

          <h1 class="mt-2 text-3xl font-black text-horizon-white md:text-5xl">
            {{ topic.title }}
          </h1>

          <p class="mt-3 max-w-3xl text-sm leading-6 text-text-secondary md:text-base">
            {{ topic.description || 'No description has been written for this archive topic yet.' }}
          </p>

          <div class="mt-5 flex flex-wrap gap-2 text-xs font-semibold text-text-muted">
            <span class="rounded-full border border-white/10 bg-black/10 px-3 py-1">{{ topic.minimum_rank_label }}</span>
            <span class="rounded-full border border-white/10 bg-black/10 px-3 py-1">{{ entries.length }} visible entries</span>
            <span v-if="hasActiveFilters" class="rounded-full border border-[color:var(--horizon-sunset-magenta)]/30 bg-[color:var(--horizon-sunset-magenta)]/10 px-3 py-1 text-[color:var(--horizon-text-primary)]">Filtered</span>
          </div>
        </div>
      </section>

      <section class="rounded-3xl border border-white/10 bg-white/[0.035] p-4 md:p-5">
        <div class="flex flex-col gap-4">
          <div class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
            <div>
              <div class="text-xs font-bold uppercase tracking-[0.24em] text-text-muted">Search & Filter Topic</div>
              <p class="mt-1 text-sm text-text-secondary">Search and filter only within the entries you are allowed to see.</p>
            </div>

            <HorizonButton v-if="hasActiveFilters" type="button" variant="ghost" @click="clearFilters">Clear filters</HorizonButton>
          </div>

          <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-[minmax(0,1fr)_13rem_13rem_13rem_auto] xl:items-end">
            <HorizonInput v-model="search" type="search" label="Search" placeholder="Search entries..." />
            <HorizonSelect v-model="category" label="Category" :options="categorySelectOptions" @update:model-value="applyFilters" />
            <HorizonSelect v-model="tag" label="Tag" :options="tagSelectOptions" @update:model-value="applyFilters" />
            <HorizonSelect v-model="sort" label="Sort" :options="sortOptions" @update:model-value="applyFilters" />
            <HorizonButton type="button" @click="applyFilters">Apply</HorizonButton>
          </div>
        </div>
      </section>

      <section class="space-y-4">
        <div class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
          <div>
            <div class="text-xs font-bold uppercase tracking-[0.24em] text-text-muted">Entries</div>
            <h2 class="text-2xl font-black text-horizon-white">Documents and records</h2>
          </div>

          <div v-if="hasActiveFilters" class="text-sm text-text-secondary">
            Showing <span class="font-bold text-horizon-white">{{ entries.length }}</span> matching entr{{ entries.length === 1 ? 'y' : 'ies' }}
          </div>
        </div>

        <div v-if="entries.length" class="grid gap-4 md:grid-cols-2">
          <Link v-for="entry in entries" :key="entry.id" :href="entry.href" class="group rounded-2xl border border-white/10 bg-white/[0.035] p-5 transition hover:border-[color:var(--horizon-sunset-blue)]/45 hover:bg-white/[0.055]">
            <div class="flex flex-wrap gap-2">
              <span class="rounded-full border border-[color:var(--horizon-sunset-blue)]/25 bg-[color:var(--horizon-sunset-blue)]/10 px-3 py-1 text-xs font-bold uppercase tracking-[0.16em] text-[color:var(--horizon-sunset-blue)]">{{ entry.minimum_rank_label }}</span>
              <span v-for="categoryItem in entry.categories" :key="`category-${entry.id}-${categoryItem.id}`" class="rounded-full border border-white/10 bg-white/[0.035] px-3 py-1 text-xs font-semibold text-text-secondary">{{ categoryItem.name }}</span>
              <span v-for="tagItem in entry.tags" :key="`tag-${entry.id}-${tagItem.id}`" class="rounded-full border border-[color:var(--horizon-sunset-magenta)]/25 bg-[color:var(--horizon-sunset-magenta)]/10 px-3 py-1 text-xs font-semibold text-[color:var(--horizon-sunset-magenta)]">#{{ tagItem.name }}</span>
            </div>

            <h3 class="mt-3 text-xl font-black text-horizon-white group-hover:text-white">{{ entry.title }}</h3>
            <p class="mt-2 text-sm leading-6 text-text-secondary">{{ entry.excerpt || 'No excerpt has been written for this archive entry yet.' }}</p>
            <div v-if="entry.updated_label" class="mt-4 text-xs font-semibold text-text-muted">Updated {{ entry.updated_label }}</div>
          </Link>
        </div>

        <div v-else class="rounded-2xl border border-white/10 bg-white/[0.035] p-6 text-sm text-text-secondary">
          No visible entries matched this topic and filter set.
        </div>
      </section>
    </div>
  </HorizonContainer>
</template>
