<script setup>
import { computed, ref } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

import HorizonButton from '@/Components/HorizonButton.vue'
import HorizonConfirmDialog from '@/Components/HorizonConfirmDialog.vue'
import HorizonContainer from '@/Components/HorizonContainer.vue'
import HorizonInput from '@/Components/HorizonInput.vue'
import HorizonRichTextEditor from '@/Components/HorizonRichTextEditor.vue'
import HorizonSelect from '@/Components/HorizonSelect.vue'
import ArchiveMediaPicker from './Components/ArchiveMediaPicker.vue'

const props = defineProps({
  topic: { type: Object, required: true },
  entries: { type: Array, default: () => [] },
  categoryOptions: { type: Array, default: () => [] },
  tagOptions: { type: Array, default: () => [] },
  rankOptions: { type: Array, default: () => [] },
  previewRankLevel: { type: [Number, null], default: null },
})

const editingEntryId = ref(null)
const previewRank = ref(props.previewRankLevel)
const deleteEntryDialog = ref(null)
const entryPendingDelete = ref(null)

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
const categorySelectOptions = computed(() => (props.categoryOptions ?? []).map(category => ({ value: category.id, label: category.name })))
const tagSelectOptions = computed(() => (props.tagOptions ?? []).map(tag => ({ value: tag.id, label: tag.name })))
const previewOptions = computed(() => (props.rankOptions ?? []).map(option => ({ ...option, label: option.value === null ? 'All verified members' : option.label })))
const previewLabel = computed(() => previewOptions.value.find(option => option.value === previewRank.value)?.label ?? 'All verified members')

function changePreviewRank() {
  router.get(route('admin.archive.topics.entries.index', props.topic.id), {
    preview_rank_level: previewRank.value ?? '',
  }, {
    preserveScroll: true,
    preserveState: true,
    replace: true,
  })
}

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

function askDeleteEntry(entry) {
  entryPendingDelete.value = entry
  deleteEntryDialog.value?.show()
}

function confirmDeleteEntry({ close }) {
  if (!entryPendingDelete.value) return

  router.delete(route('admin.archive.topics.entries.destroy', [props.topic.id, entryPendingDelete.value.id]), {
    preserveScroll: true,
    onFinish: () => {
      entryPendingDelete.value = null
      close()
    },
  })
}
</script>

