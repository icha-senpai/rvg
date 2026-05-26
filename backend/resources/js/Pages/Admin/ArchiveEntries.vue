<script setup>
import { computed, ref } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

import HorizonContainer from '@/Components/HorizonContainer.vue'

const props = defineProps({
  topic: { type: Object, required: true },
  entries: { type: Array, default: () => [] },
  categoryOptions: { type: Array, default: () => [] },
  tagOptions: { type: Array, default: () => [] },
  rankOptions: { type: Array, default: () => [] },
})

const editingEntryId = ref(null)

const blankEntry = {
  title: '',
  slug: '',
  excerpt: '',
  body: '',
  banner_image_path: '',
  sort_order: 0,
  minimum_rank_level: null,
  category_ids: [],
  tag_ids: [],
  is_published: true,
}

const createForm = useForm({ ...blankEntry, category_ids: [], tag_ids: [] })
const editForm = useForm({ ...blankEntry, category_ids: [], tag_ids: [] })

const sortedEntries = computed(() => props.entries ?? [])
const hasCategories = computed(() => (props.categoryOptions ?? []).length > 0)
const hasTags = computed(() => (props.tagOptions ?? []).length > 0)

function startEdit(entry) {
  editingEntryId.value = entry.id
  editForm.title = entry.title ?? ''
  editForm.slug = entry.slug ?? ''
  editForm.excerpt = entry.excerpt ?? ''
  editForm.body = entry.body ?? ''
  editForm.banner_image_path = entry.banner_image_path ?? ''
  editForm.sort_order = entry.sort_order ?? 0
  editForm.minimum_rank_level = entry.minimum_rank_level ?? null
  editForm.category_ids = [...(entry.category_ids ?? [])]
  editForm.tag_ids = [...(entry.tag_ids ?? [])]
  editForm.is_published = Boolean(entry.is_published)
}

function cancelEdit() {
  editingEntryId.value = null
  editForm.reset()
  editForm.clearErrors()
}

function submitCreate() {
  createForm.post(route('admin.archive.topics.entries.store', props.topic.id), {
    preserveScroll: true,
    onSuccess: () => createForm.reset(),
  })
}

function submitEdit(entry) {
  editForm.put(route('admin.archive.topics.entries.update', [props.topic.id, entry.id]), {
    preserveScroll: true,
    onSuccess: () => cancelEdit(),
  })
}

function deleteEntry(entry) {
  if (!window.confirm(`Delete archive entry "${entry.title}"?`)) {
    return
  }

  router.delete(route('admin.archive.topics.entries.destroy', [props.topic.id, entry.id]), {
    preserveScroll: true,
  })
}
</script>

