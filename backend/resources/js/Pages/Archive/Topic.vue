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
  tagOptions: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
})

const search = ref(props.filters?.search ?? '')
const sort = ref(props.filters?.sort ?? 'default')
const tag = ref(props.filters?.tag ?? '')
let searchTimer = null

const hasActiveFilters = computed(() => Boolean(
  String(props.filters?.search ?? '').trim()
  || props.filters?.tag
  || (props.filters?.sort && props.filters.sort !== 'default')
))

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
    tag: tag.value,
    ...overrides,
  }

  const query = {}
  const trimmed = String(next.search ?? '').trim()

  if (trimmed) query.search = trimmed
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
    only: ['entries', 'tagOptions', 'filters'],
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
  tag.value = ''

  router.visit(route('archive.topic', props.topic.slug), {
    data: {},
    preserveState: true,
    preserveScroll: true,
    replace: true,
    only: ['entries', 'tagOptions', 'filters'],
  })
}
</script>

<template>
  <HorizonContainer class="py-8 md:py-10">
    <div class="mx-auto max-w-7xl space-y-8">
      <Link :href="route('archive.index')" class="inline-flex items-center rounded-xl border border-white/[0.055] bg-white/[0.024] px-4 py-2 text-sm font-semibold text-text-secondary hover:border-[color:var(--horizon-sunset-blue)]/35 hover:text-horizon-white">
        ← Back to Archive
      </Link>

      <section class="hz-surface-welcome relative overflow-hidden rounded-[2rem] border border-white/[0.055] ">
        <div class="pointer-events-none absolute inset-0 opacity-40">
          <div class="absolute left-8 top-0 h-px w-56 bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-blue)] to-transparent"></div>
          <div class="absolute bottom-0 right-10 h-px w-72 bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-magenta)] to-transparent"></div>
        </div>

        <div v-if="topic.banner_image_path" class="relative h-48 overflow-hidden border-b border-white/10 md:h-64">
          <img :src="topic.banner_image_path" :alt="topic.title" class="h-full w-full object-cover" />
          <div class="absolute inset-0 bg-gradient-to-t from-[color:var(--horizon-void-900)] via-[color:var(--horizon-void-900)]/55 to-transparent"></div>
        </div>

        <div class="relative p-6 md:p-8">
          <div class="flex flex-wrap gap-2">
            <span class="rounded-full border border-white/[0.055] bg-white/[0.042] px-3 py-1 text-xs font-bold uppercase tracking-[0.18em] text-[color:var(--horizon-text-primary)]">
              {{ topic.category_label || 'Archive Topic' }}
            </span>
            <span class="rounded-full border border-white/[0.055] bg-white/[0.024] px-3 py-1 text-xs font-semibold text-text-secondary">{{ topic.minimum_rank_label }}</span>
          </div>

          <h1 class="mt-4 text-3xl font-black tracking-tight text-horizon-white md:text-5xl">
            {{ topic.title }}
          </h1>

          <p class="mt-3 max-w-3xl text-sm leading-6 text-text-secondary md:text-base">
            {{ topic.description || 'No description has been written for this archive topic yet.' }}
          </p>

          <div class="mt-5 flex flex-wrap gap-2 text-xs font-semibold text-text-muted">
            <span class="rounded-full border border-white/10 bg-black/10 px-3 py-1">{{ entries.length }} visible entries</span>
            <span v-if="hasActiveFilters" class="rounded-full border border-white/[0.055] bg-white/[0.042] px-3 py-1 text-[color:var(--horizon-text-primary)]">Filtered</span>
          </div>
        </div>
      </section>

      <!-- Filters -->
      <section class="hz-surface-welcome rounded-xl border border-white/[0.055] p-3">
        <div class="flex flex-col gap-3 md:flex-row md:items-end">
          <HorizonInput v-model="search" type="search" placeholder="Search entries..." class="md:flex-1" />
          <div class="flex gap-2">
            <HorizonSelect v-model="tag" :options="tagSelectOptions" placeholder="Tag" @update:model-value="applyFilters" class="min-w-[8rem]" />
            <HorizonSelect v-model="sort" :options="sortOptions" placeholder="Sort" @update:model-value="applyFilters" class="min-w-[8rem]" />
          </div>
          <div class="flex gap-2">
            <HorizonButton type="button" size="sm" @click="applyFilters">Apply</HorizonButton>
            <HorizonButton v-if="hasActiveFilters" type="button" size="sm" variant="ghost" @click="clearFilters">Clear</HorizonButton>
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

        <div v-if="entries.length" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          <Link v-for="entry in entries" :key="entry.id" :href="entry.href" class="hz-surface-welcome hz-surface-angled [--hz-angled-corner-border:rgba(255,255,255,0.055)] group relative rounded-2xl border border-white/[0.055] p-5 transition hover:-translate-y-0.5 hover:border-[color:var(--horizon-sunset-blue)]/45 hover:[--hz-angled-corner-border:color-mix(in_srgb,var(--horizon-sunset-blue)_45%,transparent)]">
            <div class="pointer-events-none absolute -right-12 -top-16 h-32 w-32 rounded-full bg-white/[0.042] blur-3xl"></div>

            <div class="relative flex flex-wrap gap-2">
              <span class="rounded-full border border-white/[0.055] bg-white/[0.042] px-3 py-1 text-xs font-bold uppercase tracking-[0.16em] text-[color:var(--horizon-sunset-blue)]">{{ entry.minimum_rank_label }}</span>
              <span v-for="tagItem in entry.tags" :key="`tag-${entry.id}-${tagItem.id}`" class="rounded-full border border-white/[0.055] bg-white/[0.042] px-3 py-1 text-xs font-semibold text-[color:var(--horizon-sunset-magenta)]">#{{ tagItem.name }}</span>
            </div>

            <h3 class="relative mt-3 text-xl font-black text-horizon-white group-hover:text-[color:var(--horizon-sunset-blue)]">{{ entry.title }}</h3>
            <p class="relative mt-2 line-clamp-3 text-sm leading-6 text-text-secondary">{{ entry.excerpt || 'No excerpt has been written for this archive entry yet.' }}</p>
            <div v-if="entry.updated_label" class="relative mt-4 text-xs font-semibold text-text-muted">Updated {{ entry.updated_label }}</div>
          </Link>
        </div>

        <div v-else class="hz-surface-welcome rounded-2xl border border-white/[0.055] p-6 text-sm text-text-secondary">
          No visible entries matched this topic and filter set.
        </div>
      </section>
    </div>
  </HorizonContainer>
</template>






