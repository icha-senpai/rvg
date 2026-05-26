<script setup>
import { computed, reactive, ref } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

import HorizonContainer from '@/Components/HorizonContainer.vue'

const props = defineProps({
  topics: { type: Array, default: () => [] },
  rankOptions: { type: Array, default: () => [] },
})

const editingTopicId = ref(null)

const blankTopic = {
  title: '',
  slug: '',
  description: '',
  category_label: '',
  card_image_path: '',
  banner_image_path: '',
  sort_order: 0,
  minimum_rank_level: null,
  is_published: true,
}

const createForm = useForm({ ...blankTopic })
const editForm = useForm({ ...blankTopic })

const sortedTopics = computed(() => props.topics ?? [])

function startEdit(topic) {
  editingTopicId.value = topic.id
  editForm.title = topic.title ?? ''
  editForm.slug = topic.slug ?? ''
  editForm.description = topic.description ?? ''
  editForm.category_label = topic.category_label ?? ''
  editForm.card_image_path = topic.card_image_path ?? ''
  editForm.banner_image_path = topic.banner_image_path ?? ''
  editForm.sort_order = topic.sort_order ?? 0
  editForm.minimum_rank_level = topic.minimum_rank_level ?? null
  editForm.is_published = Boolean(topic.is_published)
}

function cancelEdit() {
  editingTopicId.value = null
  editForm.reset()
  editForm.clearErrors()
}

function submitCreate() {
  createForm.post(route('admin.archive.topics.store'), {
    preserveScroll: true,
    onSuccess: () => createForm.reset(),
  })
}

function submitEdit(topic) {
  editForm.put(route('admin.archive.topics.update', topic.id), {
    preserveScroll: true,
    onSuccess: () => cancelEdit(),
  })
}

function deleteTopic(topic) {
  if (!window.confirm(`Delete archive topic "${topic.title}"? This also deletes its entries.`)) {
    return
  }

  router.delete(route('admin.archive.topics.destroy', topic.id), {
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
              Director Tools
            </div>
            <h1 class="mt-2 text-3xl font-black text-horizon-white md:text-5xl">
              Archive Management
            </h1>
            <p class="mt-3 max-w-3xl text-sm leading-6 text-text-secondary md:text-base">
              Create and manage Archive topic cards. Entry editing comes next; this page is the first admin control layer.
            </p>
          </div>

          <div class="flex gap-3">
            <Link :href="route('archive.index')" class="rounded-xl border border-white/10 bg-white/[0.035] px-4 py-2 text-sm font-bold text-horizon-white hover:border-[color:var(--horizon-sunset-blue)]/45">
              View Archive
            </Link>
            <Link :href="route('admin.dashboard')" class="rounded-xl border border-white/10 bg-white/[0.035] px-4 py-2 text-sm font-bold text-text-secondary hover:text-horizon-white">
              Admin Dashboard
            </Link>
          </div>
        </div>
      </section>

      <section class="grid gap-6 lg:grid-cols-[24rem_minmax(0,1fr)]">
        <form class="rounded-3xl border border-white/10 bg-white/[0.035] p-5" @submit.prevent="submitCreate">
          <div class="text-xs font-bold uppercase tracking-[0.22em] text-[color:var(--horizon-sunset-blue)]">
            New Topic
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
              <label class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Category Label</label>
              <input v-model="createForm.category_label" class="mt-1 w-full rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-sm text-horizon-white" />
            </div>

            <div>
              <label class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Description</label>
              <textarea v-model="createForm.description" rows="4" class="mt-1 w-full rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-sm text-horizon-white"></textarea>
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
              Create Topic
            </button>
          </div>
        </form>

        <div class="space-y-4">
          <div v-if="sortedTopics.length" class="space-y-4">
            <article v-for="topic in sortedTopics" :key="topic.id" class="rounded-3xl border border-white/10 bg-white/[0.035] p-5">
              <div v-if="editingTopicId !== topic.id" class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto]">
                <div>
                  <div class="flex flex-wrap gap-2">
                    <span class="rounded-full border border-white/10 px-3 py-1 text-xs font-semibold text-text-muted">{{ topic.category_label || 'Archive' }}</span>
                    <span class="rounded-full border border-white/10 px-3 py-1 text-xs font-semibold text-text-muted">{{ topic.minimum_rank_label }}</span>
                    <span class="rounded-full border border-white/10 px-3 py-1 text-xs font-semibold" :class="topic.is_published ? 'text-emerald-300' : 'text-red-300'">{{ topic.is_published ? 'Published' : 'Draft' }}</span>
                    <span class="rounded-full border border-white/10 px-3 py-1 text-xs font-semibold text-text-muted">{{ topic.entries_count }} entries</span>
                  </div>

                  <h2 class="mt-3 text-2xl font-black text-horizon-white">{{ topic.title }}</h2>
                  <p class="mt-2 text-sm leading-6 text-text-secondary">{{ topic.description }}</p>
                  <div class="mt-3 text-xs text-text-muted">/{{ topic.slug }} · Sort {{ topic.sort_order }}</div>
                </div>

                <div class="flex flex-wrap gap-2 lg:flex-col">
                  <Link :href="topic.public_href" class="rounded-xl border border-white/10 px-4 py-2 text-sm font-bold text-text-secondary hover:text-horizon-white">View</Link>
                  <button type="button" class="rounded-xl border border-white/10 px-4 py-2 text-sm font-bold text-horizon-white hover:border-[color:var(--horizon-sunset-blue)]/45" @click="startEdit(topic)">Edit</button>
                  <button type="button" class="rounded-xl border border-red-300/25 px-4 py-2 text-sm font-bold text-red-200 hover:bg-red-300/10" @click="deleteTopic(topic)">Delete</button>
                </div>
              </div>

              <form v-else class="space-y-4" @submit.prevent="submitEdit(topic)">
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
                  <label class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Description</label>
                  <textarea v-model="editForm.description" rows="4" class="mt-1 w-full rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-sm text-horizon-white"></textarea>
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                  <div>
                    <label class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Category</label>
                    <input v-model="editForm.category_label" class="mt-1 w-full rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-sm text-horizon-white" />
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
          </div>

          <div v-else class="rounded-3xl border border-white/10 bg-white/[0.035] p-6 text-sm text-text-secondary">
            No archive topics exist yet.
          </div>
        </div>
      </section>
    </div>
  </HorizonContainer>
</template>