<template>
  <HorizonContainer class="py-8 md:py-10">
    <div class="mx-auto max-w-7xl space-y-8">
      <section class="rounded-[2rem] border border-white/10 bg-white/[0.035] p-6 md:p-8">
        <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
          <div>
            <div class="text-xs font-bold uppercase tracking-[0.24em] text-text-muted">
              Archive Entries
            </div>
            <h1 class="mt-2 text-3xl font-black text-horizon-white md:text-5xl">
              {{ topic.title }}
            </h1>
            <p class="mt-3 max-w-3xl text-sm leading-6 text-text-secondary md:text-base">
              Manage the visible documents inside this archive topic. Draft and rank-gated entries stay hidden from members until published and permitted.
            </p>
            <div class="mt-4 flex flex-wrap gap-2 text-xs font-semibold text-text-muted">
              <span class="rounded-full border border-white/10 px-3 py-1">{{ topic.minimum_rank_label }}</span>
              <span class="rounded-full border border-white/10 px-3 py-1">{{ topic.is_published ? 'Topic published' : 'Topic draft' }}</span>
            </div>
          </div>

          <div class="flex flex-wrap gap-3">
            <Link :href="route('admin.archive.index')" class="rounded-xl border border-white/10 bg-white/[0.035] px-4 py-2 text-sm font-bold text-text-secondary hover:text-horizon-white">
              Back to Topics
            </Link>
            <Link :href="topic.public_href" class="rounded-xl border border-white/10 bg-white/[0.035] px-4 py-2 text-sm font-bold text-horizon-white hover:border-[color:var(--horizon-sunset-blue)]/45">
              View Topic
            </Link>
          </div>
        </div>
      </section>

      <section class="grid gap-6 lg:grid-cols-[26rem_minmax(0,1fr)]">
        <form class="rounded-3xl border border-white/10 bg-white/[0.035] p-5" @submit.prevent="submitCreate">
          <div class="text-xs font-bold uppercase tracking-[0.22em] text-[color:var(--horizon-sunset-blue)]">
            New Entry
          </div>

          <div class="mt-4 space-y-4">
            <div>
              <label class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Title</label>
              <input v-model="createForm.title" class="mt-1 w-full rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-sm text-horizon-white" />
              <div v-if="createForm.errors.title" class="mt-1 text-xs text-red-300">{{ createForm.errors.title }}</div>
            </div>

            <div>
              <label class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Slug</label>
              <input v-model="createForm.slug" placeholder="auto-from-title if blank" class="mt-1 w-full rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-sm text-horizon-white" />
              <div v-if="createForm.errors.slug" class="mt-1 text-xs text-red-300">{{ createForm.errors.slug }}</div>
            </div>

            <div>
              <label class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Excerpt</label>
              <textarea v-model="createForm.excerpt" rows="3" class="mt-1 w-full rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-sm text-horizon-white"></textarea>
            </div>

            <div>
              <label class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Body</label>
              <textarea v-model="createForm.body" rows="8" class="mt-1 w-full rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-sm text-horizon-white"></textarea>
            </div>

            <div v-if="hasCategories">
              <label class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Categories</label>
              <select v-model="createForm.category_ids" multiple class="mt-1 min-h-28 w-full rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-sm text-horizon-white">
                <option v-for="category in categoryOptions" :key="category.id" :value="category.id">{{ category.name }}</option>
              </select>
              <p class="mt-1 text-xs text-text-muted">Hold Ctrl/Cmd to select multiple.</p>
            </div>

            <div v-if="hasTags">
              <label class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Tags</label>
              <select v-model="createForm.tag_ids" multiple class="mt-1 min-h-28 w-full rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-sm text-horizon-white">
                <option v-for="tag in tagOptions" :key="tag.id" :value="tag.id">{{ tag.name }}</option>
              </select>
              <p class="mt-1 text-xs text-text-muted">Hold Ctrl/Cmd to select multiple.</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
              <div>
                <label class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Sort</label>
                <input v-model="createForm.sort_order" type="number" min="0" class="mt-1 w-full rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-sm text-horizon-white" />
              </div>

              <div>
                <label class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Minimum Rank</label>
                <select v-model="createForm.minimum_rank_level" class="mt-1 w-full rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-sm text-horizon-white">
                  <option v-for="option in rankOptions" :key="String(option.value)" :value="option.value">{{ option.label }}</option>
                </select>
              </div>
            </div>

            <label class="flex items-center gap-2 text-sm font-semibold text-text-secondary">
              <input v-model="createForm.is_published" type="checkbox" />
              Published
            </label>

            <button type="submit" class="w-full rounded-xl border border-[color:var(--horizon-sunset-blue)]/40 bg-[color:var(--horizon-sunset-blue)]/15 px-4 py-2 text-sm font-bold text-horizon-white hover:bg-[color:var(--horizon-sunset-blue)]/25" :disabled="createForm.processing">
              Create Entry
            </button>
          </div>
        </form>

        <div class="space-y-4">
          <article v-for="entry in sortedEntries" :key="entry.id" class="rounded-3xl border border-white/10 bg-white/[0.035] p-5">
            <div v-if="editingEntryId !== entry.id" class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto]">
              <div>
                <div class="flex flex-wrap gap-2">
                  <span class="rounded-full border border-white/10 px-3 py-1 text-xs font-semibold text-text-muted">{{ entry.minimum_rank_label }}</span>
                  <span class="rounded-full border border-white/10 px-3 py-1 text-xs font-semibold" :class="entry.is_published ? 'text-emerald-300' : 'text-red-300'">{{ entry.is_published ? 'Published' : 'Draft' }}</span>
                  <span class="rounded-full border border-white/10 px-3 py-1 text-xs font-semibold text-text-muted">Sort {{ entry.sort_order }}</span>
                </div>

                <h2 class="mt-3 text-2xl font-black text-horizon-white">{{ entry.title }}</h2>
                <p class="mt-2 text-sm leading-6 text-text-secondary">{{ entry.excerpt || 'No excerpt yet.' }}</p>

                <div v-if="entry.categories?.length || entry.tags?.length" class="mt-3 flex flex-wrap gap-2">
                  <span v-for="category in entry.categories" :key="`category-${category.id}`" class="rounded-full border border-[color:var(--horizon-sunset-blue)]/25 bg-[color:var(--horizon-sunset-blue)]/10 px-3 py-1 text-xs font-semibold text-[color:var(--horizon-sunset-blue)]">
                    {{ category.name }}
                  </span>
                  <span v-for="tag in entry.tags" :key="`tag-${tag.id}`" class="rounded-full border border-[color:var(--horizon-sunset-magenta)]/25 bg-[color:var(--horizon-sunset-magenta)]/10 px-3 py-1 text-xs font-semibold text-[color:var(--horizon-sunset-magenta)]">
                    #{{ tag.name }}
                  </span>
                </div>

                <div class="mt-3 text-xs text-text-muted">/{{ topic.slug }}/{{ entry.slug }}</div>
              </div>

              <div class="flex flex-wrap gap-2 lg:flex-col">
                <Link :href="entry.public_href" class="rounded-xl border border-white/10 px-4 py-2 text-sm font-bold text-text-secondary hover:text-horizon-white">View</Link>
                <button type="button" class="rounded-xl border border-white/10 px-4 py-2 text-sm font-bold text-horizon-white hover:border-[color:var(--horizon-sunset-blue)]/45" @click="startEdit(entry)">Edit</button>
                <button type="button" class="rounded-xl border border-red-300/25 px-4 py-2 text-sm font-bold text-red-200 hover:bg-red-300/10" @click="deleteEntry(entry)">Delete</button>
              </div>
            </div>

            <form v-else class="space-y-4" @submit.prevent="submitEdit(entry)">
              <div class="grid gap-4 md:grid-cols-2">
                <div>
                  <label class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Title</label>
                  <input v-model="editForm.title" class="mt-1 w-full rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-sm text-horizon-white" />
                </div>
                <div>
                  <label class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Slug</label>
                  <input v-model="editForm.slug" class="mt-1 w-full rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-sm text-horizon-white" />
                </div>
              </div>

              <div>
                <label class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Excerpt</label>
                <textarea v-model="editForm.excerpt" rows="3" class="mt-1 w-full rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-sm text-horizon-white"></textarea>
              </div>

              <div>
                <label class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Body</label>
                <textarea v-model="editForm.body" rows="10" class="mt-1 w-full rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-sm text-horizon-white"></textarea>
              </div>

              <div class="grid gap-4 md:grid-cols-3">
                <div>
                  <label class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Banner Path</label>
                  <input v-model="editForm.banner_image_path" class="mt-1 w-full rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-sm text-horizon-white" />
                </div>
                <div>
                  <label class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Sort</label>
                  <input v-model="editForm.sort_order" type="number" min="0" class="mt-1 w-full rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-sm text-horizon-white" />
                </div>
                <div>
                  <label class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Minimum Rank</label>
                  <select v-model="editForm.minimum_rank_level" class="mt-1 w-full rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-sm text-horizon-white">
                    <option v-for="option in rankOptions" :key="String(option.value)" :value="option.value">{{ option.label }}</option>
                  </select>
                </div>
              </div>

              <div class="grid gap-4 md:grid-cols-2">
                <div v-if="hasCategories">
                  <label class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Categories</label>
                  <select v-model="editForm.category_ids" multiple class="mt-1 min-h-28 w-full rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-sm text-horizon-white">
                    <option v-for="category in categoryOptions" :key="category.id" :value="category.id">{{ category.name }}</option>
                  </select>
                </div>

                <div v-if="hasTags">
                  <label class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Tags</label>
                  <select v-model="editForm.tag_ids" multiple class="mt-1 min-h-28 w-full rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-sm text-horizon-white">
                    <option v-for="tag in tagOptions" :key="tag.id" :value="tag.id">{{ tag.name }}</option>
                  </select>
                </div>
              </div>

              <label class="flex items-center gap-2 text-sm font-semibold text-text-secondary">
                <input v-model="editForm.is_published" type="checkbox" />
                Published
              </label>

              <div class="flex flex-wrap gap-2">
                <button type="submit" class="rounded-xl border border-[color:var(--horizon-sunset-blue)]/40 bg-[color:var(--horizon-sunset-blue)]/15 px-4 py-2 text-sm font-bold text-horizon-white">Save</button>
                <button type="button" class="rounded-xl border border-white/10 px-4 py-2 text-sm font-bold text-text-secondary hover:text-horizon-white" @click="cancelEdit">Cancel</button>
              </div>
            </form>
          </article>

          <div v-if="!sortedEntries.length" class="rounded-3xl border border-white/10 bg-white/[0.035] p-6 text-sm text-text-secondary">
            No archive entries exist for this topic yet.
          </div>
        </div>
      </section>
    </div>
  </HorizonContainer>
</template>