<template>
  <HorizonContainer class="py-8 md:py-10">
    <div class="mx-auto max-w-7xl space-y-8">
      <section class="rounded-[2rem] border border-white/10 bg-white/[0.035] p-6 md:p-8">
        <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
          <div>
            <div class="text-xs font-bold uppercase tracking-[0.24em] text-text-muted">Archive Entries</div>
            <h1 class="mt-2 text-3xl font-black text-horizon-white md:text-5xl">{{ topic.title }}</h1>
            <p class="mt-3 max-w-3xl text-sm leading-6 text-text-secondary md:text-base">
              Manage the visible documents inside this archive topic. Draft and rank-gated entries stay hidden from members until published and permitted.
            </p>
            <div class="mt-4 flex flex-wrap gap-2 text-xs font-semibold text-text-muted">
              <span class="rounded-full border border-white/10 px-3 py-1">{{ topic.minimum_rank_label }}</span>
              <span class="rounded-full border border-white/10 px-3 py-1">{{ topic.is_published ? 'Topic published' : 'Topic draft' }}</span>
              <span class="rounded-full border px-3 py-1" :class="topic.preview_visible ? 'border-emerald-300/25 bg-emerald-300/10 text-emerald-200' : 'border-red-300/25 bg-red-300/10 text-red-200'">
                {{ topic.preview_visible ? 'Topic visible in preview' : 'Topic hidden in preview' }}
              </span>
            </div>
          </div>

          <div class="flex flex-wrap gap-3">
            <Link :href="route('admin.archive.index')" class="rounded-xl border border-white/10 bg-white/[0.035] px-4 py-2 text-sm font-bold text-text-secondary hover:text-horizon-white">Back to Topics</Link>
            <Link :href="topic.public_href" class="rounded-xl border border-white/10 bg-white/[0.035] px-4 py-2 text-sm font-bold text-horizon-white hover:border-[color:var(--horizon-sunset-blue)]/45">View Topic</Link>
          </div>
        </div>
      </section>

      <section class="rounded-3xl border border-white/10 bg-white/[0.035] p-5">
        <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
          <div>
            <div class="text-xs font-bold uppercase tracking-[0.24em] text-text-muted">Rank Preview</div>
            <p class="mt-1 text-sm text-text-secondary">
              Currently previewing entry visibility as <span class="font-bold text-horizon-white">{{ previewLabel }}</span>. Entries also inherit the topic's visibility gate.
            </p>
          </div>

          <div class="w-full md:w-72">
            <HorizonSelect v-model="previewRank" :options="previewOptions" @update:model-value="changePreviewRank" />
          </div>
        </div>
      </section>

      <section class="grid gap-6 lg:grid-cols-[26rem_minmax(0,1fr)]">
        <form class="rounded-3xl border border-white/10 bg-white/[0.035] p-5" @submit.prevent="submitCreate">
          <div class="text-xs font-bold uppercase tracking-[0.22em] text-[color:var(--horizon-sunset-blue)]">New Entry</div>

          <div class="mt-4 space-y-4">
            <div>
              <HorizonInput v-model="createForm.title" label="Title" />
              <div v-if="createForm.errors.title" class="mt-1 text-xs text-red-300">{{ createForm.errors.title }}</div>
            </div>

            <div>
              <HorizonInput v-model="createForm.slug" label="Slug" placeholder="auto-from-title if blank" />
              <div v-if="createForm.errors.slug" class="mt-1 text-xs text-red-300">{{ createForm.errors.slug }}</div>
            </div>

            <HorizonInput v-model="createForm.excerpt" label="Excerpt" type="textarea" rows="3" />

            <div>
              <label class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Body</label>
              <HorizonRichTextEditor v-model="createForm.body" placeholder="Write the archive entry body..." :rows="12" />
            </div>

            <ArchiveMediaPicker v-model="createForm.banner_image_path" label="Entry Banner Image" />

            <div v-if="hasCategories">
              <HorizonSelect v-model="createForm.category_ids" label="Categories" multiple :options="categorySelectOptions" />
              <p class="mt-1 text-xs text-text-muted">Click items to toggle them on or off.</p>
            </div>

            <div v-if="hasTags">
              <HorizonSelect v-model="createForm.tag_ids" label="Tags" multiple :options="tagSelectOptions" />
              <p class="mt-1 text-xs text-text-muted">Click items to toggle them on or off.</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
              <HorizonInput v-model="createForm.sort_order" label="Sort" type="number" min="0" />
              <HorizonSelect v-model="createForm.minimum_rank_level" label="Minimum Rank" :options="rankOptions" />
            </div>

            <label class="flex items-center gap-2 text-sm font-semibold text-text-secondary">
              <input v-model="createForm.is_published" type="checkbox" />
              Published
            </label>

            <HorizonButton type="submit" class="w-full" :disabled="createForm.processing">Create Entry</HorizonButton>
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
                  <span class="rounded-full border px-3 py-1 text-xs font-semibold" :class="entry.preview_visible ? 'border-emerald-300/25 bg-emerald-300/10 text-emerald-200' : 'border-red-300/25 bg-red-300/10 text-red-200'">
                    {{ entry.preview_visible ? 'Visible in preview' : 'Hidden in preview' }}
                  </span>
                </div>

                <h2 class="mt-3 text-2xl font-black text-horizon-white">{{ entry.title }}</h2>
                <p class="mt-2 text-sm leading-6 text-text-secondary">{{ entry.excerpt || 'No excerpt yet.' }}</p>

                <div v-if="entry.categories?.length || entry.tags?.length" class="mt-3 flex flex-wrap gap-2">
                  <span v-for="category in entry.categories" :key="`category-${category.id}`" class="rounded-full border border-[color:var(--horizon-sunset-blue)]/25 bg-[color:var(--horizon-sunset-blue)]/10 px-3 py-1 text-xs font-semibold text-[color:var(--horizon-sunset-blue)]">{{ category.name }}</span>
                  <span v-for="tag in entry.tags" :key="`tag-${tag.id}`" class="rounded-full border border-[color:var(--horizon-sunset-magenta)]/25 bg-[color:var(--horizon-sunset-magenta)]/10 px-3 py-1 text-xs font-semibold text-[color:var(--horizon-sunset-magenta)]">#{{ tag.name }}</span>
                </div>

                <div class="mt-3 text-xs text-text-muted">/{{ topic.slug }}/{{ entry.slug }}</div>
              </div>

              <div class="flex flex-wrap gap-2 lg:flex-col">
                <Link :href="entry.public_href" class="rounded-xl border border-white/10 px-4 py-2 text-sm font-bold text-text-secondary hover:text-horizon-white">View</Link>
                <HorizonButton type="button" variant="ghost" size="sm" @click="startEdit(entry)">Edit</HorizonButton>
                <HorizonButton type="button" variant="danger" size="sm" @click="askDeleteEntry(entry)">Delete</HorizonButton>
              </div>
            </div>

            <form v-else class="space-y-4" @submit.prevent="submitEdit(entry)">
              <div class="grid gap-4 md:grid-cols-2">
                <HorizonInput v-model="editForm.title" label="Title" />
                <HorizonInput v-model="editForm.slug" label="Slug" />
              </div>

              <HorizonInput v-model="editForm.excerpt" label="Excerpt" type="textarea" rows="3" />

              <div>
                <label class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Body</label>
                <HorizonRichTextEditor v-model="editForm.body" placeholder="Write the archive entry body..." :rows="14" />
              </div>

              <ArchiveMediaPicker v-model="editForm.banner_image_path" label="Entry Banner Image" />

              <div class="grid gap-4 md:grid-cols-2">
                <HorizonInput v-model="editForm.sort_order" label="Sort" type="number" min="0" />
                <HorizonSelect v-model="editForm.minimum_rank_level" label="Minimum Rank" :options="rankOptions" />
              </div>

              <div class="grid gap-4 md:grid-cols-2">
                <div v-if="hasCategories">
                  <HorizonSelect v-model="editForm.category_ids" label="Categories" multiple :options="categorySelectOptions" />
                </div>

                <div v-if="hasTags">
                  <HorizonSelect v-model="editForm.tag_ids" label="Tags" multiple :options="tagSelectOptions" />
                </div>
              </div>

              <label class="flex items-center gap-2 text-sm font-semibold text-text-secondary">
                <input v-model="editForm.is_published" type="checkbox" />
                Published
              </label>

              <div class="flex flex-wrap gap-2">
                <HorizonButton type="submit">Save</HorizonButton>
                <HorizonButton type="button" variant="ghost" @click="cancelEdit">Cancel</HorizonButton>
              </div>
            </form>
          </article>

          <div v-if="!sortedEntries.length" class="rounded-3xl border border-white/10 bg-white/[0.035] p-6 text-sm text-text-secondary">
            No archive entries exist for this topic yet.
          </div>
        </div>
      </section>

      <HorizonConfirmDialog
        ref="deleteEntryDialog"
        title="Delete Archive Entry"
        confirm-label="Delete Entry"
        cancel-label="Cancel"
        variant="danger"
        close-on-confirm="false"
        :message="`Delete archive entry '${entryPendingDelete?.title ?? ''}'? This cannot be undone.`"
        @confirm="confirmDeleteEntry"
      />
    </div>
  </HorizonContainer>
</template>
