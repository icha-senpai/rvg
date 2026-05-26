<script setup>
import { ref, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import HorizonButton from '@/Components/HorizonButton.vue'
import HorizonContainer from '@/Components/HorizonContainer.vue'
import HorizonInput from '@/Components/HorizonInput.vue'

const props = defineProps({
  topic: Object,
  entries: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
})

const search = ref(props.filters?.search ?? '')
let searchTimer = null

watch(search, value => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    const trimmed = String(value ?? '').trim()

    router.visit(route('archive.topic', props.topic.slug), {
      data: trimmed ? { search: trimmed } : {},
      preserveState: true,
      preserveScroll: true,
      replace: true,
      only: ['entries', 'filters'],
    })
  }, 250)
})

function clearSearch() {
  search.value = ''
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
          </div>
        </div>
      </section>

      <section class="rounded-3xl border border-white/10 bg-white/[0.035] p-4 md:p-5">
        <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
          <div>
            <div class="text-xs font-bold uppercase tracking-[0.24em] text-text-muted">Search Topic</div>
            <p class="mt-1 text-sm text-text-secondary">Search only within the entries you are allowed to see.</p>
          </div>

          <div class="flex w-full gap-2 md:w-[28rem] md:items-end">
            <HorizonInput v-model="search" type="search" placeholder="Search entries..." class="min-w-0 flex-1" />
            <HorizonButton v-if="search" type="button" variant="ghost" @click="clearSearch">Clear</HorizonButton>
          </div>
        </div>
      </section>

      <section class="space-y-4">
        <div class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
          <div>
            <div class="text-xs font-bold uppercase tracking-[0.24em] text-text-muted">Entries</div>
            <h2 class="text-2xl font-black text-horizon-white">Documents and records</h2>
          </div>

          <div v-if="filters?.search" class="text-sm text-text-secondary">
            Results for <span class="font-bold text-horizon-white">{{ filters.search }}</span>
          </div>
        </div>

        <div v-if="entries.length" class="grid gap-4 md:grid-cols-2">
          <Link v-for="entry in entries" :key="entry.id" :href="entry.href" class="group rounded-2xl border border-white/10 bg-white/[0.035] p-5 transition hover:border-[color:var(--horizon-sunset-blue)]/45 hover:bg-white/[0.055]">
            <div class="flex flex-wrap gap-2">
              <span class="rounded-full border border-[color:var(--horizon-sunset-blue)]/25 bg-[color:var(--horizon-sunset-blue)]/10 px-3 py-1 text-xs font-bold uppercase tracking-[0.16em] text-[color:var(--horizon-sunset-blue)]">{{ entry.minimum_rank_label }}</span>
              <span v-for="category in entry.categories" :key="`category-${entry.id}-${category.id}`" class="rounded-full border border-white/10 bg-white/[0.035] px-3 py-1 text-xs font-semibold text-text-secondary">{{ category.name }}</span>
              <span v-for="tag in entry.tags" :key="`tag-${entry.id}-${tag.id}`" class="rounded-full border border-[color:var(--horizon-sunset-magenta)]/25 bg-[color:var(--horizon-sunset-magenta)]/10 px-3 py-1 text-xs font-semibold text-[color:var(--horizon-sunset-magenta)]">#{{ tag.name }}</span>
            </div>

            <h3 class="mt-3 text-xl font-black text-horizon-white group-hover:text-white">{{ entry.title }}</h3>
            <p class="mt-2 text-sm leading-6 text-text-secondary">{{ entry.excerpt || 'No excerpt has been written for this archive entry yet.' }}</p>
          </Link>
        </div>

        <div v-else class="rounded-2xl border border-white/10 bg-white/[0.035] p-6 text-sm text-text-secondary">
          No visible entries matched this topic.
        </div>
      </section>
    </div>
  </HorizonContainer>
</template>
